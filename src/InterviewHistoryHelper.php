<?php
declare(strict_types=1);

/**
 * Helper d'extraction et de normalisation de l'historique d'entretiens (N-1)
 * Fait le pont entre les formats U16 et U18+ pour la reprise d'objectifs et d'engagements
 */
class InterviewHistoryHelper {

    /**
     * Récupère la synthèse normalisée du dernier entretien archivé d'un athlète
     * @return array|null Tableau associatif structuré ou null si aucun historique
     */
    public static function getPreviousSummary(int $athlete_id, int $current_season_id, ?PDO $db = null): ?array {
        if ($athlete_id <= 0) {
            return null;
        }

        $db = $db ?? get_db();

        $stmt = $db->prepare("
            SELECT i.*, s.name as season_name 
            FROM interviews i 
            JOIN seasons s ON s.id = i.season_id 
            WHERE i.athlete_id = ? AND i.season_id != ? 
            ORDER BY s.is_active DESC, s.name DESC, i.id DESC 
            LIMIT 1
        ");
        $stmt->execute([$athlete_id, $current_season_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $ath_answers = safe_json_decode($row['athlete_answers'] ?? null);
        $trainer_answers = safe_json_decode($row['trainer_answers'] ?? null);
        $decisions = safe_json_decode($row['decisions'] ?? null);

        // 1. Objectifs & Engagements passés
        $perf_goal = trim((string)($ath_answers['target_performance'] ?? ''));
        $comp_goal = trim((string)($ath_answers['target_competitions'] ?? ''));
        
        $attitude_goal = trim((string)(
            $ath_answers['commitment_1'] 
            ?? ($ath_answers['attitude_contract'] 
            ?? ($decisions['moral_contract'] ?? ''))
        ));
        
        $commitment_2 = trim((string)($ath_answers['commitment_2'] ?? ''));

        // 2. Bilan & Rétrospective passée
        $top_success = trim((string)(
            $ath_answers['top_success_description'] 
            ?? ($ath_answers['pride_highlight'] 
            ?? ($ath_answers['top_success'] ?? ''))
        ));

        $main_obstacle = trim((string)(
            $ath_answers['main_limiting_barrier'] 
            ?? ($ath_answers['main_obstacle'] 
            ?? ($ath_answers['obstacle_notes'] ?? ''))
        ));

        // 3. Orientations & Disciplines passées
        $d1 = trim((string)(
            $decisions['primary_discipline'] 
            ?? ($decisions['friday_discipline_approved'] 
            ?? ($ath_answers['chosen_discipline_1'] 
            ?? ($ath_answers['friday_discipline'] ?? '')))
        ));

        $d2 = trim((string)(
            $decisions['secondary_discipline'] 
            ?? ($ath_answers['chosen_discipline_2'] ?? '')
        ));

        $d1_label = CategoryHelper::get_discipline_label($d1);
        $d2_label = CategoryHelper::get_discipline_label($d2);

        // 4. Volume et disponibilités
        $weekly_sessions = (string)(
            $decisions['approved_weekly_sessions'] 
            ?? ($ath_answers['target_sessions_count'] ?? '')
        );

        $available_days = $decisions['approved_training_days'] 
            ?? ($ath_answers['available_days'] ?? []);
        if (!is_array($available_days)) {
            $available_days = $available_days ? [(string)$available_days] : [];
        }

        // 5. Cadres et situation
        $cadres = $decisions['cadres'] ?? ($ath_answers['cadres'] ?? []);
        if (!is_array($cadres)) {
            $cadres = $cadres ? [(string)$cadres] : [];
        }
        $cadres_labels = array_map(fn($c) => CategoryHelper::get_cadre_label((string)$c), $cadres);

        $study_work = trim((string)($ath_answers['study_work_situation'] ?? ''));
        $trainer_notes = trim((string)($row['trainer_notes'] ?? ($trainer_answers['general_comment'] ?? '')));

        $has_goals = ($perf_goal !== '' || $comp_goal !== '' || $attitude_goal !== '' || $commitment_2 !== '');
        $has_orientation = ($d1 !== '' || $weekly_sessions !== '' || !empty($available_days) || $study_work !== '');

        return [
            'interview_id'           => (int)$row['id'],
            'season_id'              => (int)$row['season_id'],
            'season_name'            => (string)$row['season_name'],
            'interview_type'         => (string)$row['interview_type'],
            'status'                 => (string)$row['status'],
            'is_validated'           => (int)($row['is_validated'] ?? 0),
            'has_goals'              => $has_goals,
            'has_orientation'        => $has_orientation,
            // Objectifs
            'perf_goal'              => $perf_goal,
            'comp_goal'              => $comp_goal,
            'attitude_goal'          => $attitude_goal,
            'commitment_2'           => $commitment_2,
            // Rétrospective
            'top_success'            => $top_success,
            'main_obstacle'          => $main_obstacle,
            // Orientations sportives
            'discipline_1'           => $d1,
            'discipline_1_label'     => $d1_label,
            'discipline_2'           => $d2,
            'discipline_2_label'     => $d2_label,
            'weekly_sessions'        => $weekly_sessions,
            'available_days'         => $available_days,
            'cadres'                 => $cadres,
            'cadres_labels'          => $cadres_labels,
            'study_work'             => $study_work,
            'trainer_notes'          => $trainer_notes,
        ];
    }
}
