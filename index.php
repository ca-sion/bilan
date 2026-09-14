<?php
declare(strict_types=1);

/**
 * Routeur principal et contrôleur frontal
 * Optimisé pour hébergement mutualisé Infomaniak en racine ou sous-répertoire (ex: /debriefing/)
 */

// Initialisation de la configuration et de la base de données SQLite
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/CategoryHelper.php';
require_once __DIR__ . '/src/WhatsAppHelper.php';
require_once __DIR__ . '/src/AthleteController.php';
require_once __DIR__ . '/src/AdminController.php';
require_once __DIR__ . '/src/ApiController.php';

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
        redirect('/bilan');
    }

    if ($path === '/bilan') {
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

    if ($path === '/bilan/login') {
        if ($method === 'POST') {
            $athlete_ctrl->login_submit();
        } else {
            $athlete_ctrl->login_view();
        }
        return;
    }

    if ($path === '/bilan/update-birth-date' && $method === 'POST') {
        $athlete_ctrl->update_birth_date();
        return;
    }

    if ($path === '/bilan/logout') {
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

    if ($path === '/admin/u16-group') {
        $admin_ctrl->u16_group_view();
        return;
    }

    if ($path === '/admin/u18-split') {
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
