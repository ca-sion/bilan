<?php
declare(strict_types=1);

/**
 * Repository pour l'accès aux données des athlètes
 */
class AthleteRepository {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
    }

    /**
     * Récupère la liste de tous les athlètes classés par Nom, Prénom
     */
    public function getAllOrdered(): array {
        $stmt = $this->db->query(
            "SELECT id, first_name, last_name, birth_year, category, access_pin, birth_date, access_token, phone, email 
             FROM athletes 
             ORDER BY last_name COLLATE NOCASE ASC, first_name COLLATE NOCASE ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère les athlètes avec les informations d'entretien pour une saison donnée
     */
    public function getAllWithInterviewForSeason(int $season_id): array {
        $query = "
            SELECT 
                a.*,
                i.id as interview_id,
                i.status as interview_status,
                i.athlete_answers,
                i.trainer_answers,
                i.decisions,
                i.trainer_notes,
                i.is_validated,
                i.validated_at,
                i.updated_at as interview_updated_at
            FROM athletes a
            LEFT JOIN interviews i ON a.id = i.athlete_id AND i.season_id = ?
            ORDER BY a.last_name COLLATE NOCASE ASC, a.first_name COLLATE NOCASE ASC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$season_id]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère un athlète par son ID
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM athletes WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Récupère un athlète par son token d'accès direct WhatsApp
     */
    public function findByToken(string $token): ?array {
        $stmt = $this->db->prepare("SELECT * FROM athletes WHERE access_token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crée un nouvel athlète
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO athletes (first_name, last_name, birth_date, birth_year, category, access_token, access_pin, phone, email, meta) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['birth_date'] ?? null,
            $data['birth_year'],
            $data['category'],
            $data['access_token'] ?? Auth::generate_token(),
            $data['access_pin'] ?? '0000',
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['meta'] ?? '{}'
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Met à jour les informations d'un athlète
     */
    public function update(int $id, array $data): void {
        $stmt = $this->db->prepare(
            "UPDATE athletes 
             SET first_name = ?, last_name = ?, birth_date = ?, birth_year = ?, category = ?, phone = ?, email = ?, meta = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['birth_date'] ?? null,
            $data['birth_year'],
            $data['category'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['meta'] ?? '{}',
            $id
        ]);
    }

    /**
     * Met à jour le code PIN et la date de naissance
     */
    public function updatePinAndBirthDate(int $id, string $birth_date, int $birth_year, string $pin): void {
        $stmt = $this->db->prepare("UPDATE athletes SET birth_date = ?, birth_year = ?, access_pin = ? WHERE id = ?");
        $stmt->execute([$birth_date, $birth_year, $pin, $id]);
    }

    /**
     * Réinitialise le code PIN d'un athlète
     */
    public function resetPin(int $id, string $pin): void {
        $stmt = $this->db->prepare("UPDATE athletes SET access_pin = ? WHERE id = ?");
        $stmt->execute([$pin, $id]);
    }

    /**
     * Supprime un athlète et tous ses entretiens
     */
    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM interviews WHERE athlete_id = ?");
        $stmt->execute([$id]);
        $stmt = $this->db->prepare("DELETE FROM athletes WHERE id = ?");
        $stmt->execute([$id]);
    }
}
