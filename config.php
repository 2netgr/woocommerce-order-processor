<?php
/**
 * WooDashboard — Konfigurace
 *
 * Changelog:
 *   2.2.2 - Bezpečnostní audit: brute-force ochrana, session fixation fix,
 *           SQL injection fix, security headers, defense-in-depth .htaccess
 *   2.2.1 - Tlačítko 'Otevřít v shopu' v detailu objednávky
 *   2.2.0 - Hamburger menu na mobilu, GitHub update checker, 13 jazyků
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
define('CRON_SECRET', 'changeme');

// ── Aplikace ───────────────────────────────────────────────────
define('APP_NAME', 'WooDashboard');
define('APP_VERSION', '2.2.2');

date_default_timezone_set(APP_TIMEZONE);
