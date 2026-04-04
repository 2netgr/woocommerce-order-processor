<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Lang.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/WooApi.php';

Auth::requireAuth();

// Přepnutí jazyka
if (isset($_GET['setlang'])) {
    Lang::setLang($_GET['setlang']);
    Helpers::redirect('settings.php');
}

$db = Database::get();
$flash = ''; $flashType = 'ok';
$tab  = Helpers::get('tab', 'stores');

if (Helpers::isPost()) {
    Auth::verifyCsrf();
    $formAction = Helpers::post('action');

    if ($formAction === 'save_store') {
        $id = (int)Helpers::post('id') ?: null;
        $visibleStatuses = Helpers::post('visible_statuses', array());
        $revenueStatuses = Helpers::post('revenue_statuses', array());
        $data = array(
            'name'            => trim(Helpers::post('name')),
            'url'             => rtrim(trim(Helpers::post('url')), '/'),
            'consumer_key'    => trim(Helpers::post('consumer_key')),
            'consumer_secret' => trim(Helpers::post('consumer_secret')),
            'color'           => Helpers::post('color', '#4f46e5'),
            'active'          => Helpers::post('active', 0) ? 1 : 0,
            'max_orders'      => max(10, min(500, (int)Helpers::post('max_orders', 50))),
            'preview_rows'    => max(5,  min(100, (int)Helpers::post('preview_rows', 10))),
            'visible_statuses'=> empty($visibleStatuses) ? null : json_encode(array_values($visibleStatuses)),
            'revenue_statuses'=> json_encode(array_values($revenueStatuses) ?: array('completed', 'processing')),
        );
        if (!$data['name'] || !$data['url'] || !$data['consumer_key'] || !$data['consumer_secret']) {
            $flash = t('required_fields'); $flashType = 'error';
        } else {
            $db->saveStore($data, $id);
            $flash = $id ? t('store_saved') : t('store_added');
        }
    }
    if ($formAction === 'delete_store') {
        $db->deleteStore((int)Helpers::post('id'));
        $flash = t('store_deleted');
    }
    if ($formAction === 'toggle_store') {
        $id = (int)Helpers::post('id');
        $store = $db->getStore($id);
        if ($store) { $store['active'] = $store['active'] ? 0 : 1; $db->saveStore($store, $id); }
        Helpers::redirect('settings.php?tab=stores');
    }
}

$stores      = $db->getStores();
$cronLog     = $db->getCronLog(30);
$editId      = (int)Helpers::get('edit');
$editing     = $editId ? $db->getStore($editId) : null;
$allStatuses = Helpers::allStatuses();
$pageTitle   = t('settings_title') . ' — ' . APP_NAME;
require __DIR__ . '/views/layout_top.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= t('settings_title') ?></h1>
    <p class="page-sub"><?= t('settings_subtitle') ?></p>
  </div>
  <a href="index.php" class="btn btn--secondary"><?= t('settings_back') ?></a>
</div>

<?php if ($flash): ?>
<div class="alert alert--<?= $flashType === 'error' ? 'error' : 'ok' ?>"><?= Helpers::e($flash) ?></div>
<?php endif; ?>

<div class="tabs">
  <a href="?tab=stores" class="tab <?= $tab === 'stores' ? 'tab--active' : '' ?>"><?= t('settings_tab_stores') ?></a>
  <a href="?tab=add"    class="tab <?= $tab === 'add'    ? 'tab--active' : '' ?>"><?= $editing ? t('settings_tab_edit') : t('settings_tab_add') ?></a>
  <a href="?tab=cron"   class="tab <?= $tab === 'cron'   ? 'tab--active' : '' ?>"><?= t('settings_tab_cron') ?></a>
</div>

