const state = {
  sidebarToggleProperties: {
    isSideNavOpen: true,
    isSecondarySideNavOpen: false,
    isActiveSecondarySideNav: false
  },
  sidebarLayout: localStorage.getItem('sidebarLayout') || 'vertical', // 'horizontal' or 'vertical'
  verticalSidebarCollapsed: localStorage.getItem('verticalSidebarCollapsed') === 'true' || false
};

const getters = {
  getSideBarToggleProperties: state => state.sidebarToggleProperties,
  getSidebarLayout: state => state.sidebarLayout,
  getVerticalSidebarCollapsed: state => state.verticalSidebarCollapsed
};

const actions = {
  changeSidebarProperties({commit}) {
    commit("toggleSidebarProperties");
  },
  changeSecondarySidebarProperties({commit}) {
    commit("toggleSecondarySidebarProperties");
  },
  changeSecondarySidebarPropertiesViaMenuItem({commit}, data) {
    commit("toggleSecondarySidebarPropertiesViaMenuItem", data);
  },
  changeSecondarySidebarPropertiesViaOverlay({commit}) {
    commit("toggleSecondarySidebarPropertiesViaOverlay");
  },
  setSidebarLayout({commit}, layout) {
    commit("setSidebarLayout", layout);
  },
  setVerticalSidebarCollapsed({commit}, collapsed) {
    commit("setVerticalSidebarCollapsed", collapsed);
  }
};

const mutations = {
  toggleSidebarProperties: state =>
    (state.sidebarToggleProperties.isSideNavOpen = !state
      .sidebarToggleProperties.isSideNavOpen),

  toggleSecondarySidebarProperties: state =>
    (state.sidebarToggleProperties.isSecondarySideNavOpen = !state
      .sidebarToggleProperties.isSecondarySideNavOpen),
  toggleSecondarySidebarPropertiesViaMenuItem(state, data) {
    state.sidebarToggleProperties.isSecondarySideNavOpen = data;
    state.sidebarToggleProperties.isActiveSecondarySideNav = data;
  },
  toggleSecondarySidebarPropertiesViaOverlay(state) {
    state.sidebarToggleProperties.isSecondarySideNavOpen = !state
      .sidebarToggleProperties.isSecondarySideNavOpen;
  },
  setSidebarLayout(state, layout) {
    state.sidebarLayout = layout;
    localStorage.setItem('sidebarLayout', layout);
  },
  setVerticalSidebarCollapsed(state, collapsed) {
    state.verticalSidebarCollapsed = collapsed;
    localStorage.setItem('verticalSidebarCollapsed', collapsed);
  }
};

export default {
  state,
  getters,
  actions,
  mutations
};
