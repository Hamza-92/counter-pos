<template>
  <div class="main-content">
    <breadcumb :page="$t('Quickbooks_Sync')" :folder="$t('Settings')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-col md="12" v-if="!isLoading">
      <b-tabs pills content-class="mt-3">

        <!-- Connection tab -->
        <b-tab :title="$t('Connection')" active>
          <div class="row border rounded p-3 mt-3">
            <b-col md="12" class="mb-2">
              <p class="mb-1"><strong>{{ $t('Environment') }}:</strong> {{ status.env }}</p>
              <p class="mb-1"><strong>{{ $t('Redirect_URI_active') }}:</strong> {{ status.redirect }}</p>
              <p class="mb-1"><strong>{{ $t('Callback') }}:</strong> {{ status.callback }}</p>
              <p class="mb-1">
                <strong>{{ $t('Status') }}:</strong>
                <span v-if="status.has_token" class="text-success">{{ $t('Connected') }}</span>
                <span v-else class="text-danger">{{ $t('Disconnected') }}</span>
              </p>
              <p v-if="status.token_realm" class="mb-3"><strong>{{ $t('Realm_ID') }}:</strong> {{ status.token_realm }}</p>

              <div class="d-flex gap-2">
                <b-button v-if="!status.has_token" variant="primary" @click="connectBlank">
                  {{ $t('Connect_to_QuickBooks') }}
                </b-button>
                <b-button v-else variant="danger" :disabled="busy" @click="disconnect">
                  {{ $t('Disconnect') }}
                </b-button>

                <!-- NEW: Clear cache -->
                <b-button variant="outline-secondary" size="sm" class="ml-2" @click="Clear_Cache()">
                  {{ $t('Clear_Cache') }}
                </b-button>
              </div>
            </b-col>
          </div>
        </b-tab>

        <!-- Settings tab (manual .env edit via API) -->
        <b-tab :title="$t('Settings')">
          <div class="row border rounded p-3 mt-3">
            <b-form @submit.prevent="save" style="width:100%">
              <b-row>
                 <b-col md="6">
                  <b-form-group :label="$t('Client_ID')">
                    <b-form-input v-model.trim="form.client_id" required />
                    <b-form-text class="text-muted">
                      {{ $t('From_Intuit_Keys_Identifies_App') }}
                      {{ $t('Paste_value_exactly_as_in_app_keys') }}
                    </b-form-text>
                  </b-form-group>
                </b-col>

                <b-col md="6">
                  <b-form-group :label="$t('Client_Secret')">
                    <b-form-input v-model.trim="form.client_secret" type="password" required />
                    <b-form-text class="text-muted">
                      {{ $t('From_Intuit_Keys_Keep_private_rotate') }}
                    </b-form-text>
                  </b-form-group>
                </b-col>

                <b-col md="6">
                  <b-form-group :label="$t('Redirect_URI')">
                    <b-form-input v-model.trim="form.redirect" required />
                    <b-form-text class="text-muted">
                      {{ $t('Must_match_Intuit_redirect_exactly') }}
                      {{ $t('Typical') }}: <code>{{ callbackExample }}</code>. {{ $t('Add_this_exact_URL_on_Keys_page_too') }}
                    </b-form-text>
                  </b-form-group>
                </b-col>

                <b-col md="3">
                  <b-form-group :label="$t('Environment')">
                    <b-form-select v-model="form.env" :options="envOptions" />
                    <b-form-text class="text-muted">
                      <strong>{{ $t('Development') }}</strong> = {{ $t('Sandbox_company_test_data') }}<br>
                      <strong>{{ $t('Production') }}</strong> = {{ $t('Live_QB_company') }}
                    </b-form-text>
                  </b-form-group>
                </b-col>

                <b-col md="3">
                  <b-form-group :label="$t('Realm_ID')">
                    <b-form-input v-model.trim="form.realm_id" />
                    <b-form-text class="text-muted">
                      {{ $t('Realm_ID_Hint') }}
                    </b-form-text>
                  </b-form-group>
                </b-col>
                
                <b-col md="6">
                  <b-form-group :label="$t('Income_Account_Name')">
                    <b-form-input
                      v-model.trim="form.income_account_name"
                      :placeholder="$t('Income_Account_Name')"
                    />
                    <b-form-text class="text-muted">
                      {{ $t('Income_Account_Name_Hint') }}
                    </b-form-text>
                  </b-form-group>
                </b-col>

              </b-row>

              <b-button type="submit" variant="primary" :disabled="busy">{{ $t('Save') }}</b-button>
            </b-form>
          </div>
        </b-tab>

        <!-- Clients Sync Tab -->
        <b-tab :title="$t('Clients_Sync')">
          <div class="row rounded p-3 mt-3 w-100" style="background:#0f172a10;border:1px solid #e5e7eb">

            <!-- If not connected, show an alert with a connect button -->
            <b-alert
              v-if="!status.has_token"
              show
              variant="warning"
              class="w-100 mb-3 d-flex align-items-center justify-content-between"
            >
              <div>
                <strong>{{ $t('Not_connected_to_QuickBooks') }}</strong>
                {{ $t('Please_connect_before_syncing_clients') }}
              </div>
              <b-button size="sm" variant="primary" @click="connectBlank">{{ $t('Connect') }}</b-button>
            </b-alert>

            <!-- Stats + progress -->
            <b-row class="w-100">
              <b-col md="3" class="mb-3">
                <b-card class="h-100 shadow-sm border-0" style="background:linear-gradient(135deg,#eef2ff,#e0e7ff)">
                  <div class="d-flex align-items-center">
                    <div class="mr-3" style="font-size:26px">👥</div>
                    <div>
                      <div class="text-muted small">{{ $t('Total_Clients') }}</div>
                      <div class="h4 mb-0">{{ clientStats.total }}</div>
                    </div>
                  </div>
                </b-card>
              </b-col>

              <b-col md="3" class="mb-3">
                <b-card class="h-100 shadow-sm border-0" style="background:linear-gradient(135deg,#dcfce7,#bbf7d0)">
                  <div class="d-flex align-items-center">
                    <div class="mr-3" style="font-size:26px">✅</div>
                    <div>
                      <div class="text-muted small">{{ $t('Synced') }}</div>
                      <div class="h4 mb-0">{{ clientStats.synced }}</div>
                    </div>
                  </div>
                </b-card>
              </b-col>

              <b-col md="3" class="mb-3">
                <b-card class="h-100 shadow-sm border-0" style="background:linear-gradient(135deg,#fee2e2,#fecaca)">
                  <div class="d-flex align-items-center">
                    <div class="mr-3" style="font-size:26px">⚠️</div>
                    <div>
                      <div class="text-muted small">{{ $t('Not_Synced') }}</div>
                      <div class="h4 mb-0">{{ clientStats.not_synced }}</div>
                    </div>
                  </div>
                </b-card>
              </b-col>

              <b-col md="3" class="mb-3">
                <b-card class="h-100 shadow-sm border-0 d-flex align-items-center justify-content-center">
                  <div class="text-center">
                    <b-button
                      variant="primary"
                      size="lg"
                      :disabled="syncingClients || !status.has_token || clientStats.not_synced === 0"
                      @click="syncAllClients"
                    >
                      <span v-if="!syncingClients">{{ $t('Sync_All_Clients') }}</span>
                      <span v-else><i class="fa fa-spinner fa-spin"></i> {{ $t('Syncing') }}...</span>
                    </b-button>
                    <div class="small text-muted mt-2">{{ $t('Bulk_sync_unsynced_clients') }}</div>
                  </div>
                </b-card>
              </b-col>
            </b-row>

            <!-- Progress -->
            <div class="w-100 mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted">{{ $t('Overall_progress') }}</small>
                <small class="text-muted">{{ syncPercent }}%</small>
              </div>
              <b-progress :value="clientStats.synced" :max="clientStats.total" height="10px"></b-progress>
            </div>

            <!-- Search + table -->
            <div class="w-100">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <b-input-group size="sm" class="w-50">
                  <b-form-input
                    v-model.trim="search"
                    :placeholder="$t('Search_unsynced_by_name_email')"
                    @keyup.enter="loadUnsynced(1)"
                  />
                  <b-input-group-append>
                    <b-button size="sm" variant="outline-secondary" @click="loadUnsynced(1)">{{ $t('Search') }}</b-button>
                  </b-input-group-append>
                </b-input-group>

                <div>
                  <b-button size="sm" variant="outline-secondary" class="mr-2" @click="loadClientStats">{{ $t('Refresh_Stats') }}</b-button>
                  <b-button size="sm" variant="outline-secondary" @click="loadUnsynced(page)">{{ $t('Refresh_List') }}</b-button>
                </div>
              </div>

              <b-table
                small
                hover
                :items="unsynced.items"
                :fields="unsyncedFields"
                :busy="unsyncedBusy"
                primary-key="id"
                show-empty
                :empty-text="$t('All_clients_are_synced') + ' 🎉'"
              >
                <template #cell(select)="row">
                  <b-form-checkbox v-model="selectedIds" :value="row.item.id" />
                </template>

                <template #cell(name)="row">
                  <div class="font-weight-600">{{ row.item.name || '-' }}</div>
                  <div class="text-muted small">{{ row.item.email || '-' }}</div>
                </template>

                 <template #cell(created_at)="row">
                  <div>{{ fmtDate(row.item.created_at) }}</div>
                  <div class="text-muted small">{{ fmtTime(row.item.created_at) }}</div>
                </template>

                <template #cell(actions)="row">
                  <b-button size="sm" variant="success" :disabled="syncingRowId === row.item.id || !status.has_token"
                            @click="syncOne(row.item)">
                    <span v-if="syncingRowId !== row.item.id">{{ $t('Sync') }}</span>
                    <span v-else><i class="fa fa-spinner fa-spin"></i></span>
                  </b-button>
                </template>
              </b-table>

              <div class="d-flex justify-content-between align-items-center mt-2">
                <div>
                  <b-button size="sm" variant="primary" class="mr-2"
                            :disabled="selectedIds.length === 0 || syncingClients || !status.has_token"
                            @click="syncSelected">
                    {{ $t('Sync_Selected') }} ({{ selectedIds.length }})
                  </b-button>
                  <b-button size="sm" variant="outline-secondary" @click="selectedIds = []">{{ $t('Clear_Selection') }}</b-button>
                </div>

                <div>
                  <b-button size="sm" :disabled="page<=1" @click="loadUnsynced(page-1)">{{ $t('Prev') }}</b-button>
                  <span class="mx-2">{{ $t('Page') }} {{ page }} / {{ unsynced.last_page || 1 }}</span>
                  <b-button size="sm" :disabled="page>=unsynced.last_page" @click="loadUnsynced(page+1)">{{ $t('Next') }}</b-button>
                </div>
              </div>

              <!-- Last sync report -->
              <b-alert v-if="syncReport" show :variant="(syncReport.failed_count||0)>0 ? 'warning' : 'success'" class="mt-3">
                <div class="mb-1">
                  {{ $t('Synced') }}: <strong>{{ syncReport.synced_count }}</strong>,
                  {{ $t('Failed') }}: <strong>{{ syncReport.failed_count || 0 }}</strong>
                </div>
                <div v-if="(syncReport.failures||[]).length" class="small">
                  <details>
                    <summary>{{ $t('Show_failures') }}</summary>
                    <ul class="mb-0 mt-2">
                      <li v-for="f in syncReport.failures" :key="f.id">
                        #{{ f.id }} — {{ f.name || '(' + $t('no_name') + ')' }} — <code>{{ f.error }}</code>
                      </li>
                    </ul>
                  </details>
                </div>
              </b-alert>
            </div>

            <div class="mt-4 text-muted small">
              <strong>{{ $t('notes') }}:</strong>
              <ul class="mb-0">
                <li>{{ $t('Only_clients_without_quickbooks_customer_id_are_candidates') }}</li>
                <li>{{ $t('If_matching_email_exists_reuse_instead_of_duplicate') }}</li>
              </ul>
            </div>
          </div>
        </b-tab>



        <!-- Audit tab -->
        <b-tab :title="$t('Audit')">
          <div class="row border rounded p-3 mt-3" style="width:100%">
            <div class="d-flex align-items-center mb-2">
              <b-form-select v-model="level" :options="levelOptions" class="mr-2" @change="loadAudits(1)" />
              <b-button variant="outline-secondary" size="sm" @click="loadAudits(page)">{{ $t('Refresh') }}</b-button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-striped">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>{{ $t('When') }}</th>
                    <th>{{ $t('Operation') }}</th>
                    <th>{{ $t('Level') }}</th>
                    <th>{{ $t('Sale') }}</th>
                    <th>{{ $t('Realm_ID') }}</th>
                    <th>{{ $t('Env') }}</th>
                    <th>{{ $t('Message') }}</th>
                    <th>HTTP</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="a in audits.data" :key="a.id">
                    <td>{{ a.id }}</td>
                    <td>{{ fmtWhen(a.created_at) }}</td>
                    <td>{{ a.operation }}</td>
                    <td><span :class="badge(a.level)">{{ a.level }}</span></td>
                    <td>{{ a.sale_id || '-' }}</td>
                    <td>{{ a.realm_id || '-' }}</td>
                    <td>{{ a.environment }}</td>
                    <td class="text-truncate" style="max-width:300px">{{ a.message }}</td>
                    <td>{{ a.http_code || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <span>{{ $t('Total') }}: {{ audits.total }}</span>
              <div>
                <b-button size="sm" :disabled="!audits.prev_page_url" @click="loadAudits(page-1)">{{ $t('Prev') }}</b-button>
                <span class="mx-2">{{ $t('Page') }} {{ page }} / {{ audits.last_page || 1 }}</span>
                <b-button size="sm" :disabled="!audits.next_page_url" @click="loadAudits(page+1)">{{ $t('Next') }}</b-button>
              </div>
            </div>
          </div>
        </b-tab>

      </b-tabs>
    </b-col>
  </div>
</template>

<script>
import NProgress from 'nprogress';

export default {
  metaInfo: { title: 'QuickBooks Sync' },
  data() {
    return {
      // page state
      isLoading: true,
      busy: false,

      // connection / settings / audits
      status: { env:'', redirect:'', callback:'', has_token:false, token_realm:null, updated_at:'', connect_url:'' },
      form: { client_id:'', client_secret:'', redirect:'', env:'Development', realm_id:'', income_account_name:'' },
      envOptions: [
        { value: 'Development', text: this.$t('Development') },
        { value: 'Production', text: this.$t('Production') },
      ],
      level: '',
      levelOptions: [
        { value: '', text: this.$t('All') },
        { value: 'error', text: this.$t('Errors') },
        { value: 'warning', text: this.$t('Warnings') },
        { value: 'info', text: this.$t('Info') },
      ],
      audits: { data: [], total: 0, last_page: 1, next_page_url: null, prev_page_url: null },
      page: 1,

      // clients sync
      loadingClients: false,
      syncingClients: false,
      syncingRowId: null,
      clientStats: { total: 0, synced: 0, not_synced: 0 },
      unsyncedBusy: false,
      unsynced: { items: [], last_page: 1, total: 0, current_page: 1 },
      unsyncedFields: [
        { key: 'select', label: '' },
        { key: 'id', label: this.$t('ID'), sortable: true },
        { key: 'name', label: this.$t('Client'), sortable: true },
        { key: 'email', label: this.$t('Email'), sortable: true },
        { key: 'created_at', label: this.$t('Added'), sortable: true },
        { key: 'actions', label: this.$t('Actions') },
      ],
      search: '',
      selectedIds: [],
      syncReport: null,
    };
  },

  computed: {
    callbackExample() {
      // For the Settings tab hint — what to put in Intuit App Redirect URIs
      const origin = window.location.origin;
      return `${origin}/quickbooks/callback`;
    },
    syncPercent() {
      const t = this.clientStats.total || 0;
      const s = this.clientStats.synced || 0;
      if (t === 0) return 0;
      return Math.round((s / t) * 100);
    },
  },

  methods: {
    // --------------------- helpers ---------------------
    toast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    },

    fmtWhen(iso) {
      if (!iso) return '-';
      try {
        const d = new Date(iso);
        return new Intl.DateTimeFormat(undefined, {
          timeZone: 'Africa/Casablanca',
          dateStyle: 'medium',
          timeStyle: 'short'
        }).format(d);
      } catch { return iso; }
    },
    fmtDate(iso) {
      if (!iso) return '-';
      return new Intl.DateTimeFormat(undefined, {
        timeZone: 'Africa/Casablanca',
        year: 'numeric', month: '2-digit', day: '2-digit'
      }).format(new Date(iso));
    },
    fmtTime(iso) {
      if (!iso) return '-';
      return new Intl.DateTimeFormat(undefined, {
        timeZone: 'Africa/Casablanca',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      }).format(new Date(iso));
    },

    badge(level) {
      return {
        'badge badge-danger': level === 'error',
        'badge badge-warning': level === 'warning',
        'badge badge-success': level === 'info',
      };
    },

    // --------------------- cache clear ---------------------
    Clear_Cache() {
      NProgress.start(); NProgress.set(0.1);
      axios.get('/clear_cache')
        .then(() => this.toast('success', this.$t('Cache_cleared_successfully'), this.$t('Success')))
        .catch(() => this.toast('danger', this.$t('Failed_to_clear_cache'), this.$t('Failed')))
        .finally(() => NProgress.done());
    },

    // --------------------- connection / status ---------------------
    async loadStatus() {
      try {
        const { data } = await axios.get('/quickbooks/status');
        this.status = data;
      } catch {
        this.toast('danger', this.$t('Failed_to_load_status'), this.$t('QuickBooks'));
      }
    },
    connectBlank() {
      const url = this.status.connect_url || '/quickbooks/connect';
      window.location.href = url;
    },

    async disconnect() {
      this.busy = true; NProgress.start();
      try {
        await axios.post('/quickbooks/disconnect');
        this.toast('success', this.$t('Disconnected'), this.$t('QuickBooks'));
        await this.loadStatus();
      } catch {
        this.toast('danger', this.$t('Failed_to_disconnect'), this.$t('QuickBooks'));
      } finally { this.busy = false; NProgress.done(); }
    },

    // --------------------- settings (.env) ---------------------
    async loadSettings() {
      try {
        const { data } = await axios.get(`/quickbooks/settings?time=${Date.now()}`);
        this.form = { ...this.form, ...data };
      } catch {
        this.toast('danger', this.$t('Failed_to_load_settings'), this.$t('QuickBooks'));
      }
    },
    async save() {
      this.busy = true; NProgress.start();
      try {
        await axios.post('/quickbooks/settings', this.form);
        this.toast('success', this.$t('Settings_saved_to_env'), this.$t('QuickBooks'));
        await this.loadStatus();
      } catch {
        this.toast('danger', this.$t('Failed_to_save_settings'), this.$t('QuickBooks'));
      } finally { this.busy = false; NProgress.done(); }
    },

    // --------------------- audits ---------------------
    async loadAudits(p = 1) {
      this.page = p;
      try {
        const { data } = await axios.get('/quickbooks/audits', { params: { page: p, level: this.level }});
        this.audits = data;
      } catch {
        this.toast('danger', this.$t('Failed_to_load_audit_logs'), this.$t('QuickBooks'));
      }
    },

    // --------------------- clients sync: stats & list ---------------------
    async loadClientStats() {
      this.loadingClients = true;
      try {
        const { data } = await axios.get('/quickbooks/clients-stats');
        // backend should count NOT NULL and != ''
        this.clientStats = data;
      } catch {
        this.toast('danger', this.$t('Failed_to_load_client_stats'), this.$t('QuickBooks'));
      } finally {
        this.loadingClients = false;
      }
    },

    async loadUnsynced(p = 1) {
      this.unsyncedBusy = true;
      try {
        const { data } = await axios.get('/quickbooks/clients-unsynced', { params: { page: p, q: this.search } });
        this.unsynced = data;
        this.page = data.current_page || p;
      } catch {
        this.toast('danger', this.$t('Failed_to_load_unsynced_clients'), this.$t('QuickBooks'));
      } finally {
        this.unsyncedBusy = false;
      }
    },

    // --------------------- clients sync: actions ---------------------
    async syncAllClients() {
      if (!this.status.has_token) {
        this.toast('warning', this.$t('Connect_to_QuickBooks_first'), this.$t('QuickBooks'));
        return;
      }
      this.syncingClients = true; this.syncReport = null; NProgress.start();
      try {
        const { data } = await axios.post('/quickbooks/sync-clients');
        this.syncReport = data;
        await Promise.all([this.loadClientStats(), this.loadUnsynced(this.page)]);
        if ((data.synced_count || 0) > 0) {
          this.toast('success', `${this.$t('Synced')} ${data.synced_count} ${this.$t('Clients')}`, this.$t('QuickBooks'));
        } else {
          // Common reasons: no unsynced clients, email missing, or not connected
          this.toast('warning', data.note || this.$t('No_clients_were_synced'), this.$t('QuickBooks'));
        }
      } catch {
        this.toast('danger', this.$t('Failed_to_sync_clients'), this.$t('QuickBooks'));
      } finally {
        this.syncingClients = false; NProgress.done();
      }
    },

    async syncSelected() {
      if (!this.status.has_token) {
        this.toast('warning', this.$t('Connect_to_QuickBooks_first'), this.$t('QuickBooks'));
        return;
      }
      if (this.selectedIds.length === 0) return;

      this.syncingClients = true; this.syncReport = null; NProgress.start();
      try {
        const { data } = await axios.post('/quickbooks/sync-clients', { ids: this.selectedIds });
        this.syncReport = data;
        this.selectedIds = [];
        await Promise.all([this.loadClientStats(), this.loadUnsynced(this.page)]);
        this.toast((data.synced_count||0)>0 ? 'success' : 'warning',
                   `${this.$t('Synced')} ${data.synced_count} ${this.$t('Selected')}`,
                   this.$t('QuickBooks'));
      } catch {
        this.toast('danger', this.$t('Sync_selected_failed'), this.$t('QuickBooks'));
      } finally {
        this.syncingClients = false; NProgress.done();
      }
    },

    async syncOne(row) {
      if (!this.status.has_token) {
        this.toast('warning', this.$t('Connect_to_QuickBooks_first'), this.$t('QuickBooks'));
        return;
      }
      this.syncingRowId = row.id; this.syncReport = null; NProgress.start();
      try {
        const { data } = await axios.post('/quickbooks/sync-clients', { ids: [row.id] });
        this.syncReport = data;
        await Promise.all([this.loadClientStats(), this.loadUnsynced(this.page)]);
        this.toast((data.synced_count||0)>0 ? 'success' : 'warning', this.$t('Client_sync_attempted'), this.$t('QuickBooks'));
      } catch {
        this.toast('danger', this.$t('Client_sync_failed'), this.$t('QuickBooks'));
      } finally {
        this.syncingRowId = null; NProgress.done();
      }
    },
  },

  async created() {
    try {
      await Promise.all([
        this.loadStatus(),
        this.loadSettings(),
        this.loadAudits(1),
        this.loadClientStats(),
        this.loadUnsynced(1),
      ]);
    } finally {
      this.isLoading = false;
    }
  }
};
</script>
