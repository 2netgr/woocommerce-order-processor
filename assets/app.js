// ── Lang dropdown — zavři při kliknutí mimo ────────────────────
document.addEventListener('click', function(e) {
  if (!e.target.closest('.lang-dropdown')) {
    document.querySelectorAll('.lang-dropdown--open').forEach(function(el) {
      el.classList.remove('lang-dropdown--open');
    });
  }
});

// ── Status filter (client-side, bez reloadu) ──────────────────
// selectedStatuses = prázdné pole znamená "vše"
let selectedStatuses = [];

function applyStatusFilter() {
  // Aktualizuj vzhled tlačítek
  document.querySelectorAll('.filter-btn[data-status]').forEach(btn => {
    const on = selectedStatuses.length === 0 || selectedStatuses.includes(btn.dataset.status);
    btn.classList.toggle('filter-btn--active', on);
  });
  const allBtn = document.querySelector('.filter-btn--all');
  if (allBtn) allBtn.classList.toggle('filter-btn--active-all', selectedStatuses.length === 0);

  // Skryj/zobraz řádky objednávek
  document.querySelectorAll('tbody tr.order-row').forEach(row => {
    const statusBadge = row.querySelector('.status-badge');
    if (!statusBadge) return;
    // Přečteme stav z data atributu nebo z třídy detailu
    const detailId = 'detail-' + row.dataset.store + '-' + row.dataset.id;
    const inner = document.getElementById(detailId);
    // Stav je uložen jako data-status na řádku (přidáme ho při renderování)
    const st = row.dataset.status;
    const show = selectedStatuses.length === 0 || selectedStatuses.includes(st);
    row.style.display = show ? '' : 'none';
    // Skryj i detail row
    const detailRow = row.nextElementSibling;
    if (detailRow && detailRow.classList.contains('detail-row')) {
      if (!show) {
        detailRow.style.display = 'none';
        // Zavři otevřený detail
        const innerEl = detailRow.querySelector('.detail-inner');
        if (innerEl) innerEl.classList.remove('open');
        row.classList.remove('row-open');
      } else {
        detailRow.style.display = '';
      }
    }
  });

  // Aktualizuj "zobrazeno" počítadla a show-more tlačítka
  document.querySelectorAll('.store-section').forEach(section => {
    const visibleRows = section.querySelectorAll('tbody tr.order-row:not([style*="display: none"])');
    const isEmpty = visibleRows.length === 0;
    let emptyMsg = section.querySelector('.empty-orders');
    if (!emptyMsg) {
      emptyMsg = document.createElement('div');
      emptyMsg.className = 'empty-orders empty-orders--filter';
      emptyMsg.textContent = 'Žádné objednávky pro zvolené stavy';
      const wrap = section.querySelector('.orders-wrap');
      if (wrap) wrap.appendChild(emptyMsg);
    }
    emptyMsg.style.display = isEmpty ? '' : 'none';
  });
}

function toggleStatus(st) {
  if (st === '__all__') {
    selectedStatuses = [];
  } else {
    const idx = selectedStatuses.indexOf(st);
    if (idx >= 0) {
      selectedStatuses.splice(idx, 1);
    } else {
      selectedStatuses.push(st);
    }
    // Pokud jsou vybrány všechny → jako vše
    const allBtns = document.querySelectorAll('.filter-btn[data-status]');
    if (selectedStatuses.length === allBtns.length) selectedStatuses = [];
  }
  applyStatusFilter();
}


// ── Toast ──────────────────────────────────────────────────────
function showToast(msg, type = 'ok', duration = 4000) {
  let t = document.getElementById('toast');
  if (!t) { t = document.createElement('div'); t.id = 'toast'; document.body.appendChild(t); }
  t.textContent = msg;
  t.className = `toast toast--${type}`;
  t.style.display = 'block';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.style.display = 'none'; }, duration);
}

