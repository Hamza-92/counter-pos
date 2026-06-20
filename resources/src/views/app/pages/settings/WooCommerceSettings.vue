<template>
  <div class="main-content">
    <breadcumb :page="$t('WooCommerce_Settings')" :folder="$t('Settings')"/>
    <div v-if="loading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else>
      <b-card no-body class="woocommerce-settings-card shadow-sm">
        <div class="woocommerce-header px-4 pt-4 pb-3">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
              <div class="woocommerce-icon-wrapper mr-3">
                <lucide-icon class="woocommerce-icon" name="shopping-cart" />
              </div>
              <div>
                <h4 class="mb-1 font-weight-bold text-white">{{ $t('WooCommerce_Settings') }}</h4>
                <p class="mb-0 small text-white" style="opacity: 0.9;">Manage your WooCommerce integration and synchronization</p>
              </div>
            </div>
            <div>
              <b-badge :variant="connectionBadgeVariant" class="connection-badge px-3 py-2">
                <lucide-icon :name="connectionIcon" class="mr-2" />
                {{ connectionBadgeText }}
              </b-badge>
            </div>
          </div>
        </div>
        <b-tabs v-model="activeTab" @input="onTabChange" content-class="woocommerce-tabs-content position-relative" class="woocommerce-tabs">
          <div v-if="tabLoading" class="loading_page spinner spinner-primary mr-3"></div>
          <b-tab lazy>
            <template #title>
              <lucide-icon class="mr-2" name="settings" />
              {{ $t('Settings') }}
            </template>
            <div v-show="!tabLoading" class="px-4 py-3">
              <SettingsTab @ready="onTabReady" @connection="onConnectionUpdate" @updated="onChildRefreshed" />
            </div>
          </b-tab>
          <b-tab lazy>
            <template #title>
              <lucide-icon class="mr-2" name="barcode" />
              {{ $t('Products') }}
            </template>
            <div v-show="!tabLoading" class="px-4 py-3">
              <ProductsTab @ready="onTabReady" @refreshed="onChildRefreshed" />
            </div>
          </b-tab>
          <b-tab lazy>
            <template #title>
              <lucide-icon class="mr-2" name="package" />
              {{ $t('Stock') }}
            </template>
            <div v-show="!tabLoading" class="px-4 py-3">
              <StockTab @ready="onTabReady" @refreshed="onChildRefreshed" @view-logs="switchToLogs" />
            </div>
          </b-tab>
          <b-tab lazy>
            <template #title>
              <lucide-icon class="mr-2" name="folder" />
              {{ $t('Categories') }}
            </template>
            <div v-show="!tabLoading" class="px-4 py-3">
              <CategoriesTab @ready="onTabReady" @refreshed="onChildRefreshed" />
            </div>
          </b-tab>
          <b-tab lazy>
            <template #title>
              <lucide-icon class="mr-2" name="tag" />
              {{ $t('Brands') }}
            </template>
            <div v-show="!tabLoading" class="px-4 py-3">
              <BrandsTab @ready="onTabReady" @refreshed="onChildRefreshed" />
            </div>
          </b-tab>
          <b-tab lazy>
            <template #title>
              <lucide-icon class="mr-2" name="user" />
              {{ $t('Customers') }}
            </template>
            <div v-show="!tabLoading" class="px-4 py-3">
              <CustomersTab @ready="onTabReady" @refreshed="onChildRefreshed" />
            </div>
          </b-tab>
          <b-tab lazy>
            <template #title>
              <lucide-icon class="mr-2" name="shopping-bag" />
              {{ $t('Orders') }}
            </template>
            <div v-show="!tabLoading" class="px-4 py-3">
              <OrdersTab @ready="onTabReady" />
            </div>
          </b-tab>
          <b-tab lazy>
            <template #title>
              <lucide-icon class="mr-2" name="clipboard-list" />
              {{ $t('View_Logs') }}
            </template>
            <div v-show="!tabLoading" class="px-4 py-3">
              <LogsTab @ready="onTabReady" />
            </div>
          </b-tab>
        </b-tabs>
      </b-card>

      <!-- Log details viewer -->
      <b-modal id="log-detail" v-model="selectedLog" :title="$t('Log_Details')" hide-footer class="log-detail-modal">
        <div v-if="selectedLog" class="log-detail-content">
          <div class="log-detail-item mb-3">
            <label class="text-muted small mb-1 d-block">{{ $t('date') }}</label>
            <p class="mb-0 font-weight-semibold">{{ formatDate(selectedLog.created_at) }}</p>
          </div>
          <div class="log-detail-item mb-3">
            <label class="text-muted small mb-1 d-block">{{ $t('Action') }}</label>
            <p class="mb-0 font-weight-semibold">{{ selectedLog.action }}</p>
          </div>
          <div class="log-detail-item mb-3">
            <label class="text-muted small mb-1 d-block">{{ $t('Level') }}</label>
            <b-badge :variant="levelToVariant(selectedLog.level)">{{ selectedLog.level }}</b-badge>
          </div>
          <div class="log-detail-item mb-3">
            <label class="text-muted small mb-1 d-block">{{ $t('Message') }}</label>
            <p class="mb-0">{{ selectedLog.message }}</p>
          </div>
          <hr>
          <div class="log-detail-item">
            <label class="text-muted small mb-2 d-block"><strong>{{ $t('Context') }}</strong></label>
            <pre class="log-context">{{ stringify(selectedLog.context) }}</pre>
          </div>
        </div>
      </b-modal>
    </div>
  </div>
