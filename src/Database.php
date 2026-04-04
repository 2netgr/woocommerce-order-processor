<?php

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . DB_PATH);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');

        $this->migrate();
    }

    public static function get()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function migrate()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS stores (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                name             TEXT    NOT NULL,
                url              TEXT    NOT NULL,
                consumer_key     TEXT    NOT NULL,
                consumer_secret  TEXT    NOT NULL,
                color            TEXT    NOT NULL DEFAULT '#4f46e5',
                active           INTEGER NOT NULL DEFAULT 1,
                max_orders       INTEGER NOT NULL DEFAULT 50,
                preview_rows     INTEGER NOT NULL DEFAULT 10,
                visible_statuses TEXT    DEFAULT NULL,
                revenue_statuses TEXT    DEFAULT '[\"completed\",\"processing\",\"on-hold\"]',
                created_at       TEXT    NOT NULL DEFAULT (datetime('now')),
                updated_at       TEXT    NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS order_cache (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id     INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
                order_id     INTEGER NOT NULL,
                order_number TEXT    NOT NULL,
                status       TEXT    NOT NULL,
                total        REAL    NOT NULL,
                currency     TEXT    NOT NULL DEFAULT 'CZK',
                customer     TEXT    NOT NULL DEFAULT '',
                date_created TEXT    NOT NULL,
                cached_at    TEXT    NOT NULL DEFAULT (datetime('now')),
                UNIQUE(store_id, order_id)
            );

            CREATE TABLE IF NOT EXISTS revenue_snapshots (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id      INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
                currency      TEXT    NOT NULL,
                total_orders  INTEGER NOT NULL DEFAULT 0,
                gross_sales   REAL    NOT NULL DEFAULT 0,
                net_revenue   REAL    NOT NULL DEFAULT 0,
                refunded      REAL    NOT NULL DEFAULT 0,
                snapshot_date TEXT    NOT NULL,
                computed_at   TEXT    NOT NULL DEFAULT (datetime('now')),
                UNIQUE(store_id, currency, snapshot_date)
            );

            CREATE TABLE IF NOT EXISTS cron_log (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id   INTEGER REFERENCES stores(id) ON DELETE SET NULL,
                type       TEXT    NOT NULL,
                status     TEXT    NOT NULL,
                message    TEXT,
                duration_s REAL,
                ran_at     TEXT    NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS cron_state (
                store_id          INTEGER PRIMARY KEY REFERENCES stores(id) ON DELETE CASCADE,
                last_full_sync    TEXT    DEFAULT NULL,
                last_incremental  TEXT    DEFAULT NULL,
                incremental_from  TEXT    DEFAULT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_order_cache_store  ON order_cache(store_id, date_created DESC);
            CREATE INDEX IF NOT EXISTS idx_revenue_store_date ON revenue_snapshots(store_id, snapshot_date DESC);
        ");

        // Migrace: přidej cron_state pokud chybí (pro existující DB)
        try {
            $this->pdo->exec("INSERT OR IGNORE INTO cron_state (store_id) SELECT id FROM stores");
        } catch (Exception $e) {}
    }

    // ── Obchody ────────────────────────────────────────────────

    public function getStores($onlyActive = false)
    {
        $sql = 'SELECT * FROM stores' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY id';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getStore($id)
    {
        $st = $this->pdo->prepare('SELECT * FROM stores WHERE id = ?');
        $st->execute(array($id));
        $row = $st->fetch();
        return $row ?: null;
    }

    public function saveStore($data, $id = null)
    {
        if ($id) {
            $st = $this->pdo->prepare("
                UPDATE stores SET
                    name=:name, url=:url, consumer_key=:consumer_key,
                    consumer_secret=:consumer_secret, color=:color,
                    active=:active, max_orders=:max_orders, preview_rows=:preview_rows,
                    visible_statuses=:visible_statuses, revenue_statuses=:revenue_statuses,
                    updated_at=datetime('now')
                WHERE id=:id
            ");
            $data['id'] = $id;
            $st->execute($data);
            return $id;
        } else {
            $st = $this->pdo->prepare("
                INSERT INTO stores
                    (name, url, consumer_key, consumer_secret, color,
                     active, max_orders, preview_rows, visible_statuses, revenue_statuses)
                VALUES
                    (:name, :url, :consumer_key, :consumer_secret, :color,
                     :active, :max_orders, :preview_rows, :visible_statuses, :revenue_statuses)
            ");
            $st->execute($data);
            $newId = (int)$this->pdo->lastInsertId();
            // Inicializuj cron_state pro nový obchod
            $this->pdo->prepare("INSERT OR IGNORE INTO cron_state (store_id) VALUES (?)")
                       ->execute(array($newId));
            return $newId;
        }
    }

    public function deleteStore($id)
    {
        $this->pdo->prepare('DELETE FROM stores WHERE id = ?')->execute(array($id));
    }

    // ── Cache objednávek ───────────────────────────────────────

    public function isCacheValid($storeId, $ttlMinutes)
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM order_cache WHERE store_id = ? AND cached_at >= datetime('now', ?)"
        );
        $st->execute(array($storeId, "-{$ttlMinutes} minutes"));
        return (int)$st->fetchColumn() > 0;
    }

    public function upsertOrders($storeId, $orders)
    {
        $st = $this->pdo->prepare("
            INSERT INTO order_cache
                (store_id, order_id, order_number, status, total, currency, customer, date_created, cached_at)
            VALUES
                (:store_id, :order_id, :order_number, :status, :total, :currency, :customer, :date_created, datetime('now'))
            ON CONFLICT(store_id, order_id) DO UPDATE SET
                status=excluded.status, total=excluded.total, customer=excluded.customer,
                cached_at=excluded.cached_at
        ");

        $this->pdo->beginTransaction();
        foreach ($orders as $o) {
            $billing  = isset($o['billing']) ? $o['billing'] : array();
            $fn       = isset($billing['first_name']) ? $billing['first_name'] : '';
            $ln       = isset($billing['last_name'])  ? $billing['last_name']  : '';
            $company  = isset($billing['company'])    ? $billing['company']    : '';
            $customer = trim($fn . ' ' . $ln) ?: $company;
            $st->execute(array(
                'store_id'     => $storeId,
                'order_id'     => (int)$o['id'],
                'order_number' => isset($o['number'])       ? $o['number']       : $o['id'],
                'status'       => isset($o['status'])       ? $o['status']       : '',
                'total'        => (float)(isset($o['total'])? $o['total']        : 0),
                'currency'     => isset($o['currency'])     ? $o['currency']     : 'CZK',
                'customer'     => $customer,
                'date_created' => isset($o['date_created']) ? $o['date_created'] : '',
            ));
        }
        $this->pdo->commit();
    }

    public function getOrdersFromCache($storeId, $visibleStatuses, $limit)
    {
        if (!empty($visibleStatuses)) {
            $placeholders = implode(',', array_fill(0, count($visibleStatuses), '?'));
            $st = $this->pdo->prepare(
                "SELECT * FROM order_cache WHERE store_id = ? AND status IN ($placeholders)
                 ORDER BY date_created DESC LIMIT ?"
            );
            $params = array_merge(array($storeId), $visibleStatuses, array($limit));
            $st->execute($params);
        } else {
            $st = $this->pdo->prepare(
                "SELECT * FROM order_cache WHERE store_id = ? ORDER BY date_created DESC LIMIT ?"
            );
            $st->execute(array($storeId, $limit));
        }
        return $st->fetchAll();
    }

    public function getTotalCachedOrders($storeId)
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM order_cache WHERE store_id = ?');
        $st->execute(array($storeId));
        return (int)$st->fetchColumn();
    }

    public function invalidateCache($storeId)
    {
        $this->pdo->prepare('DELETE FROM order_cache WHERE store_id = ?')->execute(array($storeId));
    }

    // ── Revenue snapshots ──────────────────────────────────────

    public function getLatestSnapshot($storeId)
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM revenue_snapshots WHERE store_id = ?
             ORDER BY snapshot_date DESC, computed_at DESC"
        );
        $st->execute(array($storeId));
        return $st->fetchAll();
    }

    // Vrátí agregovaný součet přes všechny snapshot_date (celková historie)
    public function getSnapshotTotals($storeId)
    {
        $st = $this->pdo->prepare(
            "SELECT currency,
                    MAX(total_orders) as total_orders,
                    MAX(gross_sales)  as gross_sales,
                    MAX(net_revenue)  as net_revenue,
                    MAX(refunded)     as refunded,
                    MAX(computed_at)  as computed_at
             FROM revenue_snapshots
             WHERE store_id = ?
             GROUP BY currency
             ORDER BY currency"
        );
        $st->execute(array($storeId));
        return $st->fetchAll();
    }

    public function saveSnapshot($storeId, $currency, $data, $date)
    {
        $st = $this->pdo->prepare("
            INSERT INTO revenue_snapshots
                (store_id, currency, total_orders, gross_sales, net_revenue, refunded, snapshot_date, computed_at)
            VALUES
                (:store_id, :currency, :total_orders, :gross_sales, :net_revenue, :refunded, :snapshot_date, datetime('now'))
            ON CONFLICT(store_id, currency, snapshot_date) DO UPDATE SET
                total_orders=excluded.total_orders, gross_sales=excluded.gross_sales,
                net_revenue=excluded.net_revenue, refunded=excluded.refunded,
                computed_at=excluded.computed_at
        ");
        $st->execute(array(
            'store_id'      => $storeId,
            'currency'      => $currency,
            'total_orders'  => isset($data['total_orders']) ? $data['total_orders'] : 0,
            'gross_sales'   => isset($data['gross_sales'])  ? $data['gross_sales']  : 0,
            'net_revenue'   => isset($data['net_revenue'])  ? $data['net_revenue']  : 0,
            'refunded'      => isset($data['refunded'])     ? $data['refunded']     : 0,
            'snapshot_date' => $date,
        ));
    }

    public function getSnapshotAge($storeId)
    {
        $st = $this->pdo->prepare(
            "SELECT computed_at FROM revenue_snapshots WHERE store_id = ? ORDER BY computed_at DESC LIMIT 1"
        );
        $st->execute(array($storeId));
        $val = $st->fetchColumn();
        return $val ?: null;
    }

    // ── Cron state (přírůstkový sync) ─────────────────────────

    public function getCronState($storeId)
    {
        $st = $this->pdo->prepare("SELECT * FROM cron_state WHERE store_id = ?");
        $st->execute(array($storeId));
        $row = $st->fetch();
        return $row ?: array('store_id' => $storeId, 'last_full_sync' => null, 'last_incremental' => null, 'incremental_from' => null);
    }

    public function setCronState($storeId, $field, $value)
    {
        $this->pdo->prepare("INSERT OR IGNORE INTO cron_state (store_id) VALUES (?)")->execute(array($storeId));
        $this->pdo->prepare("UPDATE cron_state SET $field = ? WHERE store_id = ?")->execute(array($value, $storeId));
    }

    // ── Cron log ───────────────────────────────────────────────

    public function logCron($storeId, $type, $status, $message = '', $duration = 0)
    {
        $st = $this->pdo->prepare(
            "INSERT INTO cron_log (store_id, type, status, message, duration_s) VALUES (?, ?, ?, ?, ?)"
        );
        $st->execute(array($storeId, $type, $status, $message, $duration));
    }

    public function getCronLog($limit = 20)
    {
        return $this->pdo->query(
            "SELECT l.*, s.name as store_name FROM cron_log l
             LEFT JOIN stores s ON s.id = l.store_id
             ORDER BY l.ran_at DESC LIMIT $limit"
        )->fetchAll();
    }

    // Vrátí objednávky z cache novější než fromDate (pro overlap přepočet)
    public function getCronStatePdo($storeId, $fromDate)
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM order_cache WHERE store_id = ? AND date_created >= ? ORDER BY date_created ASC"
        );
        $st->execute(array($storeId, $fromDate));
        return $st->fetchAll();
    }
}
