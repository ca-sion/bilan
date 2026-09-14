<?php
declare(strict_types=1);

/**
 * Service de gestion unifiée des paramètres de l'application
 * Priorité : Base de données SQLite (modifications directes via l'admin)
 * Fallback : Variables du fichier .env / constantes par défaut
 * Clé de secours : Le mot de passe .env reste une clé maîtresse prioritaire
 */
class SettingsService {

    /**
     * Récupère un paramètre (DB d'abord, puis .env, puis valeur par défaut)
     */
    public static function get(string $key, mixed $default = null): mixed {
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();

            if ($row !== false && $row['value'] !== null && $row['value'] !== '') {
                return $row['value'];
            }
        } catch (\Throwable) {
            // Fallback silencieux en cas d'accès pré-initialisation
        }

        // Fallback .env (recherche en majuscules comme CLUB_NAME pour key club_name)
        $env_key = strtoupper(str_replace('-', '_', $key));
        $env_val = env($env_key);
        if ($env_val !== null && $env_val !== '') {
            return $env_val;
        }

        // Fallback clé exacte
        $env_val_exact = env($key);
        if ($env_val_exact !== null && $env_val_exact !== '') {
            return $env_val_exact;
        }

        return $default;
    }

    /**
     * Enregistre un paramètre dans la base de données SQLite
     */
    public static function set(string $key, string $value): void {
        $db = get_db();
        $stmt = $db->prepare(
            "INSERT INTO settings (key, value, updated_at) 
             VALUES (?, ?, CURRENT_TIMESTAMP)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$key, $value]);
    }

    /**
     * Récupère tous les paramètres clés sous forme de tableau associatif
     */
    public static function all(): array {
        return [
            'club_name'                  => self::get_club_name(),
            'coach_phone'                => self::get_coach_phone(),
            'reference_competition_year' => self::get_reference_competition_year(),
            'current_season_name'        => self::get_current_season_name(),
        ];
    }

    public static function get_club_name(): string {
        return (string)self::get('club_name', env('CLUB_NAME', 'CA Sion'));
    }

    public static function get_coach_phone(): string {
        return (string)self::get('coach_phone', env('COACH_PHONE', '+41791234567'));
    }

    public static function get_reference_competition_year(): int {
        return (int)self::get('reference_competition_year', env('REFERENCE_COMPETITION_YEAR', 2027));
    }

    public static function get_current_season_name(): string {
        return (string)self::get('current_season_name', env('CURRENT_SEASON_NAME', '2026-2027'));
    }

    /**
     * Vérification sécurisée du mot de passe Administrateur
     * 
     * 1. Clé maîtresse (.env) : Si le mot de passe correspond à ADMIN_PASSWORD dans .env,
     *    la connexion réussit TOUJOURS immédiatement (sécurité anti-blocage).
     * 2. Mot de passe personnalisé (DB) : Sinon, vérifie le hash stocké en base.
     * 3. Fallback par défaut ('admin123' si rien n'est configuré).
     */
    public static function verify_admin_password(string $input_password): bool {
        if ($input_password === '') {
            return false;
        }

        // 1. Clé maîtresse .env prioritaire (ADMIN_PASSWORD ou ADMIN_PASSWORD_HASH)
        $env_pass = (string)env('ADMIN_PASSWORD', '');
        if ($env_pass !== '') {
            if (hash_equals($env_pass, $input_password)) {
                return true;
            }
            if (str_starts_with($env_pass, '$2y$') || str_starts_with($env_pass, '$argon2')) {
                if (password_verify($input_password, $env_pass)) {
                    return true;
                }
            }
        }

        $env_hash = (string)env('ADMIN_PASSWORD_HASH', '');
        if ($env_hash !== '' && password_verify($input_password, $env_hash)) {
            return true;
        }

        // 2. Mot de passe personnalisé enregistré via l'admin
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'admin_password_hash'");
            $stmt->execute();
            $row = $stmt->fetch();

            if ($row && !empty($row['value'])) {
                if (password_verify($input_password, (string)$row['value'])) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Ignorer
        }

        // 3. Si aucun mot de passe n'est configuré nulle part, fallback par défaut 'admin123'
        if ($env_pass === '') {
            return $input_password === 'admin123';
        }

        return false;
    }

    /**
     * Définit un nouveau mot de passe administrateur en base
     */
    public static function set_admin_password(string $new_password): void {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        self::set('admin_password_hash', $hash);
    }
}
