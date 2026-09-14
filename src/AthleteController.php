<?php
declare(strict_types=1);

/**
 * Contrôleur de l'espace athlète : connexion, saisie de bilan et autosave
 */
class AthleteController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Affiche l'écran de sélection de l'athlète et saisie de code PIN
     */
    public function login_view(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Si déjà connecté, rediriger vers le bilan
        if (!empty($_SESSION['athlete_id'])) {
            redirect('/');
        }

        // Récupérer la liste des athlètes classés par Nom, Prénom
        $stmt = $this->db->query(
            "SELECT id, first_name, last_name, birth_year, category, access_pin, birth_date 
             FROM athletes 
             ORDER BY last_name COLLATE NOCASE ASC, first_name COLLATE NOCASE ASC"
        );
        $athletes = $stmt->fetchAll();

        // Récupérer la saison courante
        $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $active_season = $season_stmt->fetch() ?: ['name' => env('CURRENT_SEASON_NAME', '2026-2027')];

        $missing_whatsapp_url = WhatsAppHelper::build_missing_athlete_whatsapp_url();

        require dirname(__DIR__) . '/views/athlete_login.php';
    }

    /**
     * Traitement de la connexion par PIN
     */
    public function login_submit(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $athlete_id = (int)($_POST['athlete_id'] ?? 0);
        $pin = trim((string)($_POST['pin'] ?? ''));

        if ($athlete_id <= 0) {
            flash('error', 'Veuillez sélectionner votre nom dans la liste.');
            redirect('/');
        }

        $stmt = $this->db->prepare("SELECT * FROM athletes WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();

        if (!$athlete) {
            flash('error', 'Athlète introuvable.');
            redirect('/');
        }

        // Vérification du PIN
        $correct_pin = trim((string)$athlete['access_pin']);
        if ($pin !== $correct_pin) {
            flash('error', 'Code PIN incorrect. Si vous l\'avez oublié, contactez votre entraîneur.');
            redirect('/');
        }

        // Connexion réussie
        $_SESSION['athlete_id'] = $athlete['id'];
        $_SESSION['athlete_name'] = $athlete['first_name'] . ' ' . $athlete['last_name'];

        redirect('/');
    }

    /**
     * Connexion directe via token d'accès WhatsApp
     */
    public function direct_token_login(string $token): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = trim($token);
        if ($token === '') {
            redirect('/');
        }

        $stmt = $this->db->prepare("SELECT * FROM athletes WHERE access_token = ?");
        $stmt->execute([$token]);
        $athlete = $stmt->fetch();

        if ($athlete) {
            $_SESSION['athlete_id'] = $athlete['id'];
            $_SESSION['athlete_name'] = $athlete['first_name'] . ' ' . $athlete['last_name'];
            redirect('/');
        }

        flash('error', 'Lien d\'accès direct invalide ou expiré.');
        redirect('/');
    }

    /**
     * Mise à jour obligatoire de la date de naissance pour les athlètes avec PIN 0000
     */
    public function update_birth_date(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            redirect('/');
        }

        $birth_date = trim((string)($_POST['birth_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            flash('error', 'Format de date de naissance invalide.');
            redirect('/');
        }

        $parts = explode('-', $birth_date);
        $birth_year = (int)$parts[0];
        $pin = $parts[2] . $parts[1]; // JJMM

        $stmt = $this->db->prepare("UPDATE athletes SET birth_date = ?, birth_year = ?, access_pin = ? WHERE id = ?");
        $stmt->execute([$birth_date, $birth_year, $pin, $athlete_id]);

        flash('success', 'Votre date de naissance et votre nouveau code PIN ont été enregistrés.');
        redirect('/');
    }

    /**
     * Affichage du formulaire de bilan adapté
     */
    public function form_view(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            redirect('/login');
        }

        // Récupérer l'athlète
        $stmt = $this->db->prepare("SELECT * FROM athletes WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();

        if (!$athlete) {
            unset($_SESSION['athlete_id']);
            redirect('/login');
        }

        // Récupérer la saison active
        $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season = $season_stmt->fetch();
        if (!$season) {
            // Créer une saison par défaut si absente
            $season_name = env('CURRENT_SEASON_NAME', '2026-2027');
            $this->db->exec("INSERT INTO seasons (name, is_active) VALUES ('{$season_name}', 1)");
            $season = ['id' => $this->db->lastInsertId(), 'name' => $season_name];
        }

        // Récupérer ou créer l'interview de l'athlète pour la saison
        $interview_stmt = $this->db->prepare("SELECT * FROM interviews WHERE athlete_id = ? AND season_id = ?");
        $interview_stmt->execute([$athlete['id'], $season['id']]);
        $interview = $interview_stmt->fetch();

        $form_type = CategoryHelper::get_form_type((int)$athlete['birth_year'], $athlete['category']);

        if (!$interview) {
            $interview_type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';
            $create_stmt = $this->db->prepare(
                "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
                 VALUES (?, ?, ?, 'waiting', '{}', '{}', '{}')"
            );
            $create_stmt->execute([$athlete['id'], $season['id'], $interview_type]);
            $interview_id = (int)$this->db->lastInsertId();

            $interview_stmt->execute([$athlete['id'], $season['id']]);
            $interview = $interview_stmt->fetch();
        }

        // Décoder les réponses JSON
        $answers = json_decode($interview['athlete_answers'] ?? '{}', true) ?: [];
        $decisions = json_decode($interview['decisions'] ?? '{}', true) ?: [];
        $trainer_answers = json_decode($interview['trainer_answers'] ?? '{}', true) ?: [];

        $is_locked = in_array($interview['status'], ['submitted', 'completed'], true);
        $category_label = CategoryHelper::get_category_label((int)$athlete['birth_year'], $athlete['category']);

        // Sélection de la vue selon le type
        if ($form_type === 'u16_1') {
            require dirname(__DIR__) . '/views/athlete_form_u16_1.php';
        } elseif ($form_type === 'u16_2') {
            require dirname(__DIR__) . '/views/athlete_form_u16_2.php';
        } else {
            require dirname(__DIR__) . '/views/athlete_form_u18.php';
        }
    }

    /**
     * Déverrouillage par l'athlète pour modifier ses réponses avant la validation
     */
    public function unlock_form(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            redirect('/login');
        }

        $season_stmt = $this->db->query("SELECT id FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season_id = (int)($season_stmt->fetchColumn() ?: 1);

        $stmt = $this->db->prepare("UPDATE interviews SET status = 'draft' WHERE athlete_id = ? AND season_id = ? AND is_validated = 0");
        $stmt->execute([$athlete_id, $season_id]);

        flash('info', 'Ton bilan est déverrouillé. Tu peux modifier tes réponses et les transmettre à nouveau.');
        redirect('/');
    }

    /**
     * Déconnexion athlète
     */
    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['athlete_id']);
        unset($_SESSION['athlete_name']);
        flash('info', 'Vous avez été déconnecté avec succès.');
        redirect('/');
    }
}
