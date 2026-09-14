<?php
declare(strict_types=1);

/**
 * Repository pour la gestion et le cycle de vie des saisons
 */
class SeasonRepository {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
    }

    /**
     * Récupère la saison active courante
     */
    public function getActive(): array {
        $stmt = $this->db->query("SELECT * FROM seasons WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $season = $stmt->fetch();
        if (!$season) {
            $season_name = env('CURRENT_SEASON_NAME', '2026-2027');
            $this->db->exec("INSERT INTO seasons (name, is_active) VALUES ('{$season_name}', 1)");
            return ['id' => (int)$this->db->lastInsertId(), 'name' => $season_name, 'is_active' => 1];
        }
        return $season;
    }

    /**
     * Récupère la saison historique précédente (N-1)
     */
    public function getPreviousSeason(int $current_season_id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM seasons WHERE id != ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$current_season_id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Récupère toutes les saisons
     */
    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM seasons ORDER BY is_active DESC, name DESC");
        return $stmt->fetchAll();
    }

    /**
     * Récupère une saison par son ID
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM seasons WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Définit la saison active
     */
    public function setActive(int $id): void {
        $this->db->exec("UPDATE seasons SET is_active = 0");
        $stmt = $this->db->prepare("UPDATE seasons SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Crée une nouvelle saison et initialise les fiches d'entretiens pour tous les athlètes existants
     */
    public function create(string $name, bool $set_active = true): int {
        if ($set_active) {
            $this->db->exec("UPDATE seasons SET is_active = 0");
        }
        $stmt = $this->db->prepare("INSERT INTO seasons (name, is_active) VALUES (?, ?)");
        $stmt->execute([$name, $set_active ? 1 : 0]);
        $season_id = (int)$this->db->lastInsertId();

        // Initialiser les entretiens de base pour chaque athlète
        $athletes = $this->db->query("SELECT id, birth_year, category FROM athletes")->fetchAll();
        $ins = $this->db->prepare(
            "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
             VALUES (?, ?, ?, 'waiting', '{}', '{}', '{}')"
        );

        foreach ($athletes as $a) {
            $type = CategoryHelper::get_form_type((int)$a['birth_year'], $a['category']) === 'u18_elite' ? 'individual_elite' : 'u16_group';
            $ins->execute([$a['id'], $season_id, $type]);
        }

        return $season_id;
    }

    /**
     * Renomme une saison
     */
    public function rename(int $id, string $newName): void {
        $stmt = $this->db->prepare("UPDATE seasons SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $id]);
    }

    /**
     * Supprime une saison et ses entretiens
     */
    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM interviews WHERE season_id = ?");
        $stmt->execute([$id]);
        $stmt = $this->db->prepare("DELETE FROM seasons WHERE id = ?");
        $stmt->execute([$id]);
    }
}
