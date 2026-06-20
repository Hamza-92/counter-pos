<template>
  <div>
    <b-alert variant="info" show class="info-alert-modern mb-4">
      <div class="d-flex align-items-start">
        <lucide-icon class="mr-3 mt-1" name="info" />
        <div>
          <strong>Two-way customer sync:</strong> Sync customers between Stocky and WooCommerce.<br>
          <strong>Email is required</strong> for sync; customers without email are skipped.<br>
          Matching uses <strong>Email</strong> as the unique identifier to prevent duplicates.<br>
          <span v-if="lastSyncResult" class="d-block mt-2">
            <lucide-icon class="mr-1 text-success" name="check-check" />
            Last sync: 
            <span v-if="lastSyncResult.created > 0">Created: <strong>{{ lastSyncResult.created }}</strong></span>
            <span v-if="lastSyncResult.created > 0 && lastSyncResult.updated > 0"> · </span>
            <span v-if="lastSyncResult.updated > 0">Updated: <strong>{{ lastSyncResult.updated }}</strong></span>
            <span v-if="lastSyncResult.linked_by_email > 0"> · Linked by email: <strong>{{ lastSyncResult.linked_by_email }}</strong></span>
            <span v-if="lastSyncResult.linked_by_phone > 0"> · Linked by phone: <strong>{{ lastSyncResult.linked_by_phone }}</strong></span>
          </span>
        </div>
      </div>
    </b-alert>

    <b-tabs v-model="activeTab" content-class="mt-3" @input="onTabChanged">
      <!-- Stocky Customers Tab -->
      <b-tab title="Stocky Customers" active>
        <template slot="title">
          <lucide-icon class="mr-2" name="user" />
          Stocky Customers
          <span v-if="loadingStockyTab" class="mini-spinner ml-2"></span>
        </template>

        <div v-if="loadingStockyTab" class="loading_page spinner spinner-primary mr-3"></div>
        <div v-show="!loadingStockyTab">
        <!-- Stocky Action Buttons -->
        <b-card class="action-card shadow-sm mb-4">
          <div class="d-flex flex-wrap align-items-center">
            <b-button variant="info" class="btn-action-primary mr-3 mb-2 d-inline-flex align-items-center" @click="manualSync('push')" :disabled="syncing || syncMode !== null">
              <template v-if="!syncing || syncMode !== 'push'">
                <lucide-icon class="mr-2" name="arrow-right" />
                Sync Stocky to WooCommerce
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                Syncing...
              </template>
            </b-button>
            <b-button variant="danger" size="sm" class="btn-action-danger mr-2 mb-2" :disabled="resetting" @click="resetSync">
              <lucide-icon class="mr-1" name="refresh-ccw" />
              <span v-if="!resetting">Reset Sync State</span>
              <span v-else>Resetting...</span>
            </b-button>
          </div>
        </b-card>

        <!-- Stocky Stats Cards -->
        <div class="stats-dashboard mb-4">
          <div class="stat-card total-customers">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="user" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ stockyStats.total }}</div>
              <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card synced-customers">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="check-check" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ stockyStats.synced }}</div>
              <div class="stat-label">Synced</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card unsynced-customers">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="pause" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ stockyStats.unsynced }}</div>
              <div class="stat-label">Not Synced</div>
            </div>
            <div class="stat-decoration"></div>
          </div>
        </div>

        <!-- Stocky Customers Table -->
        <b-card>
          <vue-good-table
            mode="remote"
            :columns="stockyColumns"
            :totalRows="stockyTotalRows"
            :rows="stockyCustomers"
            @on-page-change="onStockyPageChange"
            @on-per-page-change="onStockyPerPageChange"
            @on-sort-change="onStockySortChange"
            @on-search="onStockySearch"
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
                  <b-badge v-if="props.row.woocommerce_id && parseInt(props.row.woocommerce_id, 10) > 0" variant="success">
                    <lucide-icon class="mr-1" name="check-check" /> Synced
                  </b-badge>
                  <b-badge v-else variant="warning">
                    <lucide-icon class="mr-1" name="pause" /> Not Synced
                  </b-badge>
                </span>
              <span v-else-if="props.column.field === 'actions'">
                <b-button 
                  size="sm" 
                  variant="info" 
                  @click="syncStockyCustomer(props.row)"
                  :disabled="syncingCustomerId === props.row.id"
                >
                  <template v-if="syncingCustomerId !== props.row.id">
                    <lucide-icon class="mr-1" name="arrow-right" /> Sync
                  </template>
                  <template v-else>
                    <span class="mini-spinner mr-1"></span> Syncing...
                  </template>
                </b-button>
              </span>
            </template>
          </vue-good-table>
        </b-card>
        </div>
      </b-tab>

      <!-- WooCommerce Customers Tab -->
      <b-tab title="WooCommerce Customers">
        <template slot="title">
          <lucide-icon class="mr-2" name="shopping-bag" />
          WooCommerce Customers
          <span v-if="loadingWooTab" class="mini-spinner ml-2"></span>
        </template>

        <div v-if="loadingWooTab" class="loading_page spinner spinner-primary mr-3"></div>
        <div v-show="!loadingWooTab">
        <!-- WooCommerce Action Buttons -->
        <b-card class="action-card shadow-sm mb-4">
          <div class="d-flex flex-wrap align-items-center">
            <b-button variant="success" class="btn-action-secondary mr-3 mb-2 d-inline-flex align-items-center" @click="manualSync('pull')" :disabled="syncing || syncMode !== null">
              <template v-if="!syncing || syncMode !== 'pull'">
                <lucide-icon class="mr-2" name="arrow-left" />
                Sync WooCommerce to Stocky
              </template>
              <template v-else>
                <span class="mini-spinner mr-2"></span>
                Syncing...
              </template>
            </b-button>
            <b-button variant="danger" size="sm" class="btn-action-danger mr-2 mb-2" :disabled="resetting" @click="resetSync">
              <lucide-icon class="mr-1" name="refresh-ccw" />
              <span v-if="!resetting">Reset Sync State</span>
              <span v-else>Resetting...</span>
            </b-button>
          </div>
        </b-card>

        <!-- WooCommerce Stats Cards -->
        <div class="stats-dashboard mb-4">
          <div class="stat-card total-customers">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="user" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ wooStats.total }}</div>
              <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card synced-customers">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="check-check" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ wooStats.synced }}</div>
              <div class="stat-label">Synced</div>
            </div>
            <div class="stat-decoration"></div>
          </div>

          <div class="stat-card unsynced-customers">
            <div class="stat-icon-wrapper">
              <lucide-icon class="stat-icon" name="pause" />
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ wooStats.unsynced }}</div>
              <div class="stat-label">Not Synced</div>
            </div>
            <div class="stat-decoration"></div>
          </div>
        </div>

        <!-- WooCommerce Customers Table -->
        <b-card>
          <vue-good-table
            mode="remote"
            :columns="wooColumns"
            :totalRows="wooTotalRows"
            :rows="wooCustomers"
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
                  @click="syncWooCustomer(props.row)"
                  :disabled="syncingCustomerId === props.row.id"
                >
                  <template v-if="syncingCustomerId !== props.row.id">
                    <lucide-icon class="mr-1" name="arrow-left" /> Sync
                  </template>
                  <template v-else>
                    <span class="mini-spinner mr-1"></span> Syncing...
                  </template>
                </b-button>
              </span>
            </template>
          </vue-good-table>
        </b-card>
        </div>
      </b-tab>

      <!-- Sync Issues Tab -->
      <b-tab>
        <template slot="title">
          <lucide-icon class="mr-2" name="alert-triangle" />
          Sync Issues
          <span v-if="loadingIssuesTab" class="mini-spinner ml-2"></span>
          <b-badge v-if="issuesTotalRows > 0" variant="danger" class="ml-2">{{ issuesTotalRows }}</b-badge>
        </template>

        <div v-if="loadingIssuesTab" class="loading_page spinner spinner-primary mr-3"></div>
        <div v-show="!loadingIssuesTab">
        <b-alert variant="warning" show class="mb-4">
          Review and resolve customer sync problems (email conflicts, missing email, ambiguous matches).
        </b-alert>

        <b-card>
          <vue-good-table
            mode="remote"
            :columns="issuesColumns"
            :totalRows="issuesTotalRows"
            :rows="syncIssues"
            @on-page-change="onIssuesPageChange"
            @on-per-page-change="onIssuesPerPageChange"
            @on-sort-change="onIssuesSortChange"
            @on-search="onIssuesSearch"
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
              <span v-if="props.column.field === 'sync_issue_type_label'">
                <b-badge variant="warning">{{ issueTypeLabel(props.row.sync_issue_type) }}</b-badge>
              </span>

              <span v-else-if="props.column.field === 'sync_issue_at'">
                {{ props.row.sync_issue_at ? moment(props.row.sync_issue_at).format('YYYY-MM-DD HH:mm') : '-' }}
              </span>

              <span v-else-if="props.column.field === 'actions'">
                <b-button
                  size="sm"
                  variant="success"
                  class="mr-2"
                  @click="resolveIssue(props.row)"
                  :disabled="syncingCustomerId === props.row.id"
                >
                  Resolve
                </b-button>
                <b-button
                  size="sm"
                  variant="info"
                  class="mr-2"
                  @click="manualLinkIssue(props.row)"
                  :disabled="syncingCustomerId === props.row.id"
                >
                  Manual Link
                </b-button>
                <b-button
                  size="sm"
                  variant="primary"
                  @click="retryIssue(props.row)"
                  :disabled="syncingCustomerId === props.row.id"
                >
                  Retry Sync
                </b-button>
              </span>
            </template>
          </vue-good-table>
        </b-card>
        </div>
      </b-tab>
    </b-tabs>
  </div>
