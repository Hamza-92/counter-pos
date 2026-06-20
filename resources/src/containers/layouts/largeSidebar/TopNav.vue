<template>
  <div class="main-header">
    <div class="logo">
       <router-link to="/app/dashboard">
        <img v-if="currentUser && currentUser.logo" :src="'/images/'+currentUser.logo" alt width="60" height="60">
        <img v-else src="/images/logo.png" alt width="60" height="60">
       </router-link>
    </div>

    <div @click="sideBarToggle" class="menu-toggle">
      <div></div>
      <div></div>
      <div></div>
    </div>

    <div style="margin: auto"></div>

    <div class="header-part-right nav-right">
      <!-- POS Link -->
      <router-link 
        v-if="currentUserPermissions && currentUserPermissions.includes('Pos_view')"
        class="btn btn-primary btn-sm"
        to="/app/pos"
      >
        <lucide-icon name="calculator" />
        <span class="btn-text">POS</span>
      </router-link>

      <!-- Dark Mode Toggle -->
      <button 
        class="nav-icon-btn" 
        @click="toggleDarkMode" 
        :title="getThemeMode.dark ? 'Light Mode' : 'Dark Mode'"
      >
        <lucide-icon :name="getThemeMode.dark ? 'sun' : 'cloud-moon'" />
      </button>

      <!-- Fullscreen Toggle -->
      <button class="nav-icon-btn fullscreen-btn d-none d-sm-inline-flex" @click="handleFullScreen" title="Fullscreen">
        <lucide-icon name="maximize" />
      </button>

      <!-- Language Dropdown -->
      <div class="dropdown" v-if="show_language">
        <b-dropdown
          id="lang-dd"
          right
          toggle-class="dropdown-toggle-no-caret"
          no-caret
        >
          <template slot="button-content">
            <lucide-icon name="globe" />
          </template>
          <vue-perfect-scrollbar
            :settings="{ suppressScrollX: true, wheelPropagation: false }"
            class="dropdown-scroll"
          >
            <div class="lang-menu">
              <a 
                v-for="lang in getAvailableLanguages" 
                :key="lang.locale" 
                @click="SetLocal(lang.locale)"
                class="lang-item"
              >
                <img
                  :src="`/flags/${lang.flag}`"
                  :alt="lang.name"
                  class="flag-icon"
                />
                <span>{{ lang.name }}</span>
              </a>
            </div>
          </vue-perfect-scrollbar>
        </b-dropdown>
      </div>

      <!-- Notifications -->
      <div class="dropdown">
        <b-dropdown
          id="notif-dd"
          right
          toggle-class="dropdown-toggle-no-caret"
          no-caret
        >
          <template slot="button-content">
            <span class="badge badge-primary" v-if="notifs_alert > 0">1</span>
            <lucide-icon name="bell" />
          </template>
          <vue-perfect-scrollbar
            :settings="{ suppressScrollX: true, wheelPropagation: false }"
            class="dropdown-scroll"
          >
            <div class="notification-item" v-if="notifs_alert > 0">
              <div class="notif-icon">
                <lucide-icon class="text-primary" name="bell" />
              </div>
              <div class="notif-content" v-if="currentUserPermissions && currentUserPermissions.includes('Reports_quantity_alerts')">
                <router-link tag="a" to="/app/reports/quantity_alerts">
                  <p>{{ notifs_alert }} {{ $t('ProductQuantityAlerts') }}</p>
                </router-link>
              </div>
            </div>
          </vue-perfect-scrollbar>
        </b-dropdown>
      </div>

      <!-- User Dropdown -->
      <div class="dropdown">
        <b-dropdown
          id="user-dd"
          right
          toggle-class="user-dropdown-toggle"
          no-caret
          variant="link"
        >
          <template slot="button-content">
            <div class="user-avatar">
              <img
                v-if="currentUser && currentUser.avatar"
                :src="'/images/avatar/' + currentUser.avatar"
                alt="user"
              />
              <img v-else src="/images/avatar/avatar-default.jpg" alt="user" />
            </div>
          </template>
          <div class="user-dropdown-menu">
            <div class="dropdown-header">
              <lucide-icon class="mr-1" name="lock" />
              <span v-if="currentUser">{{ currentUser.username }}</span>
            </div>
            <router-link to="/app/profile" class="dropdown-item">
              {{ $t('profil') }}
            </router-link>
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('setting_system')"
              to="/app/settings/System_settings"
              class="dropdown-item"
            >
              {{ $t('Settings') }}
            </router-link>
            <a class="dropdown-item" href="#" @click.prevent="logoutUser">
              {{ $t('logout') }}
            </a>
          </div>
        </b-dropdown>
      </div>
    </div>
  </div>

  <!-- header top menu end -->
