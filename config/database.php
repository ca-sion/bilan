<?php
declare(strict_types=1);

/**
 * Configuration, chargeur d'environnement et gestionnaire de base de données SQLite
 * Conçu pour PHP 8.2+ natif sans framework lourd.
 */

// Chargement sécurisé des variables du fichier .env
function load_env(string $env_path): void {
    if (!file_exists($env_path)) {
        return;
    }
    
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            
            // Retirer les guillemets éventuels
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
}

// Charger .env depuis la racine du projet
load_env(dirname(__DIR__) . '/.env');

/**
 * Récupère une variable d'environnement avec valeur par défaut
 */
function env(string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null) {
        return $default;
    }
    return $val;
}

/**
 * Calcule dynamiquement le BASE_URL pour hébergement en racine ou sous-dossier (ex: /debriefing)
 */
function get_base_url(): string {
    $configured = env('BASE_URL');
    if ($configured !== null && $configured !== '') {
        return rtrim($configured, '/');
    }
    
    // Détection automatique du sous-dossier
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script_name);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }
    return rtrim(str_replace('\\', '/', $dir), '/');
}

/**
 * Génère une URL absolue interne dans l'application
 */
function url(string $path = ''): string {
    $base = get_base_url();
    $path = '/' . ltrim($path, '/');
    if ($base !== '' && (str_starts_with($path, $base . '/') || $path === $base)) {
        return $path;
    }
    return $base . $path;
}

/**
 * Redirige vers une route interne
 */
function redirect(string $path): void {
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        $target = $path;
    } else {
        $target = url($path);
    }
    if (!headers_sent()) {
        header("Location: {$target}");
    }
    if (PHP_SAPI !== 'cli') {
        exit;
    }
}

/**
 * Renvoie une réponse JSON
 */
function json_response(array $data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (PHP_SAPI !== 'cli') {
        exit;
    }
}

/**
 * Décodage JSON sécurisé sans erreur de précédence d'opérateurs
 */
function safe_json_decode(mixed $value, array $default = []): array {
    if (is_array($value)) {
        return $value;
    }
    if (empty($value) || !is_string($value)) {
        return $default;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $default;
}

/**
 * Chargeur de configuration d'athlétisme (config/athletics.php)
 */
function athletics_config(?string $key = null): mixed {
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/athletics.php';
        $config = file_exists($path) ? require $path : [];
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? null;
}

/**
 * Gestion des messages flash en session
 */
function flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function get_flashes(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

/**
 * Connexion singleton PDO SQLite et création automatique des tables
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $db_relative_path = env('DB_PATH', 'data/debriefing.sqlite');
    $db_path = dirname(__DIR__) . '/' . ltrim($db_relative_path, '/');
    $db_dir = dirname($db_path);

    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0755, true);
    }

    $is_first_creation = !file_exists($db_path);

    $pdo = new PDO("sqlite:{$db_path}", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ]);

    // Optimisations SQLite pour concurrence et intégrité
    try {
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
    } catch (\Throwable) {
        // Fallback transparent si le système de fichiers bloque la création des fichiers temporaires WAL
        $pdo->exec('PRAGMA journal_mode = DELETE;');
    }

    // Création des tables
    init_db_schema($pdo);

    // Initialisation des données de base si premier lancement
    if ($is_first_creation || get_seasons_count($pdo) === 0) {
        seed_initial_data($pdo);
    }

    return $pdo;
}

function get_seasons_count(PDO $pdo): int {
    try {
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM seasons');
        $res = $stmt->fetch();
        return (int)($res['count'] ?? 0);
    } catch (\Throwable) {
        return 0;
    }
}

/**
 * Initialisation du schéma SQLite
 */
function init_db_schema(PDO $pdo): void {
    $schema = <<<SQL
    CREATE TABLE IF NOT EXISTS seasons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS athletes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        birth_date TEXT,
        birth_year INTEGER NOT NULL,
        category TEXT NOT NULL,
        access_token TEXT UNIQUE NOT NULL,
        access_pin TEXT NOT NULL DEFAULT '0000',
        phone TEXT,
        email TEXT,
        meta TEXT DEFAULT '{}',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS interviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        athlete_id INTEGER NOT NULL,
        season_id INTEGER NOT NULL,
        interview_type TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'waiting',
        athlete_answers TEXT DEFAULT '{}',
        trainer_answers TEXT DEFAULT '{}',
        decisions TEXT DEFAULT '{}',
        trainer_notes TEXT,
        is_validated INTEGER NOT NULL DEFAULT 0,
        validated_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (athlete_id) REFERENCES athletes(id),
        FOREIGN KEY (season_id) REFERENCES seasons(id)
    );

    CREATE TABLE IF NOT EXISTS collective_meetings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        season_id INTEGER NOT NULL,
        group_name TEXT NOT NULL,
        factual_observations TEXT,
        standard_1 TEXT,
        standard_2 TEXT,
        season_roadmap TEXT,
        meeting_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (season_id) REFERENCES seasons(id)
    );

    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
SQL;

    $pdo->exec($schema);
}

/**
 * Initialisation minimale de base : création de la saison active par défaut
 */
function seed_initial_data(PDO $pdo): void {
    $season_name = env('CURRENT_SEASON_NAME', '2026-2027');
    $stmt = $pdo->prepare("INSERT INTO seasons (name, is_active) VALUES (?, 1)");
    $stmt->execute([$season_name]);
}
