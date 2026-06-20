// Storefront JS entry — Alpine.js only. Admin/POS Vue bundle is untouched.
//
// Compiled by Laravel Mix to public/js/storefront.min.js and loaded from
// layouts/store.blade.php. Replaces Bootstrap 5 JS (modals, offcanvas, tabs,
// dropdowns) with Alpine equivalents.
//
// Named "storefront" (not "store") to avoid collision with the admin Vuex
// module at resources/src/store/ which is imported as `./store`.

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

/* ----------------------------------------------------------------------------
 * Theme controller — dark-first with user toggle, persisted to localStorage.
 * Applied before Alpine starts so there's no flash of the wrong theme.
 * -------------------------------------------------------------------------- */
(function initTheme() {
  const KEY  = 'store.theme';
  const root = document.documentElement;
  const stored = localStorage.getItem(KEY);
  const mode = stored || 'dark';
  root.classList.toggle('dark', mode === 'dark');
})();

window.StoreTheme = {
  get() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
  },
  set(mode) {
    document.documentElement.classList.toggle('dark', mode === 'dark');
    try { localStorage.setItem('store.theme', mode); } catch (_) {}
    window.dispatchEvent(new CustomEvent('store:theme-changed', { detail: mode }));
  },
  toggle() {
    this.set(this.get() === 'dark' ? 'light' : 'dark');
  },
};

/* ----------------------------------------------------------------------------
 * StoreUI — imperative open/close for Alpine dialogs + drawers. Lets
 * non-Alpine callers (CartLS, global click handlers, inline onclick) pop UI.
 * Any element using x-data="dialog()" / x-data="drawer()" is discoverable
 * by id; dispatching "open"/"close" toggles it.
 * -------------------------------------------------------------------------- */
window.StoreUI = {
  open(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.dispatchEvent(new CustomEvent('ui:open'));
  },
  close(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.dispatchEvent(new CustomEvent('ui:close'));
  },
  toggle(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.dispatchEvent(new CustomEvent('ui:toggle'));
  },
};

/* ----------------------------------------------------------------------------
 * Currency + formatting helpers
 * -------------------------------------------------------------------------- */
