<?php
declare(strict_types=1);

/**
 * Autoloader PSR-4 natif pour CA Sion Débriefing
 * Résout automatiquement les classes dans les dossiers modulaires de src/
 */
spl_autoload_register(function (string $class): void {
    $src_dir = __DIR__ . '/';

    // 1. Résolution PSR-4 pour le namespace racine App\
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative_class = substr($class, strlen($prefix));
        $file = $src_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // 2. Résolution modulaire sans namespace (Controllers, Repositories, Domain, Services, Support)
    $lookup_dirs = [
        $src_dir,
        $src_dir . 'Controllers/',
        $src_dir . 'Repositories/',
        $src_dir . 'Domain/',
        $src_dir . 'Services/',
        $src_dir . 'Support/',
    ];

    $clean_class = str_replace('\\', '/', $class);
    foreach ($lookup_dirs as $dir) {
        $file = $dir . $clean_class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
