<?php
declare(strict_types=1);

/**
 * Contrôleur des points d'accès API (Autosave, Soumission, Validation d'épreuves, Export WhatsApp)
 */
class ApiController {
    private PDO $db;
    private InterviewRepository $interviewRepo;
    private SeasonRepository $seasonRepo;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->interviewRepo = new InterviewRepository($this->db);
        $this->seasonRepo = new SeasonRepository($this->db);
    }

    /**
     * Récupère le payload JSON ou les paramètres POST/GET de façon sécurisée
     */
    private function getPayload(bool $allowGet = false): array {
        $raw = (PHP_SAPI === 'cli' && empty($_SERVER['HTTP_HOST'])) ? '' : (file_get_contents('php://input') ?: '');
        $json = !empty($raw) ? json_decode($raw, true) : null;
        if (!is_array($json)) {
            $json = [];
        }
        if ($allowGet) {
            return array_merge($_GET, $_POST, $json);
        }
        return !empty($json) ? $json : $_POST;
    }

    /**
     * Sauvegarde automatique asynchrone (AJAX / Fetch)
     */
    public function saveAnswers(): void {
        Auth::init_session();

        $payload = $this->getPayload();

        $athlete_id = (int)($_SESSION['athlete_id'] ?? ($payload['athlete_id'] ?? 0));
        $interview_id = (int)($payload['interview_id'] ?? 0);

        if ($athlete_id <= 0 && $interview_id <= 0 && !Auth::is_logged_in()) {
            json_response(['success' => false, 'error' => 'Session expirée ou non authentifiée'], 401);
            return;
        }

        // Récupérer ou initialiser l'entretien
        if ($interview_id > 0) {
            $stmt = $this->db->prepare("SELECT * FROM interviews WHERE id = ?");
            $stmt->execute([$interview_id]);
            $interview = $stmt->fetch();
        } elseif ($athlete_id > 0) {
            $season = $this->seasonRepo->getActive();
            $interview = $this->interviewRepo->getOrCreateForSeason($athlete_id, (int)$season['id']);
        } else {
            $interview = null;
        }

        if (!$interview) {
            json_response(['success' => false, 'error' => 'Fiche d\'entretien introuvable'], 404);
            return;
        }

        // Vérification de sécurité
        $session_athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        $is_coach = Auth::is_logged_in();
        $is_valid_athlete = ($session_athlete_id > 0 && $session_athlete_id === (int)$interview['athlete_id']) 
                         || ($athlete_id > 0 && $athlete_id === (int)$interview['athlete_id']);

        if (!$is_coach && !$is_valid_athlete) {
            json_response(['success' => false, 'error' => 'Accès non autorisé'], 403);
            return;
        }

        // Si la fiche est verrouillée et qu'on n'est pas coach, interdire
        if (in_array($interview['status'], ['submitted', 'completed', 'locked'], true) && !$is_coach) {
            json_response(['success' => false, 'error' => 'Ce bilan a déjà été validé et ne peut plus être modifié'], 422);
            return;
        }

        $answers = $payload['answers'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        // Fusionner avec les réponses existantes
        $current_answers = safe_json_decode($interview['athlete_answers'] ?? null);
        $merged_answers = array_merge($current_answers, $answers);

        $new_status = in_array($interview['status'], ['waiting', 'draft'], true) ? 'draft' : $interview['status'];

        $this->interviewRepo->saveAthleteAnswers((int)$interview['id'], $merged_answers, $new_status);

        json_response([
            'success' => true,
            'message' => 'Modifications enregistrées',
            'saved_at' => date('H:i:s'),
            'status' => $new_status
        ]);
    }

    /**
     * Soumission finale par l'athlète (verrouille le formulaire côté athlète)
     */
    public function submitInterview(): void {
        Auth::init_session();

        $payload = $this->getPayload();

        $athlete_id = (int)($_SESSION['athlete_id'] ?? ($payload['athlete_id'] ?? 0));
        $interview_id = (int)($payload['interview_id'] ?? 0);

        if ($athlete_id <= 0 && $interview_id <= 0 && !Auth::is_logged_in()) {
            json_response(['success' => false, 'error' => 'Session expirée'], 401);
            return;
        }

        if ($interview_id > 0) {
            $stmt = $this->db->prepare("SELECT * FROM interviews WHERE id = ?");
            $stmt->execute([$interview_id]);
            $interview = $stmt->fetch();
        } elseif ($athlete_id > 0) {
            $season = $this->seasonRepo->getActive();
            $interview = $this->interviewRepo->getOrCreateForSeason($athlete_id, (int)$season['id']);
        } else {
            $interview = null;
        }

        if (!$interview) {
            json_response(['success' => false, 'error' => 'Entretien introuvable'], 404);
            return;
        }

        $session_athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        $is_coach = Auth::is_logged_in();
        $is_valid_athlete = ($session_athlete_id > 0 && $session_athlete_id === (int)$interview['athlete_id']) 
                         || ($athlete_id > 0 && $athlete_id === (int)$interview['athlete_id']);

        if (!$is_coach && !$is_valid_athlete) {
            json_response(['success' => false, 'error' => 'Accès non autorisé'], 403);
            return;
        }

        $answers = $payload['answers'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        $current_answers = safe_json_decode($interview['athlete_answers'] ?? null);
        $merged_answers = array_merge($current_answers, $answers);

        $this->interviewRepo->saveAthleteAnswers((int)$interview['id'], $merged_answers, 'submitted');

        json_response([
            'success' => true,
            'message' => 'Votre bilan a été transmis avec succès à votre entraîneur.',
            'redirect' => url('/')
        ]);
    }

    /**
     * Validation dynamique de compatibilité d'épreuves (AJAX)
     */
    public function validateDisciplines(): void {
        $payload = $this->getPayload(true);

        $d1 = $payload['discipline_1'] ?? '';
        $d2 = $payload['discipline_2'] ?? '';

        $result = CategoryHelper::check_disciplines_compatibility($d1, $d2);
        json_response($result);
    }

    /**
     * Génération de la synthèse WhatsApp pour le presse-papier
     */
    public function exportWhatsapp(): void {
        $payload = $this->getPayload(true);
        $interview_id = (int)($payload['interview_id'] ?? 0);

        if ($interview_id <= 0) {
            json_response(['success' => false, 'error' => 'Identifiant manquant'], 400);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT i.*, a.first_name, a.last_name, a.birth_year, a.category, a.phone 
             FROM interviews i 
             JOIN athletes a ON a.id = i.athlete_id 
             WHERE i.id = ?"
        );
        $stmt->execute([$interview_id]);
        $data = $stmt->fetch();

        if (!$data) {
            json_response(['success' => false, 'error' => 'Fiche introuvable'], 404);
            return;
        }

        $athlete = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_year' => $data['birth_year'],
            'category' => $data['category'],
            'phone' => $data['phone']
        ];

        $text = WhatsAppHelper::build_synthesis_text($athlete, $data);

        json_response([
            'success' => true,
            'text' => $text
        ]);
    }

    // --- Alias de rétrocompatibilité (snake_case) ---
    public function save_answers(): void { $this->saveAnswers(); }
    public function submit_interview(): void { $this->submitInterview(); }
    public function validate_disciplines(): void { $this->validateDisciplines(); }
    public function export_whatsapp(): void { $this->exportWhatsapp(); }
}