<?php if ($tab === 'stores'): ?>
<div class="card">
  <?php if (empty($stores)): ?>
    <div class="empty-state"><div class="empty-icon">🛒</div><p><?= t('store_empty') ?> <a href="?tab=add"><?= t('add') ?> →</a></p></div>
  <?php else: ?>
  <table class="settings-table">
    <thead><tr>
      <th><?= t('store_col_name') ?></th><th><?= t('store_col_url') ?></th>
      <th><?= t('store_col_max') ?></th><th><?= t('store_col_status') ?></th><th><?= t('store_col_actions') ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($stores as $s): ?>
      <tr>
        <td><span class="store-dot-sm" style="background:<?= Helpers::e($s['color']) ?>"></span><strong><?= Helpers::e($s['name']) ?></strong></td>
        <td><span class="mono muted" style="font-size:.8rem"><?= Helpers::e($s['url']) ?></span></td>
        <td><span class="mono"><?= (int)$s['max_orders'] ?></span></td>
        <td><?= $s['active'] ? '<span class="badge badge--green">' . t('active') . '</span>' : '<span class="badge badge--gray">' . t('inactive') . '</span>' ?></td>
        <td class="actions">
          <a href="?tab=add&edit=<?= $s['id'] ?>" class="btn btn--xs"><?= t('edit') ?></a>
          <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
            <input type="hidden" name="action" value="toggle_store">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn--xs btn--secondary"><?= $s['active'] ? t('deactivate') : t('activate') ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
            <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
            <input type="hidden" name="action" value="delete_store">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn--xs btn--danger"><?= t('delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'add'): ?>
<div class="card">
  <h2 class="card-title"><?= $editing ? t('store_edit_title') : t('store_add_title') ?></h2>
  <form method="post" class="store-form">
    <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
    <input type="hidden" name="action" value="save_store">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group form-group--wide">
        <label><?= t('store_name') ?> *</label>
        <input type="text" name="name" required placeholder="<?= t('store_name_ph') ?>"
               value="<?= Helpers::e(isset($editing['name']) ? $editing['name'] : '') ?>">
      </div>
      <div class="form-group form-group--wide">
        <label><?= t('store_url') ?> *</label>
        <input type="url" name="url" required placeholder="<?= t('store_url_ph') ?>"
               value="<?= Helpers::e(isset($editing['url']) ? $editing['url'] : '') ?>">
        <span class="form-hint"><?= t('store_url_hint') ?></span>
      </div>
      <div class="form-group">
        <label><?= t('store_ck') ?> *</label>
        <input type="text" name="consumer_key" required placeholder="ck_..."
               value="<?= Helpers::e(isset($editing['consumer_key']) ? $editing['consumer_key'] : '') ?>">
      </div>
      <div class="form-group">
        <label><?= t('store_cs') ?> *</label>
        <input type="password" name="consumer_secret" required placeholder="cs_..."
               value="<?= Helpers::e(isset($editing['consumer_secret']) ? $editing['consumer_secret'] : '') ?>">
        <span class="form-hint"><?= t('store_cs_hint') ?></span>
      </div>
      <div class="form-group">
        <label><?= t('store_color') ?></label>
        <div class="color-picker-wrap">
          <input type="color" name="color" value="<?= Helpers::e(isset($editing['color']) ? $editing['color'] : '#4f46e5') ?>">
          <span class="form-hint"><?= t('store_color_hint') ?></span>
        </div>
      </div>
      <div class="form-group">
        <label><?= t('store_active') ?></label>
        <label class="toggle">
          <input type="checkbox" name="active" value="1"
                 <?= (isset($editing['active']) ? $editing['active'] : 1) ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
          <span class="toggle-label"><?= t('store_active_hint') ?></span>
        </label>
      </div>
      <div class="form-group">
        <label><?= t('store_max_orders') ?></label>
        <input type="number" name="max_orders" min="10" max="500" step="10"
               value="<?= (int)(isset($editing['max_orders']) ? $editing['max_orders'] : 50) ?>">
        <span class="form-hint"><?= t('store_max_hint') ?></span>
      </div>
      <div class="form-group">
        <label><?= t('store_preview') ?></label>
        <input type="number" name="preview_rows" min="5" max="100" step="5"
               value="<?= (int)(isset($editing['preview_rows']) ? $editing['preview_rows'] : 10) ?>">
        <span class="form-hint"><?= t('store_preview_hint') ?></span>
      </div>
    </div>
    <div class="form-section">
      <h3><?= t('store_visible_title') ?></h3>
      <p class="form-hint"><?= t('store_visible_hint') ?></p>
      <div class="status-checkboxes">
        <?php
        $visibleStatuses = json_decode(isset($editing['visible_statuses']) ? $editing['visible_statuses'] : 'null', true);
        foreach ($allStatuses as $st):
          $slArr = Helpers::statusLabel($st); $slLabel=$slArr[0]; $slColor=$slArr[1]; $slBg=$slArr[2];
          $checked = ($visibleStatuses === null || in_array($st, $visibleStatuses));
        ?>
        <label class="status-check" style="--sc:<?= $slColor ?>;--sbg:<?= $slBg ?>">
          <input type="checkbox" name="visible_statuses[]" value="<?= $st ?>" <?= $checked ? 'checked' : '' ?>>
          <span class="status-check-dot"></span><?= Helpers::e($slLabel) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-section">
      <h3><?= t('store_revenue_title') ?></h3>
      <div class="status-checkboxes">
        <?php
        $revenueStatuses = json_decode(isset($editing['revenue_statuses']) ? $editing['revenue_statuses'] : '["completed","processing","on-hold"]', true);
        foreach ($allStatuses as $st):
          $slArr = Helpers::statusLabel($st); $slLabel=$slArr[0]; $slColor=$slArr[1]; $slBg=$slArr[2];
          $checked = in_array($st, $revenueStatuses);
        ?>
        <label class="status-check" style="--sc:<?= $slColor ?>;--sbg:<?= $slBg ?>">
          <input type="checkbox" name="revenue_statuses[]" value="<?= $st ?>" <?= $checked ? 'checked' : '' ?>>
          <span class="status-check-dot"></span><?= Helpers::e($slLabel) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn--primary"><?= $editing ? t('store_save') : t('store_add_btn') ?></button>
      <?php if ($editing): ?>
      <a href="?tab=stores" class="btn btn--secondary"><?= t('cancel') ?></a>
      <button type="button" class="btn btn--secondary" onclick="testConn(<?= $editing['id'] ?>)"><?= t('store_test') ?></button>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php elseif ($tab === 'cron'): ?>
