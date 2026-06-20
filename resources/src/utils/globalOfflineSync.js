import axios from 'axios';
import Util from './index';

// Simple module-level guards to prevent duplicate listeners and concurrent syncs
let listenersAttached = false;
let syncInProgress = false;
let pollTimer = null;

// Core sync routine, mirroring POS behavior but independent of any component instance
export async function syncOfflineSalesQueue() {
  if (syncInProgress) return;

  // Defensive online check
  if (typeof window !== 'undefined') {
    try {
      if (window.navigator && window.navigator.onLine === false) {
        return;
      }
    } catch (e) {}
  }

  // Confirm backend reachability before attempting any sync.
  // This prevents DevTools/flaky "online" events from triggering sync while
  // still effectively offline.
  try {
    // Use explicit /api path without double-prefixing the global axios baseURL.
    const res = await axios.get('/api/ping', { timeout: 2000, baseURL: '' });
    const ok = !!(res && res.status === 200 && res.data && res.data.ok === true);
    if (!ok) return;
  } catch (e) {
    return;
  }

  syncInProgress = true;

  // Notify UI (via global event bus) that a global offline sync has started
  try {
    if (typeof window !== 'undefined' && window.Fire && window.Fire.$emit) {
      window.Fire.$emit('offline-sync:start');
    }
  } catch (e) {}
  let syncedCount = 0;
  let lastError = null;

  try {
    if (!Util || !Util.offlinePos || !Util.offlinePos.getOfflineSales) {
      return;
    }

    const queue = Util.offlinePos.getOfflineSales() || [];
    for (let i = 0; i < queue.length; i++) {
      const sale = queue[i];
      // Skip records that are already synced, failed or currently being processed
      if (!sale || !sale.payload || sale.status === 'synced' || sale.status === 'syncing' || sale.status === 'failed') continue;

      try {
        // Mark as "syncing" in persistent storage so other workers/tabs
        // see that this record is already in-flight and will skip it.
        try {
          if (Util && Util.offlinePos && Util.offlinePos.markSaleAsSyncing) {
            Util.offlinePos.markSaleAsSyncing(sale.id);
          }
        } catch (e) {}

        // Normalize payload to ensure required keys (e.g. sale_unit_id) exist
        const basePayload = sale.payload || {};
        const normalizedDetails = Array.isArray(basePayload.details)
          ? basePayload.details.map(d => ({
              ...d,
              // Ensure sale_unit_id key always exists to satisfy backend expectations
              sale_unit_id:
                d && Object.prototype.hasOwnProperty.call(d, 'sale_unit_id')
                  ? d.sale_unit_id
                  : (d && d.sale_unit_id) || null,
            }))
          : basePayload.details;
        const payload = {
          ...basePayload,
          // Include offline_id so backend can optionally de-duplicate
          offline_id: sale.id,
          details: normalizedDetails,
        };

        // Use explicit /api path without double-prefixing the global axios baseURL.
        const response = await axios.post('/api/pos/create_pos', payload, { baseURL: '' });
        if (response && response.data && response.data.success === true) {
          Util.offlinePos.markSaleAsSynced(sale.id, response.data.id);
          syncedCount++;
          // On success, clear shadow stock deductions for this sale
          try {
            if (Util && Util.shadowStock && Util.shadowStock.revertDeductions) {
              Util.shadowStock.revertDeductions(sale.id);
            }
          } catch (e) {}
        } else {
          Util.offlinePos.markSaleAsFailed(
            sale.id,
            'Invalid response from server',
            response && response.status
          );
          try {
            if (Util && Util.shadowStock && Util.shadowStock.revertDeductions) {
              Util.shadowStock.revertDeductions(sale.id);
            }
          } catch (e) {}
        }
      } catch (error) {
        const isNetworkError = !error.response || error.message === 'Network Error';

        // If we detect that we are offline again, stop and retry later
        if (typeof window !== 'undefined') {
          try {
            if (window.navigator && window.navigator.onLine === false) {
              if (isNetworkError) {
                lastError = error.message || 'Network error during offline sync';
                break;
              }
            }
          } catch (e) {}
        }

        const msg =
          (error.response &&
            (error.response.data &&
              (error.response.data.message || error.response.data.error))) ||
          error.message ||
          'Unknown error';

        Util.offlinePos.markSaleAsFailed(
          sale.id,
          msg,
          error.response && error.response.status
        );

        lastError = msg;

        // For non-network errors, rollback shadow stock for this sale
        if (!isNetworkError) {
          try {
            if (Util && Util.shadowStock && Util.shadowStock.revertDeductions) {
              Util.shadowStock.revertDeductions(sale.id);
            }
          } catch (e) {}
        }
      }
    }

    // If there are no pending/failed offline sales anymore, clear residual shadow stock
    try {
      if (Util && Util.offlinePos && Util.offlinePos.getOfflineSales) {
        const remaining = (Util.offlinePos.getOfflineSales() || []).filter(
          s => s && (s.status === 'pending' || s.status === 'syncing')
        );
        if (remaining.length === 0 && Util.shadowStock && Util.shadowStock.clearAll) {
          Util.shadowStock.clearAll();
        }
      }
    } catch (e) {}
  } finally {
    syncInProgress = false;
    // Notify UI that global offline sync has finished
    try {
      if (typeof window !== 'undefined' && window.Fire && window.Fire.$emit) {
        window.Fire.$emit('offline-sync:end', { syncedCount, lastError });
        // Also emit a result event so POS (and others) can show a toast similar to manual sync
        window.Fire.$emit('offline-sync:auto-result', { syncedCount, lastError });
      }
    } catch (e) {}
  }

  return { syncedCount, lastError };
}

