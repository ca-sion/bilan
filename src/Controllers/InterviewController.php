<?php
declare(strict_types=1);

/**
 * Contrôleur des entretiens de cadrage U16 et d'entretiens individuels U18+
 */
class InterviewController {
    private SeasonRepository $seasonRepo;
    private AthleteRepository $athleteRepo;
    private InterviewRepository $interviewRepo;
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->seasonRepo = new SeasonRepository($this->db);
        $this->athleteRepo = new AthleteRepository($this->db);
        $this->interviewRepo = new InterviewRepository($this->db);
    }

    /**
     * Module U16 express (groupe de 2 à 4 athlètes ou individuel)
     */
    public function u16Group(): void {
        Auth::require_admin();

        $ids_param = $_GET['ids'] ?? '';
        $athlete_ids = array_filter(array_map('intval', explode(',', (string)$ids_param)));

        if (empty($athlete_ids)) {
            flash('warning', 'Veuillez sélectionner au moins un athlète U16 pour ouvrir l\'entretien.');
            redirect('/admin');
        }

        if (count($athlete_ids) > 4) {
            flash('warning', 'Veuillez sélectionner au maximum 4 athlètes simultanément.');
            redirect('/admin');
        }

        $season = $this->seasonRepo->getActive();

        // Récupérer les athlètes sélectionnés
        $in_placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT a.*, i.id as interview_id, i.status as interview_status, i.athlete_answers, 
                    i.trainer_answers, i.decisions, i.trainer_notes, i.is_validated, i.validated_at
             FROM athletes a
             LEFT JOIN interviews i ON i.athlete_id = a.id AND i.season_id = ?
             WHERE a.id IN ({$in_placeholders})
             ORDER BY a.last_name ASC"
        );
        $stmt->execute(array_merge([$season['id']], $athlete_ids));
        $athletes = $stmt->fetchAll();

        // Récupérer l'historique N-1 pour chaque athlète
        $hist_stmt = $this->db->prepare(
            "SELECT i.*, s.name as season_name 
             FROM interviews i 
             JOIN seasons s ON s.id = i.season_id 
             WHERE i.athlete_id = ? AND i.season_id != ? 
             ORDER BY i.id DESC LIMIT 1"
        );

        foreach ($athletes as &$ath) {
            if (empty($ath['interview_id'])) {
                $interview = $this->interviewRepo->getOrCreateForSeason((int)$ath['id'], (int)$season['id'], 'u16_group');
                $ath['interview_id'] = (int)$interview['id'];
                $ath['interview_status'] = $interview['status'] ?? 'waiting';
                $ath['athlete_answers'] = $interview['athlete_answers'] ?? '{}';
                $ath['trainer_answers'] = $interview['trainer_answers'] ?? '{}';
                $ath['decisions'] = $interview['decisions'] ?? '{}';
                $ath['is_validated'] = (int)($interview['is_validated'] ?? 0);
            }
            $ath['answers_arr'] = safe_json_decode($ath['athlete_answers'] ?? null);
            $ath['trainer_arr'] = safe_json_decode($ath['trainer_answers'] ?? null);
            $ath['decisions_arr'] = safe_json_decode($ath['decisions'] ?? null);
            $ath['age'] = CategoryHelper::get_athlete_age((int)$ath['birth_year']);
            $ath['category_label'] = CategoryHelper::get_category_label((int)$ath['birth_year'], $ath['category']);

            $hist_stmt->execute([$ath['id'], $season['id']]);
            $ath['history'] = $hist_stmt->fetch() ?: null;
        }
        unset($ath);

        require dirname(__DIR__, 2) . '/views/admin_u16_group.php';
    }

    /**
     * Module U18+ individuel (split screen)
     */
    public function u18Split(): void {
        Auth::require_admin();

        $interview_id = (int)($_GET['interview_id'] ?? 0);
        $athlete_id = (int)($_GET['athlete_id'] ?? 0);
        $season = $this->seasonRepo->getActive();

        if ($interview_id > 0) {
            $interview = $this->interviewRepo->findByIdWithAthlete($interview_id);
        } elseif ($athlete_id > 0) {
            $ath = $this->athleteRepo->findById($athlete_id);
            if (!$ath) {
                flash('error', 'Athlète introuvable.');
                redirect('/admin');
                return;
            }
            $form_type = CategoryHelper::get_form_type((int)$ath['birth_year'], $ath['category']);
            $type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';
            $inter_row = $this->interviewRepo->getOrCreateForSeason($athlete_id, (int)$season['id'], $type);
            $interview = $this->interviewRepo->findByIdWithAthlete((int)$inter_row['id']);
        } else {
            flash('error', 'Entretien non spécifié.');
            redirect('/admin');
            return;
        }

        if (!$interview) {
            flash('error', 'Fiche d\'entretien introuvable.');
            redirect('/admin');
            return;
        }

        // Récupérer l'historique N-1
        $hist_stmt = $this->db->prepare(
            "SELECT i.*, s.name as season_name 
             FROM interviews i 
             JOIN seasons s ON s.id = i.season_id 
             WHERE i.athlete_id = ? AND i.season_id != ? 
             ORDER BY i.id DESC LIMIT 1"
        );
        $hist_stmt->execute([$interview['athlete_id'], $season['id']]);
        $history = $hist_stmt->fetch() ?: null;

        $athlete_answers = safe_json_decode($interview['athlete_answers'] ?? null);
        $trainer_answers = safe_json_decode($interview['trainer_answers'] ?? null);
        $decisions = safe_json_decode($interview['decisions'] ?? null);
        $athlete_meta = safe_json_decode($interview['athlete_meta'] ?? $interview['meta'] ?? null);

        $birth_year = (int)$interview['birth_year'];
        $category_label = CategoryHelper::get_category_label($birth_year, $interview['category']);
        $age = CategoryHelper::get_athlete_age($birth_year);

        require dirname(__DIR__, 2) . '/views/admin_u18_split.php';
    }

    /**
     * Enregistrement des notes et arbitrages coach
     */
    public function save(): void {
        Auth::require_admin();

        $interview_id = (int)($_POST['interview_id'] ?? 0);
        $redirect_to = (string)($_POST['redirect_to'] ?? '/admin');

        if ($interview_id <= 0) {
            flash('error', 'Entretien invalide.');
            redirect('/admin');
            return;
        }

        $trainer_answers = $_POST['trainer_answers'] ?? [];
        $decisions = $_POST['decisions'] ?? [];
        $trainer_notes = trim((string)($_POST['trainer_notes'] ?? ''));
        $action = (string)($_POST['action'] ?? 'save');
        $is_completed = ($action === 'validate');

        // Mise à jour éventuelle des réponses athlète modifiées par le coach
        $athlete_answers_post = $_POST['athlete_answers'] ?? null;
        if (is_array($athlete_answers_post)) {
            $curr = $this->interviewRepo->findByIdWithAthlete($interview_id);
            $curr_ath = safe_json_decode($curr['athlete_answers'] ?? '{}');
            $merged_ath = array_replace_recursive($curr_ath, $athlete_answers_post);
            $this->interviewRepo->saveAthleteAnswers($interview_id, $merged_ath);
        }

        $this->interviewRepo->saveTrainerDecisions($interview_id, $trainer_answers, $decisions, $trainer_notes, $is_completed);

        if ($is_completed) {
            flash('success', 'L\'entretien a été validé d\'un commun accord et clôturé avec succès.');
        } else {
            flash('success', 'Les notes et ajustements ont été enregistrés avec succès.');
        }

        redirect($redirect_to);
    }

    /**
     * Réouverture d'une fiche d'entretien verrouillée
     */
    public function reopen(): void {
        Auth::require_admin();

        $interview_id = (int)($_POST['interview_id'] ?? 0);
        $redirect_to = (string)($_POST['redirect_to'] ?? '/admin');

        if ($interview_id <= 0) {
            flash('error', 'Entretien invalide.');
            redirect('/admin');
            return;
        }

        $stmt = $this->db->prepare("UPDATE interviews SET status = 'draft', is_validated = 0, validated_at = NULL WHERE id = ?");
        $stmt->execute([$interview_id]);

        flash('info', 'L\'entretien a été déverrouillé. L\'athlète et vous-même pouvez à nouveau modifier les réponses.');
        redirect($redirect_to);
    }
}
