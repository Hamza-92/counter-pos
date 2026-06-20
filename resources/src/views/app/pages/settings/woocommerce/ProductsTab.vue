<template>
  <div>
    <b-tabs v-model="activeMiniTab" content-class="mt-3">
      <!-- Stocky -> WooCommerce -->
      <b-tab active>
        <template #title>
          <lucide-icon class="mr-2" name="arrow-right" />
          Stocky → WooCommerce
        </template>

        <div class="d-flex justify-content-end mb-2">
          <b-form-checkbox
            v-model="syncOnlyUnsynced"
            switch
            :disabled="isSyncActive"
          >
            {{ $t('Sync_Only_Unsynced') }}
          </b-form-checkbox>
        </div>

        <div class="stats-dashboard">
          <div class="stat-card total-products">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="barcode" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ totalProductsDisplay }}</div>
              <div class="stat-label">{{ $t('Total_Products') }}</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card synced-products">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="check-check" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ syncedProductsDisplay }}</div>
              <div class="stat-label">{{ $t('Synced_Products') }}</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card unsynced-products">
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

        <b-card class="action-card shadow-sm mb-4">
          <div class="d-flex flex-wrap align-items-center">
            <b-button variant="info" class="btn-action-primary mr-3 mb-2 d-inline-flex align-items-center" @click="manualSync('push', syncOnlyUnsynced)" :disabled="isSyncActive">
              <template v-if="!isSyncActive">
                <lucide-icon class="mr-2" name="play" />
                {{ $t('Run_Manual_Sync_Now') }}
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                {{ $t('Syncing') }}
              </template>
            </b-button>
            <b-button v-if="showStopSync" variant="warning" size="sm" class="btn-action-warning mr-2 mb-2" :disabled="stopping" @click="stopSync">
              <lucide-icon class="mr-1" name="square" />
              <span v-if="!stopping">Stop Sync</span>
              <span v-else>Stopping...</span>
            </b-button>
            <b-button v-if="isSyncActive" variant="outline-secondary" size="sm" class="btn-action-refresh mr-2 mb-2" :disabled="refreshing" @click="fetchProgress">
              <lucide-icon class="mr-1" name="refresh-cw" />
              <span v-if="!refreshing">{{ $t('Refresh') }}</span>
              <span v-else>{{ $t('Refresh') }}...</span>
            </b-button>
            <b-button variant="danger" size="sm" class="btn-action-danger mr-2 mb-2" :disabled="resetting" @click="resetSync">
              <lucide-icon class="mr-1" name="refresh-ccw" />
              <span v-if="!resetting">{{ $t('Reset_Sync_State') }}</span>
              <span v-else>{{ $t('Resetting') }}...</span>
            </b-button>
            <b-button variant="secondary" size="sm" class="mr-2 mb-2" :disabled="fixingCategories" @click="fixProductCategories">
              <template v-if="!fixingCategories">
                <lucide-icon class="mr-1" name="folder" />
                Fix Uncategorized Products
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                Fixing...
              </template>
            </b-button>
          </div>
        </b-card>

        <b-card v-if="isSyncActive && syncMode === 'push' && !progress.finished" class="progress-card shadow-sm">
          <div class="progress-header mb-3">
            <h6 class="mb-0 font-weight-bold">
              <lucide-icon class="mr-2 text-primary" name="loader" />
              {{ $t('Syncing_Products') }}
            </h6>
          </div>
          <b-progress :value="displayPercentage" :max="100" height="32px" show-progress animated class="progress-modern mb-3">
            <span class="progress-text">
              {{ displayPercentage }}%
              <span v-if="displayTotal > 0"> · {{ displayProcessed }}/{{ displayTotal }} products</span>
            </span>
          </b-progress>
          <div class="progress-details">
            <div class="progress-detail-item mb-2" v-if="displayTotal > 0">
              <lucide-icon class="mr-2 text-primary" name="info" />
              <span class="text-muted">Progress:</span>
              <strong class="ml-1">{{ displayProcessed }}/{{ displayTotal }} products</strong>
            </div>
            <div class="progress-detail-item mb-2" v-if="progress.current_sku || progress.current_product_id">
              <lucide-icon class="mr-2 text-primary" name="barcode" />
              <span class="text-muted">{{ $t('Product') }}:</span>
              <strong class="ml-1">{{ progress.current_sku || `#${progress.current_product_id}` }}</strong>
            </div>
            <div class="progress-detail-item mb-2" v-if="progress.stage">
              <lucide-icon class="mr-2 text-info" name="info" />
              <span class="text-muted">Stage:</span>
              <strong class="ml-1">{{ progress.stage }}</strong>
            </div>
            <div class="progress-detail-item mb-2" v-if="stopping">
              <lucide-icon class="mr-2 text-warning" name="info" />
              <span class="text-warning">Stopping… current item will finish then the sync will stop.</span>
            </div>
            <div class="progress-detail-item" v-if="progress.failed_products > 0">
              <lucide-icon class="mr-2 text-danger" name="x-circle" />
              <span class="text-danger">{{ $t('Errors') }}: {{ progress.failed_products }}</span>
              <b-link @click="$emit('view-logs')" class="ml-2">
                <lucide-icon class="mr-1" name="clipboard-list" />
                {{ $t('View_Logs') }}
              </b-link>
            </div>
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
          <div class="stat-card total-products">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="shopping-cart" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ pullStats.total_woo != null ? pullStats.total_woo : '—' }}</div>
              <div class="stat-label">Total in WooCommerce</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card synced-products">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="check-check" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ pullStats.imported != null ? pullStats.imported : '—' }}</div>
              <div class="stat-label">Imported to Stocky</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card unsynced-products">
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

        <b-card class="action-card shadow-sm mb-4">
          <div class="d-flex flex-wrap align-items-center">
            <b-button variant="success" class="btn-action-secondary mr-3 mb-2 d-inline-flex align-items-center" @click="manualSync('pull', false)" :disabled="isSyncActive">
              <template v-if="!isSyncActive">
                <lucide-icon class="mr-2" name="play" />
                Sync Products from WooCommerce
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                {{ $t('Syncing') }}
              </template>
            </b-button>
            <b-button v-if="showStopSync" variant="warning" size="sm" class="btn-action-warning mr-2 mb-2" :disabled="stopping" @click="stopSync">
              <lucide-icon class="mr-1" name="square" />
              <span v-if="!stopping">Stop Sync</span>
              <span v-else>Stopping...</span>
            </b-button>
            <b-button v-if="isSyncActive" variant="outline-secondary" size="sm" class="btn-action-refresh mr-2 mb-2" :disabled="refreshing" @click="fetchProgress">
              <lucide-icon class="mr-1" name="refresh-cw" />
              <span v-if="!refreshing">{{ $t('Refresh') }}</span>
              <span v-else>{{ $t('Refresh') }}...</span>
            </b-button>
            <b-button variant="danger" size="sm" class="btn-action-danger mr-2 mb-2" :disabled="resetting" @click="resetSync">
              <lucide-icon class="mr-1" name="refresh-ccw" />
              <span v-if="!resetting">{{ $t('Reset_Sync_State') }}</span>
              <span v-else>{{ $t('Resetting') }}...</span>
            </b-button>
          </div>

          <hr class="my-3" />

          <div class="text-muted small mb-2">
            <lucide-icon class="mr-1" name="alert-triangle" />
            Order import troubleshooting
          </div>
          <div class="d-flex flex-wrap align-items-center">
            <b-button variant="primary" size="sm" class="mr-2 mb-2" :disabled="pullingOrders || isSyncActive" @click="pullOrders">
              <template v-if="!pullingOrders">
                <lucide-icon class="mr-1" name="shopping-cart" />
                Sync Orders from WooCommerce
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                Pulling orders...
              </template>
            </b-button>
            <b-button variant="info" size="sm" class="mr-2 mb-2" :disabled="autoLinking || isSyncActive" @click="autoLinkBySku">
              <template v-if="!autoLinking">
                <lucide-icon class="mr-1" name="link" />
                Auto-Link Products by SKU
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                Linking...
              </template>
            </b-button>
            <b-button variant="outline-secondary" size="sm" class="mr-2 mb-2" :disabled="loadingUnmapped" @click="openUnmappedModal">
              <template v-if="!loadingUnmapped">
                <lucide-icon class="mr-1" name="clipboard-list" />
                View Unmapped Items
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                Loading...
              </template>
            </b-button>
          </div>
        </b-card>

        <b-modal v-model="unmappedModal" size="xl" title="Unmapped Items Report" hide-footer scrollable>
          <div v-if="!unmappedReport" class="text-center text-muted py-4">
            <span class="mini-spinner mr-2"></span> Loading...
          </div>
          <div v-else>
            <p class="text-muted small">
              Items below are why orders may fail with <strong>"Order contains unmapped products"</strong>.
              Use <em>Auto-Link Products by SKU</em> first to fix the easy ones, then sync products from WooCommerce to import what's still missing.
            </p>

            <h6 class="mt-3 font-weight-bold">
              <lucide-icon class="mr-1" name="x-circle" />
              Failed order line items
              <b-badge variant="danger" class="ml-1">{{ unmappedReport.order_failures.length }}</b-badge>
            </h6>
            <div v-if="!unmappedReport.order_failures.length" class="text-muted small mb-3">No recent failures.</div>
            <b-table v-else small striped responsive
              :items="unmappedReport.order_failures"
              :fields="orderFailureFields"
              class="mb-3"
            />

            <h6 class="mt-4 font-weight-bold">
              <lucide-icon class="mr-1" name="package" />
              Stocky products without WooCommerce link
              <b-badge variant="warning" class="ml-1">{{ unmappedReport.unlinked_products.total }}</b-badge>
            </h6>
            <div class="text-muted small">Showing latest {{ unmappedReport.unlinked_products.sample.length }} of {{ unmappedReport.unlinked_products.total }}.</div>
            <b-table v-if="unmappedReport.unlinked_products.sample.length" small striped responsive
              :items="unmappedReport.unlinked_products.sample"
              :fields="unlinkedProductFields"
              class="mb-3"
            />

            <h6 class="mt-4 font-weight-bold">
              <lucide-icon class="mr-1" name="layers" />
              Stocky variants without WooCommerce link
              <b-badge variant="warning" class="ml-1">{{ unmappedReport.unlinked_variants.total }}</b-badge>
            </h6>
            <div class="text-muted small">Showing latest {{ unmappedReport.unlinked_variants.sample.length }} of {{ unmappedReport.unlinked_variants.total }}.</div>
            <b-table v-if="unmappedReport.unlinked_variants.sample.length" small striped responsive
              :items="unmappedReport.unlinked_variants.sample"
              :fields="unlinkedVariantFields"
              class="mb-3"
            />

            <div class="d-flex justify-content-end mt-3">
              <b-button variant="outline-secondary" size="sm" class="mr-2" @click="loadUnmappedReport">
                <lucide-icon class="mr-1" name="refresh-cw" />
                Refresh
              </b-button>
              <b-button variant="info" size="sm" :disabled="autoLinking" @click="autoLinkBySku">
                <lucide-icon class="mr-1" name="link" />
                Auto-Link by SKU
              </b-button>
            </div>
          </div>
        </b-modal>

        <b-card v-if="isSyncActive && syncMode === 'pull' && !progress.finished" class="progress-card shadow-sm">
          <div class="progress-header mb-3">
            <h6 class="mb-0 font-weight-bold">
              <lucide-icon class="mr-2 text-primary" name="loader" />
              Syncing Products (Woo → Stocky)
            </h6>
          </div>
          <b-progress :value="displayPercentage" :max="100" height="32px" show-progress animated class="progress-modern mb-3">
            <span class="progress-text">
              {{ displayPercentage }}%
              <span v-if="displayTotal > 0"> ({{ displayProcessed }}/{{ displayTotal }})</span>
            </span>
          </b-progress>
          <div class="progress-details">
            <div class="progress-detail-item mb-2" v-if="progress.current_sku || progress.current_woocommerce_id">
              <lucide-icon class="mr-2 text-primary" name="barcode" />
              <span class="text-muted">{{ $t('Product') }}:</span>
              <strong class="ml-1">{{ progress.current_sku || `#${progress.current_woocommerce_id}` }}</strong>
            </div>
            <div class="progress-detail-item mb-2" v-if="progress.stage">
              <lucide-icon class="mr-2 text-info" name="info" />
              <span class="text-muted">Stage:</span>
              <strong class="ml-1">{{ progress.stage }}</strong>
            </div>
            <div class="progress-detail-item mb-2" v-if="stopping">
              <lucide-icon class="mr-2 text-warning" name="info" />
              <span class="text-warning">Stopping… current item will finish then the sync will stop.</span>
            </div>
            <div class="progress-detail-item" v-if="progress.failed_products > 0">
              <lucide-icon class="mr-2 text-danger" name="x-circle" />
              <span class="text-danger">{{ $t('Errors') }}: {{ progress.failed_products }}</span>
              <b-link @click="$emit('view-logs')" class="ml-2">
                <lucide-icon class="mr-1" name="clipboard-list" />
                {{ $t('View_Logs') }}
              </b-link>
            </div>
          </div>
        </b-card>
      </b-tab>
    </b-tabs>
  </div>
