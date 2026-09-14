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
    return $base . $path;
}

/**
 * Redirige vers une route interne
 */
function redirect(string $path): void {
    $target = url($path);
    header("Location: {$target}");
    exit;
}

/**
 * Renvoie une réponse JSON
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

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
 * Données de démarrage utiles et saison active
 */
function seed_initial_data(PDO $pdo): void {
    $season_name = env('CURRENT_SEASON_NAME', '2026-2027');
    $stmt = $pdo->prepare("INSERT INTO seasons (name, is_active) VALUES (?, 1)");
    $stmt->execute([$season_name]);
    $season_id = (int)$pdo->lastInsertId();

    // Import initial de démonstration avec exemples représentatifs
    $sample_athletes = [
        [
            'first_name' => 'Noah',
            'last_name' => 'Bonvin',
            'birth_date' => '2013-04-14', // 14 ans en 2027 => U16 1ère année
            'category' => 'U16',
            'phone' => '+41791234567',
            'email' => 'noah@example.ch',
            'meta' => json_encode(['notes' => 'Option vendredi demandee']),
            'type' => 'u16_group'
        ],
        [
            'first_name' => 'Julie',
            'last_name' => 'Fournier',
            'birth_date' => '2012-09-28', // 15 ans en 2027 => U16 2ème année
            'category' => 'U16',
            'phone' => '+41789876543',
            'email' => 'julie@example.ch',
            'meta' => json_encode(['notes' => 'Sprint haies']),
            'type' => 'u16_group'
        ],
        [
            'first_name' => 'Alexis',
            'last_name' => 'Reynard',
            'birth_date' => '2008-02-03', // 19 ans en 2027 => U20
            'category' => 'U20',
            'phone' => '+41761112233',
            'email' => 'alexis@example.ch',
            'meta' => json_encode(['notes' => 'Cadre romand']),
            'type' => 'individual_elite'
        ],
        [
            'first_name' => 'Camille',
            'last_name' => 'Zuber',
            'birth_date' => null, // Date inconnue => PIN '0000'
            'category' => 'U18',
            'phone' => '+41790001122',
            'email' => 'camille@example.ch',
            'meta' => json_encode(['notes' => 'Date de naissance a completer']),
            'type' => 'individual_elite'
        ],
        [
            'first_name' => 'Loïc',
            'last_name' => 'Besse',
            'birth_date' => '2009-11-15', // 18 ans en 2027 => U18+
            'category' => 'U18',
            'phone' => '+41794567890',
            'email' => 'loic@example.ch',
            'meta' => json_encode(['notes' => 'Demi-fond et steeplechase']),
            'type' => 'individual_elite'
        ],
        [
            'first_name' => 'Emma',
            'last_name' => 'Germanier',
            'birth_date' => '2013-07-22', // 14 ans en 2027 => U16 1ère année
            'category' => 'U16',
            'phone' => '+41783334455',
            'email' => 'emma@example.ch',
            'meta' => json_encode(['notes' => 'Hauteur et sprint']),
            'type' => 'u16_group'
        ]
    ];

    $athlete_stmt = $pdo->prepare(
        "INSERT INTO athletes (first_name, last_name, birth_date, birth_year, category, access_token, access_pin, phone, email, meta) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $interview_stmt = $pdo->prepare(
        "INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, trainer_answers, decisions) 
         VALUES (?, ?, ?, 'waiting', '{}', '{}', '{}')"
    );

    foreach ($sample_athletes as $a) {
        $birth_year = $a['birth_date'] ? (int)substr($a['birth_date'], 0, 4) : 0;
        $pin = '0000';
        if ($a['birth_date'] && preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $a['birth_date'], $m)) {
            $pin = $m[2] . $m[1]; // JJMM
        }
        $token = bin2hex(random_bytes(16)); // 32 chars

        $athlete_stmt->execute([
            $a['first_name'],
            $a['last_name'],
            $a['birth_date'],
            $birth_year,
            $a['category'],
            $token,
            $pin,
            $a['phone'],
            $a['email'],
            $a['meta']
        ]);

        $athlete_id = (int)$pdo->lastInsertId();

        $interview_stmt->execute([
            $athlete_id,
            $season_id,
            $a['type']
        ]);
    }
}
