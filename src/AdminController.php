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

        // Récupérer la saison active
        $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season = $season_stmt->fetch() ?: ['id' => 1, 'name' => env('CURRENT_SEASON_NAME', '2026-2027')];

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
     * Export récapitulatif global (CSV / Excel) de la grille de rentrée
     */
    public function export_grid_csv(): void {
        Auth::require_admin();

        $season_stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season = $season_stmt->fetch() ?: ['id' => 1, 'name' => '2026-2027'];

        $stmt = $this->db->prepare(
            "SELECT a.*, i.status as interview_status, i.is_validated, i.validated_at, 
                    i.athlete_answers, i.trainer_answers, i.decisions 
             FROM athletes a 
             LEFT JOIN interviews i ON i.athlete_id = a.id AND i.season_id = ? 
             ORDER BY a.last_name ASC, a.first_name ASC"
        );
        $stmt->execute([$season['id']]);
        $rows = $stmt->fetchAll();

        $filename = "grille_rentree_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $season['name']) . "_" . date('Ymd_Hi') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        // BOM UTF-8 pour ouverture directe parfaite sous Microsoft Excel
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // En-têtes CSV obligatoires
        fputcsv($output, [
            'Nom',
            'Prénom',
            'Année',
            'Catégorie',
            'Statut entretien',
            'Attitude terrain',
            'Discipline prioritaire',
            'Discipline secondaire',
            'Option vendredi accordée',
            'Jours validés',
            'Volume hebdomadaire',
            'Contrat moral règle 1',
            'Contrat moral règle 2',
            'Date de validation'
        ], ';');

        foreach ($rows as $r) {
            $trainer_answers = safe_json_decode($r['trainer_answers'] ?? null);
            $decisions = safe_json_decode($r['decisions'] ?? null);
            $athlete_answers = safe_json_decode($r['athlete_answers'] ?? null);

            $status_fr = match ($r['interview_status'] ?? 'waiting') {
                'completed' => 'Validé',
                'submitted' => 'Soumis',
                'draft' => 'Brouillon',
                default => 'En attente'
            };

            $attitude = $trainer_answers['attitude_status'] ?? ($athlete_answers['self_eval_attitude'] ?? '');
            $attitude_fr = match ($attitude) {
                'ok', 'exemplary' => 'Exemplaire / OK',
                'fair' => 'Correct',
                'fragile', 'improve' => 'À améliorer / Fragile',
                default => 'Non évalué'
            };

            $d1_raw = $decisions['primary_discipline'] 
                ?? $decisions['friday_discipline_approved'] 
                ?? $decisions['approved_disciplines'][0] 
                ?? $athlete_answers['chosen_discipline_1'] 
                ?? $athlete_answers['friday_discipline'] 
                ?? '';
            $d2_raw = $decisions['secondary_discipline'] 
                ?? $decisions['approved_disciplines'][1] 
                ?? $athlete_answers['chosen_discipline_2'] 
                ?? '';

            $d1 = CategoryHelper::DISCIPLINES[$d1_raw] ?? CategoryHelper::FRIDAY_DISCIPLINES_U16[$d1_raw] ?? $d1_raw;
            $d2 = CategoryHelper::DISCIPLINES[$d2_raw] ?? $d2_raw;

            $friday_option = !empty($decisions['friday_option_granted']) ? 'Oui' : 'Non';

            $approved_days = $decisions['approved_training_days'] ?? ($athlete_answers['available_days'] ?? []);
            if (!is_array($approved_days)) {
                $approved_days = [$approved_days];
            }
            $days_fr = [];
            foreach ($approved_days as $day) {
                $days_fr[] = CategoryHelper::DAYS_FR[$day] ?? $day;
            }
            $days_str = implode(', ', $days_fr);

            $volume = $decisions['approved_weekly_sessions'] ?? ($athlete_answers['target_sessions_count'] ?? '');

            $rule1 = $decisions['mandatory_rule_1'] ?? ($athlete_answers['attitude_contract'] ?? ($athlete_answers['commitment_1'] ?? ''));
            $rule2 = $decisions['mandatory_rule_2'] ?? ($athlete_answers['commitment_2'] ?? '');

            $val_date = $r['validated_at'] ? date('d.m.Y H:i', strtotime($r['validated_at'])) : '';

            fputcsv($output, [
                $r['last_name'],
                $r['first_name'],
                $r['birth_year'] ?: '',
                CategoryHelper::get_category_label((int)$r['birth_year'], $r['category']),
                $status_fr,
                $attitude_fr,
                $d1,
                $d2,
                $friday_option,
                $days_str,
                $volume,
                $rule1,
                $rule2,
                $val_date
            ], ';');
        }

        fclose($output);
        exit;
    }
}
