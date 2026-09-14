<?php
declare(strict_types=1);

/**
 * Gestionnaire d'authentification administrateur / coach
 */
class Auth {
    public static function init_session(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function is_logged_in(): bool {
        self::init_session();
        return !empty($_SESSION['is_coach_admin']);
    }

    public static function login(string $password): bool {
        self::init_session();
        $hash = function_exists('env') ? env('ADMIN_PASSWORD_HASH') : null;
        
        // Sécurité par défaut si non configuré
        if (!$hash) {
            $hash = password_hash('coach2026', PASSWORD_BCRYPT);
        }

        if (password_verify($password, $hash)) {
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
