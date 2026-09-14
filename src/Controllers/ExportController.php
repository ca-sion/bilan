<?php
declare(strict_types=1);

/**
 * Contrôleur pour les imports CSV, exports de grilles, synthèses imprimables et sauvegardes
 */
class ExportController {
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
     * Téléchargement du modèle CSV type
     */
    public function downloadTemplate(): void {
        Auth::require_admin();

        $file_path = dirname(__DIR__, 2) . '/modele_athletes.csv';
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
    public function importCsv(): void {
        Auth::require_admin();

        if (empty($_FILES['csv_file']['tmp_name'])) {
            flash('error', 'Veuillez sélectionner un fichier CSV.');
            redirect('/admin');
            return;
        }

        $tmp_file = $_FILES['csv_file']['tmp_name'];
        $content = file_get_contents($tmp_file);

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            flash('error', 'Le fichier CSV est vide.');
            redirect('/admin');
            return;
        }

        $first_line = $lines[0];
        $separator = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';

        $header_raw = str_getcsv($first_line, $separator);
        $header_map = [];
        foreach ($header_raw as $idx => $col_name) {
            $cleaned = strtolower(trim((string)$col_name));
            $cleaned = str_replace([' ', '_', '-', "\t"], '', $cleaned);
            $header_map[$cleaned] = $idx;
        }

        $col_aliases = [
            'last_name'  => ['lastname', 'last_name', 'nom', 'nomdefamille', 'nomfamille'],
            'first_name' => ['firstname', 'first_name', 'prenom', 'prénom'],
            'birth_date' => ['birthdate', 'birth_date', 'birthyear', 'datenaissance', 'datedenaissance', 'naissance', 'anniversaire', 'dob', 'annee', 'année'],
            'phone'      => ['phone', 'telephone', 'téléphone', 'tel', 'mobile', 'portable', 'natel'],
            'email'      => ['email', 'e-mail', 'mail', 'courriel'],
            'notes'      => ['notes', 'note', 'remarque', 'remarques', 'commentaire', 'commentaires']
        ];

        $has_detected_headers = false;
        $resolved_cols = [];
        foreach ($col_aliases as $field => $aliases) {
            foreach ($aliases as $alias) {
                $cleaned_alias = str_replace([' ', '_', '-'], '', strtolower($alias));
                if (isset($header_map[$cleaned_alias])) {
                    $resolved_cols[$field] = $header_map[$cleaned_alias];
                    $has_detected_headers = true;
                    break;
                }
            }
        }

        $start_line = $has_detected_headers ? 1 : 0;

        $season = $this->seasonRepo->getActive();
        $inserted = 0;
        $updated = 0;

        for ($i = $start_line; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') continue;

            $row = str_getcsv($line, $separator);

            $get_val = function(string $field, int $fallback_idx) use ($row, $resolved_cols, $has_detected_headers): string {
                if ($has_detected_headers) {
                    if (isset($resolved_cols[$field])) {
                        return trim((string)($row[$resolved_cols[$field]] ?? ''));
                    }
                    return '';
                }
                return trim((string)($row[$fallback_idx] ?? ''));
            };

            $last_name = $get_val('last_name', 0);
            $first_name = $get_val('first_name', 1);
            $birth_date_raw = $get_val('birth_date', 2);
            $phone = $get_val('phone', 3);
            $email = $get_val('email', 4);
            $notes = $get_val('notes', 5);

            if ($last_name === '' || $first_name === '') {
                continue;
            }

            $parsed_birth = Helper::parse_birth_date($birth_date_raw);
            $birth_date = $parsed_birth['birth_date'];
            $birth_year = $parsed_birth['birth_year'];
            $pin = $parsed_birth['pin'];

            $cat = 'U18';
            if ($birth_year > 0) {
                $cat = CategoryHelper::calculate_category($birth_year);
            }

            $check_stmt = $this->db->prepare(
                "SELECT id, birth_date, birth_year, access_pin FROM athletes 
                 WHERE LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?)"
            );
            $check_stmt->execute([$first_name, $last_name]);
            $existing = $check_stmt->fetch();

            if ($existing) {
                $update_sql = "UPDATE athletes SET phone = ?, email = ?, meta = json_set(meta, '$.notes', ?)";
                $params = [$phone, $email, $notes];

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
                $meta_json = json_encode(['notes' => $notes], JSON_UNESCAPED_UNICODE);
                $ath_id = $this->athleteRepo->create([
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'birth_date'   => $birth_date,
                    'birth_year'   => $birth_year,
                    'category'     => $cat,
                    'access_token' => Auth::generate_token(),
                    'access_pin'   => $pin,
                    'phone'        => $phone,
                    'email'        => $email,
                    'meta'         => $meta_json
                ]);

                $form_type = CategoryHelper::get_form_type($birth_year, $cat);
                $type = ($form_type === 'u18_elite') ? 'individual_elite' : 'u16_group';
                $this->interviewRepo->getOrCreateForSeason($ath_id, (int)$season['id'], $type);
                $inserted++;
            }
        }

