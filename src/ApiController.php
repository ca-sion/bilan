<?php
declare(strict_types=1);

/**
 * Contrôleur des points d'accès API (Autosave, Soumission, Validation d'épreuves, Export WhatsApp)
 */
class ApiController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Sauvegarde automatique asynchrone (AJAX/Fetch)
     */
    public function save_answers(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input_raw = file_get_contents('php://input');
        $payload = json_decode($input_raw, true) ?: $_POST;

        $athlete_id = (int)($_SESSION['athlete_id'] ?? ($payload['athlete_id'] ?? 0));
        $interview_id = (int)($payload['interview_id'] ?? 0);

        if ($athlete_id <= 0) {
            json_response(['success' => false, 'error' => 'Session expirée ou non authentifiée'], 401);
        }

        // Récupérer l'interview
        if ($interview_id > 0) {
            $stmt = $this->db->prepare("SELECT * FROM interviews WHERE id = ?");
            $stmt->execute([$interview_id]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM interviews WHERE athlete_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$athlete_id]);
        }
        $interview = $stmt->fetch();

        if (!$interview) {
            json_response(['success' => false, 'error' => 'Fiche d\'entretien introuvable'], 404);
        }

        // Vérification de sécurité (seul l'athlète concerné ou le coach peut modifier)
        if (!Auth::is_logged_in() && (int)$interview['athlete_id'] !== $athlete_id) {
            json_response(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        // Si la fiche est verrouillée et qu'on n'est pas coach, interdire
        if (in_array($interview['status'], ['submitted', 'completed'], true) && !Auth::is_logged_in()) {
            json_response(['success' => false, 'error' => 'Ce bilan a déjà été validé et ne peut plus être modifié'], 422);
        }

        $answers = $payload['answers'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        // Fusionner avec les réponses existantes
        $current_answers = json_decode($interview['athlete_answers'] ?? '{}', true) ?: [];
        $merged_answers = array_merge($current_answers, $answers);
        $json_answers = json_encode($merged_answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $new_status = ($interview['status'] === 'waiting') ? 'draft' : $interview['status'];

        $update_stmt = $this->db->prepare(
            "UPDATE interviews 
             SET athlete_answers = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        $update_stmt->execute([$json_answers, $new_status, $interview['id']]);

        json_response([
            'success' => true,
            'message' => 'Modifications enregistrées',
            'saved_at' => date('H:i:s'),
            'status' => $new_status
        ]);
    }

    /**
     * Soumission finale par l'athlète (verrouille le formulaire)
     */
    public function submit_interview(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input_raw = file_get_contents('php://input');
        $payload = json_decode($input_raw, true) ?: $_POST;

        $athlete_id = (int)($_SESSION['athlete_id'] ?? 0);
        $interview_id = (int)($payload['interview_id'] ?? 0);

        if ($athlete_id <= 0) {
            json_response(['success' => false, 'error' => 'Session expirée'], 401);
        }

        $stmt = $this->db->prepare("SELECT * FROM interviews WHERE id = ? AND athlete_id = ?");
        $stmt->execute([$interview_id, $athlete_id]);
        $interview = $stmt->fetch();

        if (!$interview) {
            json_response(['success' => false, 'error' => 'Entretien introuvable'], 404);
        }

        $answers = $payload['answers'] ?? [];
        $current_answers = json_decode($interview['athlete_answers'] ?? '{}', true) ?: [];
        $merged_answers = array_merge($current_answers, $answers);
        $json_answers = json_encode($merged_answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $update_stmt = $this->db->prepare(
            "UPDATE interviews 
             SET athlete_answers = ?, status = 'submitted', updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        $update_stmt->execute([$json_answers, $interview['id']]);

        json_response([
            'success' => true,
            'message' => 'Votre bilan a été transmis avec succès à votre entraîneur.',
            'redirect' => url('/bilan')
        ]);
    }

    /**
     * Validation dynamique de compatibilité d'épreuves (AJAX)
     */
    public function validate_disciplines(): void {
        $input_raw = file_get_contents('php://input');
        $payload = json_decode($input_raw, true) ?: $_GET;

        $d1 = $payload['discipline_1'] ?? '';
        $d2 = $payload['discipline_2'] ?? '';

        $result = CategoryHelper::check_disciplines_compatibility($d1, $d2);
        json_response($result);
    }

    /**
     * Génération de la synthèse WhatsApp pour le presse-papier
     */
    public function export_whatsapp(): void {
        $interview_id = (int)($_GET['interview_id'] ?? ($_POST['interview_id'] ?? 0));
        if ($interview_id <= 0) {
            json_response(['success' => false, 'error' => 'Identifiant manquant'], 400);
        }

        $stmt = $this->db->prepare(
            "SELECT i.*, a.first_name, a.last_name, a.birth_year, a.category, a.phone 
             FROM interviews i 
             JOIN athletes a ON a.id = i.athlete_id 
             WHERE i.id = ?"
        );
        $stmt->execute([$interview_id]);
        $data = $stmt->fetch();

        if (!$data) {
            json_response(['success' => false, 'error' => 'Fiche introuvable'], 404);
        }

        $athlete = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_year' => $data['birth_year'],
            'category' => $data['category'],
            'phone' => $data['phone']
        ];

        $text = WhatsAppHelper::build_synthesis_text($athlete, $data);

        json_response([
            'success' => true,
            'text' => $text
        ]);
    }
}
