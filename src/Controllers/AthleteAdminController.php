<?php
declare(strict_types=1);

/**
 * Contrôleur pour la gestion administrative des athlètes (CRUD, PIN, reset)
 */
class AthleteAdminController {
    private AthleteRepository $athleteRepo;
    private SeasonRepository $seasonRepo;
    private InterviewRepository $interviewRepo;
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->athleteRepo = new AthleteRepository($this->db);
        $this->seasonRepo = new SeasonRepository($this->db);
        $this->interviewRepo = new InterviewRepository($this->db);
    }

    /**
     * Réinitialisation du code PIN d'un athlète
     */
    public function resetPin(): void {
        Auth::require_admin();

        $athlete_id = (int)($_POST['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            flash('error', 'Athlète introuvable.');
            redirect('/admin');
            return;
        }

        $athlete = $this->athleteRepo->findById($athlete_id);

        if ($athlete) {
            $parsed = Helper::parse_birth_date($athlete['birth_date'] ?? null);
            $new_pin = $parsed['pin'];

            $this->athleteRepo->resetPin($athlete_id, $new_pin);
            flash('success', "Le code PIN de {$athlete['first_name']} {$athlete['last_name']} a été réinitialisé à '{$new_pin}'.");
        }

        redirect('/admin');
    }

    /**
     * Ajout manuel rapide d'un athlète
     */
    public function add(): void {
        Auth::require_admin();

        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $birth_date_raw = trim((string)($_POST['birth_date'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'U18'));

        if ($first_name === '' || $last_name === '') {
            flash('error', 'Le prénom et le nom sont obligatoires.');
            redirect('/admin');
            return;
        }

        $parsed_birth = Helper::parse_birth_date($birth_date_raw);
        $birth_date = $parsed_birth['birth_date'];
        $birth_year = $parsed_birth['birth_year'];
        $pin = $parsed_birth['pin'];

        if ($birth_year > 0) {
            $category = CategoryHelper::calculate_category($birth_year);
        }

        $athlete_id = $this->athleteRepo->create([
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'birth_date'   => $birth_date,
            'birth_year'   => $birth_year,
            'category'     => $category,
            'access_token' => Auth::generate_token(),
            'access_pin'   => $pin,
            'phone'        => $phone,
            'email'        => $email,
            'meta'         => '{}'
        ]);

        // Créer l'entretien pour la saison active
        $season = $this->seasonRepo->getActive();
        $form_type = CategoryHelper::get_form_type($birth_year, $category);
        $type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';
        $this->interviewRepo->getOrCreateForSeason($athlete_id, (int)$season['id'], $type);

        flash('success', "L'athlète {$first_name} {$last_name} a été ajouté avec succès (PIN : {$pin}).");
        redirect('/admin');
    }

    /**
     * Modification manuelle des informations d'un athlète
     */
    public function edit(): void {
        Auth::require_admin();

        $athlete_id = (int)($_POST['athlete_id'] ?? 0);
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $birth_date_raw = trim((string)($_POST['birth_date'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'U16'));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($athlete_id <= 0 || $first_name === '' || $last_name === '') {
            flash('error', 'Données athlète invalides (nom et prénom obligatoires).');
            redirect('/admin');
            return;
        }

        $athlete = $this->athleteRepo->findById($athlete_id);
        if (!$athlete) {
            flash('error', 'Athlète introuvable.');
            redirect('/admin');
            return;
        }

        $pin = (string)$athlete['access_pin'];
        $parsed_birth = Helper::parse_birth_date($birth_date_raw);
        $birth_date = $parsed_birth['birth_date'];
        $birth_year = $parsed_birth['birth_year'] > 0 ? $parsed_birth['birth_year'] : (int)$athlete['birth_year'];

        if ($parsed_birth['pin'] !== '0000' && ($pin === '0000' || empty($athlete['birth_date']) || $athlete['birth_date'] !== $birth_date)) {
            $pin = $parsed_birth['pin'];
        }

        if ($birth_year > 0) {
            $category = CategoryHelper::calculate_category($birth_year);
        }

        $meta = safe_json_decode($athlete['meta'] ?? null);
        if ($notes !== '') {
            $meta['notes'] = $notes;
        } else {
            unset($meta['notes']);
        }

        $this->athleteRepo->update($athlete_id, [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'birth_date' => $birth_date,
            'birth_year' => $birth_year,
            'category'   => $category,
            'phone'      => $phone,
            'email'      => $email,
            'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);

        if ($pin !== (string)$athlete['access_pin']) {
            $this->athleteRepo->resetPin($athlete_id, $pin);
        }

        flash('success', "L'athlète {$first_name} {$last_name} a été mis à jour avec succès.");
        redirect('/admin');
    }

    /**
     * Suppression d'un athlète et de ses entretiens
     */
    public function delete(): void {
        Auth::require_admin();

        $athlete_id = (int)($_POST['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            flash('error', 'Athlète introuvable.');
            redirect('/admin');
            return;
        }

        $this->athleteRepo->delete($athlete_id);
        flash('info', 'L\'athlète et ses entretiens associés ont été supprimés.');
        redirect('/admin');
    }
}
