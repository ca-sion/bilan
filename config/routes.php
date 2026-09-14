<?php
declare(strict_types=1);

/**
 * Déclaration centralisée des routes de l'application (Style Laravel routes/web.php)
 */

return function (Router $router): void {

    // --- Espace Athlète (Accueil, Connexion & Bilan) ---
    $router->get('/', [AthleteController::class, 'formView']);
    $router->get('/login', [AthleteController::class, 'loginView']);
    $router->post('/login', [AthleteController::class, 'loginSubmit']);
    $router->post('/update-birth-date', [AthleteController::class, 'updateBirthDate']);
    $router->post('/unlock', [AthleteController::class, 'unlockForm']);
    $router->get('/logout', [AthleteController::class, 'logout']);

    // --- Espace Entraîneur (Authentification & Tableau de bord) ---
    $router->get('/admin', [DashboardController::class, 'index']);
    $router->get('/admin/login', [DashboardController::class, 'loginView']);
    $router->post('/admin/login', [DashboardController::class, 'loginSubmit']);
    $router->get('/admin/logout', [DashboardController::class, 'logout']);

    // --- Entretiens & Arbitrage Coach ---
    $router->get('/admin/entretien/u16', [InterviewController::class, 'u16Group']);
    $router->get('/admin/entretien/u18', [InterviewController::class, 'u18Split']);
    $router->post('/admin/save-trainer', [InterviewController::class, 'save']);
    $router->post('/admin/reopen', [InterviewController::class, 'reopen']);

    // --- Gestion CRUD Athlètes & PIN ---
    $router->post('/admin/add-athlete', [AthleteAdminController::class, 'add']);
    $router->post('/admin/edit-athlete', [AthleteAdminController::class, 'edit']);
    $router->post('/admin/delete-athlete', [AthleteAdminController::class, 'delete']);
    $router->post('/admin/reset-pin', [AthleteAdminController::class, 'resetPin']);

    // --- Gestion des Saisons & Archives ---
    $router->post('/admin/create-season', [SeasonController::class, 'create']);
    $router->post('/admin/set-active-season', [SeasonController::class, 'setActive']);
    $router->post('/admin/rename-season', [SeasonController::class, 'rename']);
    $router->post('/admin/delete-season', [SeasonController::class, 'delete']);

    // --- Imports, Exports, Synthèses & Backups ---
    $router->get('/admin/download-template', [ExportController::class, 'downloadTemplate']);
    $router->post('/admin/import-csv', [ExportController::class, 'importCsv']);
    $router->get('/admin/export-grid', [ExportController::class, 'exportGridCsv']);
    $router->get('/admin/print-summary', [ExportController::class, 'printSummary']);
    $router->get('/admin/backup-json', [ExportController::class, 'backupJson']);
    $router->get('/admin/backup-sqlite', [ExportController::class, 'backupSqlite']);
    $router->post('/admin/restore-database', [ExportController::class, 'restore']);
    $router->post('/admin/settings', [ExportController::class, 'updateSettings']);

    // --- Endpoints API (AJAX & Autosave) ---
    $router->post('/api/save', [ApiController::class, 'save_answers']);
    $router->post('/api/submit', [ApiController::class, 'submit_interview']);
    $router->get('/api/validate-disciplines', [ApiController::class, 'validate_disciplines']);
    $router->post('/api/validate-disciplines', [ApiController::class, 'validate_disciplines']);
    $router->post('/api/export-whatsapp', [ApiController::class, 'export_whatsapp']);
};
