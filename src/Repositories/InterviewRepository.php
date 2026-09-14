<?php
declare(strict_types=1);

/**
 * Repository pour l'accès et l'enregistrement des entretiens
 */
class InterviewRepository {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
    }

    /**
     * Récupère ou crée une fiche d'entretien pour un athlète et une saison
     */
    public function getOrCreateForSeason(int $athlete_id, int $season_id, string $interview_type = 'u16_group'): array {
        $stmt = $this->db->prepare("SELECT * FROM interviews WHERE athlete_id = ? AND season_id = ?");
        $stmt->execute([$athlete_id, $season_id]);
        $interview = $stmt->fetch();

        if (!$interview) {
            $create = $this->db->prepare(
                "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
                 VALUES (?, ?, ?, 'waiting', '{}', '{}', '{}')"
            );
            $create->execute([$athlete_id, $season_id, $interview_type]);
            $stmt->execute([$athlete_id, $season_id]);
            return $stmt->fetch() ?: [];
        }

        return $interview;
    }

    /**
     * Récupère un entretien par son ID avec les données de l'athlète
     */
    public function findByIdWithAthlete(int $interview_id): ?array {
        $query = "
            SELECT 
                i.*,
                a.first_name,
                a.last_name,
                a.birth_date,
                a.birth_year,
                a.category,
                a.phone,
                a.email,
                a.access_token,
                a.access_pin,
                a.meta as athlete_meta
            FROM interviews i
            JOIN athletes a ON i.athlete_id = a.id
            WHERE i.id = ?
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$interview_id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Récupère l'entretien d'un athlète pour une saison
     */
    public function findForAthleteAndSeason(int $athlete_id, int $season_id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM interviews WHERE athlete_id = ? AND season_id = ?");
        $stmt->execute([$athlete_id, $season_id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Enregistre les réponses de l'athlète (autosave ou submit)
     */
    public function saveAthleteAnswers(int $interview_id, array $answers, ?string $status = null): void {
        $current = $this->findByIdWithAthlete($interview_id);
        $current_answers = json_decode($current['athlete_answers'] ?? '{}', true) ?: [];
        $merged = array_merge($current_answers, $answers);
        $json = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($status !== null) {
            $stmt = $this->db->prepare("UPDATE interviews SET athlete_answers = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$json, $status, $interview_id]);
        } else {
            $stmt = $this->db->prepare("UPDATE interviews SET athlete_answers = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$json, $interview_id]);
        }
    }

    /**
     * Enregistre les décisions et avis de l'entraîneur
     */
    public function saveTrainerDecisions(int $interview_id, array $trainer_answers, array $decisions, ?string $notes = null, bool $is_completed = false): void {
        $json_trainer = json_encode($trainer_answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json_decisions = json_encode($decisions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $status = $is_completed ? 'completed' : 'draft';
        $is_val = $is_completed ? 1 : 0;
        $val_date = $is_completed ? date('Y-m-d H:i:s') : null;

        $stmt = $this->db->prepare(
            "UPDATE interviews 
             SET trainer_answers = ?, decisions = ?, trainer_notes = ?, status = ?, is_validated = ?, validated_at = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        $stmt->execute([$json_trainer, $json_decisions, $notes, $status, $is_val, $val_date, $interview_id]);
    }

    /**
     * Déverrouille un entretien pour modifications
     */
    public function unlock(int $athlete_id, int $season_id): void {
        $stmt = $this->db->prepare("UPDATE interviews SET status = 'draft', is_validated = 0 WHERE athlete_id = ? AND season_id = ?");
        $stmt->execute([$athlete_id, $season_id]);
    }
}
