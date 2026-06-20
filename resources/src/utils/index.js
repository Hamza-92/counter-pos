// ---------- Fullscreen helper ----------
const toggleFullScreen = () => {
  if (typeof window === 'undefined') return;

  const doc = window.document;
  const docEl = doc.documentElement;

  const requestFullScreen =
    docEl.requestFullscreen ||
    docEl.mozRequestFullScreen ||
    docEl.webkitRequestFullScreen ||
    docEl.msRequestFullscreen;

  const cancelFullScreen =
    doc.exitFullscreen ||
    doc.mozCancelFullScreen ||
    doc.webkitExitFullscreen ||
    doc.msExitFullscreen;

  if (!requestFullScreen || !cancelFullScreen) return;

  if (
    !doc.fullscreenElement &&
    !doc.mozFullScreenElement &&
    !doc.webkitFullscreenElement &&
    !doc.msFullscreenElement
  ) {
    requestFullScreen.call(docEl);
  } else {
    cancelFullScreen.call(doc);
  }
};

// ---------- Offline POS helpers (localStorage-based) ----------
const hasWindow = typeof window !== 'undefined';

const getStorage = () => {
  if (!hasWindow) return null;
  try {
    if (window.localStorage) {
      return window.localStorage;
    }
  } catch (e) {
    // Access to localStorage can throw in some environments; fail gracefully
  }
  return null;
};

const readJSON = (key, fallback) => {
  const storage = getStorage();
  if (!storage) return fallback;
  try {
    const raw = storage.getItem(key);
    if (!raw) return fallback;
    return JSON.parse(raw);
  } catch (e) {
    return fallback;
  }
};

const writeJSON = (key, value) => {
  const storage = getStorage();
  if (!storage) return;
  try {
    if (value === undefined || value === null) {
      storage.removeItem(key);
    } else {
      storage.setItem(key, JSON.stringify(value));
    }
  } catch (e) {
    // Ignore quota and other errors – offline is best-effort
  }
};

const POS_BOOTSTRAP_KEY = 'pos_bootstrap_v1';
const POS_WAREHOUSE_SNAPSHOTS_KEY = 'pos_warehouse_snapshots_v1';
const POS_OFFLINE_SALES_KEY = 'pos_offline_sales_v1';
const POS_PRODUCT_DETAILS_KEY = 'pos_product_details_v1';

const makeDetailKey = (warehouseId, productId, variantId) => {
  const w = warehouseId != null ? String(warehouseId) : '0';
  const p = productId != null ? String(productId) : '0';
  const v = variantId != null && variantId !== 'null' ? String(variantId) : 'null';
  return `w:${w}:p:${p}:v:${v}`;
};

const generateId = () => {
  try {
    return (
      Date.now().toString(36) +
      '-' +
      Math.random().toString(36).substr(2, 6)
    );
  } catch (e) {
    return String(Date.now());
  }
};