// Public initializer: attach global listeners once and trigger an initial sync if needed
export function setupGlobalOfflineSync() {
  if (listenersAttached || typeof window === 'undefined') return;
  listenersAttached = true;

  const hasPendingOfflineSales = () => {
    try {
      if (!Util || !Util.offlinePos || !Util.offlinePos.getOfflineSales) return false;
      const queue = Util.offlinePos.getOfflineSales() || [];
      return queue.some(s => s && (s.status === 'pending' || s.status === 'syncing'));
    } catch (e) {
      return false;
    }
  };

  const maybeKickSync = () => {
    try {
      const online = !window.navigator || window.navigator.onLine !== false;
      if (!online) return;
      if (!hasPendingOfflineSales()) return;
      syncOfflineSalesQueue();
    } catch (e) {}
  };

  const handleOnline = () => {
    // Kept for compatibility if browser emits `online`,
    // but we don't rely on it (we poll navigator.onLine).
    maybeKickSync();
  };

  // Do NOT rely solely on the browser `online` event; some environments miss it.
  // We still listen to it as an extra hint, but polling is the primary trigger.
  try { window.addEventListener('online', handleOnline); } catch (e) {}

  // Some Chrome/DevTools scenarios won't reliably emit a fresh `online` event,
  // but focus/visibility changes do occur. Re-check there too (only if pending queue exists).
  try {
    window.addEventListener('focus', maybeKickSync);
    document.addEventListener('visibilitychange', () => {
      try {
        if (document.visibilityState === 'visible') {
          maybeKickSync();
        }
      } catch (e) {}
    });
  } catch (e) {}

  // On app startup, if we are already online and there is a queue, kick off a background sync
  try {
    maybeKickSync();
  } catch (e) {}

  // Primary trigger: poll navigator.onLine so we don't wait for the `online` event.
  // This will only attempt sync when we are online AND there are pending offline sales.
  try {
    if (!pollTimer) {
      pollTimer = setInterval(() => {
        maybeKickSync();
      }, 5000);
    }
  } catch (e) {}
}

export default {
  setupGlobalOfflineSync,
  syncOfflineSalesQueue,
};