function currencySymbol() {
  const m = document.querySelector('meta[name="currency"]');
  return (m && m.content) ? m.content : '$';
}
function fmtMoney(v, sym) {
  const s = sym || currencySymbol();
  return s + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
window.fmtMoney = fmtMoney;

/* ----------------------------------------------------------------------------
 * Cart — localStorage-backed. Key "shop.cart.v1" — must match legacy writes.
 * Emits `cart:changed` and `cart:add-item` on window.
 * -------------------------------------------------------------------------- */
(function initCart() {
  if (window.CartLS) return;
  const KEY = 'shop.cart.v1';

  function load() {
    try {
      const c = JSON.parse(localStorage.getItem(KEY) || '{}');
      if (Array.isArray(c.items)) return calc(c);
    } catch (_) {}
    return calc({ items: [], currency: currencySymbol() });
  }
  function save(c) {
    localStorage.setItem(KEY, JSON.stringify(c));
    window.dispatchEvent(new CustomEvent('cart:changed', { detail: c }));
  }
  function calc(c) {
    c.subtotal = c.items.reduce((a, i) => a + (Number(i.price) || 0) * (Number(i.qty) || 0), 0);
    c.grand = c.subtotal;
    return c;
  }
  function idx(c, id) {
    return c.items.findIndex((i) => String(i.id) === String(id));
  }

  window.CartLS = {
    get: load,
    add(item, qty) {
      qty = qty == null ? 1 : qty;
      const allowOverselling = window.__ALLOW_OVERSELLING__ !== false;
      const c = load();
      const i = idx(c, item.id);
      const stock = (item && (typeof item.stock === 'number' || (typeof item.stock !== 'undefined' && item.stock !== null)))
        ? Number(item.stock) : null;
      const requested = Number(qty) || 1;

      if (i > -1) {
        const existingQty = c.items[i].qty || 0;
        let newQty = existingQty + requested;
        if (!allowOverselling && stock != null) {
          const maxQty = Math.max(0, stock);
          newQty = Math.min(newQty, maxQty);
          if (newQty <= 0) {
            if (window.showStockAlert && window.__MSG_ALREADY_MAX__) {
              window.showStockAlert(String(window.__MSG_ALREADY_MAX__).replace('%s', maxQty));
            }
            return c;
          }
          if (newQty < existingQty + requested && window.showStockAlert && window.__MSG_MAX_ADDED__) {
            const added = newQty - existingQty;
            window.showStockAlert(String(window.__MSG_MAX_ADDED__).replace('%s', maxQty).replace('%s', added));
          }
        }
        c.items[i].qty = newQty;
        if (stock != null && c.items[i].stock === undefined) c.items[i].stock = stock;
      } else {
        let addQty = Math.max(1, requested);
        if (!allowOverselling && stock != null) {
          const maxStock = Math.max(1, stock);
          addQty = Math.min(addQty, maxStock);
          if (addQty < requested && window.showStockAlert && window.__MSG_MAX_ADDED__) {
            window.showStockAlert(String(window.__MSG_MAX_ADDED__).replace('%s', maxStock).replace('%s', addQty));
          }
        }
        const row = {
          id: String(item.id),
          name: item.name || '',
          price: Number(item.price) || 0,
          qty: addQty,
          image: item.image || '',
          slug: item.slug || '',
          currency: item.currency || c.currency,
        };
        if (stock != null) row.stock = stock;
        c.items.push(row);
      }
      save(calc(c));
      window.dispatchEvent(new CustomEvent('cart:add-item'));
      return c;
    },
    setQty(id, q) {
      const c = load();
      const i = idx(c, id);
      if (i < 0) return c;
      const allowOverselling = window.__ALLOW_OVERSELLING__ !== false;
      let requested = Math.max(1, Number(q) || 1);
      if (!allowOverselling && c.items[i].stock != null) {
        const maxStock = Math.max(0, Number(c.items[i].stock));
        if (requested > maxStock && window.showStockAlert && window.__MSG_ONLY_X_STOCK__) {
          window.showStockAlert(String(window.__MSG_ONLY_X_STOCK__).replace('%s', maxStock));
        }
        requested = Math.min(requested, maxStock);
      }
      c.items[i].qty = requested;
      save(calc(c));
      return c;
    },
    remove(id) {
      const c = load();
      c.items = c.items.filter((i) => String(i.id) !== String(id));
      save(calc(c));
      return c;
    },
    clear() {
      const base = load();
      const c = { items: [], currency: base.currency, subtotal: 0, grand: 0 };
      save(c);
      return c;
    },
  };
})();

/* ----------------------------------------------------------------------------
 * Toast helper — populates #store-stock-toast (defined in layout).
 * -------------------------------------------------------------------------- */
window.showStockAlert = function (msg) {
  const el = document.getElementById('store-stock-toast');
  if (!el) return;
  el.textContent = msg;
  el.classList.remove('hidden');
  clearTimeout(window.__stockToastT);
  window.__stockToastT = setTimeout(() => el.classList.add('hidden'), 4000);
};

/* ----------------------------------------------------------------------------
 * Cart count badge — keeps .cart-count elements in sync with CartLS.
 * -------------------------------------------------------------------------- */
function updateCartBadge() {
  const c = window.CartLS.get();
  const count = c.items.reduce((a, i) => a + (i.qty || 0), 0);
  document.querySelectorAll('.cart-count').forEach((el) => { el.textContent = count; });
}
window.addEventListener('cart:changed', updateCartBadge);
window.addEventListener('cart:add-item', updateCartBadge);
document.addEventListener('DOMContentLoaded', updateCartBadge);

/* ----------------------------------------------------------------------------
 * Global .js-add-to-cart delegate — reads data-* attrs from the trigger,
 * falls back to nearest .product-card for name/image. Used by product cards,
 * quick-view modal, and shop list items.
 * -------------------------------------------------------------------------- */
if (!window.__CART_WIREUP__) {
  window.__CART_WIREUP__ = true;

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-add-to-cart');
    if (!btn) return;
    if (btn.disabled || btn.getAttribute('data-out-of-stock') === '1') return;

    if (btn.dataset.lock === '1') return;
    btn.dataset.lock = '1';
    setTimeout(() => { btn.dataset.lock = ''; }, 400);

    const card = btn.closest('.product-card');
    const status = card ? card.querySelector('.js-add-status') : null;

    const stockVal = (btn.dataset.stock !== undefined && btn.dataset.stock !== '')
      ? parseInt(btn.dataset.stock, 10)
      : undefined;

    const item = {
      id: btn.dataset.id,
      name: btn.dataset.name || (card && card.querySelector('.product-title') ? card.querySelector('.product-title').textContent.trim() : ''),
      price: parseFloat(btn.dataset.price || '0'),
      image: btn.dataset.image || (card && card.querySelector('img') ? card.querySelector('img').src : ''),
      slug: btn.dataset.slug || '',
      currency: btn.dataset.currency || currencySymbol(),
    };
    if (stockVal !== undefined && !isNaN(stockVal)) item.stock = stockVal;

    const qty = parseInt(btn.dataset.qty || '1', 10) || 1;
    window.CartLS.add(item, qty);

    const original = btn.innerHTML;
    const addedLabel = btn.dataset.addedLabel || window.__MSG_ADDED__ || 'Added';
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><span>' + addedLabel + '</span>';
    if (status) status.textContent = addedLabel;
    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = original;
      if (status) status.textContent = '';
    }, 800);
  });
}