</template>

<script>
import moment from 'moment';

export default {
  metaInfo: { title: 'WooCommerce Settings' },
  components: {
    SettingsTab: () => import(/* webpackChunkName: "woo-settings-tab" */ './woocommerce/SettingsTab.vue'),
    ProductsTab: () => import(/* webpackChunkName: "woo-products-tab" */ './woocommerce/ProductsTab.vue'),
    StockTab: () => import(/* webpackChunkName: "woo-stock-tab" */ './woocommerce/StockTab.vue'),
    CategoriesTab: () => import(/* webpackChunkName: "woo-categories-tab" */ './woocommerce/CategoriesTab.vue'),
    BrandsTab: () => import(/* webpackChunkName: "woo-brands-tab" */ './woocommerce/BrandsTab.vue'),
    CustomersTab: () => import(/* webpackChunkName: "woo-customers-tab" */ './woocommerce/CustomersTab.vue'),
    OrdersTab: () => import(/* webpackChunkName: "woo-orders-tab" */ './woocommerce/OrdersTab.vue'),
    LogsTab: () => import(/* webpackChunkName: "woo-logs-tab" */ './woocommerce/LogsTab.vue'),
  },
  data() {
    return {
      loading: true,
      connectionOk: null,
      totalProducts: null,
      unsyncedCount: null,
      activeTab: 0,
      tabLoading: true,
    };
  },
  computed: {
    connectionBadgeVariant() {
      if (this.connectionOk === true) return 'success';
      if (this.connectionOk === false) return 'danger';
      return 'secondary';
    },
    connectionBadgeText() {
      if (this.connectionOk === true) return this.$t('Connected');
      if (this.connectionOk === false) return this.$t('Disconnected');
      return this.$t('Unknown');
    },
    connectionIcon() {
      if (this.connectionOk === true) return 'check-circle';
      if (this.connectionOk === false) return 'x-circle';
      return 'help-circle';
    },
  },
  methods: {
    onTabChange() { this.tabLoading = true; },
    onTabReady() {
      this.tabLoading = false;
    },
    switchToLogs() {
      // Tabs order: Settings=0, Products=1, Stock=2, Categories=3, Brands=4, Customers=5, Orders=6, Logs=7
      this.activeTab = 7;
    },
    fetchCounts() {
      axios.get('products', { params: { limit: 1 } }).then(({ data }) => {
        this.totalProducts = data.totalRows != null ? data.totalRows : null;
      }).catch(() => {
        this.totalProducts = null;
      });

      axios.get('woocommerce/unsynced-count').then(({ data }) => {
        this.unsyncedCount = data.count;
      }).catch(() => {
        this.unsyncedCount = null;
      }).finally(() => {
        this.loading = false;
      });
    },
    testConnection() {
      axios.post('woocommerce/test-connection').then(({ data }) => {
        this.connectionOk = !!data.ok;
      }).catch(() => {
        this.connectionOk = false;
      });
    },
    onConnectionUpdate(val) {
      this.connectionOk = val;
    },
    onChildRefreshed() {
      this.fetchCounts();
      this.testConnection();
    },
    levelToVariant(level) {
      if (level === 'error') return 'danger';
      if (level === 'warning') return 'warning';
      return 'success';
    },
    formatDate(date) {
      return date ? moment(date).format('YYYY-MM-DD HH:mm') : '';
    },
    stringify(obj) {
      return JSON.stringify(obj, null, 2);
    },
  },
  created() {
    this.fetchCounts();
    this.testConnection();
  }
};
</script>

<style scoped>
.woocommerce-settings-card {
  border-radius: 12px;
  overflow: hidden;
  border: none;
}

.woocommerce-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white !important;
  border-radius: 0;
}

.woocommerce-header h4,
.woocommerce-header p,
.woocommerce-header .text-white {
  color: white !important;
}

.woocommerce-icon-wrapper {
  width: 56px;
  height: 56px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
}

.woocommerce-icon {
  font-size: 28px;
  color: white;
}

.connection-badge {
  font-size: 14px;
  font-weight: 600;
  border-radius: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.woocommerce-tabs ::v-deep .nav-tabs {
  border-bottom: 2px solid #f0f0f0;
  padding: 0 1rem;
}

.woocommerce-tabs ::v-deep .nav-tabs .nav-link {
  border: none;
  border-bottom: 3px solid transparent;
  color: #6c757d;
  font-weight: 500;
  padding: 1rem 1.5rem;
  transition: all 0.3s ease;
  margin-right: 0.5rem;
}

.woocommerce-tabs ::v-deep .nav-tabs .nav-link:hover {
  color: #667eea;
  background: rgba(102, 126, 234, 0.05);
  border-bottom-color: rgba(102, 126, 234, 0.3);
}

.woocommerce-tabs ::v-deep .nav-tabs .nav-link.active {
  color: #667eea;
  background: transparent;
  border-bottom-color: #667eea;
  font-weight: 600;
}

.woocommerce-tabs-content {
  min-height: 400px;
}

.badge-pulse {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}

.log-detail-content {
  padding: 0.5rem 0;
}

.log-detail-item label {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.5px;
}

.log-context {
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 1rem;
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 400px;
  overflow: auto;
  font-size: 13px;
  line-height: 1.6;
  margin: 0;
}
</style>


