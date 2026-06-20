<template>
  <div>
    <b-card class="filters-card shadow-sm mb-4">
      <template #header>
        <div class="d-flex align-items-center">
          <lucide-icon class="mr-2 text-primary" name="filter" />
          <h6 class="mb-0 font-weight-bold">Filter Logs</h6>
        </div>
      </template>
      <b-row>
        <b-col md="3" sm="6" class="mb-3">
          <b-form-group :label="$t('Action')" class="form-group-modern">
            <v-select 
              :options="actionOptions" 
              :reduce="o => o.value" 
              v-model="filterAction" 
              :clearable="false"
              class="v-select-modern"
            />
          </b-form-group>
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <b-form-group :label="$t('Status')" class="form-group-modern">
            <v-select 
              :options="statusOptions" 
              :reduce="o => o.value" 
              v-model="filterStatus" 
              :clearable="false"
              class="v-select-modern"
            />
          </b-form-group>
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <b-form-group :label="$t('From')" class="form-group-modern">
            <b-form-input 
              type="date" 
              v-model="filterFrom"
              class="form-control-modern"
            />
          </b-form-group>
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <b-form-group :label="$t('To')" class="form-group-modern">
            <b-form-input 
              type="date" 
              v-model="filterTo"
              class="form-control-modern"
            />
          </b-form-group>
        </b-col>
      </b-row>
    </b-card>

    <b-card class="logs-card shadow-sm">
      <template #header>
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center">
            <lucide-icon class="mr-2 text-primary" name="clipboard-list" />
            <h6 class="mb-0 font-weight-bold">Sync Logs</h6>
            <b-badge variant="light" class="ml-3">{{ filteredLogs.length }} entries</b-badge>
          </div>
          <div>
            <b-button size="sm" variant="outline-secondary" class="btn-action-refresh mr-2" @click="load">
              <lucide-icon class="mr-1" name="refresh-cw" />
              {{ $t('Refresh') }}
            </b-button>
            <b-button size="sm" variant="danger" class="btn-action-danger" @click="clearLogs" :disabled="processing">
              <lucide-icon class="mr-1" name="trash-2" />
              {{ $t('Clear_Logs') }}
            </b-button>
          </div>
        </div>
      </template>
      <b-table 
        :items="pagedLogs" 
        :fields="logFields" 
        small 
        responsive="sm"
        class="logs-table"
        thead-class="logs-table-header"
      >
        <template #cell(date)="{ item }">
          <div class="d-flex align-items-center">
            <lucide-icon class="mr-2 text-muted" name="calendar" />
            {{ formatDate(item.created_at) }}
          </div>
        </template>
        <template #cell(action)="{ item }">
          <div class="d-flex align-items-center">
            <lucide-icon class="mr-2 text-primary" name="package" />
            {{ formatAction(item.action) }}
          </div>
        </template>
        <template #cell(direction)="{ item }">
          <div class="d-flex align-items-center">
            <lucide-icon :name="getDirectionIcon(item.action)" class="mr-2 text-info" />
            {{ formatDirection(item.action) }}
          </div>
        </template>
        <template #cell(status)="{ item }">
          <b-badge :variant="levelToVariant(item.level)" class="status-badge">
            <lucide-icon :name="getStatusIcon(item.level)" class="mr-1" />
            {{ formatStatus(item.level) }}
          </b-badge>
        </template>
        <template #cell(message)="{ item }">
          <span class="log-message">{{ item.message }}</span>
        </template>
      </b-table>
      <div class="d-flex justify-content-end mt-3">
        <b-pagination 
          v-model="currentLogPage" 
          :total-rows="filteredLogs.length" 
          :per-page="logsPerPage" 
          size="sm" 
          align="right"
          class="pagination-modern"
        />
      </div>
    </b-card>
  </div>
</template>

<script>
import moment from 'moment';