/* ----------------------------------------------------------------------------
 * Alpine components — registered before Alpine.start().
 * -------------------------------------------------------------------------- */

/*  dialog — modal dialog with focus trap + ESC + backdrop click.
 *  Usage: <div x-data="dialog()" x-id="['auth']" id="authModal">
 *           <button @click="open">open</button>
 *           <template x-teleport="body">
 *             <div x-show="isOpen" x-transition ...>...</div>
 *           </template>
 *         </div>
 *  Can also be opened externally via window.StoreUI.open('authModal').
 */
Alpine.data('dialog', (config = {}) => ({
  isOpen: false,
  _lockedScroll: false,
  init() {
    if (config.startOpen) this.open();
    this.$el.addEventListener('ui:open', () => this.open());
    this.$el.addEventListener('ui:close', () => this.close());
    this.$el.addEventListener('ui:toggle', () => (this.isOpen ? this.close() : this.open()));
  },
  open() {
    this.isOpen = true;
    if (!this._lockedScroll) {
      document.body.style.overflow = 'hidden';
      this._lockedScroll = true;
    }
    this.$nextTick(() => {
      const panel = this.$refs.panel;
      if (panel) {
        const focusable = panel.querySelector('[autofocus], input, select, textarea, button');
        if (focusable) focusable.focus();
      }
    });
    this.$dispatch('dialog-opened');
  },
  close() {
    this.isOpen = false;
    if (this._lockedScroll) {
      document.body.style.overflow = '';
      this._lockedScroll = false;
    }
    this.$dispatch('dialog-closed');
  },
  onEsc(e) { if (e.key === 'Escape' && this.isOpen) this.close(); },
}));

/*  drawer — side sheet. Behaves like dialog but slides in from start/end.
 *  Usage: <div x-data="drawer()" id="miniCart" class="...">...</div>
 */
Alpine.data('drawer', (config = {}) => ({
  isOpen: false,
  _lockedScroll: false,
  side: config.side || 'end',
  init() {
    if (config.startOpen) this.open();
    this.$el.addEventListener('ui:open', () => this.open());
    this.$el.addEventListener('ui:close', () => this.close());
    this.$el.addEventListener('ui:toggle', () => (this.isOpen ? this.close() : this.open()));
  },
  open() {
    this.isOpen = true;
    if (!this._lockedScroll) {
      document.body.style.overflow = 'hidden';
      this._lockedScroll = true;
    }
    this.$dispatch('drawer-opened');
  },
  close() {
    this.isOpen = false;
    if (this._lockedScroll) {
      document.body.style.overflow = '';
      this._lockedScroll = false;
    }
    this.$dispatch('drawer-closed');
  },
  onEsc(e) { if (e.key === 'Escape' && this.isOpen) this.close(); },
}));

/*  tabs — simple tab switcher.
 *  Usage: <div x-data="tabs('login')"> ...
 *           <button :class="tab==='login' && 'active'" @click="tab='login'">Login</button>
 *           <div x-show="tab==='login'">...</div>
 *         </div>
 */
Alpine.data('tabs', (initial) => ({
  tab: initial,
  is(name) { return this.tab === name; },
  set(name) { this.tab = name; },
}));

/*  qtyStepper — +/- with optional max.
 *  Usage: <div x-data="qtyStepper(1, 10)">
 *           <button @click="dec">−</button>
 *           <input x-model.number="qty" :max="max">
 *           <button @click="inc">+</button>
 *         </div>
 */
Alpine.data('qtyStepper', (initial, max) => ({
  qty: Math.max(1, Number(initial) || 1),
  max: max != null ? Number(max) : null,
  inc() {
    const next = this.qty + 1;
    if (this.max != null && next > this.max) {
      if (window.showStockAlert && window.__MSG_ONLY_X_STOCK__) {
        window.showStockAlert(String(window.__MSG_ONLY_X_STOCK__).replace('%s', this.max));
      }
      this.qty = this.max;
      return;
    }
    this.qty = next;
  },
  dec() { this.qty = Math.max(1, this.qty - 1); },
  set(v) {
    let n = Math.max(1, parseInt(v, 10) || 1);
    if (this.max != null) n = Math.min(n, this.max);
    this.qty = n;
  },
}));