const offlinePos = {
  // ---- Bootstrap (clients, warehouses, settings, etc.) ----
  cacheBootstrap(data) {
    if (!data || typeof data !== 'object') return;
    writeJSON(POS_BOOTSTRAP_KEY, {
      clients: data.clients || [],
      accounts: data.accounts || [],
      warehouses: data.warehouses || [],
      categories: data.categories || [],
      brands: data.brands || [],
      payment_methods: data.payment_methods || [],
      defaultWarehouse: data.defaultWarehouse || '',
      defaultClient: data.defaultClient || '',
      default_client_name: data.default_client_name || '',
      default_client_eligible: data.default_client_eligible,
      default_client_points: data.default_client_points,
      point_to_amount_rate: data.point_to_amount_rate,
      default_tax: data.default_tax,
      products_per_page: data.products_per_page,
      languages_available: data.languages_available || [],
      stripe_key: data.stripe_key || ''
    });
  },

  getCachedBootstrap() {
    return readJSON(POS_BOOTSTRAP_KEY, null);
  },

  // ---- Per-warehouse products snapshots (grid + scan data) ----
  cacheWarehouseSnapshot(warehouseId, snapshot) {
    if (!warehouseId) return;
    const key = String(warehouseId);
    const existing = readJSON(POS_WAREHOUSE_SNAPSHOTS_KEY, {});
    const prev = existing[key] || {};
    existing[key] = Object.assign({}, prev, snapshot || {}, {
      updatedAt: new Date().toISOString()
    });
    writeJSON(POS_WAREHOUSE_SNAPSHOTS_KEY, existing);
  },

  getWarehouseSnapshot(warehouseId) {
    if (!warehouseId) return null;
    const key = String(warehouseId);
    const all = readJSON(POS_WAREHOUSE_SNAPSHOTS_KEY, {});
    return all[key] || null;
  },

  // ---- Product detail cache (show_product_data) ----
  cacheProductDetail(warehouseId, productId, variantId, detail) {
    if (!detail || typeof detail !== 'object') return;
    const key = makeDetailKey(warehouseId, productId, variantId);
    const current = readJSON(POS_PRODUCT_DETAILS_KEY, {});
    current[key] = Object.assign({}, detail, {
      _cachedAt: new Date().toISOString()
    });
    writeJSON(POS_PRODUCT_DETAILS_KEY, current);
  },

  getProductDetail(warehouseId, productId, variantId) {
    const key = makeDetailKey(warehouseId, productId, variantId);
    const current = readJSON(POS_PRODUCT_DETAILS_KEY, {});
    return current[key] || null;
  },

  // ---- Offline sales queue ----
  getOfflineSales() {
    const list = readJSON(POS_OFFLINE_SALES_KEY, []);
    if (!Array.isArray(list)) return [];
    return list;
  },

  addOfflineSale(payload) {
    const list = this.getOfflineSales();
    const now = new Date().toISOString();
    let safePayload = payload || {};
    // Normalize details to ensure sale_unit_id is always present on each line
    try {
      if (Array.isArray(safePayload.details)) {
        const normalizedDetails = safePayload.details.map((d) => ({
          ...d,
          sale_unit_id:
            d && d.sale_unit_id !== undefined && d.sale_unit_id !== null && d.sale_unit_id !== ''
              ? d.sale_unit_id
              : d && d.sale_unit_id
        }));
        safePayload = { ...safePayload, details: normalizedDetails };
      }
    } catch (e) {}

    const record = {
      id: generateId(),
      // status lifecycle:
      // 'pending'  -> waiting to be synced
      // 'syncing'  -> a sync worker is currently sending this sale
      // 'synced'   -> successfully created on the server
      // 'failed'   -> last sync attempt failed (can be retried)
      createdAt: now,
      updatedAt: now,
      status: 'pending',
      lastError: null,
      payload: safePayload
    };
    list.push(record);
    writeJSON(POS_OFFLINE_SALES_KEY, list);
    return record;
  },

  _updateSale(id, updater) {
    if (!id) return;
    const list = this.getOfflineSales();
    let changed = false;
    const next = list.map((s) => {
      if (!s || s.id !== id) return s;
      const updated = typeof updater === 'function' ? updater(Object.assign({}, s)) : s;
      changed = true;
      return Object.assign({}, s, updated, {
        updatedAt: new Date().toISOString()
      });
    });
    if (changed) {
      writeJSON(POS_OFFLINE_SALES_KEY, next);
    }
  },

  // Mark a sale as "in progress" so multiple sync workers (tabs/components)
  // do not submit the same offline record concurrently.
  markSaleAsSyncing(id) {
    this._updateSale(id, (s) => ({
      status: 'syncing',
      lastError: null
    }));
  },

  markSaleAsSynced(id, remoteId) {
    this._updateSale(id, (s) => ({
      status: 'synced',
      remoteId: remoteId != null ? remoteId : s.remoteId || null,
      lastError: null
    }));
  },

  markSaleAsFailed(id, message, statusCode) {
    this._updateSale(id, () => ({
      status: 'failed',
      lastError: {
        message: message || 'Unknown error',
        statusCode: statusCode || null
      }
    }));
  },

  pruneSyncedSales() {
    const list = this.getOfflineSales();
    const next = list.filter((s) => !s || s.status !== 'synced');
    writeJSON(POS_OFFLINE_SALES_KEY, next);
  },

  // ---- Clear cache (for page reload) ----
  // Clears POS cache data (products, warehouse snapshots, bootstrap) to avoid stale/outdated data
  // This should be called when online so fresh data can be fetched and cache rebuilt
  // Note: We do NOT clear POS_OFFLINE_SALES_KEY (offline sales queue) as it needs to persist for sync
  // Note: We do NOT clear IndexedDB shadow stock as it's managed separately for offline functionality
  clearCache() {
    try {
      const storage = getStorage();
      if (storage) {
        // Clear bootstrap cache (clients, warehouses, settings, etc.)
        storage.removeItem(POS_BOOTSTRAP_KEY);
        // Clear warehouse snapshots (cached product lists per warehouse)
        storage.removeItem(POS_WAREHOUSE_SNAPSHOTS_KEY);
        // Clear product detail cache (individual product details)
        storage.removeItem(POS_PRODUCT_DETAILS_KEY);
        // Clear receipt company setting cache
        storage.removeItem('pos_receipt_company_setting');
      }
    } catch (e) {
      // Ignore errors during cache clearing
    }
  }
};

