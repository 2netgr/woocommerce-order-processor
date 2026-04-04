<?php
/**
 * WooDashboard — Konfigurace
 *
 * Changelog:
 *   2.1.0 - Multilang podpora (cs/en), přírůstkový cron, filtr stavů (client-side),
 *           nový stats layout, oprava statusu pending na "Čeká na vyřízení"
 *   2.0.0 - Kompletní přepis: SQLite DB, Settings UI, cron, responzivní design
 *   1.0.0 - První verze: single-file PHP dashboard
 */

// ── Zabezpečení ────────────────────────────────────────────────
define('DASHBOARD_PASSWORD', 'changeme');

// ── Databáze ───────────────────────────────────────────────────
define('DB_PATH', __DIR__ . '/data/dashboard.db');

// ── Jazyk ──────────────────────────────────────────────────────
// Výchozí jazyk: 'cs' nebo 'en'. Uživatel může přepnout v UI.
define('APP_LANG', 'cs');

// ── Výkon ──────────────────────────────────────────────────────
define('MAX_ORDERS', 50);
define('PREVIEW_ROWS', 10);
define('CACHE_TTL_MINUTES', 10);

// ── Časová zóna ────────────────────────────────────────────────
define('APP_TIMEZONE', 'Europe/Prague');

// ── Cron ───────────────────────────────────────────────────────
define('CRON_SECRET', 'ChangeMe2026');

// ── Aplikace ───────────────────────────────────────────────────
define('APP_NAME', 'WooDashboard');
define('APP_VERSION', '2.1.0');

date_default_timezone_set(APP_TIMEZONE);
