<?php

class Auth
{
    public static function init()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Generuj CSRF token pokud chybí
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        // Inicializuj překlady
        Lang::init();
    }

    public static function isLoggedIn()
    {
        if (DASHBOARD_PASSWORD === '') return true;
        return !empty($_SESSION['woo_auth']);
    }

    public static function login($password)
    {
        if ($password === DASHBOARD_PASSWORD) {
            $_SESSION['woo_auth'] = true;
            return true;
        }
        return false;
    }

    public static function logout()
    {
        unset($_SESSION['woo_auth']);
    }

    public static function requireAuth()
    {
        self::init();
        if (!self::isLoggedIn()) {
            header('Location: index.php?page=login');
            exit;
        }
    }

    public static function csrfToken()
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function verifyCsrf()
    {
        $token = '';
        if (isset($_POST['_csrf'])) {
            $token = $_POST['_csrf'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('CSRF token mismatch');
        }
    }

    public static function baseUrl()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $dir    = dirname($script);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        return $scheme . '://' . $host . ($dir === '/' ? '' : $dir);
    }
}
