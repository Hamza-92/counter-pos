<template>
  <div>
    <b-tabs v-model="activeMiniTab" content-class="mt-3">
      <!-- Stocky -> WooCommerce -->
      <b-tab active>
        <template #title>
          <lucide-icon class="mr-2" name="arrow-right" />
          Stocky → WooCommerce
        </template>

        <div class="stats-dashboard">
          <div class="stat-card total-brands">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="tag" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ totalBrandsDisplay }}</div>
              <div class="stat-label">{{ $t('Total_Brands') }}</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card synced-brands">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="check-check" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ syncedBrandsDisplay }}</div>
              <div class="stat-label">{{ $t('Synced_Brands') }}</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card unsynced-brands">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="pause" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ unsyncedCountDisplay }}</div>
              <div class="stat-label">{{ $t('Not_Synced') }}</div>
            </div>
            <div class="stat-decoration"></div>
          </div>
        </div>

        <b-card class="action-card shadow-sm">
          <div class="d-flex flex-wrap align-items-center">
            <b-button
              variant="info"
              class="btn-action-primary mr-3 mb-2 d-inline-flex align-items-center"
              @click="manualSync('push', false)"
              :disabled="syncing"
            >
              <template v-if="!syncing">
                <lucide-icon class="mr-2" name="play" />
                {{ $t('Run_Manual_Sync_Now') }}
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                {{ $t('Syncing') }}
              </template>
            </b-button>
            <b-button variant="danger" size="sm" class="btn-action-danger mr-2 mb-2" :disabled="resetting" @click="resetSync">
              <lucide-icon class="mr-1" name="refresh-ccw" />
              <span v-if="!resetting">{{ $t('Reset_Sync_State') }}</span>
              <span v-else>{{ $t('Resetting') }}...</span>
            </b-button>
          </div>
        </b-card>
      </b-tab>

      <!-- WooCommerce -> Stocky -->
      <b-tab>
        <template #title>
          <lucide-icon class="mr-2" name="arrow-left" />
          WooCommerce → Stocky
        </template>

        <div class="stats-dashboard">
          <div class="stat-card total-brands">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="tag" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ pullStats.total_woo != null ? pullStats.total_woo : '—' }}</div>
              <div class="stat-label">Total in WooCommerce</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card synced-brands">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="check-check" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ pullStats.imported != null ? pullStats.imported : '—' }}</div>
              <div class="stat-label">Imported to Stocky</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card unsynced-brands">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="download" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ pullStats.not_imported != null ? pullStats.not_imported : '—' }}</div>
              <div class="stat-label">Not Yet Imported</div>
            </div>
            <div class="stat-decoration"></div>
          </div>
        </div>

        <b-card class="action-card shadow-sm">
          <div class="d-flex flex-wrap align-items-center">
            <b-button
              variant="success"
              class="btn-action-secondary mr-3 mb-2 d-inline-flex align-items-center"
              @click="manualSync('pull', false)"
              :disabled="syncing"
            >
              <template v-if="!syncing">
                <lucide-icon class="mr-2" name="play" />
                Sync WooCommerce to Stocky
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                {{ $t('Syncing') }}
              </template>
            </b-button>
            <b-button variant="danger" size="sm" class="btn-action-danger mr-2 mb-2" :disabled="resetting" @click="resetSync">
              <lucide-icon class="mr-1" name="refresh-ccw" />
              <span v-if="!resetting">{{ $t('Reset_Sync_State') }}</span>
              <span v-else>{{ $t('Resetting') }}...</span>
            </b-button>
          </div>
        </b-card>
      </b-tab>
    </b-tabs>
  </div>
</template>

