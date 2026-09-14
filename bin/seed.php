<?php
declare(strict_types=1);

/**
 * Script CLI de génération de données de test et de démonstration locale
 * Usage : php bin/seed.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Ce script est réservé à l'exécution en ligne de commande (CLI).\n");
}

require_once dirname(__DIR__) . '/config/database.php';

echo "=== CA Sion - Initialisation des données de test (Local) ===\n\n";

$db = get_db();

try {
    $db->beginTransaction();

    // 1. Saison active (2026-2027)
    $stmt = $db->prepare("SELECT id FROM seasons WHERE name = ?");
    $stmt->execute(['2026-2027']);
    $season_curr_id = $stmt->fetchColumn();

    if (!$season_curr_id) {
        $ins = $db->prepare("INSERT INTO seasons (name, is_active, created_at) VALUES ('2026-2027', 1, datetime('now'))");
        $ins->execute();
        $season_curr_id = (int)$db->lastInsertId();
    } else {
        $season_curr_id = (int)$season_curr_id;
        $db->exec("UPDATE seasons SET is_active = 1 WHERE id = {$season_curr_id}");
    }
    echo "[✓] Saison active 2026-2027 (ID: {$season_curr_id})\n";

    // 2. Saison précédente (2025-2026) pour tests d'historique N-1
    $stmt->execute(['2025-2026']);
    $season_prev_id = $stmt->fetchColumn();

    if (!$season_prev_id) {
        $ins = $db->prepare("INSERT INTO seasons (name, is_active, created_at) VALUES ('2025-2026', 0, datetime('now', '-1 year'))");
        $ins->execute();
        $season_prev_id = (int)$db->lastInsertId();
    } else {
        $season_prev_id = (int)$season_prev_id;
        $db->exec("UPDATE seasons SET is_active = 0 WHERE id = {$season_prev_id}");
    }
    echo "[✓] Saison historique 2025-2026 (N-1) (ID: {$season_prev_id})\n";

    // 3. Athlètes de test
    $sample_athletes = [
        [
            'first_name' => 'Amélie',
            'last_name' => 'Torrent',
            'birth_date' => '2010-06-12', // 17 ans en 2027 => U18
            'category' => 'U18',
            'phone' => '+41791234567',
            'email' => 'amelie.torrent@example.ch',
            'meta' => ['coach' => 'Sabine Bonvin', 'notes' => 'Cadre romand demi-fond'],
            'type' => 'individual_elite',
            'n1_pride' => 'Record cantonal U18 sur 800m (2:12.45) et podium romand.',
            'n1_disc1' => 'middle_distance',
            'n1_disc2' => 'cross_country',
            'n1_sessions' => 4,
            'n1_days' => ['monday', 'wednesday', 'thursday', 'saturday'],
            'n1_rule1' => 'Assiduité à 100% sur les séances de tempo',
            'n1_rule2' => 'Communication proactive au coach sous 24h en cas de gêne'
        ],
        [
            'first_name' => 'Alexis',
            'last_name' => 'Reynard',
            'birth_date' => '2008-02-03', // 19 ans en 2027 => U20
            'category' => 'U20',
            'phone' => '+41761112233',
            'email' => 'alexis.reynard@example.ch',
            'meta' => ['coach' => 'Julien Rey', 'notes' => 'Cadre national sprint'],
            'type' => 'individual_elite',
            'n1_pride' => 'Finale CS U18 sur 100m et passage régulier sous les 11.20s.',
            'n1_disc1' => 'sprint',
            'n1_disc2' => 'short_hurdles',
            'n1_sessions' => 4,
            'n1_days' => ['monday', 'tuesday', 'thursday', 'saturday'],
            'n1_rule1' => 'Ponctualité et échauffement dynamique sans retard',
            'n1_rule2' => 'Gainage systématique après chaque séance technique'
        ],
        [
            'first_name' => 'Noah',
            'last_name' => 'Bonvin',
            'birth_date' => '2013-04-14', // 14 ans en 2027 => U16 1ère année
            'category' => 'U16',
            'phone' => '+41792345678',
            'email' => 'noah.bonvin@example.ch',
            'meta' => ['coach' => 'Sabine Bonvin', 'notes' => 'Option vendredi demandée'],
            'type' => 'u16_group',
            'n1_pride' => 'Première participation aux Championnats Valaisans U14.',
            'n1_disc1' => 'middle_distance',
            'n1_disc2' => '',
            'n1_sessions' => 2,
            'n1_days' => ['wednesday', 'friday'],
            'n1_rule1' => 'Écoute des consignes et respect du groupe',
            'n1_rule2' => 'Régularité tout au long de l\'hiver'
        ],
        [
            'first_name' => 'Julie',
            'last_name' => 'Fournier',
            'birth_date' => '2012-09-28', // 15 ans en 2027 => U16 2ème année
            'category' => 'U16',
            'phone' => '+41789876543',
            'email' => 'julie.fournier@example.ch',
            'meta' => ['coach' => 'Julien Rey', 'notes' => 'Sprint et longueur'],
            'type' => 'u16_group',
            'n1_pride' => 'Franchissement régulier des 5m en longueur.',
            'n1_disc1' => 'long_jump',
            'n1_disc2' => 'sprint',
            'n1_sessions' => 3,
            'n1_days' => ['monday', 'wednesday', 'friday'],
            'n1_rule1' => 'Régularité et dynamisme à l\'échauffement',
            'n1_rule2' => 'Soin du matériel d\'entraînement'
        ],
        [
            'first_name' => 'Loïc',
            'last_name' => 'Besse',
            'birth_date' => '2009-11-15', // 18 ans en 2027 => U18+
            'category' => 'U18',
            'phone' => '+41794567890',
            'email' => 'loic.besse@example.ch',
            'meta' => ['coach' => 'Sabine Bonvin', 'notes' => 'Demi-fond et steeple'],
            'type' => 'individual_elite',
            'n1_pride' => 'Titre cantonal sur le 2000m steeple.',
            'n1_disc1' => 'middle_distance',
            'n1_disc2' => 'cross_country',
            'n1_sessions' => 4,
            'n1_days' => ['monday', 'wednesday', 'thursday', 'saturday'],
            'n1_rule1' => 'Respect des allures d\'entraînement prescrites',
            'n1_rule2' => 'Hydratation et récupération soignées'
        ],
        [
            'first_name' => 'Camille',
            'last_name' => 'Zuber',
            'birth_date' => null, // Date inconnue => PIN '0000'
            'category' => 'U18',
            'phone' => '+41790001122',
            'email' => 'camille.zuber@example.ch',
            'meta' => ['notes' => 'Date de naissance à renseigner par l\'athlète'],
            'type' => 'individual_elite',
            'n1_pride' => 'Progression constante en hauteur.',
            'n1_disc1' => 'high_jump',
            'n1_disc2' => 'sprint',
            'n1_sessions' => 3,
            'n1_days' => ['monday', 'wednesday', 'friday'],
            'n1_rule1' => 'Assiduité aux séances techniques',
            'n1_rule2' => 'Préparation physique générale rigoureuse'
        ]
    ];

    $ath_stmt = $db->prepare("
        INSERT INTO athletes (first_name, last_name, birth_date, birth_year, category, access_token, access_pin, phone, email, meta) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $chk_ath = $db->prepare("SELECT id FROM athletes WHERE first_name = ? AND last_name = ?");
    $chk_int = $db->prepare("SELECT id FROM interviews WHERE athlete_id = ? AND season_id = ?");

    $ins_int = $db->prepare("
        INSERT INTO interviews (
            athlete_id, season_id, interview_type, status, is_validated,
            athlete_answers, trainer_answers, decisions, trainer_notes,
            validated_at, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?
        )
    ");

    $new_ath_count = 0;
    $n1_count = 0;
    $n_count = 0;

    foreach ($sample_athletes as $a) {
        $chk_ath->execute([$a['first_name'], $a['last_name']]);
        $aid = $chk_ath->fetchColumn();

        $birth_year = !empty($a['birth_date']) ? (int)substr($a['birth_date'], 0, 4) : 0;
        $pin = '0000';
        if (!empty($a['birth_date']) && preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $a['birth_date'], $m)) {
            $pin = $m[2] . $m[1]; // JJMM
        }
        $token = bin2hex(random_bytes(16));

        if (!$aid) {
            $ath_stmt->execute([
                $a['first_name'],
                $a['last_name'],
                $a['birth_date'],
                $birth_year,
                $a['category'],
                $token,
                $pin,
                $a['phone'],
                $a['email'],
                json_encode($a['meta'], JSON_UNESCAPED_UNICODE)
            ]);
            $aid = (int)$db->lastInsertId();
            $new_ath_count++;
        } else {
            $aid = (int)$aid;
        }

        // 3.1 Bilan N-1 (2025-2026) archivé
        $chk_int->execute([$aid, $season_prev_id]);
        if (!$chk_int->fetchColumn()) {
            $hist_ath = [
                'pride_highlight' => $a['n1_pride'],
                'top_success_description' => $a['n1_pride'],
                'satisfaction_training' => 4,
                'satisfaction_competition' => 4,
                'motivation_rating' => 4,
                'attitude_contract' => $a['n1_rule1'],
                'chosen_discipline_1' => $a['n1_disc1'],
                'chosen_discipline_2' => $a['n1_disc2'],
                'target_sessions_count' => $a['n1_sessions'],
                'available_days' => $a['n1_days']
            ];

            $hist_trainer = [
                'general_comment' => "Très bonne implication sur la saison 2025-2026. Belle assiduité.",
                'strengths' => 'Rigueur et régularité aux entraînements.',
                'areas_for_improvement' => 'Gestion de la récupération et des étirements.'
            ];

            $hist_dec = [
                'primary_discipline' => $a['n1_disc1'],
                'secondary_discipline' => $a['n1_disc2'],
                'approved_weekly_sessions' => $a['n1_sessions'],
                'approved_training_days' => $a['n1_days'],
                'target_milestones' => 'Confirmation du niveau régional et finale cantonal.',
                'mandatory_rule_1' => $a['n1_rule1'],
                'mandatory_rule_2' => $a['n1_rule2'],
                'strength_training' => 'Gainage et renforcement postural 2x/semaine.'
            ];

            $ins_int->execute([
                $aid,
                $season_prev_id,
                $a['type'],
                'completed',
                1,
                json_encode($hist_ath, JSON_UNESCAPED_UNICODE),
                json_encode($hist_trainer, JSON_UNESCAPED_UNICODE),
                json_encode($hist_dec, JSON_UNESCAPED_UNICODE),
                'Bilan 2025-2026 clôturé avec succès.',
                date('Y-m-d H:i:s', strtotime('-1 year')),
                date('Y-m-d H:i:s', strtotime('-1 year')),
                date('Y-m-d H:i:s', strtotime('-1 year'))
            ]);
            $n1_count++;
        }

        // 3.2 Bilan N (2026-2027) courant
        $chk_int->execute([$aid, $season_curr_id]);
        if (!$chk_int->fetchColumn()) {
            $curr_ath = [
                'pride_highlight' => $a['n1_pride'],
                'chosen_discipline_1' => $a['n1_disc1'],
                'chosen_discipline_2' => $a['n1_disc2'],
                'target_sessions_count' => $a['n1_sessions'],
                'available_days' => $a['n1_days']
            ];

            $ins_int->execute([
                $aid,
                $season_curr_id,
                $a['type'],
                'waiting',
                0,
                json_encode($curr_ath, JSON_UNESCAPED_UNICODE),
                '{}',
                '{}',
                null,
                null,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);
            $n_count++;
        }
    }

    $db->commit();
    $total_athletes = count($sample_athletes);
    echo "[✓] {$total_athletes} athlètes vérifiés ({$new_ath_count} nouveaux créés).\n";
    echo "[✓] Fiches archivées 2025-2026 (N-1) : {$n1_count} nouvelles créées.\n";
    echo "[✓] Fiches actives 2026-2027 (N) : {$n_count} nouvelles créées.\n";
    echo "\nTerminé ! L'historique N-1 et les comparatifs U16 & U18 sont prêts à être testés.\n";
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "[✕] Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
