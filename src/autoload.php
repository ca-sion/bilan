<?php
declare(strict_types=1);

/**
 * Autoloader PSR-4 natif sans dépendance externe pour CA Sion Débriefing
 * Supporte le namespace App\... et la résolution automatique des classes dans src/
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

    // 2. Résolution des classes sans namespace (src/, src/Domain/, src/Services/)
    $lookup_dirs = [
        $src_dir,
        $src_dir . 'Domain/',
        $src_dir . 'Services/',
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
