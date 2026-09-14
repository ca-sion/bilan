<?php
declare(strict_types=1);

/**
 * Routeur principal et contrôleur frontal
 * Optimisé pour hébergement mutualisé Infomaniak en racine ou sous-répertoire (ex: /debriefing/)
 */

// Initialisation de la configuration et de l'autoloader
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/autoload.php';

Auth::init_session();
$db = get_db();

// Instanciation des contrôleurs
$athlete_ctrl = new AthleteController($db);
$admin_ctrl = new AdminController($db);
$api_ctrl = new ApiController($db);

// Analyse de l'URI de la requête
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$parsed_url = parse_url($request_uri);
$path = $parsed_url['path'] ?? '/';

// Suppression du préfixe BASE_URL
$base_url = get_base_url();
if ($base_url !== '' && str_starts_with($path, $base_url)) {
    $path = substr($path, strlen($base_url));
}
$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Routage des requêtes
try {
    // --- Routes Publiques / Athlète ---
    if ($path === '/' || $path === '') {
        // Accès direct par token dans l'URL ?token=...
        if (!empty($_GET['token'])) {
            $athlete_ctrl->direct_token_login((string)$_GET['token']);
            return;
        }

        if (!empty($_SESSION['athlete_id'])) {
            $athlete_ctrl->form_view();
        } else {
            $athlete_ctrl->login_view();
        }
        return;
    }

    if ($path === '/login') {
        if ($method === 'POST') {
            $athlete_ctrl->login_submit();
        } else {
            $athlete_ctrl->login_view();
        }
        return;
    }

    if ($path === '/update-birth-date' && $method === 'POST') {
        $athlete_ctrl->update_birth_date();
        return;
    }

    if ($path === '/unlock' && $method === 'POST') {
        $athlete_ctrl->unlock_form();
        return;
    }

    if ($path === '/logout') {
        $athlete_ctrl->logout();
        return;
    }

    // --- Routes Administrateur / Entraîneur ---
    if ($path === '/admin') {
        $admin_ctrl->dashboard();
        return;
    }

    if ($path === '/admin/login') {
        if ($method === 'POST') {
            $admin_ctrl->login_submit();
        } else {
            $admin_ctrl->login_view();
        }
        return;
    }

    if ($path === '/admin/logout') {
        $admin_ctrl->logout();
        return;
    }

    if ($path === '/admin/entretien/u16') {
        $admin_ctrl->u16_group_view();
        return;
    }

    if ($path === '/admin/entretien/u18') {
        $admin_ctrl->u18_split_view();
        return;
    }

    if ($path === '/admin/save-trainer' && $method === 'POST') {
        $admin_ctrl->save_trainer_form();
        return;
    }

    if ($path === '/admin/reopen' && $method === 'POST') {
        $admin_ctrl->reopen_interview();
        return;
    }

    if ($path === '/admin/reset-pin' && $method === 'POST') {
        $admin_ctrl->reset_pin();
        return;
    }

    if ($path === '/admin/add-athlete' && $method === 'POST') {
        $admin_ctrl->add_athlete();
        return;
    }

    if ($path === '/admin/edit-athlete' && $method === 'POST') {
        $admin_ctrl->edit_athlete();
        return;
    }

    if ($path === '/admin/delete-athlete' && $method === 'POST') {
        $admin_ctrl->delete_athlete();
        return;
    }

    if ($path === '/admin/download-template') {
        $admin_ctrl->download_template_csv();
        return;
    }

    if ($path === '/admin/import-csv' && $method === 'POST') {
        $admin_ctrl->import_athletes_csv();
        return;
    }

    if ($path === '/admin/export-grid') {
        $admin_ctrl->export_grid_csv();
        return;
    }

    if ($path === '/admin/print-summary') {
        $admin_ctrl->print_summary();
        return;
    }

    if ($path === '/admin/backup-json') {
        $admin_ctrl->backup_database_json();
        return;
    }

    if ($path === '/admin/backup-sqlite') {
        $admin_ctrl->backup_database_sqlite();
        return;
    }

    if ($path === '/admin/restore-database' && $method === 'POST') {
        $admin_ctrl->restore_database();
        return;
    }

    if ($path === '/admin/create-season' && $method === 'POST') {
        $admin_ctrl->create_season();
        return;
    }

    if ($path === '/admin/set-active-season' && $method === 'POST') {
        $admin_ctrl->set_active_season();
        return;
    }

    if ($path === '/admin/rename-season' && $method === 'POST') {
        $admin_ctrl->rename_season();
        return;
    }

    if ($path === '/admin/delete-season' && $method === 'POST') {
        $admin_ctrl->delete_season();
        return;
    }

    if ($path === '/admin/settings' && $method === 'POST') {
        $admin_ctrl->update_settings();
        return;
    }

    // --- Routes API (AJAX / Fetch) ---
    if ($path === '/api/save' && $method === 'POST') {
        $api_ctrl->save_answers();
        return;
    }

    if ($path === '/api/submit' && $method === 'POST') {
        $api_ctrl->submit_interview();
        return;
    }

    if ($path === '/api/validate-disciplines') {
        $api_ctrl->validate_disciplines();
        return;
    }

    if ($path === '/api/export-whatsapp') {
        $api_ctrl->export_whatsapp();
        return;
    }

    // 404 Non Trouvé
    http_response_code(404);
    $page_title = "Page introuvable";
    require __DIR__ . '/views/error.php';

} catch (\Throwable $e) {
    if (env('APP_ENV') !== 'production') {
        echo "<h1>Erreur Système</h1><pre>" . htmlspecialchars((string)$e) . "</pre>";
    } else {
        http_response_code(500);
        $page_title = "Erreur serveur";
        $error_message = "Une erreur inattendue est survenue. Veuillez réessayer ultérieurement.";
        require __DIR__ . '/views/error.php';
    }
}