// ── Format helpers ─────────────────────────────────────────────
function formatPrice(total, currency) {
  const syms = { CZK: 'Kč', EUR: '€', USD: '$', GBP: '£', PLN: 'zł' };
  const sym  = syms[currency] || currency;
  const f    = parseFloat(total).toLocaleString('cs-CZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return ['EUR','USD','GBP'].includes(currency) ? `${sym} ${f}` : `${f} ${sym}`;
}

// ── Detail objednávky ──────────────────────────────────────────
function renderDetail(container, data) {
  if (data.error) {
    container.innerHTML = `<p style="color:#ef4444;font-size:.82rem;padding:12px 0">Chyba: ${data.error}</p>`;
    return;
  }
  const items    = data.line_items || [];
  const currency = data.currency   || 'CZK';
  const billing  = data.billing    || {};
  const shipping = data.shipping   || {};

  let html = `
    <div class="detail-meta">
      <span>${I18N.order_detail_customer}: <strong>${billing.first_name || ''} ${billing.last_name || ''}</strong></span>
      ${billing.email ? `<span>${I18N.order_detail_email}: <strong>${billing.email}</strong></span>` : ''}
      ${billing.phone ? `<span>${I18N.order_detail_phone}: <strong>${billing.phone}</strong></span>` : ''}
      ${data.payment_method_title ? `<span>${I18N.order_detail_payment}: <strong>${data.payment_method_title}</strong></span>` : ''}
    </div>
    <table class="items-table">
      <thead><tr><th>${I18N.order_items_product}</th><th>${I18N.order_items_qty}</th><th>${I18N.order_items_unit}</th><th>${I18N.order_items_total}</th></tr></thead>
      <tbody>`;

  items.forEach(item => {
    const qty   = item.quantity || 1;
    const total = parseFloat(item.total || 0);
    const unit  = qty > 0 ? total / qty : 0;
    html += `<tr>
      <td>${item.name || '—'}</td>
      <td class="item-qty">${qty}×</td>
      <td class="item-price">${formatPrice(unit.toFixed(2), currency)}</td>
      <td class="item-price">${formatPrice(total.toFixed(2), currency)}</td>
    </tr>`;
  });

  if (!items.length) html += `<tr><td colspan="4" style="color:#8589a3">${I18N.order_items_empty}</td></tr>`;
  html += '</tbody></table>';

  if (data.customer_note) {
    html += `<div class="detail-note">📝 ${I18N.order_detail_note}: ${data.customer_note}</div>`;
  }

  container.innerHTML = html;
}

function attachOrderRow(row) {
  if (row._attached) return;
  row._attached = true;
  row.addEventListener('click', async () => {
    const storeId  = row.dataset.store;
    const orderId  = row.dataset.id;
    const inner    = document.getElementById(`detail-${storeId}-${orderId}`);
    if (!inner) return;

    const isOpen = inner.classList.contains('open');

    // Zavři ostatní v téže tabulce
    row.closest('table').querySelectorAll('.detail-inner.open').forEach(el => el.classList.remove('open'));
    row.closest('table').querySelectorAll('.order-row.row-open').forEach(el => el.classList.remove('row-open'));

    if (isOpen) return;

    inner.classList.add('open');
    row.classList.add('row-open');

    if (inner._loaded) return;
    inner._loaded = true;

    try {
      const res  = await fetch(`api.php?action=order_detail&store_id=${storeId}&order_id=${orderId}`);
      const data = await res.json();
      renderDetail(inner, data);
    } catch {
      inner.innerHTML = `<p style="color:#ef4444;font-size:.82rem;padding:12px 0">${I18N.order_error}</p>`;
    }
  });
}

// ── Show more ──────────────────────────────────────────────────
function showMore(storeId, btn) {
  const hiddenTbody = document.getElementById(`hidden-${storeId}`);
  const wrap        = document.getElementById(`more-wrap-${storeId}`);
  if (!hiddenTbody || !wrap) return;

  hiddenTbody.style.display = '';
  wrap.style.display = 'none';

  // Attach handlery na nově odkryté řádky
  hiddenTbody.querySelectorAll('.order-row').forEach(attachOrderRow);
}

// ── Refresh ────────────────────────────────────────────────────
async function refreshStore(storeId, btn) {
  const orig = btn.textContent;
  btn.textContent = '…';
  btn.disabled = true;

  try {
    const res  = await fetch(`api.php?action=refresh_store&store_id=${storeId}`, {
      headers: { 'X-CSRF-Token': CSRF }
    });
    const data = await res.json();
    if (data.ok) {
      showToast(I18N.toast_refreshed.replace('%d', data.count), 'ok');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast('✗ ' + (data.error || 'Chyba'), 'error');
    }
  } catch {
    showToast(I18N.toast_net_error, 'error');
  } finally {
    btn.textContent = orig;
    btn.disabled = false;
  }
}

async function refreshAll() {
  const btn = document.getElementById('btn-refresh-all');
  if (btn) { btn.textContent = '…'; btn.disabled = true; }
  showToast(I18N.toast_refreshing, 'info', 10000);

  try {
    const res  = await fetch('api.php?action=refresh_all', {
      headers: { 'X-CSRF-Token': CSRF }
    });
    const data = await res.json();
    const errors = (data.results || []).filter(r => r.error);
    if (errors.length) {
      showToast(I18N.toast_refresh_err.replace('%s', errors.map(r => r.store).join(', ')), 'error');
    } else {
      showToast(I18N.toast_refresh_ok, 'ok');
      setTimeout(() => location.reload(), 800);
    }
  } catch {
    showToast(I18N.toast_net_error, 'error');
  } finally {
    if (btn) { btn.textContent = I18N.toast_refresh_ok ? '↺' : '↺'; btn.disabled = false; }
  }
}

// ── Init ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Attach click na všechny viditelné order-row
  document.querySelectorAll('.order-row').forEach(attachOrderRow);

  // Tlačítko refresh all v nav
  const btnRefreshAll = document.getElementById('btn-refresh-all');
  if (btnRefreshAll) btnRefreshAll.addEventListener('click', refreshAll);

  // Inicializuj filtr — zobraz vše (žádný stav nevybrán = vše)
  applyStatusFilter();
});
