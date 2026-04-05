<?php

class Auth
{
    // Maximální počet neúspěšných pokusů o přihlášení
    const MAX_LOGIN_ATTEMPTS = 5;
    // Doba blokace v sekundách (15 minut)
    const LOCKOUT_SECONDS    = 900;

    public static function init()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Bezpečná session konfigurace
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                ini_set('session.cookie_secure', '1');
            }
            session_start();
        }

        // Generuj CSRF token pokud chybí
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
        // Zkontroluj brute-force blokaci
        if (self::isLockedOut()) {
            return false;
        }

        if (hash_equals(DASHBOARD_PASSWORD, $password)) {
            // Úspěšné přihlášení — regeneruj session ID (session fixation protection)
            session_regenerate_id(true);
            $_SESSION['woo_auth']        = true;
            $_SESSION['login_attempts']  = 0;
            $_SESSION['locked_until']    = 0;
            // Zaznamenej čas přihlášení a IP
            $_SESSION['auth_ip']         = self::clientIp();
            $_SESSION['auth_time']       = time();
            return true;
        }

        // Neúspěšný pokus
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
            $_SESSION['locked_until'] = time() + self::LOCKOUT_SECONDS;
        }

        return false;
    }

    public static function isLockedOut()
    {
        if (empty($_SESSION['locked_until'])) return false;
        if (time() < $_SESSION['locked_until']) return true;
        // Blokace vypršela
        $_SESSION['login_attempts'] = 0;
        $_SESSION['locked_until']   = 0;
        return false;
    }

    public static function lockoutRemainingSeconds()
    {
        if (empty($_SESSION['locked_until'])) return 0;
        return max(0, $_SESSION['locked_until'] - time());
    }

    public static function loginAttemptsLeft()
    {
        $attempts = $_SESSION['login_attempts'] ?? 0;
        return max(0, self::MAX_LOGIN_ATTEMPTS - $attempts);
    }

    public static function logout()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireAuth()
    {
        self::init();
        if (!self::isLoggedIn()) {
            // Pokud přišel AJAX request, vrať 401
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(array('error' => 'Unauthorized'));
                exit;
            }
            header('Location: index.php');
            exit;
        }

        // Zkontroluj konzistenci IP (volitelné — může rušit u mobilů/VPN)
        // if (isset($_SESSION['auth_ip']) && $_SESSION['auth_ip'] !== self::clientIp()) {
        //     self::logout();
        //     header('Location: index.php');
        //     exit;
        // }
    }

    public static function csrfToken()
    {
        return isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    }

    public static function verifyCsrf()
    {
        $token = '';
        if (isset($_POST['_csrf'])) {
            $token = $_POST['_csrf'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        $stored = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
        if (!$stored || !hash_equals($stored, $token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode(array('error' => 'CSRF token mismatch')));
        }
    }

    public static function baseUrl()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $dir    = dirname($script);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        // Sanitize host
        $host   = preg_replace('/[^a-zA-Z0-9\.\-:]/', '', $host);
        return $scheme . '://' . $host . ($dir === '/' ? '' : $dir);
    }

    private static function clientIp()
    {
        // Preferuj real IP před proxy headery (ty jsou snadno falšovatelné)
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}