/*  dropdown — tiny helper for headline menus (language, account).
 *  Usage: <div x-data="dropdown()" @click.outside="close">
 *           <button @click="toggle">…</button>
 *           <div x-show="open" x-cloak x-transition>…</div>
 *         </div>
 */
Alpine.data('dropdown', () => ({
  open: false,
  toggle() { this.open = !this.open; },
  close() { this.open = false; },
}));

/*  searchBox — debounced suggestion fetch. Endpoint comes from data attr.
 *  Usage: <div x-data="searchBox('/online_store/search/suggestions')">
 *           <input x-model="q" @input.debounce.250ms="fetch">
 *           <div x-show="results.length">...</div>
 *         </div>
 */
Alpine.data('searchBox', (endpoint) => ({
  q: '',
  results: [],
  loading: false,
  endpoint,
  fetch() {
    const term = (this.q || '').trim();
    if (term.length < 2) { this.results = []; return; }
    this.loading = true;
    fetch(this.endpoint + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
      .then((r) => r.json())
      .then((data) => { this.results = Array.isArray(data) ? data : []; })
      .catch(() => { this.results = []; })
      .finally(() => { this.loading = false; });
  },
  clear() { this.q = ''; this.results = []; },
}));

/*  miniCart — renders the header mini-cart. Subscribes to CartLS events.
 *  The drawer wrapper is x-data="drawer()"; its body uses x-data="miniCart()".
 */
Alpine.data('miniCart', () => ({
  items: [],
  subtotal: 0,
  grand: 0,
  currency: currencySymbol(),
  hidePrices: window.__HIDE_PRICES__ === true,
  allowOverselling: window.__ALLOW_OVERSELLING__ !== false,
  init() {
    this.refresh();
    window.addEventListener('cart:changed', () => this.refresh());
  },
  refresh() {
    const c = window.CartLS.get();
    this.items = c.items;
    this.subtotal = c.subtotal;
    this.grand = c.grand;
    this.currency = c.currency || currencySymbol();
  },
  money(v) { return this.hidePrices ? '—' : fmtMoney(v, this.currency); },
  lineTotal(it) { return this.money((it.price || 0) * (it.qty || 1)); },
  maxFor(it) {
    if (this.allowOverselling) return null;
    return (it.stock != null) ? Math.max(1, Number(it.stock)) : null;
  },
  inc(it) {
    const max = this.maxFor(it);
    const next = (it.qty || 1) + 1;
    if (max != null && next > max) {
      if (window.__MSG_ONLY_X_STOCK__) window.showStockAlert(String(window.__MSG_ONLY_X_STOCK__).replace('%s', max));
      return;
    }
    window.CartLS.setQty(it.id, next);
  },
  dec(it) { window.CartLS.setQty(it.id, Math.max(1, (it.qty || 1) - 1)); },
  setQty(it, v) { window.CartLS.setQty(it.id, Math.max(1, parseInt(v, 10) || 1)); },
  remove(it) { window.CartLS.remove(it.id); },
  clear() { window.CartLS.clear(); },
  checkout(url) {
    if (window.__LOGGED_IN__) { window.location.href = url; return; }
    window.StoreUI.open('authModal');
  },
}));

/*  sidebarMenu — accordion for mobile category nav. Tracks which group is open.
 */
Alpine.data('sidebarMenu', () => ({
  open: null,
  is(id) { return this.open === id; },
  toggle(id) { this.open = this.open === id ? null : id; },
}));

/*  pageLoader — hides #page-loader once the document has loaded.
 */
Alpine.data('pageLoader', () => ({
  init() {
    const hide = () => {
      this.$el.style.opacity = 0;
      setTimeout(() => { this.$el.style.display = 'none'; }, 300);
    };
    if (document.readyState === 'complete') hide();
    else window.addEventListener('load', hide);
  },
}));

/* ----------------------------------------------------------------------------
 * Alpine plugins + start
 * -------------------------------------------------------------------------- */
Alpine.plugin(focus);
Alpine.plugin(collapse);

window.Alpine = Alpine;
Alpine.start();

/* ----------------------------------------------------------------------------
 * PWA service worker — registered only on secure contexts (HTTPS or localhost).
 * -------------------------------------------------------------------------- */
(function registerSW() {
  try {
    if (!('serviceWorker' in navigator)) return;
    const isSecure = window.isSecureContext === true
      || location.protocol === 'https:'
      || location.hostname === 'localhost'
      || location.hostname === '127.0.0.1';
    if (!isSecure) return;
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
  } catch (_) {}
})();
