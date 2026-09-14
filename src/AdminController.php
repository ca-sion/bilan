<?php
declare(strict_types=1);

/**
 * Façade de transition pour l'espace administrateur / entraîneur
 * Délègue aux contrôleurs spécialisés : DashboardController, InterviewController, 
 * AthleteAdminController, SeasonController et ExportController.
 */
class AdminController {
    private DashboardController $dashboard;
    private InterviewController $interview;
    private AthleteAdminController $athleteAdmin;
    private SeasonController $season;
    private ExportController $export;

    public function __construct(?PDO $db = null) {
        $db = $db ?? get_db();
        $this->dashboard = new DashboardController($db);
        $this->interview = new InterviewController($db);
        $this->athleteAdmin = new AthleteAdminController($db);
        $this->season = new SeasonController($db);
        $this->export = new ExportController($db);
    }

    public function login_view(): void { $this->dashboard->loginView(); }
    public function login_submit(): void { $this->dashboard->loginSubmit(); }
    public function logout(): void { $this->dashboard->logout(); }
    public function dashboard(): void { $this->dashboard->index(); }

    public function u16_group_view(): void { $this->interview->u16Group(); }
    public function u18_split_view(): void { $this->interview->u18Split(); }
    public function save_trainer_form(): void { $this->interview->save(); }
    public function reopen_interview(): void { $this->interview->reopen(); }

    public function reset_pin(): void { $this->athleteAdmin->resetPin(); }
    public function add_athlete(): void { $this->athleteAdmin->add(); }
    public function edit_athlete(): void { $this->athleteAdmin->edit(); }
    public function delete_athlete(): void { $this->athleteAdmin->delete(); }

    public function create_season(): void { $this->season->create(); }
    public function set_active_season(): void { $this->season->setActive(); }
    public function rename_season(): void { $this->season->rename(); }
    public function delete_season(): void { $this->season->delete(); }

    public function download_template_csv(): void { $this->export->downloadTemplate(); }
    public function import_athletes_csv(): void { $this->export->importCsv(); }
    public function export_grid_csv(): void { $this->export->exportGridCsv(); }
    public function print_summary(): void { $this->export->printSummary(); }
    public function backup_database_json(): void { $this->export->backupJson(); }
    public function backup_database_sqlite(): void { $this->export->backupSqlite(); }
    public function restore_database(): void { $this->export->restore(); }
    public function update_settings(): void { $this->export->updateSettings(); }
}
