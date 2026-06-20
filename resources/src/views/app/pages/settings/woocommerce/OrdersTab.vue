<template>
  <div>
    <b-alert variant="info" show class="info-alert-modern mb-4">
      <div class="d-flex align-items-start">
        <lucide-icon class="mr-3 mt-1" name="info" />
        <div>
          Orders are synced from WooCommerce to Stocky (Woo → Stocky).
        </div>
      </div>
    </b-alert>

    <b-card class="action-card shadow-sm mb-4">
      <div class="d-flex flex-wrap align-items-center">
        <b-button
          variant="success"
          class="btn-action-secondary mr-3 mb-2 d-inline-flex align-items-center"
          :disabled="syncing || tabRefreshing"
          @click="syncOrders"
        >
          <template v-if="!syncing">
            <lucide-icon class="mr-2" name="chevron-down" />
            Sync WooCommerce Orders to Stocky
          </template>
          <template v-else>
            <span class="mini-spinner mr-2"></span>
            Syncing...
          </template>
        </b-button>
      </div>
    </b-card>

    <b-alert v-if="lastResult" :variant="lastResult.ok ? 'success' : 'danger'" show>
      <div v-if="lastResult.ok">
        Imported: <strong>{{ lastResult.created }}</strong>
        · Skipped: <strong>{{ lastResult.skipped }}</strong>
        · Errors: <strong>{{ lastResult.errors }}</strong>
      </div>
      <div v-else>
        {{ lastResult.error || 'Order sync failed' }}
      </div>
    </b-alert>

    <div class="d-flex align-items-center mb-3">
      <lucide-icon class="mr-2" name="shopping-bag" />
      <strong>WooCommerce Orders</strong>
      <span v-if="loadingWooTab" class="mini-spinner ml-2"></span>
    </div>

    <div v-if="loadingWooTab" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-show="!loadingWooTab">
      <div class="stats-dashboard mb-4">
        <div class="stat-card total-customers">
          <div class="stat-icon-wrapper">
            <lucide-icon class="stat-icon" name="receipt-text" />
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ wooTotalRows }}</div>
            <div class="stat-label">Total Orders</div>
          </div>
          <div class="stat-decoration"></div>
        </div>

        <div class="stat-card synced-customers">
          <div class="stat-icon-wrapper">
            <lucide-icon class="stat-icon" name="check-check" />
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ importedStats.total_imported != null ? importedStats.total_imported : '—' }}</div>
            <div class="stat-label">Imported in Stocky</div>
          </div>
          <div class="stat-decoration"></div>
        </div>

        <div class="stat-card unsynced-customers">
          <div class="stat-icon-wrapper">
            <lucide-icon class="stat-icon" name="pause" />
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ notImportedCount }}</div>
            <div class="stat-label">Not Imported</div>
          </div>
          <div class="stat-decoration"></div>
        </div>
      </div>

      <b-card>
        <vue-good-table
          mode="remote"
          :columns="wooColumns"
          :totalRows="wooTotalRows"
          :rows="wooOrders"
          @on-page-change="onWooPageChange"
          @on-per-page-change="onWooPerPageChange"
          @on-sort-change="onWooSortChange"
          @on-search="onWooSearch"
          :search-options="{
            enabled: true,
            placeholder: $t('Search_this_table'),
          }"
          :pagination-options="{
            enabled: true,
            mode: 'records',
            nextLabel: 'next',
            prevLabel: 'prev',
          }"
          styleClass="tableOne table-hover vgt-table"
        >
          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field === 'sync_status'">
              <b-badge v-if="props.row.sync_status === 'synced'" variant="success">
                <lucide-icon class="mr-1" name="check-check" /> Synced
              </b-badge>
              <b-badge v-else variant="warning">
                <lucide-icon class="mr-1" name="pause" /> Not Synced
              </b-badge>
            </span>
            <span v-else-if="props.column.field === 'actions'">
              <b-button
                size="sm"
                variant="success"
                @click="syncWooOrder(props.row)"
                :disabled="syncingOrderId === props.row.id || props.row.sync_status === 'synced'"
              >
                <template v-if="syncingOrderId !== props.row.id">
                  <lucide-icon class="mr-1" name="chevron-down" /> Sync
                </template>
                <template v-else>
                  <span class="mini-spinner mr-2"></span> Syncing...
                </template>
              </b-button>
            </span>
          </template>
        </vue-good-table>
      </b-card>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      syncing: false,
      syncingOrderId: null,
      lastResult: null,
      loadingWooTab: true,

      // Woo orders
      wooOrders: [],
      wooTotalRows: 0,
      wooServerParams: { sort: { field: 'id', type: 'desc' }, page: 1, perPage: 10 },
      wooSearch: '',

      // Stats
      importedStats: { total_imported: null, imported_today: null },
    };
  },
  computed: {
    wooColumns() {
      return [
        { label: 'ID', field: 'id', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Number', field: 'number', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Status', field: 'status', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Date', field: 'date_created', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Total', field: 'total', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Customer', field: 'customer_display', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Email', field: 'billing_email', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Items', field: 'items_count', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Sync Status', field: 'sync_status', tdClass: 'text-center', thClass: 'text-center', sortable: false },
        { label: 'Actions', field: 'actions', tdClass: 'text-center', thClass: 'text-center', sortable: false },
      ];
    },
    notImportedCount() {
      const total = this.wooTotalRows || 0;
      const imported = this.importedStats.total_imported != null ? this.importedStats.total_imported : 0;
      return Math.max(0, total - imported);
    },
  },
  methods: {
    toast(variant, msg) {
      this.$root.$bvToast.toast(msg, { title: this.$t('WooCommerce'), variant, solid: true });
    },
    load() {
      // Clear before loading to avoid stale UI
      this.loadingWooTab = true;
      this.wooOrders = [];
      this.wooTotalRows = 0;
      this.importedStats = { total_imported: null, imported_today: null };

      return Promise.all([
        this.loadImportedStats(),
        this.loadWooOrders(),
      ]).finally(() => {
        this.loadingWooTab = false;
      });
    },
    loadImportedStats() {
      return axios.get('woocommerce/orders/imported/stats')
        .then(({ data }) => {
          this.importedStats = {
            total_imported: data.total_imported != null ? data.total_imported : 0,
            imported_today: data.imported_today != null ? data.imported_today : 0,
          };
        })
        .catch(() => {
          this.importedStats = { total_imported: null, imported_today: null };
        });
    },
    loadWooOrders() {
      const params = {
        page: this.wooServerParams.page,
        per_page: this.wooServerParams.perPage,
        search: this.wooSearch,
      };
      return axios.get('woocommerce/orders', { params })
        .then(({ data }) => {
          if (data.ok) {
            this.wooOrders = data.orders || [];
            this.wooTotalRows = data.totalRows || 0;
          } else {
            this.wooOrders = [];
            this.wooTotalRows = 0;
          }
        })
        .catch(() => {
          this.wooOrders = [];
          this.wooTotalRows = 0;
        });
    },
    syncWooOrder(order) {
      const orderId = parseInt(order.id, 10) || 0;
      if (orderId <= 0) return;
      if (this.syncingOrderId) return;

      this.syncingOrderId = orderId;
      axios.post('woocommerce/sync/orders', {}, { params: { order_id: orderId } })
        .then(({ data }) => {
          if (data && data.ok) {
            this.toast('success', 'Order synced');
          } else {
            this.toast('danger', `Order sync failed: ${data.error || 'Unknown error'}`);
          }
        })
        .catch((error) => {
          this.toast('danger', `Order sync failed: ${error.message || 'Network error'}`);
        })
        .finally(() => {
          this.syncingOrderId = null;
          this.load();
        });
    },
    onWooPageChange(params) { this.wooServerParams.page = params.currentPage; this.loadWooOrders(); },
    onWooPerPageChange(params) { this.wooServerParams.perPage = params.currentPerPage; this.wooServerParams.page = 1; this.loadWooOrders(); },
    onWooSortChange(params) { this.wooServerParams.sort.field = params[0].field; this.wooServerParams.sort.type = params[0].type; this.loadWooOrders(); },
    onWooSearch(params) { this.wooSearch = params.searchTerm || ''; this.wooServerParams.page = 1; this.loadWooOrders(); },
    syncOrders() {
      if (this.syncing) return;
      this.syncing = true;
      this.lastResult = null;
      axios.post('woocommerce/sync/orders')
        .then(({ data }) => {
          this.lastResult = data;
          if (data && data.ok) {
            this.toast('success', 'Orders sync completed');
          } else {
            this.toast('danger', `Orders sync failed: ${data.error || 'Unknown error'}`);
          }
        })
        .catch((error) => {
          this.lastResult = { ok: false, error: error.message || 'Network error' };
          this.toast('danger', `Orders sync failed: ${error.message || 'Network error'}`);
        })
        .finally(() => {
          this.syncing = false;
          this.load();
          this.$emit('refreshed');
        });
    },
  },
  created() {
    this.load().finally(() => { this.$emit('ready'); });
  }
};
</script>

<style scoped>
.info-alert-modern {
  border-radius: 12px;
  border-left: 4px solid #17a2b8;
  background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%);
  padding: 1.25rem;
}

.action-card {
  border-radius: 12px;
  border: none;
  padding: 1.5rem;
  background: #f8f9fa;
}

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

.stat-content { flex: 1; z-index: 1; }

.stat-value {
  font-size: 2.25rem;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 0.5rem;
}

.stat-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-card.total-customers .stat-icon-wrapper { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-card.total-customers .stat-decoration { background: #667eea; }
.stat-card.synced-customers .stat-icon-wrapper { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-card.synced-customers .stat-decoration { background: #10b981; }
.stat-card.unsynced-customers .stat-icon-wrapper { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.stat-card.unsynced-customers .stat-decoration { background: #f59e0b; }
</style>
