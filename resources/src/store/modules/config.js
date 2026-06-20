const DEFAULT_PRIMARY_COLOR = '#663399';

function readStoredPrimaryColor() {
  try {
    const v = localStorage.getItem('primaryColor');
    if (v && /^#([0-9a-f]{3}){1,2}$/i.test(v)) return v;
  } catch (e) {}
  return DEFAULT_PRIMARY_COLOR;
}

function hexToRgb(hex) {
  let h = hex.replace('#', '');
  if (h.length === 3) h = h.split('').map((c) => c + c).join('');
  const num = parseInt(h, 16);
  return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 };
}

function shade(hex, percent) {
  const { r, g, b } = hexToRgb(hex);
  const t = percent < 0 ? 0 : 255;
  const p = Math.abs(percent);
  const nr = Math.round((t - r) * p) + r;
  const ng = Math.round((t - g) * p) + g;
  const nb = Math.round((t - b) * p) + b;
  return `rgb(${nr}, ${ng}, ${nb})`;
}

function rgbaFromHex(hex, alpha) {
  const { r, g, b } = hexToRgb(hex);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

export function applyPrimaryColor(color) {
  if (typeof document === 'undefined') return;
  const darker = shade(color, -0.15);
  const lighter = shade(color, 0.15);
  const soft = rgbaFromHex(color, 0.12);

  const css = `
    :root {
      --primary-color: ${color};
      --primary-color-darker: ${darker};
      --primary-color-lighter: ${lighter};
      --primary-color-soft: ${soft};
    }

    /* Text / background / border utilities */
    .text-primary, a.text-primary { color: ${color} !important; }
    .bg-primary { background-color: ${color} !important; color: #fff !important; }
    .border-primary { border-color: ${color} !important; }
    .badge-primary { background-color: ${color} !important; color: #fff !important; }

    /* Buttons */
    .btn-primary,
    .btn-primary:focus,
    .btn-primary:not(:disabled):not(.disabled).active,
    .btn-primary:not(:disabled):not(.disabled):active {
      background-color: ${color} !important;
      border-color: ${color} !important;
      color: #fff !important;
    }
    .btn-primary:hover { background-color: ${darker} !important; border-color: ${darker} !important; color: #fff !important; }
    .btn-outline-primary { color: ${color} !important; border-color: ${color} !important; }
    .btn-outline-primary:hover,
    .btn-outline-primary:not(:disabled):not(.disabled).active,
    .btn-outline-primary:not(:disabled):not(.disabled):active {
      background-color: ${color} !important;
      border-color: ${color} !important;
      color: #fff !important;
    }

    /* Links */
    a { color: ${color}; }
    a:hover { color: ${darker}; }

    /* Form controls */
    .form-control:focus { border-color: ${lighter} !important; box-shadow: 0 0 0 0.2rem ${soft} !important; }
    .custom-control-input:checked ~ .custom-control-label::before { background-color: ${color} !important; border-color: ${color} !important; }
    .custom-control-input:focus ~ .custom-control-label::before { box-shadow: 0 0 0 0.2rem ${soft} !important; }
    .custom-select:focus { border-color: ${lighter} !important; box-shadow: 0 0 0 0.2rem ${soft} !important; }
    .switch input:checked + .slider { background-color: ${color} !important; }
    .checkbox-primary input:checked ~ .checkmark,
    .checkbox.checkbox-primary input:checked ~ .checkmark { background-color: ${color} !important; border-color: ${color} !important; }
    .radio.radio-primary input:checked ~ .checkmark::after { background-color: ${color} !important; }

    /* Pagination */
    .page-item.active .page-link { background-color: ${color} !important; border-color: ${color} !important; color: #fff !important; }
    .page-link { color: ${color} !important; }
    .page-link:hover { color: ${darker} !important; }

    /* Nav pills / tabs */
    .nav-pills .nav-link.active, .nav-pills .show > .nav-link { background-color: ${color} !important; color: #fff !important; }
    .nav-tabs .nav-link.active { border-bottom-color: ${color} !important; color: ${color} !important; }
    .nav-tabs .nav-link:hover { border-bottom-color: ${color}; }

    /* Dropdown */
    .dropdown-item.active, .dropdown-item:active { background-color: ${color} !important; color: #fff !important; }
    .dropdown-menu-right .dropdown-item:hover { background-color: ${soft} !important; }

    /* Progress / spinners / loaders */
    .progress-bar { background-color: ${color} !important; }
    .spinner-border, .spinner-border.text-primary,
    .spinner-grow, .spinner-grow.text-primary { color: ${color} !important; }
    .module-loader .spinner, .module-loader .loader { border-top-color: ${color} !important; border-left-color: ${color} !important; }
    /* Pre-Vue master loader (from /css/master.css) */
    .loading {
      border-color: ${rgbaFromHex(color, 0.45)} !important;
      border-top-color: ${color} !important;
    }
    /* App.vue offline-sync + initial loaders */
    .global-sync-spinner,
    .initial-loader-overlay .global-sync-spinner {
      border-top-color: ${color} !important;
    }
    /* NProgress top bar + spinner */
    #nprogress .bar { background: ${color} !important; }
    #nprogress .peg { box-shadow: 0 0 10px ${color}, 0 0 5px ${color} !important; }
    #nprogress .spinner-icon { border-top-color: ${color} !important; border-left-color: ${color} !important; }
    /* Custom .spinner-primary / .spinner-bubble-primary / .loader-bubble-primary */
    .spinner-primary {
      background: linear-gradient(to right, ${color} 10%, rgba(255,255,255,0) 42%) !important;
    }
    .spinner-primary:before { background: ${color} !important; }
    .spinner-bubble-primary, .loader-bubble-primary { color: ${color} !important; }
    .spinner-glow-primary { background: ${rgbaFromHex(color, 0.45)} !important; }

    /* ============ GENERIC .nav-item.active (any sidebar wrapper) ============ */
    /* Paint the inner clickable child (the visible "pill"), not the <li>. */
    .nav-item.active > .nav-link,
    .nav-item.active > .nav-item-hold,
    .nav-item.active > a {
      background: linear-gradient(135deg, ${lighter} 0%, ${color} 100%) !important;
      color: #fff !important;
    }
    .nav-item.active > .nav-link .nav-icon,
    .nav-item.active > .nav-link .nav-text,
    .nav-item.active > .nav-link .feather,
    .nav-item.active > .nav-link .submenu-arrow,
    .nav-item.active > .nav-item-hold .nav-icon,
    .nav-item.active > .nav-item-hold .nav-text,
    .nav-item.active > .nav-item-hold .feather,
    .nav-item.active > a .nav-icon,
    .nav-item.active > a .nav-text,
    .nav-item.active > a .feather {
      color: #fff !important;
    }

    /* ============ SIDEBAR (large) ============ */
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item:hover,
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item:hover .nav-item-hold {
      color: ${color} !important;
    }
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active > .nav-item-hold,
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active > .nav-link,
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active > a {
      background: linear-gradient(135deg, ${lighter} 0%, ${color} 100%) !important;
    }
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active,
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active .nav-item-hold,
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active .nav-icon,
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active .nav-text,
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active .feather,
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active a {
      color: #fff !important;
    }
    .layout-sidebar-large .sidebar-left .navigation-left .nav-item.active .triangle {
      border-color: transparent transparent #fff transparent !important;
    }
    .layout-sidebar-large .sidebar-left-secondary .childNav li.nav-item a.open,
    .layout-sidebar-large .sidebar-left-secondary .childNav li.nav-item a.open .nav-icon,
    .layout-sidebar-large .sidebar-left-secondary .childNav li.nav-item a.router-link-active,
    .layout-sidebar-large .sidebar-left-secondary .childNav li.nav-item a.router-link-active .nav-icon {
      color: ${color} !important;
    }
    .layout-sidebar-large .sidebar-left-secondary .childNav li.nav-item a.open,
    .layout-sidebar-large .sidebar-left-secondary .childNav li.nav-item a.router-link-active {
      background: ${soft} !important;
    }
    .layout-sidebar-large .sidebar-left-secondary .childNav li.nav-item a:hover {
      background: ${soft} !important;
      color: ${color} !important;
    }

    /* ============ SIDEBAR (compact) ============ */
    .layout-sidebar-compact .sidebar-left .navigation-left .nav-item.active,
    .layout-sidebar-compact .sidebar-left .navigation-left .nav-item.active .nav-item-hold,
    .layout-sidebar-compact .sidebar-left .navigation-left .nav-item.active i,
    .layout-sidebar-compact .side-content-wrap .sidebar-left ul.ul-list-1 li.active > a,
    .layout-sidebar-compact .side-content-wrap .sidebar-left ul.ul-list-1 li a.router-link-active {
      color: ${color} !important;
    }
    .layout-sidebar-compact .sidebar-left .navigation-left .nav-item.active::before,
    .layout-sidebar-compact .sidebar-left .navigation-left .nav-item:hover::before {
      background: ${color} !important;
    }

    /* ============ HORIZONTAL / MENU ============ */
    .main-header .menu ul.menu li.nav-item.active > a,
    .main-header .menu ul.menu li.nav-item:hover > a,
    .main-header .header-part-right .user.dropdown .dropdown-toggle:hover,
    .main-header .header-icon:hover { color: ${color} !important; }

    /* ============ HEADER SEARCH / ICONS ============ */
    .main-header .search-bar input:focus { border-color: ${color} !important; }
    .main-header .header-icon:hover { color: ${color} !important; }

    /* ============ CARDS / DASHBOARD ============ */
    .card .card-title,
    .card-title.text-primary,
    .card .card-header .card-title { color: inherit; }
    .card.o-hidden .card-header.text-primary,
    .card .icon-primary,
    .card .text-primary { color: ${color} !important; }
    .card.bg-primary, .card.o-hidden.bg-primary,
    .widget-title.bg-primary { background-color: ${color} !important; color: #fff !important; }
    .icon-box.bg-primary, .icon-box .icon.bg-primary { background-color: ${color} !important; color: #fff !important; }

    /* ============ TABS ============ */
    .nav.nav-tabs .nav-item .nav-link.active {
      border-bottom: 2px solid ${color} !important;
      background: ${soft} !important;
      color: ${color} !important;
    }

    /* ============ CALENDAR / EVENTS ============ */
    .fc-event, .fc-event-dot { background-color: ${color} !important; border-color: ${color} !important; }

    /* ============ VUE-SELECT (v-select) ============ */
    .vs__dropdown-option--highlight,
    .vs__dropdown-option--selected,
    .vs__dropdown-option--highlight i,
    .vs__dropdown-option--selected i {
      background: ${color} !important;
      color: #fff !important;
    }
    .vs__dropdown-option:hover {
      background: ${soft} !important;
      color: ${color} !important;
    }
    .v-select.vs--open .vs__dropdown-toggle,
    .vs__dropdown-toggle:focus-within {
      border-color: ${lighter} !important;
      box-shadow: 0 0 0 0.2rem ${soft} !important;
    }

    /* ============ TAGS / CHIPS ============ */
    .ti-tag, .vue-tags-input .ti-tag { background: ${color} !important; color: #fff !important; }
    .ti-tag:hover { background: ${darker} !important; }

    /* ============ SLIDER (noUiSlider) ============ */
    .noUi-connect { background: ${color} !important; }
    .noUi-handle { border: 5px solid ${color} !important; }

    /* ============ INBOX / OTHER VIEWS ============ */
    .email-list .email-item.active,
    .email-list .email-item.unread .from { color: ${color} !important; }

    /* ============ ALERTS (primary) ============ */
    .alert-primary {
      background-color: ${soft} !important;
      border-color: ${color} !important;
      color: ${darker} !important;
    }
    .close:focus { box-shadow: 0 0 0 0.2rem ${rgbaFromHex(color, 0.5)} !important; }

    /* ============ CUSTOMIZER ============ */
    .customizer .handle { background: ${color} !important; color: #fff !important; }
    .customizer .card-header p { color: ${color}; }
    .customizer .layout-option.active { border-color: ${color} !important; background: ${soft} !important; }
    .customizer .layout-option.active .option-label,
    .customizer .layout-option .option-label i { color: ${color} !important; }

    /* ============ VERTICAL SIDEBAR (custom) ============ */
    .vertical-sidebar-wrapper .nav-link:hover {
      color: ${color} !important;
    }
    .vertical-sidebar-wrapper .nav-item.active > .nav-link {
      background: linear-gradient(135deg, ${lighter} 0%, ${color} 100%) !important;
      color: #fff !important;
    }
    .vertical-sidebar-wrapper .nav-item.active > .nav-link .nav-icon,
    .vertical-sidebar-wrapper .nav-item.active > .nav-link .nav-text,
    .vertical-sidebar-wrapper .nav-item.active > .nav-link .submenu-arrow {
      color: #fff !important;
    }
    .vertical-sidebar-wrapper .submenu-link::before,
    .vertical-sidebar-wrapper .nested-submenu {
      background: ${color} !important;
    }
    .vertical-sidebar-wrapper .nested-submenu {
      background: transparent !important;
      border-left: 2px solid ${rgbaFromHex(color, 0.15)} !important;
    }
    .vertical-sidebar-wrapper .submenu-link:hover {
      background: ${rgbaFromHex(color, 0.08)} !important;
      color: ${color} !important;
    }
    .vertical-sidebar-wrapper .submenu-link.router-link-active {
      color: ${color} !important;
      background: ${rgbaFromHex(color, 0.1)} !important;
    }
    .vertical-sidebar-wrapper .nested-link:hover {
      color: ${color} !important;
      background: ${rgbaFromHex(color, 0.05)} !important;
    }
    .vertical-sidebar-wrapper .nested-link.router-link-active {
      color: ${color} !important;
      background: ${rgbaFromHex(color, 0.1)} !important;
    }
    .vertical-sidebar-wrapper .logo-placeholder {
      background: linear-gradient(135deg, ${lighter} 0%, ${color} 100%) !important;
    }

    /* ============ DASHBOARD STATIC / PAGE ROOT ============ */
    .dashboard-page-root .dashboard-header,
    .dashboard-static .dashboard-header {
      background: linear-gradient(135deg, ${lighter} 0%, ${color} 100%) !important;
    }
    .dashboard-static .sales-card .stat-card-icon,
    .dashboard-static .invoices-card .stat-card-icon {
      background: linear-gradient(135deg, ${color} 0%, ${lighter} 100%) !important;
    }
    .dashboard-static .stat-card-link { color: ${color} !important; }
    .dashboard-static .stat-card-link:hover { color: ${darker} !important; }
    .dashboard-static .date-picker-btn:hover {
      border-color: ${color} !important;
      color: ${color} !important;
    }
    .dashboard-static .quick-wrap .btn:hover {
      box-shadow: 0 4px 8px ${rgbaFromHex(color, 0.25)} !important;
    }
    .dashboard-static .warehouse-filter .vs__dropdown-option--highlight,
    .dashboard-static .warehouse-filter .vs__dropdown-option--highlight i {
      background: ${color} !important;
      color: #fff !important;
    }
    .dashboard-page-root a,
    .dashboard-page-root .text-primary,
    .dashboard-static a:not(.btn),
    .dashboard-static .text-primary { color: ${color} !important; }
    .dashboard-page-root .bg-primary,
    .dashboard-static .bg-primary { background-color: ${color} !important; color: #fff !important; }

    /* ============ MISC ACCENTS ============ */
    ::selection { background: ${color}; color: #fff; }
    .breadcrumb-item.active { color: ${color}; }
    hr.divider-primary { border-top-color: ${color} !important; }
  `;

  let tag = document.getElementById('dynamic-primary-color');
  if (!tag) {
    tag = document.createElement('style');
    tag.id = 'dynamic-primary-color';
    document.head.appendChild(tag);
  }
  tag.innerHTML = css;
}

function readStoredCustomizeButtonVisible() {
  try {
    const v = localStorage.getItem('customizeButtonVisible');
    if (v === 'false') return false;
  } catch (e) {}
  return true;
}

function readStoredDarkMode() {
  try {
    const v = localStorage.getItem('darkMode');
    if (v === 'true') return true;
    if (v === 'false') return false;
  } catch (e) {}
  return false;
}

function persistDarkMode(value) {
  try {
    localStorage.setItem('darkMode', value ? 'true' : 'false');
  } catch (e) {}
}

const initialDark = readStoredDarkMode();

const state = {
  themeMode: {
    dark: initialDark,
    light: !initialDark,
    semi_dark: false,
    theme_color: 'lite-purple',
    layout: 'large-sidebar',
    rtl: false,
  },
  primaryColor: readStoredPrimaryColor(),
  customizeButtonVisible: readStoredCustomizeButtonVisible(),
};

const getters = {
  getThemeMode: (state) => state.themeMode,
  getPrimaryColor: (state) => state.primaryColor,
  getCustomizeButtonVisible: (state) => state.customizeButtonVisible,
};

const actions = {
  changeThemeMode({ commit }) {
    // Per-browser preference only — the toggleThemeMode mutation writes
    // to localStorage, which the initial state reader picks up on next
    // refresh. No backend round-trip, so each user/browser keeps its
    // own theme without affecting other users on the same backend.
    commit('toggleThemeMode');
  },
  changeThemeLayout({ commit }, data) {
    commit('toggleThemeLayout', data);
  },
  changeThemeRtl({ commit }) {
    commit('toggleThemeRtl');
  },
  setPrimaryColor({ commit }, color) {
    commit('setPrimaryColor', color);
    applyPrimaryColor(color);
  },
  initPrimaryColor({ state }) {
    applyPrimaryColor(state.primaryColor);
  },
  setCustomizeButtonVisible({ commit }, visible) {
    commit('setCustomizeButtonVisible', !!visible);
  },
};

const mutations = {
  toggleThemeMode: (state) => {
    state.themeMode.dark = !state.themeMode.dark;
    state.themeMode.light = !state.themeMode.dark;
    persistDarkMode(state.themeMode.dark);
  },
  setDarkMode: (state, darkMode) => {
    state.themeMode.dark = !!darkMode;
    state.themeMode.light = !state.themeMode.dark;
    persistDarkMode(state.themeMode.dark);
  },
  toggleThemeLayout(state, data) {
    state.themeMode.layout = data;
  },
  toggleThemeRtl(state) {
    state.themeMode.rtl = !state.themeMode.rtl;
  },
  setPrimaryColor(state, color) {
    state.primaryColor = color;
    try {
      localStorage.setItem('primaryColor', color);
    } catch (e) {}
  },
  setCustomizeButtonVisible(state, visible) {
    state.customizeButtonVisible = visible;
    try {
      localStorage.setItem('customizeButtonVisible', visible ? 'true' : 'false');
    } catch (e) {}
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