</template>
<script>
import Util from "./../../../utils";
// import Sidebar from "./Sidebar";
import { isMobile } from "mobile-device-detect";
import { mapGetters, mapActions } from "vuex";
import { mixin as clickaway } from "vue-clickaway";
// import { setTimeout } from 'timers';

export default {
  mixins: [clickaway],
 
  data() {
  
    return {
     
      isDisplay: true,
      isStyle: true,
      isSearchOpen: false,
      isMouseOnMegaMenu: true,
      isMegaMenuOpen: false,
      is_Load:false,
     
    };
  },
 
   computed: {
     
     ...mapGetters([
       "currentUser",
      "getSideBarToggleProperties",
      "currentUserPermissions",
      "notifs_alert",
      "show_language",
      "getAvailableLanguages"
    ]),
    ...mapGetters("config", ["getThemeMode"]),

  },

  methods: {
    
    ...mapActions([
      "changeSecondarySidebarProperties",
      "changeSidebarProperties",
      "logout",
    ]),
    ...mapActions("config", ["changeThemeMode"]),

    SetLocal(locale) {
      this.$i18n.locale = locale;
      this.$store.dispatch("setLanguage", locale);
      Fire.$emit("ChangeLanguage");
      window.location.reload();
    },

    handleFullScreen() {
      Util.toggleFullScreen();
    },

    toggleDarkMode() {
      this.changeThemeMode();
      // Apply dark theme class to body element
      if (this.getThemeMode.dark) {
        document.body.classList.add('dark-theme');
      } else {
        document.body.classList.remove('dark-theme');
      }
    },

    logoutUser() {
      this.logout();
    },

    closeMegaMenu() {
      this.isMegaMenuOpen = false;
    },
    toggleMegaMenu() {
      this.isMegaMenuOpen = !this.isMegaMenuOpen;
    },
    toggleSearch() {
      this.isSearchOpen = !this.isSearchOpen;
    },

    sideBarToggle(el) {
      if (
        this.getSideBarToggleProperties.isSideNavOpen &&
        this.getSideBarToggleProperties.isSecondarySideNavOpen &&
        isMobile
      ) {
        this.changeSidebarProperties();
        this.changeSecondarySidebarProperties();
      } else if (
        this.getSideBarToggleProperties.isSideNavOpen &&
        this.getSideBarToggleProperties.isSecondarySideNavOpen
      ) {
        this.changeSecondarySidebarProperties();
      } else if (this.getSideBarToggleProperties.isSideNavOpen) {
        this.changeSidebarProperties();
      } else if (
        !this.getSideBarToggleProperties.isSideNavOpen &&
        !this.getSideBarToggleProperties.isSecondarySideNavOpen &&
        !this.getSideBarToggleProperties.isActiveSecondarySideNav
      ) {
        this.changeSidebarProperties();
      } else if (
        !this.getSideBarToggleProperties.isSideNavOpen &&
        !this.getSideBarToggleProperties.isSecondarySideNavOpen
      ) {

        this.changeSidebarProperties();
        this.changeSecondarySidebarProperties();
      }
    }
  },

  mounted() {
    // Apply dark theme class on mount if dark mode is enabled
    if (this.getThemeMode.dark) {
      document.body.classList.add('dark-theme');
    } else {
      document.body.classList.remove('dark-theme');
    }
  }
};
</script>

<style>
/* Non-scoped styles for Bootstrap Vue dropdown buttons */
.main-header .dropdown .dropdown-toggle-no-caret,
.main-header .dropdown-toggle-no-caret,
.main-header .dropdown-toggle-no-caret.btn,
.main-header button.dropdown-toggle-no-caret {
  padding: 0 !important;
  background: white !important;
  border: 1px solid #e5e7eb !important;
  width: 36px !important;
  height: 36px !important;
  border-radius: 8px !important;
  color: #6b7280 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  line-height: 1 !important;
  font-size: 16px !important;
  transition: all 0.3s !important;
  position: relative !important;
}

