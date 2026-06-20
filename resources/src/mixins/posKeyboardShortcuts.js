/**
 * POS Keyboard Shortcuts Mixin
 * --------------------------------------------------------------------
 * Adds optional keyboard shortcuts to the POS screen WITHOUT modifying
 * any existing logic. The mixin only invokes methods that already exist
 * on the host component (pos.vue). It is fully opt-in: the listener does
 * nothing unless the user enables shortcuts in POS Settings.
 *
 * Persistence: per-device localStorage key `pos_keyboard_shortcuts_enabled`
 * (no backend / database changes). Default = OFF, so existing installs
 * see no behavior change after upgrade.
 *
 * Safety guarantees:
 *   - Listener is attached on mounted() and removed on beforeDestroy().
 *   - Events are ignored when focus is inside an input/textarea/select
 *     or a contenteditable element, so existing @keyup handlers on the
 *     tax / discount / shipping / search inputs keep working unchanged.
 *   - Only calls host methods that exist; missing methods are skipped.
 */

export const POS_SHORTCUTS_STORAGE_KEY = "pos_keyboard_shortcuts_enabled";

export function posShortcutsEnabled() {
  try {
    // Tri-state: missing key → default ON (so new devices get shortcuts
    // without a settings detour). "1" / "0" still respect an explicit
    // user choice from the POS Settings toggle.
    const v = localStorage.getItem(POS_SHORTCUTS_STORAGE_KEY);
    if (v === null) return true;
    return v === "1";
  } catch (e) {
    return true;
  }
}

export function setPosShortcutsEnabled(value) {
  try {
    localStorage.setItem(POS_SHORTCUTS_STORAGE_KEY, value ? "1" : "0");
  } catch (e) {
    /* ignore storage errors */
  }
}

// Shortcut definitions used both by the listener and the help modal.
// Each entry: { id, keys (display), match(event), action(vm) }
export const POS_SHORTCUTS = [
  {
    id: "search",
    keys: "F2",
    descriptionKey: "Shortcut_Focus_Search",
    descriptionFallback: "Focus product search",
    match: (e) => e.key === "F2",
    action: (vm) => {
      // The live class on the modern POS shell input is
      // `.pos-shell-search-input`; the previous `.search-input` selector
      // never matched, so F2 silently did nothing. Keep `.search-input`
      // as a fallback for any older skin.
      const el =
        document.querySelector(".pos-shell-search-input") ||
        document.querySelector(".search-input");
      if (el && typeof el.focus === "function") {
        el.focus();
        if (typeof el.select === "function") el.select();
      }
    },
  },
  {
    id: "payment",
    keys: "F4",
    descriptionKey: "Shortcut_Open_Payment",
    descriptionFallback: "Open payment modal",
    match: (e) => e.key === "F4",
    action: (vm) => {
      if (typeof vm.openModernPaymentModal === "function" && vm.details && vm.details.length) {
        vm.openModernPaymentModal();
      }
    },
  },
  {
    id: "hold",
    keys: "F6",
    descriptionKey: "Shortcut_Hold_Sale",
    descriptionFallback: "Hold sale (draft)",
    match: (e) => e.key === "F6",
    action: (vm) => {
      if (typeof vm.Submit_Draft === "function") vm.Submit_Draft();
    },
  },
  {
    id: "recall",
    keys: "F7",
    descriptionKey: "Shortcut_Recall_Sale",
    descriptionFallback: "Recall held sales",
    match: (e) => e.key === "F7",
    action: (vm) => {
      if (typeof vm.loadDraftSale === "function") vm.loadDraftSale();
    },
  },
  {
    id: "customer",
    keys: "F8",
    descriptionKey: "Shortcut_Quick_Customer",
    descriptionFallback: "Quick add customer",
    match: (e) => e.key === "F8",
    action: (vm) => {
      if (typeof vm.Quick_Add_Client === "function") vm.Quick_Add_Client();
    },
  },
  {
    id: "print",
    keys: "F9",
    descriptionKey: "Shortcut_Print_Receipt",
    descriptionFallback: "Print last receipt",
    match: (e) => e.key === "F9",
    action: (vm) => {
      // Prefer the host's "print last receipt" helper which re-opens the
      // receipt modal for the most recent sale id. `print_pos()` alone
      // requires the receipt modal's #invoice-POS element to already be
      // in the DOM and silently returns otherwise — so it appeared
      // broken once the modal was dismissed.
      if (typeof vm.print_last_receipt === "function") {
        vm.print_last_receipt();
      } else if (typeof vm.print_pos === "function") {
        vm.print_pos();
      }
    },
  },
  {
    id: "clear",
    keys: "Esc",
    descriptionKey: "Shortcut_Clear_Cart",
    descriptionFallback: "Clear cart (with confirmation)",
    match: (e) => e.key === "Escape",
    action: (vm) => {
      if (!vm.details || !vm.details.length) return;
      // Prefer the confirmation flow; only fall back to direct reset for
      // hosts that haven't wired up the modal.
      if (typeof vm.confirmClearCart === "function") {
        vm.confirmClearCart();
      } else if (typeof vm.Reset_Pos === "function") {
        vm.Reset_Pos();
      }
    },
  },
  {
    id: "inc",
    keys: "Ctrl + ArrowUp",
    descriptionKey: "Shortcut_Increase_Last",
    descriptionFallback: "Increase quantity of last item in cart",
    match: (e) => e.ctrlKey && e.key === "ArrowUp",
    action: (vm) => {
      if (!vm.details || !vm.details.length) return;
      const last = vm.details[vm.details.length - 1];
      if (last && typeof vm.increment === "function") vm.increment(last.detail_id);
    },
  },
  {
    id: "dec",
    keys: "Ctrl + ArrowDown",
    descriptionKey: "Shortcut_Decrease_Last",
    descriptionFallback: "Decrease quantity of last item in cart",
    match: (e) => e.ctrlKey && e.key === "ArrowDown",
    action: (vm) => {
      if (!vm.details || !vm.details.length) return;
      const last = vm.details[vm.details.length - 1];
      if (last && typeof vm.decrement === "function") vm.decrement(last, last.detail_id);
    },
  },
  {
    id: "remove",
    keys: "Ctrl + Delete",
    descriptionKey: "Shortcut_Remove_Last",
    descriptionFallback: "Remove last item from cart",
    match: (e) => e.ctrlKey && e.key === "Delete",
    action: (vm) => {
      if (!vm.details || !vm.details.length) return;
      const last = vm.details[vm.details.length - 1];
      if (last && typeof vm.delete_Product_Detail === "function") {
        vm.delete_Product_Detail(last.detail_id);
      }
    },
  },
  {
    id: "help",
    keys: "Shift + ?",
    descriptionKey: "Shortcut_Show_Help",
    descriptionFallback: "Show this shortcuts help",
    match: (e) => e.shiftKey && (e.key === "?" || e.key === "/"),
    action: (vm) => {
      if (vm.$bvModal && typeof vm.$bvModal.show === "function") {
        vm.$bvModal.show("pos-keyboard-shortcuts-help");
      }
    },
  },
];

