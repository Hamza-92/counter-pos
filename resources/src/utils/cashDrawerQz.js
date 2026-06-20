/**
 * Cash drawer auto-open via QZ Tray (browser → local printing bridge).
 * Sends ESC/POS "kick" pulse to the receipt printer (drawer connected via RJ11/RJ12).
 *
 * Requires: QZ Tray running on the client machine.
 * Optional: Configure signing in QZ Tray or set certificate/signature URLs for production.
 */

const QZ_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/qz-tray@2.2.3/qz-tray.min.js';

/** ESC/POS: open drawer — ESC p m t1 t2 (pin 2, pulse 25ms + 250ms) */
const ESC_POS_DRAWER_KICK = [0x1b, 0x70, 0x00, 0x19, 0xfa];

function loadScript(src) {
  return new Promise((resolve, reject) => {
    if (typeof document === 'undefined') {
      reject(new Error('No document'));
      return;
    }
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      resolve();
      return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Failed to load QZ Tray script'));
    document.head.appendChild(script);
  });
}

function rawDrawerBytes() {
  return ESC_POS_DRAWER_KICK.map(b => String.fromCharCode(b)).join('');
}

/**
 * Open the cash drawer via QZ Tray.
 * @param {string} [printerName] - Exact printer name (as in QZ Tray). Empty = default printer.
 * @returns {Promise<boolean>} - true if drawer was triggered, false otherwise (no QZ / error).
 */
export function openCashDrawer(printerName = '') {
  if (typeof window === 'undefined') return Promise.resolve(false);

  const run = () => {
    const qz = window.qz;
    if (!qz || !qz.websocket || !qz.printers || !qz.configs) {
      return Promise.resolve(false);
    }

    const rawData = rawDrawerBytes();
    const printData = [{ type: 'raw', format: 'plain', data: rawData }];

    const getConfig = () => {
      if (printerName && String(printerName).trim()) {
        return qz.printers.find(printerName.trim()).then(p => qz.configs.create(p));
      }
      return Promise.resolve(qz.configs.create());
    };

    return qz.websocket.connect()
      .then(getConfig)
      .then(config => qz.print(config, printData))
      .then(() => true)
      .catch(() => false);
  };

  if (window.qz) {
    return run();
  }

  return loadScript(QZ_SCRIPT_URL)
    .then(run)
    .catch(() => false);
}

export default { openCashDrawer };
