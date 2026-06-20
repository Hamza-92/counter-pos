<template>
  <div class="app-admin-wrap layout-sidebar-large clearfix" :class="{ 
    'vertical-layout': getSidebarLayout === 'vertical',
    'vertical-collapsed': getSidebarLayout === 'vertical' && getVerticalSidebarCollapsed
  }">
    <!-- Conditional Top Navigation -->
    <vertical-top-nav v-if="getSidebarLayout === 'vertical'" />
    <top-nav v-else />

    <!-- Conditional Sidebar Rendering -->
    <vertical-sidebar v-if="getSidebarLayout === 'vertical'" />
    <sidebar v-else />

    <main :class="{ 'with-vertical-sidebar': getSidebarLayout === 'vertical' }">
      <div
        :class="{ 
          'sidenav-open': getSideBarToggleProperties.isSideNavOpen && getSidebarLayout !== 'vertical',
          'with-vertical-topnav': getSidebarLayout === 'vertical'
        }"
        class="main-content-wrap d-flex flex-column flex-grow-1"
      >
        <transition name="page" mode="out-in">
          <router-view />
        </transition>

        <div class="flex-grow-1"></div>
        <appFooter />
      </div>
    </main>
  </div>
</template>

<script>
import Sidebar from "./Sidebar";
import VerticalSidebar from "./VerticalSidebar";
import TopNav from "./TopNav";
import VerticalTopNav from "./VerticalTopNav";
import appFooter from "../common/footer";
import { mapGetters, mapActions } from "vuex";

export default {
  components: {
    Sidebar,
    VerticalSidebar,
    TopNav,
    VerticalTopNav,
    appFooter,
  },
  data() {
    return {};
  },
  computed: {
    ...mapGetters(["getSideBarToggleProperties", "getSidebarLayout", "getVerticalSidebarCollapsed"]),
  },
  methods: {},
};
</script>
<style scoped>
/* Layout adjustments for vertical sidebar */
.vertical-layout main.with-vertical-sidebar {
  margin-left: 260px;
  transition: margin-left 0.3s ease;
}

.vertical-layout.vertical-collapsed main.with-vertical-sidebar {
  margin-left: 0;
}

/* Adjust content for vertical topnav */
.with-vertical-topnav {
  /* padding-top removed for flush layout */
}

/* Mobile & Tablet adjustments */
@media (max-width: 1024px) {
  .vertical-layout main.with-vertical-sidebar {
    margin-left: 0;
  }
}

/* RTL Support */
html[dir="rtl"] .vertical-layout main.with-vertical-sidebar {
  margin-left: 0;
  margin-right: 260px;
}

html[dir="rtl"] .vertical-layout.vertical-collapsed main.with-vertical-sidebar {
  margin-right: 70px;
}
</style>