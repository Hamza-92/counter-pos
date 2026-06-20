<template>
  <div>
    <div class="stats-dashboard">
      <div class="stat-card in-stock">
        <div class="stat-icon-wrapper">
          <lucide-icon class="stat-icon" name="check-check" />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ metrics.in_stock }}</div>
          <div class="stat-label">{{ $t('In_Stock') }}</div>
        </div>
        <div class="stat-decoration"></div>
      </div>

      <div class="stat-card out-stock">
        <div class="stat-icon-wrapper">
          <lucide-icon class="stat-icon" name="x" />
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ metrics.out_stock }}</div>
          <div class="stat-label">{{ $t('Out_of_Stock') }}</div>
        </div>
        <div class="stat-decoration"></div>
      </div>
    </div>

    <b-card class="action-card shadow-sm">
      <div class="d-flex flex-wrap align-items-center">
        <b-button variant="primary" class="btn-action-primary mr-3 mb-2 d-inline-flex align-items-center" @click="syncStock" :disabled="syncing">
          <template v-if="!syncing">
            <lucide-icon class="mr-2" name="play" />
            {{ $t('Sync_Stock_Now') }}
          </template>
          <template v-else>
            <span class="mini-spinner mr-2"></span>
            {{ $t('Syncing') }}
          </template>
        </b-button>
        <b-button v-if="syncing && token" variant="warning" size="sm" class="btn-action-warning mr-2 mb-2" :disabled="stopping" @click="stopStock">
          <lucide-icon class="mr-1" name="square" />
          <span v-if="!stopping">{{ $t('Stop') || 'Stop' }}</span>
          <span v-else>{{ $t('Stopping') || 'Stopping' }}...</span>
        </b-button>
        <b-button variant="danger" size="sm" class="btn-action-danger mr-2 mb-2" :disabled="resetting" @click="resetSync">
          <lucide-icon class="mr-1" name="refresh-ccw" />
          <span v-if="!resetting">{{ $t('Reset_Sync_State') }}</span>
          <span v-else>{{ $t('Resetting') }}...</span>
        </b-button>
        <b-button variant="outline-secondary" class="ml-auto mb-2 btn-action-refresh" size="sm" @click="$emit('refreshed')">
          <lucide-icon class="mr-1" name="refresh-cw" />
          {{ $t('Refresh') }}
        </b-button>
      </div>
    </b-card>

    <b-card v-if="syncing && !progress.finished" class="progress-card shadow-sm">
      <div class="progress-header mb-3">
        <h6 class="mb-0 font-weight-bold">
          <lucide-icon class="mr-2 text-primary" name="loader" />
          {{ $t('Syncing_Products') }}
        </h6>
      </div>
      <b-progress :value="displayPercentage" :max="100" height="32px" show-progress animated class="progress-modern mb-3">
        <span class="progress-text">
          {{ displayPercentage }}%
          <span v-if="displayTotal > 0"> ({{ displayProcessed }}/{{ displayTotal }})</span>
        </span>
      </b-progress>
      <div class="progress-details" v-if="progress.failed_products > 0">
        <div class="progress-detail-item">
          <lucide-icon class="mr-2 text-danger" name="x" />
          <span class="text-danger">{{ $t('Errors') }}: {{ progress.failed_products }}</span>
          <b-link @click="$emit('view-logs')" class="ml-2">
            <lucide-icon class="mr-1" name="clipboard-list" />
            {{ $t('View_Logs') }}
          </b-link>
        </div>
      </div>
    </b-card>
  </div>
</template>

