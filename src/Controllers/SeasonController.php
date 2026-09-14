<?php
declare(strict_types=1);

/**
 * Contrôleur pour la gestion administrative des saisons et archives
 */
class SeasonController {
    private SeasonRepository $seasonRepo;
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? get_db();
        $this->seasonRepo = new SeasonRepository($this->db);
    }

    /**
     * Création d'une nouvelle saison
     */
    public function create(): void {
        Auth::require_admin();

        $name = trim((string)($_POST['season_name'] ?? ''));
        $set_active = !empty($_POST['set_active']);

        if ($name === '') {
            flash('error', 'Le nom de la saison est obligatoire (ex: 2027-2028).');
            redirect('/admin');
            return;
        }

        $season_id = $this->seasonRepo->create($name, $set_active);
        flash('success', "La saison « {$name} » a été créée avec succès.");
        redirect("/admin?season_id={$season_id}");
    }

    /**
     * Définir une saison comme active
     */
    public function setActive(): void {
        Auth::require_admin();

        $season_id = (int)($_POST['season_id'] ?? 0);
        if ($season_id <= 0) {
            flash('error', 'Saison invalide.');
            redirect('/admin');
            return;
        }

        $season = $this->seasonRepo->findById($season_id);
        if ($season) {
            $this->seasonRepo->setActive($season_id);
            flash('success', "La saison « {$season['name']} » est désormais la saison active par défaut.");
        }

        redirect("/admin?season_id={$season_id}");
    }

    /**
     * Renommer une saison
     */
    public function rename(): void {
        Auth::require_admin();

        $season_id = (int)($_POST['season_id'] ?? 0);
        $new_name = trim((string)($_POST['new_name'] ?? ($_POST['season_name'] ?? '')));

        if ($season_id <= 0 || $new_name === '') {
            flash('error', 'Le nouveau nom de la saison est obligatoire.');
            redirect('/admin');
            return;
        }

        $this->seasonRepo->rename($season_id, $new_name);
        flash('success', "La saison a été renommée en « {$new_name} ».");
        redirect("/admin?season_id={$season_id}");
    }

    /**
     * Supprimer une saison
     */
    public function delete(): void {
        Auth::require_admin();

        $season_id = (int)($_POST['season_id'] ?? 0);
        if ($season_id <= 0) {
            flash('error', 'Saison invalide.');
            redirect('/admin');
            return;
        }

        $all_seasons = $this->seasonRepo->getAll();
        if (count($all_seasons) <= 1) {
            flash('error', 'Impossible de supprimer la seule saison existante.');
            redirect('/admin');
            return;
        }

        $season = $this->seasonRepo->findById($season_id);
        if ($season) {
            $this->seasonRepo->delete($season_id);
            flash('info', "La saison « {$season['name']} » a été supprimée.");
        }

        $active = $this->seasonRepo->getActive();
        redirect("/admin?season_id={$active['id']}");
    }
}