.main-header .dropdown .dropdown-toggle-no-caret:hover,
.main-header .dropdown .dropdown-toggle-no-caret:focus,
.main-header .dropdown .dropdown-toggle-no-caret:active,
.main-header .dropdown-toggle-no-caret:hover,
.main-header .dropdown-toggle-no-caret:focus,
.main-header .dropdown-toggle-no-caret:active,
.main-header .dropdown-toggle-no-caret.btn:hover,
.main-header .dropdown-toggle-no-caret.btn:focus,
.main-header .dropdown-toggle-no-caret.btn:active,
.main-header button.dropdown-toggle-no-caret:hover,
.main-header button.dropdown-toggle-no-caret:focus,
.main-header button.dropdown-toggle-no-caret:active {
  background: #f9fafb !important;
  color: #663399 !important;
  border-color: #663399 !important;
  box-shadow: none !important;
  outline: none !important;
}

/* Dark mode for dropdown buttons */
body.dark-theme .main-header .dropdown .dropdown-toggle-no-caret,
body.dark-theme .main-header .dropdown-toggle-no-caret,
body.dark-theme .main-header .dropdown-toggle-no-caret.btn,
body.dark-theme .main-header button.dropdown-toggle-no-caret {
  background: #1a1a2e !important;
  border-color: #2d2d44 !important;
  color: #d0d0d0 !important;
}

body.dark-theme .main-header .dropdown .dropdown-toggle-no-caret:hover,
body.dark-theme .main-header .dropdown .dropdown-toggle-no-caret:focus,
body.dark-theme .main-header .dropdown .dropdown-toggle-no-caret:active,
body.dark-theme .main-header .dropdown-toggle-no-caret:hover,
body.dark-theme .main-header .dropdown-toggle-no-caret:focus,
body.dark-theme .main-header .dropdown-toggle-no-caret:active,
body.dark-theme .main-header .dropdown-toggle-no-caret.btn:hover,
body.dark-theme .main-header .dropdown-toggle-no-caret.btn:focus,
body.dark-theme .main-header .dropdown-toggle-no-caret.btn:active,
body.dark-theme .main-header button.dropdown-toggle-no-caret:hover,
body.dark-theme .main-header button.dropdown-toggle-no-caret:focus,
body.dark-theme .main-header button.dropdown-toggle-no-caret:active {
  background: #2d2d44 !important;
  border-color: #764ba2 !important;
  color: #fff !important;
}

/* Dropdown menu styling */
.main-header .dropdown-menu {
  border-radius: 12px !important;
  border: 1px solid #e5e7eb !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
  padding: 0 !important;
  min-width: 280px !important;
  margin-top: 8px !important;
}

.main-header #notif-dd .dropdown-menu {
  min-width: 320px !important;
}

.main-header #lang-dd .dropdown-menu {
  min-width: 220px !important;
}

.main-header #user-dd .dropdown-menu {
  min-width: 200px !important;
}

/* Dark mode dropdown menu */
body.dark-theme .main-header .dropdown-menu {
  background: #1a1a2e !important;
  border-color: #2d2d44 !important;
}
</style>

<style scoped>
.nav-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-primary {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  background: #fff;
  color: #663399;
  border: 1px solid #e5e7eb;
  transition: all 0.3s;
  box-shadow: none;
}

.btn-primary:hover,
.btn-primary:focus,
.btn-primary:active,
.btn-primary:not(:disabled):not(.disabled):active,
.btn-primary:not(:disabled):not(.disabled).active {
  background: #f9fafb !important;
  color: #663399 !important;
  border-color: #663399 !important;
  box-shadow: none !important;
  outline: none !important;
}

.btn-text {
  font-weight: 600;
}
/* Dark mode toggle button - same design as VerticalTopNav */
.nav-icon-btn {
  width: 36px;
  height: 36px;
  padding: 0;
  border: 1px solid #e5e7eb;
  background: white;
  color: #6b7280;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.3s;
  position: relative;
}

.nav-icon-btn:hover {
  background: #f9fafb;
  color: #663399;
  border-color: #663399;
}

.nav-icon-btn:focus,
.nav-icon-btn:active {
  outline: none !important;
  box-shadow: none !important;
}

.nav-icon-btn:focus-visible {
  outline: none !important;
}

.nav-icon-btn i {
  font-size: 16px;
  line-height: 1;
}

.nav-icon-btn i {
  font-size: 16px;
  line-height: 1;
}

