<?php
declare(strict_types=1);

/**
 * Contrôleur de l'espace entraîneur et administrateur
 */
class AdminController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Page de connexion entraîneur
     */
    public function login_view(): void {
        if (Auth::is_logged_in()) {
            redirect('/admin');
        }
        require dirname(__DIR__) . '/views/admin_login.php';
    }

    /**
     * Traitement de la connexion entraîneur
     */
    public function login_submit(): void {
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
    public function dashboard(): void {
        Auth::require_admin();

        // Récupérer toutes les saisons
        $all_seasons_stmt = $this->db->query("SELECT * FROM seasons ORDER BY id DESC");
        $all_seasons = $all_seasons_stmt->fetchAll();

        // Récupérer la saison sélectionnée ou active
        $selected_season_id = (int)($_GET['season_id'] ?? 0);
        $season = null;
        if ($selected_season_id > 0) {
            foreach ($all_seasons as $s) {
                if ((int)$s['id'] === $selected_season_id) {
                    $season = $s;
                    break;
                }
            }
        }
        if (!$season) {
            foreach ($all_seasons as $s) {
                if ((int)$s['is_active'] === 1) {
                    $season = $s;
                    break;
                }
            }
        }
        if (!$season) {
            $season = $all_seasons[0] ?? ['id' => 1, 'name' => env('CURRENT_SEASON_NAME', '2026-2027'), 'is_active' => 1];
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

        require dirname(__DIR__) . '/views/admin_dashboard.php';
    }

    /**
     * Module U16 express (groupe de 2 à 4 athlètes)
     */
    public function u16_group_view(): void {
        Auth::require_admin();

        $ids_param = $_GET['ids'] ?? '';
        $athlete_ids = array_filter(array_map('intval', explode(',', (string)$ids_param)));

        if (empty($athlete_ids)) {
            flash('warning', 'Veuillez sélectionner entre 2 et 4 athlètes U16 pour lancer l\'entretien groupé.');
            redirect('/admin');
        }

        if (count($athlete_ids) > 4) {
            flash('warning', 'Veuillez sélectionner au maximum 4 athlètes simultanément.');
            redirect('/admin');
        }

        // Récupérer la saison
        $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season = $season_stmt->fetch();

        // Récupérer les données des athlètes sélectionnés
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

        // Récupérer les données de la saison précédente pour chaque athlète (historique N-1)
        $hist_stmt = $this->db->prepare(
            "SELECT i.*, s.name as season_name 
             FROM interviews i 
             JOIN seasons s ON s.id = i.season_id 
             WHERE i.athlete_id = ? AND i.season_id != ? 
             ORDER BY i.id DESC LIMIT 1"
        );

        // Pour chaque athlète sans interview créée, en créer une
        foreach ($athletes as &$ath) {
            if (empty($ath['interview_id'])) {
                $c_stmt = $this->db->prepare(
                    "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
                     VALUES (?, ?, 'u16_group', 'waiting', '{}', '{}', '{}')"
                );
                $c_stmt->execute([$ath['id'], $season['id']]);
                $ath['interview_id'] = (int)$this->db->lastInsertId();
                $ath['interview_status'] = 'waiting';
                $ath['athlete_answers'] = '{}';
                $ath['trainer_answers'] = '{}';
                $ath['decisions'] = '{}';
                $ath['is_validated'] = 0;
            }
            $ath['answers_arr'] = safe_json_decode($ath['athlete_answers'] ?? null);
            $ath['trainer_arr'] = safe_json_decode($ath['trainer_answers'] ?? null);
            $ath['decisions_arr'] = safe_json_decode($ath['decisions'] ?? null);
            $ath['age'] = CategoryHelper::get_athlete_age((int)$ath['birth_year']);
            $ath['category_label'] = CategoryHelper::get_category_label((int)$ath['birth_year'], $ath['category']);

            // Historique N-1
            $hist_stmt->execute([$ath['id'], $season['id']]);
            $ath['history'] = $hist_stmt->fetch() ?: null;
        }
        unset($ath);

        require dirname(__DIR__) . '/views/admin_u16_group.php';
    }

    /**
     * Module U18+ individuel (split screen)
     */
    public function u18_split_view(): void {
        Auth::require_admin();

        $interview_id = (int)($_GET['interview_id'] ?? 0);
        $athlete_id = (int)($_GET['athlete_id'] ?? 0);

        // Saison
        $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season = $season_stmt->fetch();

        if ($interview_id > 0) {
            $stmt = $this->db->prepare(
                "SELECT i.*, a.first_name, a.last_name, a.birth_date, a.birth_year, a.category, a.phone, a.email, a.access_token, a.access_pin, a.meta 
                 FROM interviews i 
                 JOIN athletes a ON a.id = i.athlete_id 
                 WHERE i.id = ?"
            );
            $stmt->execute([$interview_id]);
            $interview = $stmt->fetch();
        } elseif ($athlete_id > 0) {
            $stmt = $this->db->prepare(
                "SELECT i.*, a.first_name, a.last_name, a.birth_date, a.birth_year, a.category, a.phone, a.email, a.access_token, a.access_pin, a.meta 
                 FROM athletes a 
                 LEFT JOIN interviews i ON i.athlete_id = a.id AND i.season_id = ? 
                 WHERE a.id = ?"
            );
            $stmt->execute([$season['id'], $athlete_id]);
            $interview = $stmt->fetch();

            if ($interview && empty($interview['id'])) {
                // Créer l'interview
                $form_type = CategoryHelper::get_form_type((int)$interview['birth_year'], $interview['category']);
                $type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';
                $ins_stmt = $this->db->prepare(
                    "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
                     VALUES (?, ?, ?, 'waiting', '{}', '{}', '{}')"
                );
                $ins_stmt->execute([$athlete_id, $season['id'], $type]);
                $new_id = (int)$this->db->lastInsertId();
                redirect("/admin/u18-split?interview_id={$new_id}");
            }
        } else {
            flash('error', 'Entretien non spécifié.');
            redirect('/admin');
        }

        if (!$interview) {
            flash('error', 'Fiche d\'entretien introuvable.');
            redirect('/admin');
        }

        // Récupérer l'historique de la saison précédente s'il existe
        $history_stmt = $this->db->prepare(
            "SELECT i.*, s.name as season_name 
             FROM interviews i 
             JOIN seasons s ON s.id = i.season_id 
             WHERE i.athlete_id = ? AND i.season_id != ? 
             ORDER BY i.id DESC LIMIT 1"
        );
        $history_stmt->execute([$interview['athlete_id'], $season['id']]);
        $history = $history_stmt->fetch();

        $athlete_answers = safe_json_decode($interview['athlete_answers'] ?? null);
        $trainer_answers = safe_json_decode($interview['trainer_answers'] ?? null);
        $decisions = safe_json_decode($interview['decisions'] ?? null);
        $athlete_meta = safe_json_decode($interview['meta'] ?? null);

        $birth_year = (int)$interview['birth_year'];
        $category_label = CategoryHelper::get_category_label($birth_year, $interview['category']);
        $age = CategoryHelper::get_athlete_age($birth_year);

        require dirname(__DIR__) . '/views/admin_u18_split.php';
    }

    /**
     * Enregistrement des notes et arbitrages coach
     */
    public function save_trainer_form(): void {
        Auth::require_admin();

        $interview_id = (int)($_POST['interview_id'] ?? 0);
        $redirect_to = (string)($_POST['redirect_to'] ?? '/admin');

        if ($interview_id <= 0) {
            flash('error', 'Entretien invalide.');
            redirect('/admin');
        }

        $trainer_answers = $_POST['trainer_answers'] ?? [];
        $decisions = $_POST['decisions'] ?? [];
        $trainer_notes = trim((string)($_POST['trainer_notes'] ?? ''));
        $action = (string)($_POST['action'] ?? 'save'); // 'save' ou 'validate'

        // Sécuriser les JSON
        $json_trainer = json_encode($trainer_answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json_decisions = json_encode($decisions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Gestion de la correction / complétion des réponses athlète par l'entraîneur
        $athlete_answers_post = $_POST['athlete_answers'] ?? null;
        $sql_athlete_update = "";
        $params_extra = [];

        if (is_array($athlete_answers_post)) {
            $curr_stmt = $this->db->prepare("SELECT athlete_answers FROM interviews WHERE id = ?");
            $curr_stmt->execute([$interview_id]);
            $curr_ath = safe_json_decode($curr_stmt->fetchColumn() ?: '{}');
            $merged_ath = array_replace_recursive($curr_ath, $athlete_answers_post);
            $json_athlete = json_encode($merged_ath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $sql_athlete_update = ", athlete_answers = ?";
            $params_extra[] = $json_athlete;
        }

        if ($action === 'validate') {
            $sql = "UPDATE interviews 
                    SET trainer_answers = ?, decisions = ?, trainer_notes = ?, 
                        is_validated = 1, validated_at = CURRENT_TIMESTAMP, status = 'completed', 
                        updated_at = CURRENT_TIMESTAMP {$sql_athlete_update} 
                    WHERE id = ?";
            $params = array_merge([$json_trainer, $json_decisions, $trainer_notes], $params_extra, [$interview_id]);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            flash('success', 'L\'entretien a été validé d\'un commun accord et clôturé avec succès.');
        } else {
            $sql = "UPDATE interviews 
                    SET trainer_answers = ?, decisions = ?, trainer_notes = ?, 
                        updated_at = CURRENT_TIMESTAMP {$sql_athlete_update} 
                    WHERE id = ?";
            $params = array_merge([$json_trainer, $json_decisions, $trainer_notes], $params_extra, [$interview_id]);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            flash('success', 'Les notes et ajustements ont été enregistrés avec succès.');
        }

        redirect($redirect_to);
    }

    /**
     * Réouverture d'une fiche d'entretien verrouillée
     */
    public function reopen_interview(): void {
        Auth::require_admin();

        $interview_id = (int)($_POST['interview_id'] ?? 0);
        if ($interview_id > 0) {
            $stmt = $this->db->prepare(
                "UPDATE interviews 
                 SET status = 'draft', is_validated = 0, validated_at = NULL, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?"
            );
            $stmt->execute([$interview_id]);
            flash('info', 'La fiche a été réouverte aux modifications.');
        }

        redirect($_POST['redirect_to'] ?? '/admin');
    }

    /**
     * Réinitialisation du code PIN d'un athlète
     */
    public function reset_pin(): void {
        Auth::require_admin();

        $athlete_id = (int)($_POST['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            flash('error', 'Athlète introuvable.');
            redirect('/admin');
        }

        $stmt = $this->db->prepare("SELECT * FROM athletes WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();

        if ($athlete) {
            $new_pin = '0000';
            if (!empty($athlete['birth_date']) && preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $athlete['birth_date'], $m)) {
                $new_pin = $m[2] . $m[1]; // JJMM
            }

            $up_stmt = $this->db->prepare("UPDATE athletes SET access_pin = ? WHERE id = ?");
            $up_stmt->execute([$new_pin, $athlete_id]);

            flash('success', "Le code PIN de {$athlete['first_name']} {$athlete['last_name']} a été réinitialisé à '{$new_pin}'.");
        }

        redirect('/admin');
    }

    /**
     * Ajout manuel rapide d'un athlète
     */
    public function add_athlete(): void {
        Auth::require_admin();

        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $birth_date = trim((string)($_POST['birth_date'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'U18'));

        if ($first_name === '' || $last_name === '') {
            flash('error', 'Le prénom et le nom sont obligatoires.');
            redirect('/admin');
        }

        $birth_year = 0;
        $pin = '0000';
        if ($birth_date !== '' && preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $birth_date, $m)) {
            $birth_year = (int)substr($birth_date, 0, 4);
            $pin = $m[2] . $m[1];
        } else {
            $birth_date = null;
        }

        $token = bin2hex(random_bytes(16));

        $stmt = $this->db->prepare(
            "INSERT INTO athletes (first_name, last_name, birth_date, birth_year, category, access_token, access_pin, phone, email, meta) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '{}')"
        );
        $stmt->execute([$first_name, $last_name, $birth_date, $birth_year, $category, $token, $pin, $phone, $email]);
        $athlete_id = (int)$this->db->lastInsertId();

        // Récupérer la saison active
        $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season = $season_stmt->fetch();
        if ($season) {
            $form_type = CategoryHelper::get_form_type($birth_year, $category);
            $type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';
            $int_stmt = $this->db->prepare(
                "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
                 VALUES (?, ?, ?, 'waiting', '{}', '{}', '{}')"
            );
            $int_stmt->execute([$athlete_id, $season['id'], $type]);
        }

        flash('success', "L'athlète {$first_name} {$last_name} a été ajouté avec succès (PIN : {$pin}).");
        redirect('/admin');
    }

    /**
     * Modification manuelle des informations d'un athlète
     */
    public function edit_athlete(): void {
        Auth::require_admin();

        $athlete_id = (int)($_POST['athlete_id'] ?? 0);
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $birth_date = trim((string)($_POST['birth_date'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'U16'));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($athlete_id <= 0 || $first_name === '' || $last_name === '') {
            flash('error', 'Données athlète invalides (nom et prénom obligatoires).');
            redirect('/admin');
        }

        $stmt = $this->db->prepare("SELECT * FROM athletes WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();

        if (!$athlete) {
            flash('error', 'Athlète introuvable.');
            redirect('/admin');
        }

        $birth_year = (int)$athlete['birth_year'];
        $pin = (string)$athlete['access_pin'];

        if ($birth_date !== '') {
            if (preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $birth_date, $m)) {
                $birth_year = (int)substr($birth_date, 0, 4);
                if ($pin === '0000' || empty($athlete['birth_date']) || $athlete['birth_date'] !== $birth_date) {
                    $pin = $m[2] . $m[1];
                }
            }
        } else {
            $birth_date = null;
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
        $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $upd = $this->db->prepare(
            "UPDATE athletes 
             SET first_name = ?, last_name = ?, birth_date = ?, birth_year = ?, category = ?, access_pin = ?, phone = ?, email = ?, meta = ? 
             WHERE id = ?"
        );
        $upd->execute([$first_name, $last_name, $birth_date, $birth_year, $category, $pin, $phone, $email, $meta_json, $athlete_id]);

        flash('success', "L'athlète {$first_name} {$last_name} a été mis à jour avec succès.");
        redirect('/admin');
    }

    /**
     * Suppression définitive d'un athlète et de ses entretiens associés
     */
    public function delete_athlete(): void {
        Auth::require_admin();

        $athlete_id = (int)($_POST['athlete_id'] ?? 0);
        if ($athlete_id <= 0) {
            flash('error', 'Athlète introuvable.');
            redirect('/admin');
        }

        $stmt = $this->db->prepare("SELECT first_name, last_name FROM athletes WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();

        if ($athlete) {
            $name = $athlete['first_name'] . ' ' . $athlete['last_name'];
            $del_int = $this->db->prepare("DELETE FROM interviews WHERE athlete_id = ?");
            $del_int->execute([$athlete_id]);

            $del_ath = $this->db->prepare("DELETE FROM athletes WHERE id = ?");
            $del_ath->execute([$athlete_id]);

            flash('success', "L'athlète {$name} et l'ensemble de ses données ont été supprimés.");
        } else {
            flash('error', 'Athlète introuvable.');
        }

        redirect('/admin');
    }

    /**
     * Téléchargement du modèle CSV type
     */
    public function download_template_csv(): void {
        Auth::require_admin();

        $file_path = dirname(__DIR__) . '/modele_athletes.csv';
        if (!file_exists($file_path)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "last_name,first_name,birth_date,phone,email,notes\n";
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="modele_athletes.csv"');
        readfile($file_path);
        exit;
    }

    /**
     * Import CSV avec logique intelligente d'Upsert
     */
    public function import_athletes_csv(): void {
        Auth::require_admin();

        if (empty($_FILES['csv_file']['tmp_name'])) {
            flash('error', 'Veuillez sélectionner un fichier CSV.');
            redirect('/admin');
        }

        $tmp_file = $_FILES['csv_file']['tmp_name'];
        $content = file_get_contents($tmp_file);

        // Supprimer le BOM UTF-8 éventuel
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            flash('error', 'Le fichier CSV est vide.');
            redirect('/admin');
        }

        // Détection du séparateur (, ou ;)
        $first_line = $lines[0];
        $separator = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';

        $header = str_getcsv($first_line, $separator);
        $header_map = [];
        foreach ($header as $idx => $col_name) {
            $header_map[strtolower(trim($col_name))] = $idx;
        }

        $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season = $season_stmt->fetch();

        $inserted = 0;
        $updated = 0;

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') continue;

            $row = str_getcsv($line, $separator);

            $last_name = trim($row[$header_map['last_name'] ?? 0] ?? '');
            $first_name = trim($row[$header_map['first_name'] ?? 1] ?? '');
            $birth_date_raw = trim($row[$header_map['birth_date'] ?? 2] ?? '');
            $phone = trim($row[$header_map['phone'] ?? 3] ?? '');
            $email = trim($row[$header_map['email'] ?? 4] ?? '');
            $notes = trim($row[$header_map['notes'] ?? 5] ?? '');

            if ($last_name === '' || $first_name === '') {
                continue;
            }

            $birth_date = null;
            $birth_year = 0;
            $pin = '0000';

            if ($birth_date_raw !== '' && preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $birth_date_raw, $m)) {
                $birth_date = $birth_date_raw;
                $birth_year = (int)substr($birth_date, 0, 4);
                $pin = $m[2] . $m[1]; // JJMM
            }

            // Calcul catégorie par défaut
            $cat = 'U18';
            if ($birth_year > 0) {
                $age = CategoryHelper::get_athlete_age($birth_year);
                if ($age <= 15) $cat = 'U16';
                elseif ($age <= 17) $cat = 'U18';
                elseif ($age <= 19) $cat = 'U20';
                elseif ($age <= 22) $cat = 'U23';
                else $cat = 'Actif';
            }

            // Vérifier existence par (first_name + last_name)
            $check_stmt = $this->db->prepare(
                "SELECT id, birth_date, birth_year, access_pin FROM athletes 
                 WHERE LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?)"
            );
            $check_stmt->execute([$first_name, $last_name]);
            $existing = $check_stmt->fetch();

            if ($existing) {
                // Upsert: mettre à jour coordonnées et notes sans écraser les réponses
                $update_sql = "UPDATE athletes SET phone = ?, email = ?, meta = json_set(meta, '$.notes', ?)";
                $params = [$phone, $email, $notes];

                // Si la date de naissance était manquante et qu'on l'a maintenant
                if (empty($existing['birth_date']) && $birth_date !== null) {
                    $update_sql .= ", birth_date = ?, birth_year = ?, category = ?";
                    $params[] = $birth_date;
                    $params[] = $birth_year;
                    $params[] = $cat;
                    if ($existing['access_pin'] === '0000') {
                        $update_sql .= ", access_pin = ?";
                        $params[] = $pin;
                    }
                }
                $update_sql .= " WHERE id = ?";
                $params[] = $existing['id'];

                $u_stmt = $this->db->prepare($update_sql);
                $u_stmt->execute($params);
                $updated++;
            } else {
                // Insertion nouvel athlète
                $token = bin2hex(random_bytes(16));
                $meta_json = json_encode(['notes' => $notes], JSON_UNESCAPED_UNICODE);

                $ins_stmt = $this->db->prepare(
                    "INSERT INTO athletes (first_name, last_name, birth_date, birth_year, category, access_token, access_pin, phone, email, meta) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $ins_stmt->execute([$first_name, $last_name, $birth_date, $birth_year, $cat, $token, $pin, $phone, $email, $meta_json]);
                $ath_id = (int)$this->db->lastInsertId();

                if ($season) {
                    $form_type = CategoryHelper::get_form_type($birth_year, $cat);
                    $type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';
                    $int_stmt = $this->db->prepare(
                        "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
                         VALUES (?, ?, ?, 'waiting', '{}', '{}', '{}')"
                    );
                    $int_stmt->execute([$ath_id, $season['id'], $type]);
                }
                $inserted++;
            }
        }

        flash('success', "Importation réussie : {$inserted} athlète(s) ajouté(s), {$updated} mis à jour.");
        redirect('/admin');
    }

    /**
     * Export récapitulatif global (CSV / Excel) de la grille de rentrée (17 colonnes exactes)
     */
    public function export_grid_csv(): void {
        Auth::require_admin();

        $selected_season_id = (int)($_GET['season_id'] ?? 0);
        if ($selected_season_id > 0) {
            $season_stmt = $this->db->prepare("SELECT * FROM seasons WHERE id = ?");
            $season_stmt->execute([$selected_season_id]);
            $season = $season_stmt->fetch();
        }
        if (empty($season)) {
            $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
            $season = $season_stmt->fetch() ?: ['id' => 1, 'name' => '2026-2027'];
        }

        $stmt = $this->db->prepare(
            "SELECT a.*, i.status as interview_status, i.is_validated, i.validated_at, 
                    i.athlete_answers, i.trainer_answers, i.decisions 
             FROM athletes a 
             LEFT JOIN interviews i ON i.athlete_id = a.id AND i.season_id = ? 
             ORDER BY a.last_name COLLATE NOCASE ASC, a.first_name COLLATE NOCASE ASC"
        );
        $stmt->execute([$season['id']]);
        $rows = $stmt->fetchAll();

        $filename = "grille_cadrage_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $season['name']) . "_" . date('Ymd_Hi') . ".csv";

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        // BOM UTF-8 pour ouverture parfaite sous Microsoft Excel et Apple Numbers
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // 18 colonnes officielles conformes à la grille du club
        fputcsv($output, [
            'Classe d’âge',
            'Entraîneur référent',
            'Groupe',
            'Groupe s.',
            'Vu',
            'Année',
            'Nom de famille',
            'Prénom',
            'Disciplines',
            'Dispo',
            'Lu',
            'Ma',
            'Me',
            'Je',
            'Ve',
            'Sa',
            'Force',
            'Cadre'
        ], ';', '"', "\\");

        foreach ($rows as $r) {
            $trainer_answers = safe_json_decode($r['trainer_answers'] ?? null);
            $decisions = safe_json_decode($r['decisions'] ?? null);
            $athlete_answers = safe_json_decode($r['athlete_answers'] ?? null);
            $meta = safe_json_decode($r['meta'] ?? null);

            $birth_year = (int)$r['birth_year'];
            $age = CategoryHelper::get_athlete_age($birth_year);
            $cat_label = CategoryHelper::get_category_label($birth_year, $r['category']);

            // 1. Classe d'âge (ex: Cadre U18+, U16 1ère année...)
            $classe_age = ($age >= 16) ? 'Cadre U18+' : $cat_label;

            // 2. Entraîneur référent
            $coach_ref = $decisions['coach_in_charge'] ?? ($meta['coach_in_charge'] ?? ($meta['coach'] ?? ''));

            // Disciplines
            $d1_raw = $decisions['primary_discipline'] 
                ?? ($decisions['friday_discipline_approved'] 
                ?? ($decisions['approved_disciplines'][0] 
                ?? ($athlete_answers['chosen_discipline_1'] 
                ?? ($athlete_answers['friday_discipline'] 
                ?? ''))));
            $d2_raw = $decisions['secondary_discipline'] 
                ?? ($decisions['approved_disciplines'][1] 
                ?? ($athlete_answers['chosen_discipline_2'] 
                ?? ''));

            $d1 = CategoryHelper::get_discipline_label($d1_raw);
            $d2 = CategoryHelper::get_discipline_label($d2_raw);
            $disciplines_str = trim($d1 . ($d2 !== 'Aucune' && $d2 !== '' ? ', ' . $d2 : ''));

            // 3. Groupe principal
            $groupe = $decisions['training_group'] ?? ($meta['training_group'] ?? '');
            if ($groupe === '' && $d1 !== '' && $d1 !== 'Aucune') {
                $fam = CategoryHelper::get_discipline_family($d1);
                $groupe = match ($fam) {
                    'endurance' => 'Demi-Fond',
                    'explosive_sprint_jump' => 'Sprint / Concours',
                    'throws' => 'Lancers',
                    'combined' => 'Combiné',
                    default => 'Général'
                };
            }

            // 4. Groupe secondaire
            $groupe_s = $decisions['secondary_group'] ?? ($meta['secondary_group'] ?? ($d2 !== 'Aucune' ? $d2 : ''));

            // 5. Vu / Statut
            $status_fr = match ($r['interview_status'] ?? 'waiting') {
                'completed' => 'Validé',
                'submitted' => 'Soumis',
                'draft' => 'Brouillon',
                default => 'En attente'
            };

            // 6. Année, 7. Nom, 8. Prénom
            $annee_str = $birth_year > 0 ? (string)$birth_year : '';

            // 10. Dispo (Synthèse claire du volume et jours d'entraînement)
            $vol_count = $decisions['approved_weekly_sessions'] ?? ($athlete_answers['target_sessions_count'] ?? '');
            $approved_days = $decisions['approved_training_days'] ?? ($athlete_answers['available_days'] ?? []);
            if (!is_array($approved_days)) {
                $approved_days = [$approved_days];
            }

            $dispo_str = Helper::format_availability_summary($approved_days, $vol_count !== '' ? (string)$vol_count : null);

            // 11-16. Jours Lu, Ma, Me, Je, Ve, Sa
            $has_day = fn($key) => in_array($key, $approved_days, true) || in_array("{$key}_morning", $approved_days, true);

            // Détermination du code par jour : code spécifique de séance si fixé, sinon 'X' si disponible
            $day_code = function(string $day_key, ?string $explicit_val = null) use ($has_day, $groupe) {
                if (!$has_day($day_key)) {
                    return '';
                }
                if ($explicit_val !== null && trim($explicit_val) !== '') {
                    return \App\Domain\DisciplineRules::get_short_code($explicit_val);
                }
                if ($groupe !== '' && $groupe !== 'Général') {
                    return \App\Domain\DisciplineRules::get_short_code($groupe);
                }
                return 'X';
            };

            $friday_spec = !empty($decisions['friday_discipline_approved']) 
                ? CategoryHelper::get_discipline_label($decisions['friday_discipline_approved']) 
                : ($d2 !== 'Aucune' && $d2 !== '' ? $d2 : null);

            $jour_lu = $day_code('monday', $decisions['monday_session'] ?? null);
            $jour_ma = $day_code('tuesday', $decisions['tuesday_session'] ?? null);
            $jour_me = $day_code('wednesday', $decisions['wednesday_session'] ?? null);
            $jour_je = $day_code('thursday', $decisions['thursday_session'] ?? null);
            $jour_ve = $day_code('friday', $decisions['friday_session'] ?? $friday_spec);
            $jour_sa = $has_day('saturday') ? 'End.' : '';

            // 17. Force / Renforcement
            $force_str = $decisions['strength_training'] ?? ($meta['strength_notes'] ?? ($decisions['mandatory_rule_1'] ?? ''));

            // 18. Cadre / Structure partenaire
            $cadre_str = $decisions['cadre_structure'] ?? ($meta['cadre'] ?? ($decisions['partner_structure'] ?? ''));

            fputcsv($output, [
                $classe_age,
                $coach_ref,
                $groupe,
                $groupe_s,
                $status_fr,
                $annee_str,
                $r['last_name'],
                $r['first_name'],
                $disciplines_str,
                $dispo_str,
                $jour_lu,
                $jour_ma,
                $jour_me,
                $jour_je,
                $jour_ve,
                $jour_sa,
                $force_str,
                $cadre_str
            ], ';', '"', "\\");
        }

        fclose($output);
        exit;
    }

    /**
     * Fiche Bilan imprimable (Clean Print A4 / PDF)
     */
    public function print_summary(): void {
        Auth::require_admin();

        $interview_id = (int)($_GET['interview_id'] ?? 0);
        $athlete_id = (int)($_GET['athlete_id'] ?? 0);

        if ($interview_id > 0) {
            $stmt = $this->db->prepare(
                "SELECT i.*, a.first_name, a.last_name, a.birth_date, a.birth_year, a.category, a.phone, a.email, a.meta,
                        s.name as season_name
                 FROM interviews i
                 JOIN athletes a ON a.id = i.athlete_id
                 JOIN seasons s ON s.id = i.season_id
                 WHERE i.id = ?"
            );
            $stmt->execute([$interview_id]);
            $interview = $stmt->fetch();
        } elseif ($athlete_id > 0) {
            $stmt = $this->db->prepare(
                "SELECT i.*, a.first_name, a.last_name, a.birth_date, a.birth_year, a.category, a.phone, a.email, a.meta,
                        s.name as season_name
                 FROM athletes a
                 JOIN seasons s ON s.is_active = 1
                 LEFT JOIN interviews i ON i.athlete_id = a.id AND i.season_id = s.id
                 WHERE a.id = ?"
            );
            $stmt->execute([$athlete_id]);
            $interview = $stmt->fetch();
        } else {
            flash('error', 'Entretien non spécifié.');
            redirect('/admin');
        }

        if (!$interview) {
            flash('error', 'Fiche d\'entretien introuvable.');
            redirect('/admin');
        }

        $athlete = [
            'id'         => $interview['athlete_id'] ?? $athlete_id,
            'first_name' => $interview['first_name'],
            'last_name'  => $interview['last_name'],
            'birth_date' => $interview['birth_date'] ?? null,
            'birth_year' => $interview['birth_year'],
            'category'   => $interview['category'],
            'phone'      => $interview['phone'] ?? null,
            'email'      => $interview['email'] ?? null,
            'meta'       => $interview['meta'] ?? '{}',
        ];

        $season = [
            'id'   => $interview['season_id'] ?? 1,
            'name' => $interview['season_name'] ?? '2026-2027'
        ];

        require dirname(__DIR__) . '/views/admin_print_summary.php';
    }

    /**
     * Sauvegarde complète de la base de données au format JSON
     */
    public function backup_database_json(): void {
        Auth::require_admin();

        $seasons = $this->db->query("SELECT * FROM seasons ORDER BY id ASC")->fetchAll();
        $athletes = $this->db->query("SELECT * FROM athletes ORDER BY id ASC")->fetchAll();
        $interviews = $this->db->query("SELECT * FROM interviews ORDER BY id ASC")->fetchAll();

        $backup_data = [
            'format_version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'club_name' => env('CLUB_NAME', 'CA Sion'),
            'data' => [
                'seasons' => $seasons,
                'athletes' => $athletes,
                'interviews' => $interviews
            ]
        ];

        while (ob_get_level()) {
            ob_end_clean();
        }

        $filename = "backup_ca_sion_" . date('Ymd_His') . ".json";
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        echo json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Téléchargement direct du fichier SQLite brut
     */
    public function backup_database_sqlite(): void {
        Auth::require_admin();

        $db_relative_path = env('DB_PATH', 'data/debriefing.sqlite');
        $db_path = dirname(__DIR__) . '/' . ltrim($db_relative_path, '/');

        if (!file_exists($db_path)) {
            flash('error', 'Fichier de base de données introuvable.');
            redirect('/admin');
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        $filename = "debriefing_ca_sion_" . date('Ymd_His') . ".sqlite";
        header('Content-Type: application/x-sqlite3');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Content-Length: ' . filesize($db_path));

        readfile($db_path);
        exit;
    }

    /**
     * Restauration de la base de données depuis un fichier JSON ou SQLite
     */
    public function restore_database(): void {
        Auth::require_admin();

        if (empty($_FILES['backup_file']['tmp_name'])) {
            flash('error', 'Veuillez sélectionner un fichier de sauvegarde (.json ou .sqlite).');
            redirect('/admin');
        }

        $tmp_path = $_FILES['backup_file']['tmp_name'];
        $orig_name = strtolower((string)$_FILES['backup_file']['name']);

        try {
            if (str_ends_with($orig_name, '.json')) {
                $content = file_get_contents($tmp_path);
                $json = json_decode($content, true);

                if (!is_array($json) || empty($json['data'])) {
                    throw new \Exception('Format de sauvegarde JSON invalide.');
                }

                $this->db->beginTransaction();

                // 1. Saisons
                if (isset($json['data']['seasons']) && is_array($json['data']['seasons'])) {
                    $this->db->exec("DELETE FROM seasons");
                    $stmt = $this->db->prepare("INSERT INTO seasons (id, name, is_active, created_at) VALUES (?, ?, ?, ?)");
                    foreach ($json['data']['seasons'] as $s) {
                        $stmt->execute([$s['id'], $s['name'], $s['is_active'], $s['created_at'] ?? date('Y-m-d H:i:s')]);
                    }
                }

                // 2. Athlètes
                if (isset($json['data']['athletes']) && is_array($json['data']['athletes'])) {
                    $this->db->exec("DELETE FROM athletes");
                    $stmt = $this->db->prepare("INSERT INTO athletes (id, first_name, last_name, birth_date, birth_year, category, access_token, access_pin, phone, email, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($json['data']['athletes'] as $a) {
                        $stmt->execute([
                            $a['id'], $a['first_name'], $a['last_name'], $a['birth_date'] ?? null,
                            $a['birth_year'], $a['category'], $a['access_token'], $a['access_pin'] ?? '0000',
                            $a['phone'] ?? null, $a['email'] ?? null, $a['meta'] ?? '{}', $a['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                }

                // 3. Interviews
                if (isset($json['data']['interviews']) && is_array($json['data']['interviews'])) {
                    $this->db->exec("DELETE FROM interviews");
                    $stmt = $this->db->prepare("INSERT INTO interviews (id, athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions, trainer_notes, is_validated, validated_at, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($json['data']['interviews'] as $i) {
                        $stmt->execute([
                            $i['id'], $i['athlete_id'], $i['season_id'], $i['interview_type'],
                            $i['status'] ?? 'waiting', $i['athlete_answers'] ?? '{}', $i['trainer_answers'] ?? '{}',
                            $i['decisions'] ?? '{}', $i['trainer_notes'] ?? null, $i['is_validated'] ?? 0,
                            $i['validated_at'] ?? null, $i['updated_at'] ?? date('Y-m-d H:i:s'), $i['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                }

                $this->db->commit();
                flash('success', 'La base de données a été restaurée avec succès depuis le fichier JSON.');
            } elseif (str_ends_with($orig_name, '.sqlite') || str_ends_with($orig_name, '.db')) {
                $db_relative_path = env('DB_PATH', 'data/debriefing.sqlite');
                $db_dest = dirname(__DIR__) . '/' . ltrim($db_relative_path, '/');
                copy($tmp_path, $db_dest);
                flash('success', 'Le fichier SQLite a été remplacé avec succès.');
            } else {
                flash('error', 'Extension de fichier non supportée (utilisez .json ou .sqlite).');
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            flash('error', 'Erreur lors de la restauration : ' . $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Création d'une nouvelle saison avec reconduction automatique des athlètes
     */
    public function create_season(): void {
        Auth::require_admin();

        $name = trim((string)($_POST['season_name'] ?? ''));
        if ($name === '') {
            flash('error', 'Le nom de la saison est obligatoire (ex: 2027-2028).');
            redirect('/admin');
        }

        // Désactiver les autres saisons et activer la nouvelle
        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE seasons SET is_active = 0");
            $ins = $this->db->prepare("INSERT INTO seasons (name, is_active) VALUES (?, 1)");
            $ins->execute([$name]);
            $new_season_id = (int)$this->db->lastInsertId();

            // Créer les fiches d'entretien pour chaque athlète existant
            $athletes = $this->db->query("SELECT * FROM athletes ORDER BY id ASC")->fetchAll();
            $int_ins = $this->db->prepare(
                "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
                 VALUES (?, ?, ?, 'waiting', '{}', '{}', '{}')"
            );

            foreach ($athletes as $a) {
                $form_type = CategoryHelper::get_form_type((int)$a['birth_year'], $a['category']);
                $type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';
                $int_ins->execute([$a['id'], $new_season_id, $type]);
            }

            $this->db->commit();
            flash('success', "La nouvelle saison {$name} a été créée et activée avec succès. L'ensemble des athlètes a été reconduit.");
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            flash('error', 'Erreur lors de la création de la saison : ' . $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Changer la saison active
     */
    public function set_active_season(): void {
        Auth::require_admin();

        $season_id = (int)($_POST['season_id'] ?? 0);
        if ($season_id <= 0) {
            flash('error', 'Saison invalide.');
            redirect('/admin');
        }

        $this->db->beginTransaction();
        try {
            $this->db->exec("UPDATE seasons SET is_active = 0");
            $stmt = $this->db->prepare("UPDATE seasons SET is_active = 1 WHERE id = ?");
            $stmt->execute([$season_id]);
            $this->db->commit();

            flash('success', 'La saison active a été modifiée.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            flash('error', 'Erreur : ' . $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Renommer une saison
     */
    public function rename_season(): void {
        Auth::require_admin();

        $season_id = (int)($_POST['season_id'] ?? 0);
        $new_name = trim((string)($_POST['season_name'] ?? ''));

        if ($season_id <= 0 || $new_name === '') {
            flash('error', 'Nom de saison invalide.');
            redirect('/admin');
        }

        try {
            $stmt = $this->db->prepare("UPDATE seasons SET name = ? WHERE id = ?");
            $stmt->execute([$new_name, $season_id]);
            flash('success', "La saison a été renommée en « {$new_name} » avec succès.");
        } catch (\Throwable $e) {
            flash('error', 'Erreur lors du renommage de la saison : ' . $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Supprimer une saison et ses fiches associées
     */
    public function delete_season(): void {
        Auth::require_admin();

        $season_id = (int)($_POST['season_id'] ?? 0);
        if ($season_id <= 0) {
            flash('error', 'Saison invalide.');
            redirect('/admin');
        }

        // Vérifier le nombre total de saisons
        $total_seasons = (int)$this->db->query("SELECT COUNT(*) FROM seasons")->fetchColumn();
        if ($total_seasons <= 1) {
            flash('error', 'Impossible de supprimer la seule saison existante de l\'application.');
            redirect('/admin');
        }

        $this->db->beginTransaction();
        try {
            // Récupérer la saison
            $stmt = $this->db->prepare("SELECT * FROM seasons WHERE id = ?");
            $stmt->execute([$season_id]);
            $season = $stmt->fetch();

            if (!$season) {
                throw new \Exception("Saison introuvable.");
            }

            // Si elle était active, activer une autre saison
            if ((int)$season['is_active'] === 1) {
                $other_stmt = $this->db->prepare("SELECT id FROM seasons WHERE id != ? ORDER BY id DESC LIMIT 1");
                $other_stmt->execute([$season_id]);
                $other_id = (int)$other_stmt->fetchColumn();
                if ($other_id > 0) {
                    $upd = $this->db->prepare("UPDATE seasons SET is_active = 1 WHERE id = ?");
                    $upd->execute([$other_id]);
                }
            }

            // Supprimer les entretiens liés
            $del_int = $this->db->prepare("DELETE FROM interviews WHERE season_id = ?");
            $del_int->execute([$season_id]);

            // Supprimer les séances collectives
            $del_cm = $this->db->prepare("DELETE FROM collective_meetings WHERE season_id = ?");
            $del_cm->execute([$season_id]);

            // Supprimer la saison
            $del_s = $this->db->prepare("DELETE FROM seasons WHERE id = ?");
            $del_s->execute([$season_id]);

            $this->db->commit();
            flash('success', "La saison « {$season['name']} » et ses données associées ont été supprimées avec succès.");
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            flash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        redirect('/admin');
    }

    /**
     * Mise à jour des paramètres généraux du club et du mot de passe
     */
    public function update_settings(): void {
        Auth::require_admin();

        $club_name = trim((string)($_POST['club_name'] ?? ''));
        $coach_phone = trim((string)($_POST['coach_phone'] ?? ''));
        $ref_year = (int)($_POST['reference_competition_year'] ?? 2027);
        $new_password = (string)($_POST['new_admin_password'] ?? '');

        if ($club_name !== '') {
            SettingsService::set('club_name', $club_name);
        }
        if ($coach_phone !== '') {
            SettingsService::set('coach_phone', $coach_phone);
        }
        if ($ref_year > 1900 && $ref_year < 2100) {
            SettingsService::set('reference_competition_year', (string)$ref_year);
        }

        if ($new_password !== '') {
            if (strlen($new_password) < 4) {
                flash('error', 'Le mot de passe administrateur doit contenir au moins 4 caractères.');
                redirect('/admin');
            }
            SettingsService::set_admin_password($new_password);
            flash('success', 'Paramètres et nouveau mot de passe enregistrés avec succès. (Le mot de passe .env reste utilisable en secours).');
        } else {
            flash('success', 'Les paramètres du club ont été mis à jour avec succès.');
        }

        redirect('/admin');
    }
}

