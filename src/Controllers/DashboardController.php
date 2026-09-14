<?php
declare(strict_types=1);

/**
 * Contrôleur du tableau de bord d'administration et de pilotage des entretiens
 */
class DashboardController {
    private SeasonRepository $seasonRepo;
    private AthleteRepository $athleteRepo;
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->seasonRepo = new SeasonRepository($this->db);
        $this->athleteRepo = new AthleteRepository($this->db);
    }

    /**
     * Page de connexion entraîneur
     */
    public function loginView(): void {
        if (Auth::is_logged_in()) {
            redirect('/admin');
        }
        require dirname(__DIR__, 2) . '/views/admin_login.php';
    }

    /**
     * Traitement de la connexion entraîneur
     */
    public function loginSubmit(): void {
        $password = (string)($_POST['password'] ?? '');
        if (Auth::login($password)) {
            flash('success', 'Bienvenue dans votre espace entraîneur.');
            redirect('/admin');
        } else {
            flash('error', 'Mot de passe incorrect.');
            redirect('/admin/login');
        }
    }

    /**
     * Déconnexion entraîneur
     */
    public function logout(): void {
        Auth::logout();
        flash('info', 'Vous avez été déconnecté de l\'espace entraîneur.');
        redirect('/admin/login');
    }

    /**
     * Tableau de bord principal
     */
    public function index(): void {
        Auth::require_admin();

        $all_seasons = $this->seasonRepo->getAll();

        // Récupérer la saison sélectionnée ou active
        $selected_season_id = (int)($_GET['season_id'] ?? 0);
        $season = null;
        if ($selected_season_id > 0) {
            $season = $this->seasonRepo->findById($selected_season_id);
        }
        if (!$season) {
            $season = $this->seasonRepo->getActive();
        }

        // Filtres
        $category_filter = trim((string)($_GET['category'] ?? ''));
        $status_filter = trim((string)($_GET['status'] ?? ''));
        $search = trim((string)($_GET['q'] ?? ''));

        // Requête sur les athlètes et leurs interviews pour la saison
        $sql = "SELECT a.*, i.id as interview_id, i.status as interview_status, i.interview_type, 
                       i.is_validated, i.validated_at, i.updated_at as interview_updated_at,
                       i.trainer_answers, i.decisions, i.athlete_answers
                FROM athletes a
                LEFT JOIN interviews i ON i.athlete_id = a.id AND i.season_id = :season_id
                WHERE 1=1";
        $params = [':season_id' => $season['id']];

        if ($category_filter !== '') {
            $sql .= " AND a.category = :cat";
            $params[':cat'] = $category_filter;
        }

        if ($status_filter !== '') {
            if ($status_filter === 'waiting') {
                $sql .= " AND (i.status = 'waiting' OR i.status IS NULL)";
            } else {
                $sql .= " AND i.status = :status";
                $params[':status'] = $status_filter;
            }
        }

        if ($search !== '') {
            $sql .= " AND (a.first_name LIKE :search OR a.last_name LIKE :search OR a.email LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $sql .= " ORDER BY a.last_name COLLATE NOCASE ASC, a.first_name COLLATE NOCASE ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $athletes = $stmt->fetchAll();

        // Statistiques globales
        $stats_stmt = $this->db->prepare(
            "SELECT 
                COUNT(DISTINCT a.id) as total_athletes,
                SUM(CASE WHEN i.status = 'completed' OR i.is_validated = 1 THEN 1 ELSE 0 END) as count_completed,
                SUM(CASE WHEN i.status = 'submitted' AND i.is_validated = 0 THEN 1 ELSE 0 END) as count_submitted,
                SUM(CASE WHEN i.status = 'draft' THEN 1 ELSE 0 END) as count_draft,
                SUM(CASE WHEN i.status = 'waiting' OR i.status IS NULL THEN 1 ELSE 0 END) as count_waiting
             FROM athletes a
             LEFT JOIN interviews i ON i.athlete_id = a.id AND i.season_id = ?"
        );
        $stats_stmt->execute([$season['id']]);
        $stats = $stats_stmt->fetch() ?: [
            'total_athletes' => 0, 'count_completed' => 0, 'count_submitted' => 0, 'count_draft' => 0, 'count_waiting' => 0
        ];

        $settings = SettingsService::all();

        require dirname(__DIR__, 2) . '/views/admin_dashboard.php';
    }
}