.badge-container {
  position: relative;
}

.badge {
  position: absolute;
  top: -8px;
  right: -8px;
  min-width: 18px;
  height: 18px;
  padding: 2px 6px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.2;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-dropdown-toggle {
  padding: 0;
  background: transparent;
  border: none;
}

.user-avatar {
  width: 36px;
  height: 36px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s;
  border-radius: 50%;
}

.user-avatar:hover {
  opacity: 0.8;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.dropdown-scroll {
  max-height: 300px;
  overflow-y: auto;
}

.lang-menu {
  padding: 10px;
}

.lang-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  border-radius: 6px;
  cursor: pointer;
  color: #333;
  text-decoration: none;
  transition: background 0.3s;
}

.lang-item:hover {
  background: #f5f5f5;
}

.flag-icon {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  object-fit: cover;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 15px 20px;
  border-bottom: 1px solid #f0f0f0;
  transition: background 0.3s;
  cursor: pointer;
}

.notification-item:hover {
  background: #f9fafb;
}

.notification-item:last-child {
  border-bottom: none;
}

.notif-icon {
  font-size: 24px;
  line-height: 1;
  flex-shrink: 0;
}

.notif-content {
  flex: 1;
}

.notif-content p {
  margin: 0;
  font-size: 14px;
  color: #666;
  line-height: 1.5;
}

.notif-content a {
  color: #663399;
  text-decoration: none;
  display: block;
}

.notif-content a:hover {
  color: #5a2a80;
}

.user-dropdown-menu {
  min-width: 200px;
}

.dropdown-header {
  padding: 15px;
  border-bottom: 1px solid #e0e0e0;
  font-weight: 600;
  color: #333;
}

.dropdown-item {
  padding: 12px 20px;
  color: #666;
  text-decoration: none;
  display: block;
  transition: all 0.3s;
}

.dropdown-item:hover {
  background: #f5f5f5;
  color: #663399;
}

/* Dark Mode */
body.dark-theme .nav-icon-btn,
body.dark-theme .btn-primary {
  background: #1a1a2e;
  border-color: #2d2d44;
  color: #d0d0d0;
}

body.dark-theme .nav-icon-btn:hover,
body.dark-theme .btn-primary:hover,
body.dark-theme .btn-primary:focus,
body.dark-theme .btn-primary:active {
  background: #2d2d44 !important;
  border-color: #764ba2 !important;
  color: #fff !important;
  box-shadow: none !important;
}

body.dark-theme .lang-item {
  color: #e0e0e0;
}

body.dark-theme .lang-item:hover {
  background: #2d2d44;
}

body.dark-theme .notification-item {
  border-bottom-color: #2d2d44;
}

body.dark-theme .notification-item:hover {
  background: #2d2d44;
}

body.dark-theme .notif-content p {
  color: #d0d0d0;
}

body.dark-theme .notif-content a {
  color: #a78bfa;
}

body.dark-theme .notif-content a:hover {
  color: #c4b5fd;
}

body.dark-theme .dropdown-header {
  border-bottom-color: #2d2d44;
  color: #e0e0e0;
}

body.dark-theme .dropdown-item {
  color: #d0d0d0;
}

body.dark-theme .dropdown-item:hover {
  background: #2d2d44;
  color: #fff;
}

/* Mobile adjustments */
@media (max-width: 768px) {
  /* Hide fullscreen button on mobile */
  .fullscreen-btn {
    display: none !important;
  }

  .btn-text {
    display: none;
  }

  .btn-primary {
    padding: 8px 12px;
    font-size: 13px;
  }

  /* Make POS button look like icon buttons on mobile */
  .nav-right .btn.btn-primary {
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    background: #fff;
    color: #663399;
    border: 1px solid #e5e7eb;
  }

  .nav-right .btn.btn-primary:hover,
  .nav-right .btn.btn-primary:focus,
  .nav-right .btn.btn-primary:active {
    background: #f9fafb;
    color: #663399;
    border-color: #663399;
    box-shadow: none;
  }

  .nav-right .btn.btn-primary i {
    font-size: 16px;
    color: inherit;
    line-height: 1;
  }
}

/* Remove outline from header icons when clicked */
.header-icon:focus,
.header-icon:active {
  outline: none !important;
  box-shadow: none !important;
}

.header-icon:focus-visible {
  outline: none !important;
}
</style>