<script>
import moment from 'moment';
export default {
  data() {
    return {
      syncing: false,
      stopping: false,
      refreshing: false,
      resetting: false,
      token: '',
      poller: null,
      progress: { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0 },
      metrics: { in_stock: 0, out_stock: 0, last_sync: null },
      autoSync: false,
    };
  },
  computed: {
    displayTotal() {
      const v = Number(this.progress?.total_products ?? 0);
      return Number.isFinite(v) ? v : 0;
    },
    displayProcessed() {
      const direct = Number(this.progress?.processed);
      if (Number.isFinite(direct) && direct > 0) return direct;
      const s = Number(this.progress?.synced_products ?? 0);
      const f = Number(this.progress?.failed_products ?? 0);
      const p = (Number.isFinite(s) ? s : 0) + (Number.isFinite(f) ? f : 0);
      return p;
    },
    displayPercentage() {
      const direct = Number(this.progress?.percentage);
      if (Number.isFinite(direct) && direct > 0) return Math.max(0, Math.min(100, direct));
      const total = this.displayTotal;
      const processed = this.displayProcessed;
      if (!total) return 0;
      return Math.max(0, Math.min(100, Math.floor((processed / total) * 100)));
    },
  },
  methods: {
    load() {
      return this.fetchMetrics();
    },
    fetchMetrics() {
      return axios.get('woocommerce/stock-metrics').then(({ data }) => {
        this.metrics = data || { in_stock: 0, out_stock: 0, last_sync: null };
      }).catch(() => { this.metrics = { in_stock: 0, out_stock: 0, last_sync: null }; });
    },
    syncStock() {
      if (this.syncing) return;
      this.syncing = true;
      this.stopping = false;
      this.refreshing = false;
      this.lastProgressSignature = '';
      this.lastProgressChangeAt = Date.now();
      this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0 };
      axios.post('woocommerce/sync/stock').then(({ data }) => {
        if (data.ok && data.token) {
          this.token = data.token;
          this.startPolling();
        } else {
          this.toast('danger', this.$t('Sync_Failed'));
          this.syncing = false;
        }
      }).catch(() => { this.toast('danger', this.$t('Sync_Failed')); this.syncing = false; });
    },
    stopStock() {
      if (!this.token || this.stopping) return;
      this.stopping = true;
      axios.post('woocommerce/sync/stock/stop', { token: this.token })
        .catch(() => {})
        .finally(() => {
          // Let polling observe finished/cancelled state.
          this.stopping = false;
        });
    },
    startPolling() {
      if (this.poller) clearInterval(this.poller);
      this.poller = setInterval(this.fetchProgress, 5000);
      this.fetchProgress();
    },
    fetchProgress() {
      if (this.refreshing) return;
      if (!this.token) {
        this.syncing = false;
        if (this.poller) {
          clearInterval(this.poller);
          this.poller = null;
        }
        return;
      }
      this.refreshing = true;
      axios.get('woocommerce/sync/stock/progress', { params: { token: this.token } }).then(({ data }) => {
        if (data && data.state) {
          this.progress = data.state;

          if (this.progress.finished) {
            clearInterval(this.poller);
            this.poller = null;
            this.token = '';
            this.syncing = false;
            this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0 };
            const hadError = !!(data.state && data.state.error);
            this.toast(hadError ? 'danger' : 'success', hadError ? this.$t('Sync_Failed') : this.$t('Sync_Completed'));
            this.fetchMetrics();
            this.$emit('refreshed');
          }
        } else {
          // Token expired or invalid, stop polling
          clearInterval(this.poller);
          this.poller = null;
          this.token = '';
          this.syncing = false;
          this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0 };
        }
      }).catch(() => {
        // On error, stop polling and reset state
        clearInterval(this.poller);
        this.poller = null;
        this.token = '';
        this.syncing = false;
        this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0 };
      }).finally(() => { this.refreshing = false; });
    },
    
    toast(variant, msg) { this.$root.$bvToast.toast(msg, { title: this.$t('WooCommerce'), variant, solid: true }); },
    formatDate(v) { return v ? moment(v).format('YYYY-MM-DD HH:mm') : ''; },
    resetSync() {
      if (this.resetting) return;
      this.resetting = true;
      axios.post('woocommerce/reset-stock-sync')
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
  created() {
    // Reset any stale state on component creation
    this.syncing = false;
    this.token = '';
    if (this.poller) {
      clearInterval(this.poller);
      this.poller = null;
    }
    this.progress = { total_products: 0, synced_products: 0, failed_products: 0, percentage: 0 };
    this.load().finally(() => { this.$emit('ready'); });
  },
  beforeDestroy() {
    if (this.poller) {
      clearInterval(this.poller);
      this.poller = null;
    }
  }
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

.stat-value-small {
  font-size: 1.5rem;
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

.stat-card.in-stock .stat-icon-wrapper {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.in-stock .stat-decoration {
  background: #10b981;
}

.stat-card.in-stock .stat-value {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-card.out-stock .stat-icon-wrapper {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-card.out-stock .stat-decoration {
  background: #ef4444;
}

.stat-card.out-stock .stat-value {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
  box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
  transition: all 0.3s ease;
}

.btn-action-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 123, 255, 0.4);
}

.btn-action-warning,
.btn-action-refresh,
.btn-action-danger {
  border-radius: 8px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-action-warning:hover:not(:disabled),
.btn-action-refresh:hover:not(:disabled),
.btn-action-danger:hover:not(:disabled) {
  transform: translateY(-2px);
}

.progress-card {
  border-radius: 12px;
  border: none;
  padding: 1.5rem;
  background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
}

.progress-header {
  display: flex;
  align-items: center;
}

.progress-modern {
  border-radius: 10px;
  overflow: hidden;
  background: #e9ecef;
}

.progress-modern ::v-deep .progress-bar {
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  border-radius: 10px;
}

.progress-text {
  font-weight: 700;
  font-size: 14px;
  color: white;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.progress-details {
  padding-top: 0.5rem;
}

.progress-detail-item {
  display: flex;
  align-items: center;
  font-size: 14px;
}

.mini-spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(0, 123, 255, 0.2);
  border-top-color: #007bff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