<script>
export default {
  data() {
    return {
      activeMiniTab: 0,
      syncing: false,
      resetting: false,
      totalBrands: null,
      unsyncedCount: null,
      pullStats: { total_woo: null, imported: null, not_imported: null },
    };
  },
  computed: {
    totalBrandsDisplay() { return this.totalBrands != null ? this.totalBrands : '—'; },
    unsyncedCountDisplay() { return this.unsyncedCount != null ? this.unsyncedCount : '—'; },
    syncedBrands() { if (this.totalBrands == null || this.unsyncedCount == null) return null; return Math.max(0, (this.totalBrands || 0) - (this.unsyncedCount || 0)); },
    syncedBrandsDisplay() { return this.syncedBrands != null ? this.syncedBrands : '—'; },
    unsyncedAvailable() { return this.unsyncedCount != null && this.unsyncedCount > 0; },
  },
  methods: {
    load() {
      const p1 = axios.get('brands', { params: { limit: 1, SortField: 'id', SortType: 'desc' } }).then(({ data }) => { this.totalBrands = data.totalRows != null ? data.totalRows : null; });
      const p2 = axios.get('woocommerce/brands/unsynced-count').then(({ data }) => { this.unsyncedCount = data.count; });
      const p3 = this.loadPullStats();
      return Promise.all([p1, p2, p3]);
    },
    loadPullStats() {
      return axios.get('woocommerce/brands/pull-stats')
        .then(({ data }) => {
          this.pullStats = {
            total_woo: data.total_woo != null ? data.total_woo : null,
            imported: data.imported != null ? data.imported : 0,
            not_imported: data.not_imported != null ? data.not_imported : null,
          };
        })
        .catch(() => {
          this.pullStats = { total_woo: null, imported: null, not_imported: null };
        });
    },
    manualSync(mode, onlyUnsynced) {
      this.syncing = true;
      const m = mode === 'pull' ? 'pull' : 'push';
      let url = `woocommerce/sync/brands?mode=${m}`;
      if (m === 'push' && onlyUnsynced) url += '&only_unsynced=1';
      axios.post(url).then(({ data }) => {
        if (data.ok) this.toast('success', this.$t('Sync_Completed')); else this.toast('danger', this.$t('Sync_Failed'));
      }).catch(() => {
        this.toast('danger', this.$t('Sync_Failed'));
      }).finally(() => {
        this.syncing = false;
        this.load();
        this.$emit('refreshed');
      });
    },
    toast(variant, msg) { this.$root.$bvToast.toast(msg, { title: this.$t('WooCommerce'), variant, solid: true }); },
    resetSync() {
      if (this.resetting) return;
      this.resetting = true;
      axios.post('woocommerce/reset-brands-sync')
        .then(() => {
          this.toast('success', this.$t('Successfully_Updated'));
          this.load();
          this.$emit('refreshed');
        })
        .catch(() => {
          this.toast('danger', this.$t('Sync_Failed'));
        })
        .finally(() => {
          this.resetting = false;
        });
    },
  },
  created() { this.load().finally(() => { this.$emit('ready'); }); }
};
</script>

<style scoped>
.stats-dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  position: relative;
  background: white;
  border-radius: 16px;
  padding: 1.75rem;
  display: flex;
  align-items: center;
  gap: 1.25rem;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
}

.stat-decoration {
  position: absolute;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  opacity: 0.08;
  top: -30px;
  right: -30px;
}

.stat-icon-wrapper {
  width: 64px;
  height: 64px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 1;
  flex-shrink: 0;
}

.stat-icon {
  font-size: 28px;
  color: white;
}

.stat-content {
  flex: 1;
  z-index: 1;
}

.stat-value {
  font-size: 2.25rem;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 0.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-card.total-brands .stat-icon-wrapper {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.total-brands .stat-decoration {
  background: #667eea;
}

.stat-card.synced-brands .stat-icon-wrapper {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.synced-brands .stat-decoration {
  background: #10b981;
}

.stat-card.synced-brands .stat-value {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-card.unsynced-brands .stat-icon-wrapper {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-card.unsynced-brands .stat-decoration {
  background: #f59e0b;
}

.stat-card.unsynced-brands .stat-value {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.action-card {
  border-radius: 12px;
  border: none;
  padding: 1.5rem;
  background: #f8f9fa;
}

.btn-action-primary {
  border-radius: 8px;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
  transition: all 0.3s ease;
}

.btn-action-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(23, 162, 184, 0.4);
}

.btn-action-danger {
  border-radius: 8px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-action-danger:hover:not(:disabled) {
  transform: translateY(-2px);
}

.mini-spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(23, 162, 184, 0.2);
  border-top-color: #17a2b8;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
