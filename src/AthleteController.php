<?php
declare(strict_types=1);

/**
 * Contrôleur de l'espace athlète : connexion PIN, token direct et saisie du bilan
 */
class AthleteController {
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
     * Affiche l'écran d'accueil athlète (ou redirige vers le bilan si connecté / token direct)
     */
    public function formView(): void {
        Auth::init_session();

        // Accès direct par token dans l'URL ?token=...
        if (!empty($_GET['token'])) {
            $this->directTokenLogin((string)$_GET['token']);
            return;
        }

        $athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            $this->loginView();
            return;
        }

        $athlete = $this->athleteRepo->findById($athlete_id);
        if (!$athlete) {
            unset($_SESSION['athlete_id'], $_SESSION['athlete_name']);
            redirect('/login');
            return;
        }

        $season = $this->seasonRepo->getActive();
        $form_type = CategoryHelper::get_form_type((int)$athlete['birth_year'], $athlete['category']);
        $type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';

        $interview = $this->interviewRepo->getOrCreateForSeason($athlete['id'], (int)$season['id'], $type);

        $answers = json_decode($interview['athlete_answers'] ?? '{}', true) ?: [];
        $decisions = json_decode($interview['decisions'] ?? '{}', true) ?: [];
        $trainer_answers = json_decode($interview['trainer_answers'] ?? '{}', true) ?: [];

        $is_locked = in_array($interview['status'], ['submitted', 'completed'], true);
        $category_label = CategoryHelper::get_category_label((int)$athlete['birth_year'], $athlete['category']);

        $previous_summary = InterviewHistoryHelper::getPreviousSummary((int)$athlete['id'], (int)$season['id'], $this->db);

        if ($form_type === 'u16_1') {
            require dirname(__DIR__) . '/views/athlete_form_u16_1.php';
        } elseif ($form_type === 'u16_2') {
            require dirname(__DIR__) . '/views/athlete_form_u16_2.php';
        } else {
            require dirname(__DIR__) . '/views/athlete_form_u18.php';
        }
    }

    /**
     * Écran de connexion et sélection d'athlète
     */
    public function loginView(): void {
        Auth::init_session();

        if (!empty($_SESSION['athlete_id'])) {
            redirect('/');
            return;
        }

        $athletes = $this->athleteRepo->getAllOrdered();
        $active_season = $this->seasonRepo->getActive();
        $missing_whatsapp_url = WhatsAppHelper::build_missing_athlete_whatsapp_url();

        require dirname(__DIR__) . '/views/athlete_login.php';
    }

    /**
     * Traitement de la connexion par PIN
     */
    public function loginSubmit(): void {
        Auth::init_session();

        $athlete_id = (int)($_POST['athlete_id'] ?? 0);
        $pin = trim((string)($_POST['pin'] ?? ''));

        if ($athlete_id <= 0) {
            flash('error', 'Veuillez sélectionner votre nom dans la liste.');
            redirect('/login');
            return;
        }

        $athlete = $this->athleteRepo->findById($athlete_id);
        if (!$athlete) {
            flash('error', 'Athlète introuvable.');
            redirect('/login');
            return;
        }

        $correct_pin = trim((string)$athlete['access_pin']);
        if ($pin !== $correct_pin) {
            flash('error', 'Code PIN incorrect. Si vous l\'avez oublié, contactez votre entraîneur.');
            redirect('/login');
            return;
        }

        $_SESSION['athlete_id'] = $athlete['id'];
        $_SESSION['athlete_name'] = $athlete['first_name'] . ' ' . $athlete['last_name'];

        redirect('/');
    }

    /**
     * Connexion directe via token d'accès WhatsApp
     */
    public function directTokenLogin(string $token): void {
        Auth::init_session();

        $token = trim($token);
        if ($token === '') {
            redirect('/');
            return;
        }

        $athlete = $this->athleteRepo->findByToken($token);
        if ($athlete) {
            $_SESSION['athlete_id'] = $athlete['id'];
            $_SESSION['athlete_name'] = $athlete['first_name'] . ' ' . $athlete['last_name'];
            redirect('/');
            return;
        }

        flash('error', 'Lien d\'accès direct invalide ou expiré.');
        redirect('/login');
    }

    /**
     * Mise à jour de la date de naissance (pour PIN 0000)
     */
    public function updateBirthDate(): void {
        Auth::init_session();

        $athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            redirect('/login');
            return;
        }

        $birth_date = trim((string)($_POST['birth_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            flash('error', 'Format de date de naissance invalide.');
            redirect('/');
            return;
        }

        $parts = explode('-', $birth_date);
        $birth_year = (int)$parts[0];
        $pin = $parts[2] . $parts[1];

        $this->athleteRepo->updatePinAndBirthDate($athlete_id, $birth_date, $birth_year, $pin);
        flash('success', 'Votre date de naissance et votre nouveau code PIN ont été enregistrés.');
        redirect('/');
    }

    /**
     * Déverrouillage par l'athlète
     */
    public function unlockForm(): void {
        Auth::init_session();

        $athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            redirect('/login');
            return;
        }

        $season = $this->seasonRepo->getActive();
        $this->interviewRepo->unlock($athlete_id, (int)$season['id']);

        flash('info', 'Ton bilan est déverrouillé. Tu peux modifier tes réponses et les transmettre à nouveau.');
        redirect('/');
    }

    /**
     * Déconnexion athlète
     */
    public function logout(): void {
        Auth::init_session();
        unset($_SESSION['athlete_id'], $_SESSION['athlete_name']);
        flash('info', 'Vous avez été déconnecté avec succès.');
        redirect('/login');
    }

    // --- Alias pour compatibilité snake_case ---
    public function login_view(): void { $this->loginView(); }
    public function login_submit(): void { $this->loginSubmit(); }
    public function form_view(): void { $this->formView(); }
    public function update_birth_date(): void { $this->updateBirthDate(); }
    public function unlock_form(): void { $this->unlockForm(); }
    public function direct_token_login(string $t): void { $this->directTokenLogin($t); }
}