</template>

<script>
import moment from 'moment';

export default {
  data() {
    return {
      syncing: false,
      resetting: false,
      syncMode: null,
      lastSyncResult: null,
      syncingCustomerId: null, // Track which customer is being synced
      activeTab: 0,
      tabRefreshing: false,
      loadingStockyTab: true,
      loadingWooTab: false,
      loadingIssuesTab: false,
      
      // Stocky customers
      stockyCustomers: [],
      stockyTotalRows: 0,
      stockyStatsData: {
        total: null,
        synced: null,
        unsynced: null,
      },
      stockyServerParams: {
        sort: { field: 'id', type: 'desc' },
        page: 1,
        perPage: 10,
      },
      stockySearch: '',
      
      // WooCommerce customers
      wooCustomers: [],
      wooTotalRows: 0,
      wooServerParams: {
        sort: { field: 'id', type: 'desc' },
        page: 1,
        perPage: 10,
      },
      wooSearch: '',

      // Sync issues (Stocky clients with sync_issue_type set)
      syncIssues: [],
      issuesTotalRows: 0,
      issuesServerParams: {
        sort: { field: 'sync_issue_at', type: 'desc' },
        page: 1,
        perPage: 10,
      },
      issuesSearch: '',
    };
  },
  computed: {
    stockyColumns() {
      return [
        {
          label: this.$t('Code'),
          field: 'code',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('Name'),
          field: 'name',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('Email'),
          field: 'email',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('Phone'),
          field: 'phone',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: 'Sync Status',
          field: 'sync_status',
          tdClass: 'text-center',
          thClass: 'text-center',
          sortable: false,
        },
        {
          label: 'Actions',
          field: 'actions',
          tdClass: 'text-center',
          thClass: 'text-center',
          sortable: false,
        },
      ];
    },
    wooColumns() {
      return [
        {
          label: 'ID',
          field: 'id',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('Name'),
          field: 'name',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('Email'),
          field: 'email',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: this.$t('Phone'),
          field: 'phone',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: 'City',
          field: 'city',
          tdClass: 'text-left',
          thClass: 'text-left',
        },
        {
          label: 'Sync Status',
          field: 'sync_status',
          tdClass: 'text-center',
          thClass: 'text-center',
          sortable: false,
        },
        {
          label: 'Actions',
          field: 'actions',
          tdClass: 'text-center',
          thClass: 'text-center',
          sortable: false,
        },
      ];
    },
    issuesColumns() {
      return [
        { label: 'ID', field: 'id', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Name'), field: 'name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Email'), field: 'email', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Phone'), field: 'phone', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Woo ID', field: 'woocommerce_id', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Issue', field: 'sync_issue_type_label', tdClass: 'text-left', thClass: 'text-left', sortable: false },
        { label: 'Message', field: 'sync_issue_message', tdClass: 'text-left', thClass: 'text-left', sortable: false },
        { label: 'Source', field: 'sync_issue_source', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'At', field: 'sync_issue_at', tdClass: 'text-left', thClass: 'text-left' },
        { label: 'Actions', field: 'actions', tdClass: 'text-center', thClass: 'text-center', sortable: false },
      ];
    },
    stockyStats() {
      // Use database stats based on woocommerce_id instead of filtering displayed customers
      const total = this.stockyStatsData.total != null ? this.stockyStatsData.total : 0;
      const synced = this.stockyStatsData.synced != null ? this.stockyStatsData.synced : 0;
      // Calculate unsynced as total - synced to ensure accuracy
      const unsynced = Math.max(0, total - synced);
      
      return {
        total,
        synced,
        unsynced,
      };
    },
    wooStats() {
      const total = this.wooTotalRows || 0;
      // Stats must be based on woocommerce_id linkage (not email).
      // A Woo customer is "synced" only when we have a Stocky match whose woocommerce_id === Woo id,
      // which we already encode as `sync_status === 'synced'`.
      const synced = this.wooCustomers.filter(c => c.sync_status === 'synced').length;
      const unsynced = Math.max(0, total - synced);
      return { total, synced, unsynced };
    },
  },
  methods: {
    load() {
      // Refresh all datasets when entering the page (avoid stale UI)
      this.loadingStockyTab = true;
      this.loadingWooTab = true;
      this.loadingIssuesTab = true;
      this.stockyCustomers = [];
      this.stockyTotalRows = 0;
      this.wooCustomers = [];
      this.wooTotalRows = 0;
      this.syncIssues = [];
      this.issuesTotalRows = 0;
      this.stockyStatsData = { total: null, synced: null, unsynced: null };

      return Promise.all([
        this.loadStockyCustomers(),
        this.loadStockyStats(),
        this.loadWooCommerceCustomers(),
        this.loadSyncIssues(),
      ]).finally(() => {
        this.loadingStockyTab = false;
        this.loadingWooTab = false;
        this.loadingIssuesTab = false;
      });
    },
    async onTabChanged(tabIndex) {
      // When switching tabs, refresh the tab’s dataset before showing updated data
      if (this.tabRefreshing) return;
      this.tabRefreshing = true;
      try {
        if (tabIndex === 0) {
          this.loadingStockyTab = true;
          this.stockyCustomers = [];
          this.stockyTotalRows = 0;
          await Promise.all([this.loadStockyCustomers(), this.loadStockyStats()]);
        } else if (tabIndex === 1) {
          this.loadingWooTab = true;
          this.wooCustomers = [];
          this.wooTotalRows = 0;
          await this.loadWooCommerceCustomers();
        } else if (tabIndex === 2) {
          this.loadingIssuesTab = true;
          this.syncIssues = [];
          this.issuesTotalRows = 0;
          await this.loadSyncIssues();
        }
      } finally {
        this.loadingStockyTab = false;
        this.loadingWooTab = false;
        this.loadingIssuesTab = false;
        this.tabRefreshing = false;
      }
    },
    loadStockyStats() {
      return axios.get('woocommerce/customers/stats')
        .then(({ data }) => {
          // Ensure we have valid numbers
          const total = parseInt(data.total, 10) || 0;
          const synced = parseInt(data.synced, 10) || 0;
          const unsynced = Math.max(0, total - synced); // Recalculate to ensure accuracy
          
          this.stockyStatsData = {
            total,
            synced,
            unsynced,
          };
        })
        .catch(() => {
          this.stockyStatsData = {
            total: null,
            synced: null,
            unsynced: null,
          };
        });
    },
    loadSyncIssues() {
      const params = {
        page: this.issuesServerParams.page,
        limit: this.issuesServerParams.perPage,
        SortField: this.issuesServerParams.sort.field,
        SortType: this.issuesServerParams.sort.type,
        search: this.issuesSearch,
      };

      return axios.get('woocommerce/customers/sync-issues', { params })
        .then(({ data }) => {
          if (data.ok) {
            this.syncIssues = data.issues || [];
            this.issuesTotalRows = data.totalRows || 0;
          } else {
            this.syncIssues = [];
            this.issuesTotalRows = 0;
          }
        })
        .catch(() => {
          this.syncIssues = [];
          this.issuesTotalRows = 0;
        });
    },
    loadStockyCustomers() {
      const params = {
        page: this.stockyServerParams.page,
        limit: this.stockyServerParams.perPage,
        SortField: this.stockyServerParams.sort.field,
        SortType: this.stockyServerParams.sort.type,
        search: this.stockySearch,
      };
      
      return axios.get('clients', { params })
        .then(({ data }) => {
            this.stockyCustomers = (data.clients || []).map(c => ({
              ...c,
              // Sync status: synced only if woocommerce_id exists and is > 0
              sync_status: (c.woocommerce_id && parseInt(c.woocommerce_id, 10) > 0) ? 'synced' : 'not_synced',
            }));
          this.stockyTotalRows = data.totalRows || 0;
        })
        .catch(() => {
          this.stockyCustomers = [];
          this.stockyTotalRows = 0;
        });
    },
    loadWooCommerceCustomers() {
      const params = {
        page: this.wooServerParams.page,
        per_page: this.wooServerParams.perPage,
        search: this.wooSearch,
      };
      
      return axios.get('woocommerce/customers', { params })
        .then(({ data }) => {
          if (data.ok) {
            // Load all Stocky customers to check sync status
            return axios.get('clients', { params: { limit: 10000 } })
              .then(({ data: stockyData }) => {
                const allStockyCustomers = stockyData.clients || [];
                this.wooCustomers = (data.customers || []).map(c => {
                  const wooId = parseInt(c.id, 10) || 0;
                  const wooEmail = (c.email || '').trim().toLowerCase();
                  const fullName = `${(c.first_name || '').trim()} ${(c.last_name || '').trim()}`.trim();
                  const displayName = (fullName || (c.name || '').trim() || (c.username || '').trim());

                  // Prefer match by woocommerce_id (primary link), then fallback to email
                  let stockyCustomer = null;
                  if (wooId > 0) {
                    stockyCustomer = allStockyCustomers.find(s => (parseInt(s.woocommerce_id, 10) || 0) === wooId) || null;
                  }
                  if (!stockyCustomer && wooEmail) {
                    stockyCustomer = allStockyCustomers.find(s => (s.email || '').trim().toLowerCase() === wooEmail) || null;
                  }

                  return {
                    ...c,
                    // If Woo "name" is missing, show username instead (or first+last)
                    name: displayName,
                    stocky_id: stockyCustomer ? stockyCustomer.id : null,
                    // Sync status: synced only if woocommerce_id exists and is > 0
                    sync_status: (wooId > 0 && stockyCustomer && (parseInt(stockyCustomer.woocommerce_id, 10) || 0) === wooId) ? 'synced' : 'not_synced',
                  };
                });
                this.wooTotalRows = data.totalRows || 0;
              })
              .catch(() => {
                // If we can't load Stocky customers, just show WooCommerce customers without sync status
                this.wooCustomers = (data.customers || []).map(c => ({
                  ...c,
                  stocky_id: null,
                  sync_status: 'not_synced',
                }));
                this.wooTotalRows = data.totalRows || 0;
              });
          } else {
            this.wooCustomers = [];
            this.wooTotalRows = 0;
          }
        })
        .catch(() => {
          this.wooCustomers = [];
          this.wooTotalRows = 0;
        });
    },
    onStockyPageChange(params) {
      this.stockyServerParams.page = params.currentPage;
      this.loadStockyCustomers();
    },
    onStockyPerPageChange(params) {
      this.stockyServerParams.perPage = params.currentPerPage;
      this.stockyServerParams.page = 1;
      this.loadStockyCustomers();
    },
    onStockySortChange(params) {
      this.stockyServerParams.sort.field = params[0].field;
      this.stockyServerParams.sort.type = params[0].type;
      this.loadStockyCustomers();
    },
    onStockySearch(params) {
      this.stockySearch = params.searchTerm || '';
      this.stockyServerParams.page = 1;
      this.loadStockyCustomers();
    },
    onWooPageChange(params) {
      this.wooServerParams.page = params.currentPage;
      this.loadWooCommerceCustomers();
    },
    onWooPerPageChange(params) {
      this.wooServerParams.perPage = params.currentPerPage;
      this.wooServerParams.page = 1;
      this.loadWooCommerceCustomers();
    },
    onWooSortChange(params) {
      this.wooServerParams.sort.field = params[0].field;
      this.wooServerParams.sort.type = params[0].type;
      this.loadWooCommerceCustomers();
    },
    onWooSearch(params) {
      this.wooSearch = params.searchTerm || '';
      this.wooServerParams.page = 1;
      this.loadWooCommerceCustomers();
    },
    async manualSync(mode) {
      if (this.syncing) return;
      
      this.syncing = true;
      this.syncMode = mode;
      this.lastSyncResult = null;

      try {
        await this.syncDirection(mode);
      } catch (error) {
        this.toast('danger', this.$t('Sync_Failed'));
      } finally {
        this.syncing = false;
        this.syncMode = null;
        this.load();
        this.loadStockyStats(); // Reload stats after bulk sync
        this.$emit('refreshed');
      }
    },
    syncDirection(mode) {
      return new Promise((resolve, reject) => {
        const url = `woocommerce/sync/customers?mode=${mode}`;
        axios.post(url).then(({ data }) => {
          if (data.ok) {
            const result = data.result || {};
            this.lastSyncResult = {
              created: Math.max(0, parseInt(result.created, 10) || 0),
              updated: Math.max(0, parseInt(result.updated, 10) || 0),
              linked_by_email: Math.max(0, parseInt(result.linked_by_email, 10) || 0),
              linked_by_phone: Math.max(0, parseInt(result.linked_by_phone, 10) || 0),
              errors: Math.max(0, parseInt(result.errors, 10) || 0),
              skipped: Math.max(0, parseInt(result.skipped, 10) || 0),
            };
            
            const direction = mode === 'push' ? 'Stocky → WooCommerce' : 'WooCommerce → Stocky';
            const message = `${direction}: Created ${this.lastSyncResult.created}, Updated ${this.lastSyncResult.updated}`;
            this.toast('success', message);
            resolve();
          } else {
            this.toast('danger', this.$t('Sync_Failed') + ': ' + (data.error || 'Unknown error'));
            reject(new Error(data.error || 'Sync failed'));
          }
        }).catch((error) => {
          this.toast('danger', this.$t('Sync_Failed') + ': ' + (error.message || 'Network error'));
          reject(error);
        });
      });
    },
    toast(variant, msg) { 
      this.$root.$bvToast.toast(msg, { title: this.$t('WooCommerce'), variant, solid: true }); 
    },
    resetSync() {
      if (this.resetting) return;
      this.resetting = true;
      this.lastSyncResult = null;
      axios.post('woocommerce/reset-customers-sync')
        .then(() => {
          this.toast('success', this.$t('Successfully_Updated'));
          this.load();
          this.loadStockyStats(); // Reload stats after reset
          this.$emit('refreshed');
        })
        .catch(() => {
          this.toast('danger', this.$t('Sync_Failed'));
        })
        .finally(() => {
          this.resetting = false;
        });
    },
    syncStockyCustomer(customer) {
      if (!customer.email) {
        this.toast('warning', 'Customer must have an email to sync');
        return;
      }
      
      if (this.syncingCustomerId) return; // Prevent multiple simultaneous syncs
      
      this.syncingCustomerId = customer.id;
      
      axios.post('woocommerce/sync/customers', {}, {
        params: { mode: 'push', customer_id: customer.id }
      })
        .then(({ data }) => {
          if (data.ok) {
            const action = data.created > 0 ? 'created' : 'updated';
            this.toast('success', `Customer "${customer.name}" synced successfully (${action})`);
            // Reload the current page and stats to refresh sync status
            this.loadStockyCustomers();
            this.loadStockyStats();
            this.loadSyncIssues();
          } else {
            this.toast('danger', `Sync failed: ${data.error || 'Unknown error'}`);
            this.loadSyncIssues();
          }
        })
        .catch((error) => {
          this.toast('danger', `Sync failed: ${error.message || 'Network error'}`);
          this.loadSyncIssues();
        })
        .finally(() => {
          this.syncingCustomerId = null;
        });
    },
    syncWooCustomer(customer) {
      if (!customer.email) {
        this.toast('warning', 'Customer must have an email to sync');
        return;
      }
      
      if (this.syncingCustomerId) return; // Prevent multiple simultaneous syncs
      
      this.syncingCustomerId = customer.id;
      
      axios.post('woocommerce/sync/customers', {}, {
        params: { mode: 'pull', customer_id: customer.id }
      })
        .then(({ data }) => {
          if (data.ok) {
            const action = data.created > 0 ? 'created' : 'updated';
            this.toast('success', `Customer "${customer.name}" synced successfully (${action})`);
            // Reload both tables and stats to refresh sync status
            this.loadStockyCustomers();
            this.loadStockyStats();
            this.loadWooCommerceCustomers();
            this.loadSyncIssues();
          } else {
            this.toast('danger', `Sync failed: ${data.error || 'Unknown error'}`);
            this.loadSyncIssues();
          }
        })
        .catch((error) => {
          this.toast('danger', `Sync failed: ${error.message || 'Network error'}`);
          this.loadSyncIssues();
        })
        .finally(() => {
          this.syncingCustomerId = null;
        });
    },

    // ----- Sync Issues tab handlers -----
    issueTypeLabel(type) {
      const map = {
        missing_email: 'Missing email',
        ambiguous_email: 'Ambiguous email',
        email_conflict: 'Email conflict',
        id_email_mismatch: 'ID/email mismatch',
        woo_not_found: 'Woo customer not found',
        woo_request_failed: 'Woo request failed',
      };
      return map[type] || (type || 'Unknown');
    },
    onIssuesPageChange(params) {
      this.issuesServerParams.page = params.currentPage;
      this.loadSyncIssues();
    },
    onIssuesPerPageChange(params) {
      this.issuesServerParams.perPage = params.currentPerPage;
      this.issuesServerParams.page = 1;
      this.loadSyncIssues();
    },
    onIssuesSortChange(params) {
      this.issuesServerParams.sort.field = params[0].field;
      this.issuesServerParams.sort.type = params[0].type;
      this.loadSyncIssues();
    },
    onIssuesSearch(params) {
      this.issuesSearch = params.searchTerm || '';
      this.issuesServerParams.page = 1;
      this.loadSyncIssues();
    },
    resolveIssue(issue) {
      if (this.syncingCustomerId) return;
      this.syncingCustomerId = issue.id;
      axios.post(`woocommerce/customers/sync-issues/${issue.id}/resolve`)
        .then(() => {
          this.toast('success', 'Issue resolved');
          return this.load();
        })
        .catch((error) => {
          this.toast('danger', `Resolve failed: ${error.message || 'Network error'}`);
        })
        .finally(() => {
          this.syncingCustomerId = null;
        });
    },
    manualLinkIssue(issue) {
      const wooIdStr = window.prompt('Enter WooCommerce customer ID to link:', issue.woocommerce_id || '');
      if (!wooIdStr) return;
      const wooId = parseInt(wooIdStr, 10);
      if (!wooId || wooId <= 0) {
        this.toast('warning', 'Invalid WooCommerce ID');
        return;
      }
      if (this.syncingCustomerId) return;
      this.syncingCustomerId = issue.id;
      axios.post(`woocommerce/customers/sync-issues/${issue.id}/link`, { woocommerce_id: wooId })
        .then(() => {
          this.toast('success', 'Customer linked');
          return this.load();
        })
        .catch((error) => {
          this.toast('danger', `Link failed: ${error.message || 'Network error'}`);
        })
        .finally(() => {
          this.syncingCustomerId = null;
        });
    },
    retryIssue(issue) {
      if (this.syncingCustomerId) return;
      this.syncingCustomerId = issue.id;

      const source = (issue.sync_issue_source || '').toLowerCase();
      const mode = source === 'pull' ? 'pull' : 'push';
      const customerId = mode === 'pull'
        ? (parseInt(issue.woocommerce_id, 10) || 0)
        : issue.id;

      if (mode === 'pull' && customerId <= 0) {
        this.toast('warning', 'Cannot retry pull: missing WooCommerce ID. Use Manual Link first.');
        this.syncingCustomerId = null;
        return;
      }

      axios.post('woocommerce/sync/customers', {}, { params: { mode, customer_id: customerId } })
        .then(({ data }) => {
          if (data.ok) {
            this.toast('success', 'Retry sync completed');
          } else {
            this.toast('danger', `Retry failed: ${data.error || 'Unknown error'}`);
          }
          return this.load();
        })
        .catch((error) => {
          this.toast('danger', `Retry failed: ${error.message || 'Network error'}`);
        })
        .finally(() => {
          this.syncingCustomerId = null;
        });
    },
  },
  created() { 
    this.load().finally(() => { this.$emit('ready'); }); 
  },
  activated() {
    // If this component is kept-alive, refresh when re-entering.
    this.load();
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

.stat-card.total-customers .stat-icon-wrapper {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.total-customers .stat-decoration {
  background: #667eea;
}

.stat-card.synced-customers .stat-icon-wrapper {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.synced-customers .stat-decoration {
  background: #10b981;
}

.stat-card.synced-customers .stat-value {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-card.unsynced-customers .stat-icon-wrapper {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-card.unsynced-customers .stat-decoration {
  background: #f59e0b;
}

.stat-card.unsynced-customers .stat-value {
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

.btn-action-secondary {
  border-radius: 8px;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
  transition: all 0.3s ease;
}

.btn-action-secondary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
}

.btn-action-bidirectional {
  border-radius: 8px;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
  transition: all 0.3s ease;
}

.btn-action-bidirectional:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(255, 193, 7, 0.4);
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