// ---------- IndexedDB-based shadow stock for offline sales ----------
const hasIndexedDB = hasWindow && !!window.indexedDB;
const SHADOW_DB_NAME = 'pos_shadow_stock_v1';
const SHADOW_DB_VERSION = 1;
const SHADOW_STOCK_STORE = 'shadow_stock';
const SHADOW_DEDUCTIONS_STORE = 'shadow_deductions';

const openShadowDb = () => {
  if (!hasIndexedDB) return Promise.resolve(null);
  return new Promise((resolve, reject) => {
    try {
      const request = window.indexedDB.open(SHADOW_DB_NAME, SHADOW_DB_VERSION);
      request.onerror = () => reject(request.error || new Error('IndexedDB open failed'));
      request.onsuccess = () => resolve(request.result || null);
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        try {
          if (!db.objectStoreNames.contains(SHADOW_STOCK_STORE)) {
            db.createObjectStore(SHADOW_STOCK_STORE, { keyPath: 'key' });
          }
          if (!db.objectStoreNames.contains(SHADOW_DEDUCTIONS_STORE)) {
            db.createObjectStore(SHADOW_DEDUCTIONS_STORE, { keyPath: 'saleId' });
          }
        } catch (e) {
          // ignore upgrade errors; db may be partially usable
        }
      };
    } catch (e) {
      reject(e);
    }
  });
};