export default {
  data() {
    return {
      processing: false,
      logs: [],
      filterAction: 'all',
      filterStatus: 'all',
      filterFrom: '',
      filterTo: '',
      currentLogPage: 1,
      logsPerPage: 10,
      actionOptions: [
        { label: this.$t('All'), value: 'all' },
        { label: this.$t('Product'), value: 'products' },
        { label: this.$t('Stock'), value: 'stock' },
        { label: this.$t('Order'), value: 'orders' },
      ],
      statusOptions: [
        { label: this.$t('All'), value: 'all' },
        { label: this.$t('Success'), value: 'info' },
        { label: this.$t('Warning'), value: 'warning' },
        { label: this.$t('Failed'), value: 'error' },
      ],
      logFields: [
        { key: 'date', label: this.$t('date') },
        { key: 'action', label: this.$t('Action') },
        { key: 'direction', label: this.$t('Direction') },
        { key: 'status', label: this.$t('Status') },
        { key: 'message', label: this.$t('Message') },
      ],
    };
  },
  computed: {
    filteredLogs() {
      let out = Array.isArray(this.logs) ? this.logs.slice() : [];
      if (this.filterAction !== 'all') out = out.filter(l => (l.action || '').startsWith(this.filterAction));
      if (this.filterStatus !== 'all') out = out.filter(l => (l.level || '') === this.filterStatus);
      if (this.filterFrom) {
        const from = new Date(this.filterFrom + 'T00:00:00');
        out = out.filter(l => new Date(l.created_at) >= from);
      }
      if (this.filterTo) {
        const to = new Date(this.filterTo + 'T23:59:59');
        out = out.filter(l => new Date(l.created_at) <= to);
      }
      out.sort((a,b) => new Date(b.created_at) - new Date(a.created_at));
      return out;
    },
    pagedLogs() {
      const start = (this.currentLogPage - 1) * this.logsPerPage;
      return this.filteredLogs.slice(start, start + this.logsPerPage);
    },
  },
  methods: {
    load() { return axios.get('woocommerce/logs').then(({ data }) => { this.logs = data.data || []; }); },
    clearLogs() {
      this.processing = true;
      axios.delete('woocommerce/logs').then(() => { this.toast('success', this.$t('Successfully_Updated')); this.load(); })
      .catch(() => { this.toast('danger', this.$t('Not_Available')); })
      .finally(() => { this.processing = false; });
    },
    formatDate(val) { return val ? moment(val).format('YYYY-MM-DD HH:mm') : ''; },
    formatAction(action) { if (!action) return ''; const key = String(action).split('.')[0]; if (key==='products') return this.$t('Product'); if (key==='orders') return this.$t('Order'); if (key==='stock') return this.$t('Stock'); return key; },
    formatDirection(action) { const key = String(action||'').split('.')[0]; if (key==='orders') return this.$t('WooCommerce_to_POS'); return this.$t('POS_to_WooCommerce'); },
    levelToVariant(level) { if (level==='error') return 'danger'; if (level==='warning') return 'warning'; return 'success'; },
    formatStatus(level) { if (level==='error') return this.$t('Failed'); if (level==='warning') return this.$t('Warning'); return this.$t('Success'); },
    getDirectionIcon(action) {
      const key = String(action||'').split('.')[0];
      return key === 'orders' ? 'arrow-left' : 'arrow-right';
    },
    getStatusIcon(level) {
      if (level === 'error') return 'x-circle';
      if (level === 'warning') return 'alert-circle';
      return 'check-circle';
    },
    toast(variant, msg) { this.$root.$bvToast.toast(msg, { title: this.$t('WooCommerce'), variant, solid: true }); },
  },
  created() { this.load().finally(() => { this.$emit('ready'); }); }
};
</script>

<style scoped>
.filters-card {
  border-radius: 12px;
  border: none;
}

.filters-card ::v-deep .card-header {
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  border-bottom: 2px solid #e9ecef;
  padding: 1rem 1.5rem;
  border-radius: 12px 12px 0 0;
}

.form-group-modern ::v-deep label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 0.5rem;
  font-size: 13px;
}

.form-control-modern {
  border-radius: 8px;
  border: 1px solid #dee2e6;
  transition: all 0.3s ease;
  height: 40px;
}

.form-control-modern:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

.v-select-modern ::v-deep .vs__dropdown-toggle {
  border-radius: 8px;
  border: 1px solid #dee2e6;
  min-height: 40px;
}

.v-select-modern ::v-deep .vs__dropdown-toggle:focus {
  border-color: #667eea;
}

.logs-card {
  border-radius: 12px;
  border: none;
}

.logs-card ::v-deep .card-header {
  background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
  border-bottom: 2px solid #e9ecef;
  padding: 1.25rem 1.5rem;
  border-radius: 12px 12px 0 0;
}

.logs-table ::v-deep thead.logs-table-header th {
  background: #f8f9fa;
  font-weight: 700;
  color: #495057;
  text-transform: uppercase;
  font-size: 12px;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #dee2e6;
  padding: 1rem;
}

.logs-table ::v-deep tbody tr {
  transition: all 0.2s ease;
}

.logs-table ::v-deep tbody tr:hover {
  background: #f8f9ff;
  transform: scale(1.01);
}

.logs-table ::v-deep tbody td {
  padding: 1rem;
  vertical-align: middle;
}

.log-message {
  font-size: 13px;
  color: #6c757d;
  max-width: 400px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-badge {
  font-weight: 600;
  padding: 0.4rem 0.8rem;
  border-radius: 20px;
  font-size: 12px;
}

.btn-action-refresh,
.btn-action-danger {
  border-radius: 8px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.btn-action-refresh:hover:not(:disabled),
.btn-action-danger:hover:not(:disabled) {
  transform: translateY(-2px);
}

.pagination-modern ::v-deep .page-link {
  border-radius: 8px;
  margin: 0 2px;
  border: 1px solid #dee2e6;
  color: #667eea;
}

.pagination-modern ::v-deep .page-item.active .page-link {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-color: #667eea;
  color: white;
}

.pagination-modern ::v-deep .page-link:hover {
  background: #f8f9ff;
  border-color: #667eea;
}
</style>