function isTypingTarget(target) {
  if (!target) return false;
  const tag = (target.tagName || "").toUpperCase();
  if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") return true;
  if (target.isContentEditable) return true;
  return false;
}

export default {
  mounted() {
    // Stored directly on the instance (not in data) to avoid Vue 2's
    // reactivity warning for keys prefixed with "_".
    this._posShortcutsHandler = null;
    const handler = (e) => {
      // Opt-in: do nothing unless the cashier explicitly enabled shortcuts.
      if (!posShortcutsEnabled()) return;

      // When any Bootstrap modal is open (payment modal, confirmation
      // modals, etc.) let the modal own the keyboard. We attach with
      // `capture: true`, so calling preventDefault here would block the
      // modal's own Esc-to-close behaviour AND still fire the cart-clear
      // shortcut — exactly the bug where pressing Esc from inside the
      // open payment modal was wiping the cart. Returning early defers
      // to the modal naturally.
      try {
        if (
          typeof document !== "undefined" &&
          document.body &&
          document.body.classList.contains("modal-open")
        ) {
          return;
        }
      } catch (e2) { /* ignore */ }

      // Never hijack typing in form fields — preserves existing
      // @keyup handlers on tax / discount / shipping / search inputs.
      // Exception: Escape and F-keys are still handled even from inputs
      // because cashiers expect them to work globally.
      const fromInput = isTypingTarget(e.target);
      const isFunctionKey = /^F[0-9]{1,2}$/.test(e.key) || e.key === "Escape";
      if (fromInput && !isFunctionKey) return;

      for (const shortcut of POS_SHORTCUTS) {
        if (shortcut.match(e)) {
          e.preventDefault();
          e.stopPropagation();
          try {
            shortcut.action(this);
          } catch (err) {
            // Never let a shortcut error break the POS page.
            // eslint-disable-next-line no-console
            console.warn("[POS shortcut] action failed:", shortcut.id, err);
          }
          return;
        }
      }
    };
    this._posShortcutsHandler = handler;
    try {
      window.addEventListener("keydown", handler, true);
    } catch (e) {
      /* ignore */
    }
  },
  beforeDestroy() {
    try {
      if (this._posShortcutsHandler) {
        window.removeEventListener("keydown", this._posShortcutsHandler, true);
        this._posShortcutsHandler = null;
      }
    } catch (e) {
      /* ignore */
    }
  },
};
