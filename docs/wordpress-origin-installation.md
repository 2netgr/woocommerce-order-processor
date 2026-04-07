# WooDashboard in a standard WordPress installation

This document explains what the current repository does and how to implement the same concept as a native WordPress / WooCommerce plugin.

## 1. What this repository does today

The current project is a **standalone PHP dashboard** that connects to **multiple external WooCommerce stores** through the WooCommerce REST API.

Core flow:

1. A store is added in the admin UI with:
   - store name
   - store URL
   - consumer key
   - consumer secret
2. The app stores that configuration locally.
3. A cron job fetches orders from every active store.
4. The app caches recent orders locally and stores aggregated revenue snapshots.
5. The dashboard shows:
   - total orders
   - total revenue
   - per-store stats
   - order lists and order detail
   - filters by WooCommerce order status

## 2. How the standalone version is structured

The current implementation is intentionally compact:

- `settings.php` manages connected stores
- `index.php` renders the dashboard
- `api.php` serves AJAX actions such as order detail and refresh
- `cron.php` performs full and incremental synchronization
- `src/WooApi.php` talks to WooCommerce REST endpoints
- `src/Database.php` stores shops, cached orders, and revenue snapshots
- `src/Auth.php` protects the dashboard login

Important implementation details:

- stores are configured from the UI, not from source code
- sync is incremental after the first full load
- the sync re-checks the last 30 days to catch status changes
- revenue is grouped by currency
- order data is cached locally so the dashboard stays fast

## 3. Best way to build the same concept in WordPress

The cleanest approach is to build a **custom WordPress plugin** that acts as a central aggregation hub.

That plugin should:

- run on one “main” WordPress installation
- connect to many external WooCommerce stores with REST API credentials
- fetch and cache their data locally
- show one unified admin dashboard inside WordPress

## 4. Recommended plugin architecture

### 4.1 Plugin responsibilities

Create a plugin with four main responsibilities:

1. **Store registry**
   - save all connected stores
   - validate API credentials
   - allow enabling/disabling a store

2. **Sync engine**
   - perform first full import
   - run incremental sync on schedule
   - refresh a rolling overlap window to capture status changes

3. **Local reporting layer**
   - store normalized order data locally
   - compute revenue snapshots
   - support fast filtering and summary widgets

4. **Admin application**
   - dashboard page
   - store management page
   - manual sync actions
   - order drill-down

### 4.2 WordPress equivalents for the current standalone parts

| Standalone app | WordPress equivalent |
| --- | --- |
| `config.php` | plugin settings + constants only when needed |
| custom login in `src/Auth.php` | WordPress users, roles, capabilities, nonces |
| SQLite in `src/Database.php` | custom MySQL tables via `$wpdb` / `dbDelta()` |
| `cron.php` | `wp_schedule_event()` + custom action hooks |
| `index.php` dashboard | admin page under WooCommerce or Tools menu |
| `settings.php` | WordPress admin settings page |
| `api.php` | `admin-ajax.php` or custom REST API routes |

## 5. Data model for the WordPress plugin

The plugin should keep its own tables rather than forcing everything into `wp_options`.

Recommended tables:

In the examples below, `wdp` means **WooDashboard Plugin**.  
If you prefer, you can rename the prefix to something more explicit such as `woodashboard`.

### `wp_wdp_stores`

One row per connected store:

- `id`
- `name`
- `base_url`
- `consumer_key`
- `consumer_secret`
- `is_active`
- `color`
- `max_orders`
- `visible_statuses`
- `revenue_statuses`
- `last_full_sync_at`
- `last_incremental_sync_at`

### `wp_wdp_orders`

Cached normalized orders from all stores:

- `id`
- `store_id`
- `remote_order_id`
- `remote_order_number`
- `status`
- `currency`
- `total`
- `customer_name`
- `date_created_gmt`
- `date_modified_gmt`
- `raw_payload` (optional JSON if you want richer drill-down later)
- unique key on `(store_id, remote_order_id)`

### `wp_wdp_revenue_snapshots`

Precomputed totals for faster dashboard widgets:

