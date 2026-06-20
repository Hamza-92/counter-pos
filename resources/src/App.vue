<template>
  <div>
    <!-- Initial app loader: shown immediately after refresh until core data is ready -->
    <div v-if="!Loading" class="initial-loader-overlay">
      <div class="initial-loader-dots">
        <span></span><span></span><span></span>
      </div>
    </div>

    <router-view v-if="Loading" class="app-fade-in"></router-view>

    <!-- Global offline sync fullscreen loader -->
    <div v-if="globalSyncActive" class="global-sync-overlay">
      <div class="global-sync-card">
        <div class="global-sync-spinner"></div>
        <h3 class="global-sync-title">
          {{ $t ? ($t('pos.Syncing_offline_sales') || 'Syncing offline sales') : 'Syncing offline sales' }}
        </h3>
        <p class="global-sync-subtitle">
          {{ $t ? ($t('pos.Syncing_offline_sales_help') || 'Please wait while your offline sales are being synchronized.') : 'Please wait while your offline sales are being synchronized.' }}
        </p>
      </div>
    </div>

    <customizer v-if="show_language && !isPosPage && getCustomizeButtonVisible"></customizer>
  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";

export default {
  data() {
    return {
      Loading:false,
      globalSyncActive: false,
    };
  },
  computed: {
    
    ...mapGetters("config", ["getThemeMode", "getCustomizeButtonVisible"]),
    ...mapGetters(["isAuthenticated","show_language","currentUser"]),
    themeName() {
      return this.getThemeMode.dark ? "dark-theme" : " ";
    },
    rtl() {
      return this.getThemeMode.rtl ? "rtl" : " ";
    },

    isPosPage() {
      const p = String(this.$route.path || '');
      return p === '/app/pos' || p.startsWith('/app/pos_') || p.startsWith('/app/pos/');
    },
    titleTemplate() {
      return `%s | ${this.currentUser?.page_title_suffix || "Ultimate Inventory With POS"}`;
    }
  },

  metaInfo() {
    return {
      // if no subcomponents specify a metaInfo.title, this title will be used
      title: "Stocky",
      titleTemplate: this.titleTemplate,

      bodyAttrs: {
        class: [this.themeName, "text-left"]
      },
      htmlAttrs: {
        dir: this.rtl
      },
      
    };
  },

  beforeDestroy() {
    // Clean up listeners
    try {
      if (typeof window !== 'undefined' && window.Fire && window.Fire.$off) {
        window.Fire.$off('offline-sync:start', this.onGlobalSyncStart);
        window.Fire.$off('offline-sync:end', this.onGlobalSyncEnd);
        window.Fire.$off('offline-sync:auto-result', this.onGlobalSyncResult);
      }
    } catch (e) {}
  },
  methods:{
    ...mapActions([
      "refreshUserPermissions",
    ]),
    ...mapActions("config", ["initPrimaryColor"]),
    async initializeApp() {
      try {
        this.initPrimaryColor();
        // Ensure initial permissions and user info are fetched
        await this.refreshUserPermissions(this.$i18n);
      } catch (e) {
        // ignore; guards/interceptors will handle routing on auth errors
      } finally {
        this.Loading = true;
        // Signal that the app rendered initial route and is allowed to hide loader when no pending requests
        if (window) {
          window.__appReadyToHideLoader = true;
          if (typeof window.__hideInitialLoaderIfDone === 'function') {
            window.__hideInitialLoaderIfDone();
          }
        }
      }
    },
    onGlobalSyncStart() {
      this.globalSyncActive = true;
    },
    onGlobalSyncEnd() {
      this.globalSyncActive = false;
    },
    onGlobalSyncResult(payload) {
      try {
        const syncedCount = Number(payload && payload.syncedCount || 0);
        const lastError = payload && payload.lastError;
        // If at least one offline sale was synced successfully and there is no error,
        // reload the current page to reflect updated data everywhere – except when
        // the user is on the POS screen with a potentially active cart. In that
        // case, POS itself will decide if/when to reload via its own confirmation
        // flow to avoid disrupting an in‑progress checkout.
        if (syncedCount > 0 && !lastError) {
          const isPosRoute = this.$route &&
            (this.$route.name === 'pos' ||
             String(this.$route.path || '').includes('/app/pos'));
          if (!isPosRoute) {
            if (typeof window !== 'undefined' && window.location && typeof window.location.reload === 'function') {
              window.location.reload();
            }
          }
        }
      } catch (e) {}
    },
  },

  beforeMount() {
    // Replace timeout with awaited initialization
    this.initializeApp();
  },
  
  mounted() {
    // Listen for global offline sync start/end/result events
    try {
      if (typeof window !== 'undefined' && window.Fire && window.Fire.$on) {
        window.Fire.$on('offline-sync:start', this.onGlobalSyncStart);
        window.Fire.$on('offline-sync:end', this.onGlobalSyncEnd);
        window.Fire.$on('offline-sync:auto-result', this.onGlobalSyncResult);
      }
    } catch (e) {}
  },
};
</script>
<style scoped>
.initial-loader-overlay {
  position: fixed;
  inset: 0;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.global-sync-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  backdrop-filter: blur(4px);
}

.initial-loader-dots {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.initial-loader-dots span {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #8b5cf6;
  display: inline-block;
  animation: initial-loader-dots 1.2s ease-in-out infinite;
}

.initial-loader-dots span:nth-child(1) { animation-delay: 0s; }
.initial-loader-dots span:nth-child(2) { animation-delay: 0.15s; }
.initial-loader-dots span:nth-child(3) { animation-delay: 0.3s; }

@keyframes initial-loader-dots {
  0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
  40%           { transform: scale(1);   opacity: 1;   }
}

.app-fade-in {
  animation: app-fade-in 0.5s ease-out both;
}

@keyframes app-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}

.global-sync-card {
  background: linear-gradient(135deg, #111827, #1f2933);
  border-radius: 18px;
  padding: 24px 32px;
  box-shadow:
    0 20px 40px rgba(0, 0, 0, 0.45),
    0 0 0 1px rgba(148, 163, 184, 0.25);
  display: flex;
  flex-direction: column;
  align-items: center;
  max-width: 340px;
  width: 90%;
  text-align: center;
  color: #e5e7eb;
}

.global-sync-spinner {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  border: 3px solid rgba(148, 163, 184, 0.35);
  border-top-color: #38bdf8;
  animation: global-sync-spin 0.9s linear infinite;
  margin-bottom: 16px;
}

.global-sync-title {
  font-size: 1.05rem;
  font-weight: 600;
  margin: 0 0 6px;
}

.global-sync-subtitle {
  font-size: 0.85rem;
  opacity: 0.85;
  margin: 0;
}

@keyframes global-sync-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
