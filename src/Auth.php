<?php
declare(strict_types=1);

/**
 * Gestionnaire d'authentification administrateur / coach
 */
class Auth {
    public static function init_session(): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    public static function generate_token(int $length = 16): string {
        return bin2hex(random_bytes($length));
    }

    public static function calculate_pin_from_birth_date(?string $birth_date): string {
        $parsed = Helper::parse_birth_date($birth_date);
        return $parsed['pin'];
    }

    public static function is_logged_in(): bool {
        self::init_session();
        return !empty($_SESSION['is_coach_admin']);
    }

    public static function login(string $password): bool {
        self::init_session();
        
        $is_valid = SettingsService::verify_admin_password($password);

        // Fallback rétrocompatible si ADMIN_PASSWORD_HASH est défini dans .env
        if (!$is_valid) {
            $legacy_hash = env('ADMIN_PASSWORD_HASH');
            if ($legacy_hash && password_verify($password, $legacy_hash)) {
                $is_valid = true;
            }
        }

        if ($is_valid) {
            $_SESSION['is_coach_admin'] = true;
            $_SESSION['admin_logged_at'] = time();
            return true;
        }

        return false;
    }

    public static function logout(): void {
        self::init_session();
        unset($_SESSION['is_coach_admin']);
        unset($_SESSION['admin_logged_at']);
    }

    public static function require_admin(): void {
        if (!self::is_logged_in()) {
            flash('error', 'Veuillez vous connecter pour accéder à l\'espace entraîneur.');
            redirect('/admin/login');
        }
    }
}