<div class="card">
  <h2 class="card-title"><?= t('cron_title') ?></h2>
  <div class="cron-setup">
    <h3><?= t('cron_setup_title') ?></h3>
    <p><?= t('cron_setup_desc') ?></p>
    <pre class="code-block">0 2 * * * /usr/bin/php <?= Helpers::e(realpath(__DIR__) ?: __DIR__) ?>/cron.php >> /var/log/woo-cron.log 2>&1</pre>
    <p class="form-hint"><?= t('cron_setup_hint') ?></p>
    <?php if (CRON_SECRET !== ''): ?>
    <p><?= t('cron_http_url') ?> <code><?= Auth::baseUrl() ?>/cron.php?token=<?= Helpers::e(CRON_SECRET) ?></code></p>
    <?php endif; ?>
  </div>
  <?php if (empty($cronLog)): ?>
    <div class="empty-state"><div class="empty-icon">📋</div><p><?= t('cron_empty') ?></p></div>
  <?php else: ?>
  <table class="settings-table">
    <thead><tr>
      <th><?= t('cron_col_time') ?></th><th><?= t('cron_col_store') ?></th>
      <th><?= t('cron_col_type') ?></th><th><?= t('cron_col_status') ?></th>
      <th><?= t('cron_col_message') ?></th><th><?= t('cron_col_duration') ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($cronLog as $log): ?>
      <tr>
        <td><span class="mono" style="font-size:.75rem"><?= Helpers::e($log['ran_at']) ?></span></td>
        <td><?= Helpers::e(isset($log['store_name']) ? $log['store_name'] : '—') ?></td>
        <td><span class="badge badge--gray"><?= Helpers::e($log['type']) ?></span></td>
        <td><span class="badge <?= $log['status'] === 'ok' ? 'badge--green' : 'badge--red' ?>"><?= Helpers::e($log['status']) ?></span></td>
        <td style="font-size:.78rem;max-width:280px"><?= Helpers::e(isset($log['message']) ? $log['message'] : '') ?></td>
        <td><span class="mono"><?= $log['duration_s'] ? round((float)$log['duration_s'], 1) . 's' : '—' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<div id="toast" class="toast" style="display:none"></div>
<script>
const CSRF = <?= json_encode(Auth::csrfToken()) ?>;
const I18N = { toast_testing: <?= json_encode(t('toast_testing')) ?>, store_test_ok: <?= json_encode(t('store_test_ok')) ?> };
async function testConn(storeId) {
  showToast(I18N.toast_testing, 'info');
  const r = await fetch('api.php?action=test_connection&store_id=' + storeId, { headers: {'X-CSRF-Token': CSRF} });
  const d = await r.json();
  showToast(d.ok ? I18N.store_test_ok : '✗ ' + d.message, d.ok ? 'ok' : 'error');
}
function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = 'toast toast--' + type; t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 4000);
}
</script>
<?php require __DIR__ . '/views/layout_bottom.php'; ?>
