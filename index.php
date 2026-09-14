<?php
declare(strict_types=1);

/**
 * CA Sion — Débriefing & Cadrage de saison
 * Contrôleur frontal et point d'entrée unique (Architecture modulaire PHP 8.2+)
 */

// Initialisation de l'environnement, de la base de données et de l'autoloader
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/autoload.php';

Auth::init_session();
$db = get_db();

try {
    // Initialisation du routeur et chargement des routes déclaratives (style Laravel)
    $router = new Router();
    $load_routes = require __DIR__ . '/config/routes.php';
    $load_routes($router);

    // Distribution de la requête HTTP courante
    $router->dispatch();

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