const shadowStock = {
  /**
   * Record per-line quantity deductions for an offline sale in IndexedDB.
   * - warehouseId: current warehouse
   * - saleId: offline queue id (string) – used to rollback later
   * - details: array of POS detail lines (must include product_id, product_variant_id, quantity, product_type)
   */
  async recordDeductions(warehouseId, saleId, details) {
    if (!hasIndexedDB || !warehouseId || !saleId || !Array.isArray(details) || !details.length) return;
    const db = await openShadowDb();
    if (!db) return;

    try {
      const tx = db.transaction([SHADOW_STOCK_STORE, SHADOW_DEDUCTIONS_STORE], 'readwrite');
      const stockStore = tx.objectStore(SHADOW_STOCK_STORE);
      const dedStore = tx.objectStore(SHADOW_DEDUCTIONS_STORE);

      const items = [];
      const now = new Date().toISOString();

      details.forEach((d) => {
        if (!d || d.product_type === 'is_service') return;
        const productId = d.product_id || d.id;
        if (!productId) return;
        const variantId = d.product_variant_id != null ? d.product_variant_id : null;
        const qty = Number(d.quantity || 0);
        if (!qty || qty <= 0) return;
        const key = makeDetailKey(warehouseId, productId, variantId);
        items.push({
          key,
          warehouse_id: warehouseId,
          product_id: productId,
          product_variant_id: variantId,
          quantity: qty
        });

        const getReq = stockStore.get(key);
        getReq.onsuccess = (ev) => {
          try {
            const existing = ev.target.result || {
              key,
              warehouse_id: warehouseId,
              product_id: productId,
              product_variant_id: variantId,
              sold: 0
            };
            existing.sold = Number(existing.sold || 0) + qty;
            existing.updatedAt = now;
            stockStore.put(existing);
          } catch (e) {
            // ignore per-row errors
          }
        };
      });

      // Store mapping saleId -> affected items so we can rollback precisely
      const dedRecord = {
        saleId,
        warehouse_id: warehouseId,
        items,
        createdAt: now
      };
      try {
        dedStore.put(dedRecord);
      } catch (e) {
        // ignore mapping errors
      }
    } catch (e) {
      // fail silently; shadow stock is best-effort
    }
  },

  /**
   * Rollback previously recorded deductions for an offline sale.
   * Called when sync to backend fails permanently (non-network error).
   */
  async revertDeductions(saleId) {
    if (!hasIndexedDB || !saleId) return;
    const db = await openShadowDb();
    if (!db) return;
    try {
      const tx = db.transaction([SHADOW_STOCK_STORE, SHADOW_DEDUCTIONS_STORE], 'readwrite');
      const stockStore = tx.objectStore(SHADOW_STOCK_STORE);
      const dedStore = tx.objectStore(SHADOW_DEDUCTIONS_STORE);

      const getReq = dedStore.get(saleId);
      getReq.onsuccess = (ev) => {
        try {
          const rec = ev.target.result;
          if (!rec || !Array.isArray(rec.items) || !rec.items.length) {
            try { dedStore.delete(saleId); } catch (e2) {}
            return;
          }
          const now = new Date().toISOString();
          rec.items.forEach((item) => {
            if (!item || !item.key) return;
            const qty = Number(item.quantity || 0);
            if (!qty || qty <= 0) return;
            const sReq = stockStore.get(item.key);
            sReq.onsuccess = (sev) => {
              try {
                const row = sev.target.result;
                if (!row) return;
                const currentSold = Number(row.sold || 0) - qty;
                if (currentSold <= 0) {
                  stockStore.delete(item.key);
                } else {
                  row.sold = currentSold;
                  row.updatedAt = now;
                  stockStore.put(row);
                }
              } catch (e3) {}
            };
          });
          try { dedStore.delete(saleId); } catch (e4) {}
        } catch (e) {
          // ignore rollback errors
        }
      };
    } catch (e) {
      // ignore db errors
    }
  },

  /**
   * Apply current shadow stock (unsynced offline sales) to a given products list.
   * For each non-service item in the list, qte_sale is reduced by the recorded 'sold' amount (never below 0).
   */
  async applyToList(warehouseId, list) {
    if (!hasIndexedDB || !warehouseId || !Array.isArray(list) || !list.length) return;
    const db = await openShadowDb();
    if (!db) return;

    try {
      const tx = db.transaction([SHADOW_STOCK_STORE], 'readonly');
      const stockStore = tx.objectStore(SHADOW_STOCK_STORE);
      const request = stockStore.openCursor();

      request.onsuccess = (event) => {
        const cursor = event.target.result;
        if (!cursor) return;
        try {
          const row = cursor.value;
          if (row && String(row.warehouse_id) === String(warehouseId)) {
            const sold = Number(row.sold || 0);
            if (sold > 0) {
              const pid = row.product_id;
              const vid = row.product_variant_id != null ? row.product_variant_id : null;
              for (let i = 0; i < list.length; i++) {
                const item = list[i];
                if (!item || item.product_type === 'is_service') continue;
                const itemPid = item.product_id || item.id;
                const itemVid = item.product_variant_id != null ? item.product_variant_id : null;
                if (String(itemPid) === String(pid) && String(itemVid) === String(vid)) {
                  const cur = Number(item.qte_sale || 0);
                  const next = cur - sold;
                  item.qte_sale = next > 0 ? next : 0;
                }
              }
            }
          }
        } catch (e) {
          // ignore per-row failures
        }
        cursor.continue();
      };
    } catch (e) {
      // ignore
    }
  },

  /**
   * Return the available quantity for a single product/variant in sale units,
   * given the base qte_sale reported by the server/cache.
   */
  async getAvailableQuantity(warehouseId, productId, variantId, baseQteSale) {
    const base = Number(baseQteSale || 0);
    if (!hasIndexedDB || !warehouseId || !productId || base <= 0) return base;
    const db = await openShadowDb();
    if (!db) return base;

    try {
      const tx = db.transaction([SHADOW_STOCK_STORE], 'readonly');
      const stockStore = tx.objectStore(SHADOW_STOCK_STORE);
      const key = makeDetailKey(warehouseId, productId, variantId);

      return await new Promise((resolve) => {
        const req = stockStore.get(key);
        req.onsuccess = (ev) => {
          try {
            const row = ev.target.result;
            const sold = row ? Number(row.sold || 0) : 0;
            const next = base - sold;
            resolve(next > 0 ? next : 0);
          } catch (e) {
            resolve(base);
          }
        };
        req.onerror = () => resolve(base);
      });
    } catch (e) {
      return base;
    }
  },

  /**
   * Clear all shadow stock and deduction records.
   * Used when there are no pending offline sales, so local adjustments
   * must not affect displayed stock anymore.
   */
  async clearAll() {
    if (!hasIndexedDB) return;
    const db = await openShadowDb();
    if (!db) return;
    try {
      const tx = db.transaction([SHADOW_STOCK_STORE, SHADOW_DEDUCTIONS_STORE], 'readwrite');
      try {
        tx.objectStore(SHADOW_STOCK_STORE).clear();
      } catch (e) {}
      try {
        tx.objectStore(SHADOW_DEDUCTIONS_STORE).clear();
      } catch (e) {}
    } catch (e) {
      // ignore
    }
  }
};