- `id`
- `store_id`
- `snapshot_date`
- `currency`
- `total_orders`
- `gross_sales`
- `net_revenue`
- `refunded_total`

### `wp_wdp_sync_log`

For observability:

- `id`
- `store_id`
- `sync_type`
- `status`
- `message`
- `started_at`
- `finished_at`

## 6. Sync strategy

Use the same concept as this repository because it is already a good fit for multi-store WooCommerce aggregation:

### First sync

- fetch all orders page by page from each store
- save normalized rows locally
- create initial revenue snapshots

### Incremental sync

- run every 5 to 15 minutes, depending on scale
- fetch only orders after the last sync checkpoint
- also re-fetch a safety overlap window, for example the last 30 days
- upsert changed orders and recompute affected aggregates

### Why this matters

WooCommerce order statuses can change after the order was first created.  
If you only fetch “new” orders, revenue totals become inaccurate.  
The overlap-window pattern used in this repository is the right idea to keep.

## 7. WordPress admin UX

Suggested admin pages:

### Dashboard

Show:

- total revenue by currency
- total orders by status
- recent orders across all stores
- per-store performance cards
- sync health / last sync times

### Stores

Allow admins to:

- add a new remote WooCommerce store
- test credentials before saving
- pause/resume syncing
- define which statuses count toward revenue
- define which statuses are visible in previews

### Order detail

Allow drill-down into:

- customer info
- line items
- totals
- payment method
- store origin

## 8. Recommended WordPress implementation details

### Authentication and authorization

Use native WordPress permissions:

- `manage_woocommerce` or `manage_options` for administrators
- nonces for form submissions and manual sync actions
- no separate password screen

### Scheduling

Use:

- `register_activation_hook()` to schedule jobs
- `wp_schedule_event()` for recurring sync
- optional Action Scheduler if you expect heavy workloads

For larger installations, **Action Scheduler** is the better choice because it handles queued background work more reliably than plain WP-Cron.  
If WooCommerce is active on the central site, Action Scheduler is already available there; otherwise you should include it deliberately as a plugin dependency before designing the sync flow around it.

### HTTP layer

The current `src/WooApi.php` is a strong base conceptually.  
Inside WordPress, prefer `wp_remote_get()` over raw cURL so the plugin follows WordPress HTTP conventions.

### Storage

Use custom tables instead of post types for orders and snapshots because:

- sync volume can be high
- reporting queries need indexes
- the data is integration/cache data, not editorial content

### Security

Protect secrets carefully:

- if you encrypt stored API secrets, use a real crypto library such as `sodium_crypto_secretbox()` and keep the encryption key outside the database and repository, preferably in an environment variable or external secrets manager
- mask secrets in the UI
- never expose remote credentials in AJAX or REST responses
- sanitize all store URLs and status arrays before saving

## 9. Suggested plugin structure

```text
woo-dashboard/
├── woo-dashboard.php
├── includes/
│   ├── class-plugin.php
│   ├── class-store-repository.php
│   ├── class-order-repository.php
│   ├── class-sync-service.php
│   ├── class-woo-client.php
│   ├── class-admin-pages.php
│   └── class-rest-controller.php
├── assets/
│   ├── admin.css
│   └── admin.js
├── templates/
│   ├── dashboard.php
│   ├── stores.php
│   └── order-detail.php
└── languages/
```

## 10. Practical rollout plan

The smallest-risk implementation plan is:

1. build the plugin shell and custom tables
2. implement store CRUD and credential test
3. implement a reusable WooCommerce client
4. implement full sync for one store
5. add incremental sync with overlap window
6. add dashboard widgets and order list
7. add manual re-sync controls and sync logs
8. optimize with batching, indexes, and background jobs

## 11. Key conclusion

Yes, this can absolutely be built inside a normal WordPress installation.

The best design is **not** to turn every connected store into a multisite or direct database integration.  
Instead, keep the same successful concept from this repository:

- one central installation
- many remote WooCommerce stores
- REST API connections per store
- local cached reporting tables
- scheduled incremental synchronization

That gives you a native WordPress admin experience while preserving the most useful part of the current project: **one dashboard for many WooCommerce stores**.
