<?php

class Lang
{
    private static $strings = array();
    private static $lang    = 'cs';

    public static function init($lang = null)
    {
        // Zjisti jazyk: 1) parametr, 2) session, 3) config default, 4) 'cs'
        if ($lang) {
            self::$lang = $lang;
        } elseif (!empty($_SESSION['lang'])) {
            self::$lang = $_SESSION['lang'];
        } elseif (defined('APP_LANG')) {
            self::$lang = APP_LANG;
        }

        // Sanitize — jen písmena
        self::$lang = preg_replace('/[^a-z]/', '', strtolower(self::$lang));

        $file = __DIR__ . '/../lang/' . self::$lang . '.php';
        if (!file_exists($file)) {
            $file = __DIR__ . '/../lang/cs.php';
            self::$lang = 'cs';
        }

        self::$strings = require $file;
    }

    public static function setLang($lang)
    {
        $_SESSION['lang'] = preg_replace('/[^a-z]/', '', strtolower($lang));
        self::init($_SESSION['lang']);
    }

    public static function get()
    {
        return self::$lang;
    }

    // Přeloží klíč, volitelně sprintf formátování
    public static function t($key, ...$args)
    {
        $str = isset(self::$strings[$key]) ? self::$strings[$key] : $key;
        if ($args) {
            return vsprintf($str, $args);
        }
        return $str;
    }

    // Vrátí překlad stavu objednávky
    public static function status($wooStatus)
    {
        $key = 'status_' . $wooStatus;
        return isset(self::$strings[$key]) ? self::$strings[$key] : ucfirst($wooStatus);
    }

    // Dostupné jazyky
    public static function available()
    {
        $langs = array();
        foreach (glob(__DIR__ . '/../lang/*.php') as $f) {
            $langs[] = basename($f, '.php');
        }
        return $langs;
    }

    // Vlajky / názvy jazyků
    public static function label($lang)
    {
        return strtoupper($lang);
    }

    public static function fullLabel($lang)
    {
        $labels = array(
            'cs' => 'Čeština',
            'sk' => 'Slovenčina',
            'en' => 'English',
            'de' => 'Deutsch',
            'pl' => 'Polski',
            'es' => 'Español',
            'fr' => 'Français',
            'it' => 'Italiano',
            'hu' => 'Magyar',
            'ro' => 'Română',
            'nl' => 'Nederlands',
            'ru' => 'Русский',
            'el' => 'Ελληνικά',
            'uk' => 'Українська',
            'pt' => 'Português',
        );
        return isset($labels[$lang]) ? $labels[$lang] : strtoupper($lang);
    }

    // Vrátí <img> tag s vlajkou pokud SVG existuje, jinak jen kód
    public static function flag($lang, $size = 20)
    {
        $path = __DIR__ . '/../assets/flags/' . $lang . '.svg';
        if (file_exists($path)) {
            return '<img src="assets/flags/' . htmlspecialchars($lang) . '.svg"'
                 . ' width="' . $size . '" height="' . round($size * 0.75) . '"'
                 . ' alt="' . htmlspecialchars(strtoupper($lang)) . '"'
                 . ' style="border-radius:2px;vertical-align:middle">';
        }
        return '<span>' . htmlspecialchars(strtoupper($lang)) . '</span>';
    }
}

// Zkratka pro pohodlné použití v šablonách
function t($key, ...$args)
{
    return Lang::t($key, ...$args);
}