// ---------- Date Formatting Utility ----------

/**
 * Safely format a date (and optional time) for display without timezone shifts.
 * Supports:
 * - YYYY-MM-DD
 * - YYYY-MM-DD HH:mm[:ss]
 * - YYYY-MM-DDTHH:mm[:ss]Z
 * - DD/MM/YYYY
 * - DD-MM-YYYY
 */
export const formatDisplayDate = (input, format = 'YYYY-MM-DD') => {
  if (!input) return '';

  const pad = (n) => String(n).padStart(2, '0');

  let original = input;
  let dateStr = '';
  let timeStr = '';

  try {
    // If it's a real Date object, format using local components (safe)
    if (input instanceof Date) {
      const y = String(input.getFullYear());
      const m = pad(input.getMonth() + 1);
      const d = pad(input.getDate());
      const hh = pad(input.getHours());
      const mm = pad(input.getMinutes());
      const ss = pad(input.getSeconds());

      const formattedDate = formatDateParts(y, m, d, format);
      const hasTime = (hh !== '00' || mm !== '00' || ss !== '00');
      return hasTime ? `${formattedDate} ${hh}:${mm}` : formattedDate;
    }

    const str = String(input).trim();
    original = str;

    // Split date/time without parsing timezone
    if (str.includes('T')) {
      const [dPart, tPartRaw = ''] = str.split('T');
      dateStr = dPart;
      timeStr = tPartRaw.replace(/Z$/i, '').split('.')[0]; // remove Z and ms
    } else if (str.includes(' ')) {
      const [dPart, tPart = ''] = str.split(' ');
      dateStr = dPart;
      timeStr = tPart;
    } else {
      dateStr = str;
      timeStr = '';
    }

    // Parse date part safely (NO browser Date parsing)
    const parsed = parseDateOnlySafely(dateStr);
    if (!parsed) return original;

    const { year, month, day } = parsed;
    const formattedDate = formatDateParts(year, month, day, format);

    // Keep HH:mm only
    if (timeStr) {
      const [hh = '00', mm = '00'] = timeStr.split(':');
      return `${formattedDate} ${pad(hh)}:${pad(mm)}`;
    }

    return formattedDate;
  } catch (e) {
    return String(original ?? input);
  }

  function formatDateParts(year, month, day, fmt) {
    switch (fmt) {
      case 'DD/MM/YYYY':
        return `${day}/${month}/${year}`;
      case 'MM/DD/YYYY':
        return `${month}/${day}/${year}`;
      case 'YYYY-MM-DD':
      default:
        return `${year}-${month}-${day}`;
    }
  }

  /**
   * Parse date part only, safely, without timezone shifts.
   * IMPORTANT: no "new Date(ds)" fallback here.
   */
  function parseDateOnlySafely(ds) {
    if (!ds) return null;

    // YYYY-MM-DD
    let m = ds.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (m) return { year: m[1], month: m[2], day: m[3] };

    // DD/MM/YYYY
    m = ds.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (m) return { year: m[3], month: m[2], day: m[1] };

    // DD-MM-YYYY
    m = ds.match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (m) return { year: m[3], month: m[2], day: m[1] };

    // If we can't parse, return null (better than wrong date)
    return null;
  }
};



/**
 * Get the date format from database (Vuex getter) or localStorage cache
 * @param {Object|null} store
 * @returns {'DD/MM/YYYY'|'MM/DD/YYYY'|'YYYY-MM-DD'}
 */
export const getDateFormat = (store = null) => {
  const allowed = ['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD'];

  try {
    // 1) Vuex store getter
    if (store?.getters?.getDateFormat) {
      const fmt = store.getters.getDateFormat;
      if (allowed.includes(fmt)) return fmt;
    }

    // 2) localStorage fallback
    const stored = localStorage.getItem('app_date_format');
    if (allowed.includes(stored)) return stored;
  } catch (e) {
    // ignore
  }

  return 'YYYY-MM-DD';
};

export default {
  toggleFullScreen,
  offlinePos,
  shadowStock,
  formatDisplayDate,
  getDateFormat
};