        flash('success', "Importation réussie : {$inserted} athlète(s) ajouté(s), {$updated} mis à jour.");
        redirect('/admin');
    }

    /**
     * Export récapitulatif global (CSV / Excel) de la grille de rentrée
     */
    public function exportGridCsv(): void {
        Auth::require_admin();

        $selected_season_id = (int)($_GET['season_id'] ?? 0);
        $season = null;
        if ($selected_season_id > 0) {
            $season = $this->seasonRepo->findById($selected_season_id);
        }
        if (!$season) {
            $season = $this->seasonRepo->getActive();
        }

        $rows = $this->athleteRepo->getAllWithInterviewForSeason((int)$season['id']);

        $filename = "grille_cadrage_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $season['name']) . "_" . date('Ymd_Hi') . ".csv";

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

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

            $classe_age = ($age >= 16) ? 'Cadre U18+' : $cat_label;
            $coach_ref = $decisions['coach_in_charge'] ?? ($meta['coach_in_charge'] ?? ($meta['coach'] ?? ''));

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

            $groupe_s = $decisions['secondary_group'] ?? ($meta['secondary_group'] ?? ($d2 !== 'Aucune' ? $d2 : ''));

            $status_fr = match ($r['interview_status'] ?? 'waiting') {
                'completed' => 'Validé',
                'submitted' => 'Soumis',
                'draft' => 'Brouillon',
                default => 'En attente'
            };

            $annee_str = $birth_year > 0 ? (string)$birth_year : '';

            $vol_count = $decisions['approved_weekly_sessions'] ?? ($athlete_answers['target_sessions_count'] ?? '');
            $approved_days = $decisions['approved_training_days'] ?? ($athlete_answers['available_days'] ?? []);
            if (!is_array($approved_days)) {
                $approved_days = [$approved_days];
            }

            $dispo_str = Helper::format_availability_summary($approved_days, $vol_count !== '' ? (string)$vol_count : null);

            $has_day = fn($key) => in_array($key, $approved_days, true) || in_array("{$key}_morning", $approved_days, true);

            $day_code = function(string $day_key, ?string $explicit_val = null) use ($has_day, $groupe) {
                if (!$has_day($day_key)) return '';
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
                $day_code('monday', $decisions['monday_session'] ?? null),
                $day_code('tuesday', $decisions['tuesday_session'] ?? null),
                $day_code('wednesday', $decisions['wednesday_session'] ?? null),
                $day_code('thursday', $decisions['thursday_session'] ?? null),
                $day_code('friday', $decisions['friday_session'] ?? $friday_spec),
                $has_day('saturday') ? 'End.' : '',
                $decisions['strength_training'] ?? ($meta['strength_notes'] ?? ($decisions['mandatory_rule_1'] ?? '')),
                $decisions['cadre_structure'] ?? ($meta['cadre'] ?? ($decisions['partner_structure'] ?? ''))
            ], ';', '"', "\\");
        }

        fclose($output);
        exit;
    }

    /**
     * Fiche Bilan imprimable (Clean Print A4 / PDF)
     */
    public function printSummary(): void {
        Auth::require_admin();

        $interview_id = (int)($_GET['interview_id'] ?? 0);
        $athlete_id = (int)($_GET['athlete_id'] ?? 0);

        if ($interview_id > 0) {
            $interview = $this->interviewRepo->findByIdWithAthlete($interview_id);
            $season = $this->seasonRepo->findById((int)($interview['season_id'] ?? 1));
        } elseif ($athlete_id > 0) {
            $season = $this->seasonRepo->getActive();
            $ath = $this->athleteRepo->findById($athlete_id);
            $interview = $ath ? $this->interviewRepo->findForAthleteAndSeason($athlete_id, (int)$season['id']) : null;
            if ($interview && $ath) {
                $interview = array_merge($ath, $interview);
            }
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

        $athlete = [
            'id'         => $interview['athlete_id'] ?? $interview['id'] ?? $athlete_id,
            'first_name' => $interview['first_name'],
            'last_name'  => $interview['last_name'],
            'birth_date' => $interview['birth_date'] ?? null,
            'birth_year' => $interview['birth_year'],
            'category'   => $interview['category'],
            'phone'      => $interview['phone'] ?? null,
            'email'      => $interview['email'] ?? null,
            'meta'       => $interview['meta'] ?? $interview['athlete_meta'] ?? '{}',
        ];

        require dirname(__DIR__, 2) . '/views/admin_print_summary.php';
    }

    /**
     * Sauvegarde JSON
     */
    public function backupJson(): void {
        Auth::require_admin();

        $seasons = $this->seasonRepo->getAll();
        $athletes = $this->athleteRepo->getAllOrdered();
        $interviews = $this->db->query("SELECT * FROM interviews ORDER BY id ASC")->fetchAll();

        $backup_data = [
            'format_version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'club_name' => SettingsService::get_club_name(),
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
     * Téléchargement direct SQLite
     */
    public function backupSqlite(): void {
        Auth::require_admin();

        $db_relative_path = env('DB_PATH', 'data/debriefing.sqlite');
        $db_path = dirname(__DIR__, 2) . '/' . ltrim($db_relative_path, '/');

        if (!file_exists($db_path)) {
            flash('error', 'Fichier de base de données introuvable.');
            redirect('/admin');
            return;
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
     * Restauration JSON / SQLite
     */
    public function restore(): void {
        Auth::require_admin();

        if (empty($_FILES['backup_file']['tmp_name'])) {
            flash('error', 'Veuillez sélectionner un fichier de sauvegarde (.json ou .sqlite).');
            redirect('/admin');
            return;
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

                if (isset($json['data']['seasons']) && is_array($json['data']['seasons'])) {
                    $this->db->exec("DELETE FROM seasons");
                    $stmt = $this->db->prepare("INSERT INTO seasons (id, name, is_active, created_at) VALUES (?, ?, ?, ?)");
                    foreach ($json['data']['seasons'] as $s) {
                        $stmt->execute([$s['id'], $s['name'], $s['is_active'], $s['created_at'] ?? date('Y-m-d H:i:s')]);
                    }
                }

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
                $db_dest = dirname(__DIR__, 2) . '/' . ltrim($db_relative_path, '/');
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
     * Mise à jour des paramètres du club
     */
    public function updateSettings(): void {
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
                return;
            }
            SettingsService::set_admin_password($new_password);
            flash('success', 'Paramètres et nouveau mot de passe enregistrés avec succès.');
        } else {
            flash('success', 'Les paramètres du club ont été mis à jour avec succès.');
        }

        redirect('/admin');
    }
}
