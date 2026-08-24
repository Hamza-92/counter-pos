const MAX_INTER_KEY_DELAY_MS = 80;
const IDLE_SCAN_DELAY_MS = 120;
const MIN_BARCODE_LENGTH = 3;

function isEditableElement(target) {
  if (!target) return false;
  const tag = String(target.tagName || "").toUpperCase();
  return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || target.isContentEditable;
}

function isProductSearch(target) {
  return !!(target && target.classList && target.classList.contains("pos-shell-search-input"));
}

function emptyScannerState() {
  return {
    buffer: "",
    firstAt: 0,
    lastAt: 0,
    target: null,
    originalValue: null,
    idleTimer: null,
  };
}

/**
 * Captures USB/Bluetooth "keyboard wedge" scanners on the POS screen.
 *
 * Normal keystrokes are allowed through. A sequence is claimed only when it
 * arrives at scanner speed and matches a product currently available to POS.
 * This keeps shortcuts and form inputs working normally while allowing scans
 * when focus is on the page, a button, or one of the main POS inputs.
 */
export default {
  mounted() {
    this.__posScannerState = emptyScannerState();

    this.__posScannerReset = () => {
      const state = this.__posScannerState;
      if (state && state.idleTimer) clearTimeout(state.idleTimer);
      this.__posScannerState = emptyScannerState();
    };

    this.__posScannerSubmit = (event = null) => {
      const state = this.__posScannerState;
      if (!state) return false;

      if (state.idleTimer) clearTimeout(state.idleTimer);
      const code = String(state.buffer || "").trim();
      const target = state.target;
      const originalValue = state.originalValue;
      const elapsed = state.lastAt - state.firstAt;
      const scannerSpeed = code.length >= MIN_BARCODE_LENGTH &&
        elapsed <= Math.max(MAX_INTER_KEY_DELAY_MS, (code.length - 1) * MAX_INTER_KEY_DELAY_MS);
      const knownProduct = scannerSpeed && this.__posScannerMatchesProduct(code);

      this.__posScannerState = emptyScannerState();
      if (!knownProduct) return false;

      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
      }

      // Scanner characters have already reached a focused non-search input.
      // Restore its pre-scan value before routing the barcode to product search.
      if (isEditableElement(target) && !isProductSearch(target) && originalValue !== null) {
        try {
          target.value = originalValue;
          target.dispatchEvent(new Event("input", { bubbles: true }));
        } catch (e) { /* leave the field untouched if the browser rejects restoration */ }
      }

      this.handleHardwareBarcodeScan(code);
      return true;
    };

    this.__posScannerKeydown = (event) => {
      try {
        if (!event || event.isComposing || event.ctrlKey || event.altKey || event.metaKey) {
          this.__posScannerReset();
          return;
        }

        // Payment, customer, camera, and confirmation dialogs own their input.
        if (document.body && document.body.classList.contains("modal-open")) {
          this.__posScannerReset();
          return;
        }

        const key = event.key;
        if (key === "Enter" || key === "Tab") {
          this.__posScannerSubmit(event);
          return;
        }

        // Shift/CapsLock can be emitted by scanners for uppercase/symbol codes.
        if (key === "Shift" || key === "CapsLock") return;
        if (!key || key.length !== 1) {
          this.__posScannerReset();
          return;
        }

        const now = typeof performance !== "undefined" ? performance.now() : Date.now();
        let state = this.__posScannerState || emptyScannerState();
        const sameTarget = !state.target || state.target === event.target;
        const withinScannerGap = !state.lastAt || (now - state.lastAt) <= MAX_INTER_KEY_DELAY_MS;

        if (!sameTarget || !withinScannerGap) {
          this.__posScannerReset();
          state = this.__posScannerState;
        }

        if (!state.buffer) {
          state.firstAt = now;
          state.target = event.target;
          state.originalValue = isEditableElement(event.target) ? String(event.target.value || "") : null;
        }

        state.buffer += key;
        state.lastAt = now;

        if (state.idleTimer) clearTimeout(state.idleTimer);
        // Scanners without an Enter suffix can still work while focus is not in
        // an editable field. Inputs require a terminator so ordinary typing is
        // never mistaken for a scan.
        if (!isEditableElement(event.target)) {
          state.idleTimer = setTimeout(() => this.__posScannerSubmit(), IDLE_SCAN_DELAY_MS);
        }
      } catch (e) {
        this.__posScannerReset();
      }
    };

    window.addEventListener("keydown", this.__posScannerKeydown, true);
  },

  beforeDestroy() {
    try {
      if (this.__posScannerKeydown) {
        window.removeEventListener("keydown", this.__posScannerKeydown, true);
      }
      if (this.__posScannerReset) this.__posScannerReset();
    } catch (e) { /* ignore cleanup errors */ }
  },

  methods: {
    __posScannerMatchesProduct(code) {
      const normalized = String(code || "").trim();
      if (!normalized || !Array.isArray(this.products_pos)) return false;

      const exactMatch = this.products_pos.some(product =>
        String(product.code || "").trim() === normalized ||
        String(product.barcode || "").trim() === normalized
      );
      if (exactMatch) return true;

      // Preserve the existing EAN-13 scale-label convention: first seven
      // digits identify the product and the next five contain the weight.
      if (normalized.length === 13 && /^\d{13}$/.test(normalized)) {
        const productCode = normalized.substring(0, 7);
        return this.products_pos.some(product =>
          String(product.code || "").trim() === productCode ||
          String(product.barcode || "").trim() === productCode
        );
      }

      return false;
    },

    handleHardwareBarcodeScan(code) {
      const normalized = String(code || "").trim();
      if (!normalized) return;
      if (this.timer) {
        clearTimeout(this.timer);
        this.timer = null;
      }
      this.search_input = normalized;
      this.search({ immediate: true, source: "hardware-scanner" });
    },
  },
};
