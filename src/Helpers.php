<?php

class Helpers
{
    public static function formatPrice($amount, $currency)
    {
        $symbols = array('CZK' => 'Kč', 'EUR' => '€', 'USD' => '$', 'GBP' => '£', 'PLN' => 'zł', 'HUF' => 'Ft');
        $symbol  = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
        $formatted = number_format((float)$amount, 2, ',', ' ');
        if (in_array($currency, array('EUR', 'USD', 'GBP'))) {
            return $symbol . ' ' . $formatted;
        }
        return $formatted . ' ' . $symbol;
    }

    public static function formatDate($date)
    {
        if (!$date) return '—';
        try {
            $dt = new DateTime($date);
            return $dt->format('d.m.Y H:i');
        } catch (Exception $e) {
            return $date;
        }
    }

    public static function formatRelative($date)
    {
        if (!$date) return '—';
        try {
            $dt   = new DateTime($date);
            $now  = new DateTime();
            $diff = $now->getTimestamp() - $dt->getTimestamp();
            if ($diff < 60)     return Lang::t('rel_moment');
            if ($diff < 3600)   return sprintf(Lang::t('rel_minutes'), round($diff / 60));
            if ($diff < 86400)  return sprintf(Lang::t('rel_hours'),   round($diff / 3600));
            if ($diff < 604800) return sprintf(Lang::t('rel_days'),    round($diff / 86400));
            return $dt->format('d.m.Y');
        } catch (Exception $e) {
            return $date;
        }
    }

    // Vrátí [label, color, bgColor] — label přichází z Lang
    public static function statusLabel($status)
    {
        $colors = array(
            'pending'    => array('#F59E0B', '#FEF3C7'),
            'processing' => array('#3B82F6', '#EFF6FF'),
            'on-hold'    => array('#8B5CF6', '#F5F3FF'),
            'completed'  => array('#10B981', '#ECFDF5'),
            'cancelled'  => array('#EF4444', '#FEF2F2'),
            'refunded'   => array('#6B7280', '#F9FAFB'),
            'failed'     => array('#DC2626', '#FEF2F2'),
            'trash'      => array('#9CA3AF', '#F9FAFB'),
        );
        $label  = Lang::status($status);
        $colors = isset($colors[$status]) ? $colors[$status] : array('#9CA3AF', '#F9FAFB');
        return array($label, $colors[0], $colors[1]);
    }

    public static function allStatuses()
    {
        return array('pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed');
    }

    public static function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    public static function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function isPost()
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public static function post($key, $default = '')
    {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    public static function get($key, $default = '')
    {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    public static function numberFormat($n)
    {
        return number_format((float)$n, 0, ',', ' ');
    }
}