</template>

<script>
import moment from 'moment';

export default {
  data() {
    return {
      activeMiniTab: 0,
      syncMode: null, // 'push' | 'pull'
      syncing: false,
      resetting: false,
      fixingCategories: false,
      stopping: false,
      pendingCancel: false,
      refreshing: false,
      lastProgressFetchStartedAt: 0,
      syncOnlyUnsynced: false,
      token: '',
      syncJobId: null,
      syncStatus: null,
      poller: null,
      fastPoller: null,
      fastPollsRemaining: 0,
      lastProgressSignature: '',
      lastProgressChangeAt: 0,
      progress: { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0, created: 0, updated: 0 },
      totalProducts: null,
      unsyncedCount: null,
      pullStats: { total_woo: null, imported: null, not_imported: null },
      pullingOrders: false,
      autoLinking: false,
      loadingUnmapped: false,
      unmappedModal: false,
      unmappedReport: null,
      orderFailureFields: [
        { key: 'woo_product_id', label: 'Woo Product ID' },
        { key: 'woo_variation_id', label: 'Woo Variation ID' },
        { key: 'sku', label: 'SKU' },
        { key: 'count', label: 'Failures' },
        { key: 'last_order_id', label: 'Last Order' },
        { key: 'last_seen_at', label: 'Last Seen' },
      ],
      unlinkedProductFields: [
        { key: 'id', label: 'ID' },
        { key: 'name', label: 'Name' },
        { key: 'code', label: 'SKU / Code' },
      ],
      unlinkedVariantFields: [
        { key: 'id', label: 'ID' },
        { key: 'product_id', label: 'Product ID' },
        { key: 'name', label: 'Variant' },
        { key: 'code', label: 'SKU / Code' },
      ],
    };
  },
  computed: {
    totalProductsDisplay() { return this.totalProducts != null ? this.totalProducts : '—'; },
    unsyncedCountDisplay() { return this.unsyncedCount != null ? this.unsyncedCount : '—'; },
    syncedProducts() { if (this.totalProducts == null || this.unsyncedCount == null) return null; return Math.max(0, (this.totalProducts || 0) - (this.unsyncedCount || 0)); },
    syncedProductsDisplay() { return this.syncedProducts != null ? this.syncedProducts : '—'; },
    unsyncedAvailable() { return this.unsyncedCount != null && this.unsyncedCount > 0; },
    displayTotal() {
      const v = Number(this.progress?.total_products ?? 0);
      return Number.isFinite(v) ? v : 0;
    },
    displayProcessed() {
      const v = Number(this.progress?.processed ?? this.progress?.synced_products ?? 0);
      return Number.isFinite(v) ? v : 0;
    },
    displayPercentage() {
      const direct = Number(this.progress?.percentage);
      // If backend still reports 0 but we have processed/total, compute from processed to avoid “stuck at 0%”.
      if (Number.isFinite(direct) && direct > 0) return Math.max(0, Math.min(100, direct));
      const total = this.displayTotal;
      const processed = this.displayProcessed;
      if (!total) return 0;
      return Math.max(0, Math.min(100, Math.floor((processed / total) * 100)));
    },
    showStopSync() {
      const st = String(this.syncStatus || '').toLowerCase();
      // Show immediately once user starts sync (even before API responds with job id/token).
      if (this.syncing && !this.progress?.finished) return true;

      // DB-based
      if (this.syncJobId) {
        if (st === 'running' || st === 'cancelling') return true;
        // If status hasn't been fetched yet, but we know we're syncing, show the button.
        return !st && this.syncing && !this.progress?.finished;
      }

      // Legacy token-based fallback: if a token-based sync is running, still allow stopping.
      return !!this.token && this.syncing && !this.progress?.finished;
    },
    isSyncActive() {
      // DB-based: if we have a sync job id, treat sync as active even after reload.
      return !!this.syncJobId || this.syncing;
    },
  },
  watch: {
    syncOnlyUnsynced(val) {
      try {
        localStorage.setItem('woo_products_push_only_unsynced', val ? '1' : '0');
      } catch (e) {}
    }
  },
  methods: {
    storageKey() {
      return this.syncMode === 'pull' ? 'woo_products_pull_job_id' : 'woo_products_push_job_id';
    },
    trySendCancelSignal() {
      const token = this.token;
      const jobId = this.syncJobId;
      if (jobId) {
        axios.post(`woo-sync/${jobId}/cancel`).catch(() => {});
        return true;
      }
      if (token) {
        axios.post('woocommerce/sync/products/stop', { token }).catch(() => {});
        return true;
      }
      return false;
    },
    restoreRunningJob() {
      // Restore last running job (supports reload during sync).
      try {
        const pullStored = localStorage.getItem('woo_products_pull_job_id');
        const pushStored = localStorage.getItem('woo_products_push_job_id');
        const pullId = pullStored ? Number(pullStored) : null;
        const pushId = pushStored ? Number(pushStored) : null;

        if (pullId && Number.isFinite(pullId) && pullId > 0) {
          this.syncMode = 'pull';
          this.syncJobId = pullId;
        } else if (pushId && Number.isFinite(pushId) && pushId > 0) {
          this.syncMode = 'push';
          this.syncJobId = pushId;
        }

        if (this.syncJobId) {
          this.syncing = true;
          this.syncStatus = 'running';
          this.startPolling();
          return;
        }
      } catch (e) {}
    },
    normalizeProgressState(state) {
      const s = state || {};
      const total = Number(s.total_products ?? s.total ?? 0);
      const processed = Number(s.processed ?? s.synced_products ?? s.synced ?? 0);

      let percentage = Number(s.percentage ?? s.percent);
      if (!Number.isFinite(percentage)) {
        percentage = total > 0 ? Math.floor((processed / total) * 100) : 0;
      }

      return {
        ...s,
        total_products: Number.isFinite(total) ? total : 0,
        processed: Number.isFinite(processed) ? processed : 0,
        percentage: Math.max(0, Math.min(100, Number.isFinite(percentage) ? percentage : 0)),
      };
    },
    load() {
      const p1 = axios.get('products', { params: { limit: 1 } }).then(({ data }) => { this.totalProducts = data.totalRows != null ? data.totalRows : null; });
      const p2 = axios.get('woocommerce/unsynced-count').then(({ data }) => {
        this.unsyncedCount = data.count;
        if (!(this.unsyncedCount != null && this.unsyncedCount > 0)) {
          this.syncOnlyUnsynced = false;
        }
      });
      const p3 = this.loadPullStats();
      return Promise.all([p1, p2, p3]);
    },
    loadPullStats() {
      return axios.get('woocommerce/products/pull-stats')
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
      if (this.syncing) return;
      this.syncMode = mode === 'pull' ? 'pull' : 'push';
      this.syncing = true;
      this.stopping = false;
      this.pendingCancel = false;
      this.refreshing = false;
      this.syncStatus = 'starting';
      this.lastProgressSignature = '';
      this.lastProgressChangeAt = Date.now();
      this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0, created: 0, updated: 0 };
      // Start polling immediately so progress appears/updates without manual Refresh,
      // even during the short window before POST returns ids.
      this.startPolling(true);
      let url = `woocommerce/sync/products?mode=${this.syncMode}`;
      if (onlyUnsynced) url += '&only_unsynced=1';
      // Ensure UI updates (spinner/stop button) before network work begins.
      this.$nextTick(() => {
        axios.post(url).then(({ data }) => {
          const jobId = data ? (data.sync_job_id ?? data.syncJobId ?? null) : null;
          if (data && data.ok && (jobId || data.token)) {
            this.token = data.token || '';
            this.syncJobId = jobId || null;
            this.syncStatus = 'running';
            if (this.syncJobId) {
              try { localStorage.setItem(this.storageKey(), String(this.syncJobId)); } catch (e) {}
            }
            // Polling is already running; do a fresh fetch now that we have an id/token.
            this.fetchProgress();
            // If user already clicked Stop while we were "starting", send cancel now.
            if (this.pendingCancel) this.stopSync();
          } else {
            this.toast('danger', this.$t('Sync_Failed'));
            this.syncing = false;
            this.syncStatus = null;
            this.syncMode = null;
          }
        }).catch(() => {
          this.toast('danger', this.$t('Sync_Failed'));
          this.syncing = false;
          this.syncStatus = null;
          this.syncMode = null;
        });
      });
    },
    stopSync() {
      // Allow clicking Stop even before token/jobId arrives.
      // If we can't send the cancel signal yet, mark it as pending and send once ids arrive.
      if (this.stopping && !this.pendingCancel) return;
      this.stopping = true;
      this.syncStatus = 'cancelling';

      const sent = this.trySendCancelSignal();
      this.pendingCancel = !sent;
    },
    startPolling(immediate = true) {
      if (this.poller) clearInterval(this.poller);
      if (this.fastPoller) clearInterval(this.fastPoller);
      this.lastProgressSignature = '';
      this.lastProgressChangeAt = Date.now();

      // Short "bootstrap" polling right after start so progress moves without manual Refresh.
      // (Not tight polling: limited count, 4s interval, then falls back to 20s.)
      this.fastPollsRemaining = 8;
      this.fastPoller = setInterval(() => {
        if (this.fastPollsRemaining <= 0) {
          clearInterval(this.fastPoller);
          this.fastPoller = null;
          return;
        }
        this.fastPollsRemaining -= 1;
        this.fetchProgress();
      }, 4000);

      // Refresh progress every 10 seconds during sync
      this.poller = setInterval(() => this.fetchProgress(), 10000);
      if (immediate) this.fetchProgress();
    },
    fetchProgress() {
      // Guard against stuck refreshing state (network hiccup / cancelled request).
      if (this.refreshing) {
        const started = Number(this.lastProgressFetchStartedAt || 0);
        if (started && (Date.now() - started) < 30000) return;
        this.refreshing = false;
      }
      this.refreshing = true;
      this.lastProgressFetchStartedAt = Date.now();

      // Prefer DB-based sync progress when available
      if (this.syncJobId) {
        // Ensure progress card renders after reload (default to push if unknown).
        if (!this.syncMode) this.syncMode = 'push';
        axios.get(`woo-sync/status/${this.syncJobId}`)
          .then(({ data }) => {
            const st = data || {};
            this.syncStatus = st.status || null;
            this.progress = this.normalizeProgressState({
              total_products: st.total_items || 0,
              processed: st.processed_items || 0,
              failed_products: st.failed_items || 0,
              synced_products: st.success_items || 0,
              percentage: st.percentage || 0,
              stage: st.stage || null,
              current_product_id: st.current_product_id || null,
              current_sku: st.current_sku || null,
              finished: ['completed','failed','cancelled'].includes(String(st.status || '').toLowerCase()),
              error: st.last_error || null,
              worker_heartbeat_at: st.worker_heartbeat_at || null,
            });

            const status = String(st.status || '').toLowerCase();
            if (['completed', 'failed', 'cancelled'].includes(status)) {
              if (this.poller) {
                clearInterval(this.poller);
                this.poller = null;
              }
              if (this.fastPoller) {
                clearInterval(this.fastPoller);
                this.fastPoller = null;
              }
              this.syncing = false;
              this.token = '';
              // Clear DB job id so the main button stops showing "Syncing"
              this.syncJobId = null;
              this.syncStatus = null;
              this.stopping = false;
              this.pendingCancel = false;
              try { localStorage.removeItem(this.storageKey()); } catch (e) {}
              this.syncMode = null;
              if (status === 'completed') {
                this.toast('success', this.$t('Sync_Completed'));
              } else if (status === 'cancelled') {
                this.toast('warning', (this.$t && this.$t('Cancelled')) ? this.$t('Cancelled') : 'Cancelled');
              } else {
                this.toast('danger', this.$t('Sync_Failed'));
              }
              this.load();
              this.$emit('refreshed');
            }
          })
          .catch(() => {
            // Don't freeze the UI if status call fails.
            // Keep syncing=true so user can retry via Refresh.
          })
          .finally(() => { this.refreshing = false; });
        return;
      }

      // Legacy token-based fallback
      if (!this.token) {
        // If we don't have token/jobId, don't kill UI; the job may still be starting.
        // Next refresh/poll will retry discovery.
        this.refreshing = false;
        return;
      }

      axios.get('woocommerce/sync/products/progress', { params: { token: this.token } }).then(({ data }) => {
        if (data && data.state) {
          this.progress = this.normalizeProgressState(data.state);

          // Detect stuck sync (queue worker not running / no progress updates).
          // If state doesn't change for 60s, stop polling and unblock UI.
          const sigObj = {
            finished: !!this.progress.finished,
            percentage: this.displayPercentage,
            processed: this.displayProcessed,
            total: this.displayTotal,
            stage: this.progress.stage || null,
            sku: this.progress.current_sku || null,
            id: this.progress.current_product_id || null,
            hb: this.progress.worker_heartbeat_at || null,
            err: this.progress.error || null,
          };
          const signature = JSON.stringify(sigObj);
          if (signature !== this.lastProgressSignature) {
            this.lastProgressSignature = signature;
            this.lastProgressChangeAt = Date.now();
          } else if (Date.now() - (this.lastProgressChangeAt || 0) > 60000) {
            clearInterval(this.poller);
            this.poller = null;
            this.token = '';
            this.syncing = false;
            this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0, created: 0, updated: 0 };
            this.toast('danger', this.$t('Sync_Failed'));
            return;
          }

          if (this.progress.finished) {
            const finishedState = this.progress || {};
            const hadError = !!finishedState.error;
            clearInterval(this.poller);
            this.poller = null;
            this.token = '';
            this.syncing = false;
            this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0, created: 0, updated: 0 };
            this.toast(hadError ? 'danger' : 'success', hadError ? this.$t('Sync_Failed') : this.$t('Sync_Completed'));
            this.load();
            this.$emit('refreshed');
          }
        } else {
          // Token expired or invalid, stop polling
          clearInterval(this.poller);
          this.poller = null;
          this.token = '';
          this.syncing = false;
          this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0, created: 0, updated: 0 };
        }
      }).catch(() => {
        // On error, stop polling and reset state
        clearInterval(this.poller);
        this.poller = null;
        this.token = '';
        this.syncing = false;
        this.syncMode = null;
        this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0, created: 0, updated: 0 };
      }).finally(() => { this.refreshing = false; });
    },
    toast(variant, msg) { this.$root.$bvToast.toast(msg, { title: this.$t('WooCommerce'), variant, solid: true }); },
    fixProductCategories() {
      if (this.fixingCategories) return;
      this.fixingCategories = true;
      axios.post('woocommerce/products/fix-categories')
        .then(({ data }) => {
          if (data && data.ok) {
            const b = data.skipped_breakdown || {};
            const extra = (b.missing_category_mapping != null || b.already_categorized != null)
              ? ` (no mapping: ${b.missing_category_mapping || 0}, already ok: ${b.already_categorized || 0})`
              : '';
            let sampleNote = '';
            if ((data.skipped || 0) > 0 && Array.isArray(data.samples) && data.samples.length > 0) {
              const s = data.samples[0] || {};
              sampleNote = ` · Example skipped: product #${s.product_id || '?'} (${s.reason || 'unknown'})`;
            }
            this.toast('success', `Fixed: ${data.fixed || 0} · Skipped: ${data.skipped || 0}${extra} · Errors: ${data.errors || 0}${sampleNote}`);
          } else {
            this.toast('danger', `Fix failed: ${data.error || 'Unknown error'}`);
          }
        })
        .catch((error) => {
          this.toast('danger', `Fix failed: ${error.message || 'Network error'}`);
        })
        .finally(() => {
          this.fixingCategories = false;
        });
    },
    resetSync() {
      if (this.resetting) return;
      this.resetting = true;
      // If a sync is running, cancel it first so the worker stops ASAP
      if (this.syncing) {
        try { this.stopSync(); } catch (e) {}
      }
      axios.post('woocommerce/reset-products-sync')
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
    pullOrders() {
      if (this.pullingOrders) return;
      this.pullingOrders = true;
      axios.post('woocommerce/sync/orders')
        .then(({ data }) => {
          if (data && data.ok !== false) {
            const created = data.created != null ? data.created : 0;
            const skipped = data.skipped != null ? data.skipped : 0;
            const errors = data.errors != null ? data.errors : 0;
            const variant = errors > 0 ? 'warning' : 'success';
            this.toast(variant, `Orders pulled — created: ${created} · skipped: ${skipped} · errors: ${errors}`);
            this.$emit('refreshed');
          } else {
            this.toast('danger', `Order sync failed: ${(data && data.error) || 'Unknown error'}`);
          }
        })
        .catch((err) => {
          this.toast('danger', `Order sync failed: ${(err && err.message) || 'Network error'}`);
        })
        .finally(() => { this.pullingOrders = false; });
    },
    autoLinkBySku() {
      if (this.autoLinking) return;
      this.autoLinking = true;
      axios.post('woocommerce/products/auto-link')
        .then(({ data }) => {
          if (data && data.ok) {
            const p = data.products || {};
            const v = data.variants || {};
            const variant = (p.linked || 0) + (v.linked || 0) > 0 ? 'success' : 'warning';
            this.toast(
              variant,
              `Linked ${p.linked || 0} products & ${v.linked || 0} variants · ambiguous: ${p.ambiguous || 0} · no match: ${p.no_match || 0}`
            );
            // Refresh stats and modal if open
            this.load();
            if (this.unmappedModal) this.loadUnmappedReport();
            this.$emit('refreshed');
          } else {
            this.toast('danger', `Auto-link failed: ${(data && data.error) || 'Unknown error'}`);
          }
        })
        .catch((err) => {
          this.toast('danger', `Auto-link failed: ${(err && err.message) || 'Network error'}`);
        })
        .finally(() => { this.autoLinking = false; });
    },
    openUnmappedModal() {
      this.unmappedModal = true;
      this.loadUnmappedReport();
    },
    loadUnmappedReport() {
      this.loadingUnmapped = true;
      this.unmappedReport = null;
      axios.get('woocommerce/products/unmapped-report', { params: { limit: 100 } })
        .then(({ data }) => {
          if (data && data.ok) {
            this.unmappedReport = data;
          } else {
            this.toast('danger', `Failed to load report: ${(data && data.error) || 'Unknown error'}`);
            this.unmappedModal = false;
          }
        })
        .catch((err) => {
          this.toast('danger', `Failed to load report: ${(err && err.message) || 'Network error'}`);
          this.unmappedModal = false;
        })
        .finally(() => { this.loadingUnmapped = false; });
    },
  },
  created() {
    // Reset any stale state on component creation
    this.syncing = false;
    this.stopping = false;
    this.token = '';
    this.syncStatus = null;
    this.syncMode = null;
    if (this.poller) {
      clearInterval(this.poller);
      this.poller = null;
    }
    if (this.fastPoller) {
      clearInterval(this.fastPoller);
      this.fastPoller = null;
    }
    this.progress = { total_products: 0, processed: 0, synced_products: 0, failed_products: 0, percentage: 0, created: 0, updated: 0 };
    // Restore user preference (Stocky -> Woo push)
    try {
      const pref = localStorage.getItem('woo_products_push_only_unsynced');
      if (pref === '1' || pref === 'true') {
        this.syncOnlyUnsynced = true;
      }
    } catch (e) {}
    // Restore last running job (localStorage or backend)
    this.restoreRunningJob();
    this.load().finally(() => { this.$emit('ready'); });
  },
  beforeDestroy() {
    if (this.poller) {
      clearInterval(this.poller);
      this.poller = null;
    }
    if (this.fastPoller) {
      clearInterval(this.fastPoller);
      this.fastPoller = null;
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

.stat-card.total-products .stat-icon-wrapper {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.total-products .stat-decoration {
  background: #667eea;
}

.stat-card.synced-products .stat-icon-wrapper {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.synced-products .stat-decoration {
  background: #10b981;
}

.stat-card.synced-products .stat-value {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-card.unsynced-products .stat-icon-wrapper {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-card.unsynced-products .stat-decoration {
  background: #f59e0b;
}

.stat-card.unsynced-products .stat-value {
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
  border: 2px solid rgba(23, 162, 184, 0.2);
  border-top-color: #17a2b8;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>


