<template>
  <div>
    <div class="stats-dashboard">
      <div class="stat-card connection-status" :class="connectionStatusClass">
        <div class="stat-icon-wrapper">
          <lucide-icon :name="connectionIcon" class="stat-icon" />
        </div>
        <div class="stat-content">
          <div class="stat-value-small connection-text">
            {{ connectionBadgeText }}
          </div>
          <div class="stat-label">{{ $t('Connection_Status') }}</div>
        </div>
        <div class="stat-decoration"></div>
      </div>
    </div>

    <b-card class="logs-overview-card shadow-sm">
      <template #header>
        <div class="d-flex align-items-center">
          <lucide-icon class="mr-2 text-primary" name="clipboard-list" />
          <h5 class="mb-0 font-weight-bold">{{ $t('Last_5_Log_Entries') }}</h5>
        </div>
      </template>
      <b-table 
        :items="lastFiveLogs" 
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
    </b-card>
  </div>
</template>

<script>
import moment from 'moment';

export default {
  data() {
    return {
      connectionOk: null,
      logs: [],
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
    connectionBadgeVariant() { if (this.connectionOk === true) return 'success'; if (this.connectionOk === false) return 'danger'; return 'secondary'; },
    connectionBadgeText() { if (this.connectionOk === true) return this.$t('Connected'); if (this.connectionOk === false) return this.$t('Disconnected'); return this.$t('Unknown'); },
    lastSyncAtFromNow() { return this.last_sync_at ? moment(this.last_sync_at).fromNow() : null; },
    unsyncedCountDisplay() { return this.unsyncedCount != null ? this.unsyncedCount : '—'; },
    syncedProducts() { if (this.totalProducts == null || this.unsyncedCount == null) return null; return Math.max(0, (this.totalProducts || 0) - (this.unsyncedCount || 0)); },
    syncedProductsDisplay() { return this.syncedProducts != null ? this.syncedProducts : '—'; },
    lastFiveLogs() { const list = this.logs.slice(); list.sort((a,b)=>new Date(b.created_at)-new Date(a.created_at)); return list.slice(0,5); },
    connectionStatusClass() {
      if (this.connectionOk === true) return 'connected';
      if (this.connectionOk === false) return 'disconnected';
      return '';
    },
    connectionIcon() {
      if (this.connectionOk === true) return 'check-circle';
      if (this.connectionOk === false) return 'x';
      return 'help-circle';
    },
  },
  methods: {
    load() {
      const p1 = axios.post('woocommerce/test-connection').then(({ data }) => { this.connectionOk = !!data.ok; }).catch(()=>{ this.connectionOk = false; });
      const p2 = axios.get('woocommerce/logs').then(({ data }) => { this.logs = data.data || []; });
      return Promise.all([p1,p2]);
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
      if (level === 'error') return 'x';
      if (level === 'warning') return 'info';
      return 'check-circle';
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


.stat-card.connection-status .stat-icon-wrapper {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.connection-status .stat-decoration {
  background: #667eea;
}

.stat-card.connection-status.connected .stat-icon-wrapper {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.connection-status.connected .stat-decoration {
  background: #10b981;
}

.stat-card.connection-status.disconnected .stat-icon-wrapper {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-card.connection-status.disconnected .stat-decoration {
  background: #ef4444;
}

.connection-text {
  font-weight: 700;
  color: #667eea;
}

.stat-card.connection-status.connected .connection-text {
  color: #10b981;
}

.stat-card.connection-status.disconnected .connection-text {
  color: #ef4444;
}



.logs-overview-card {
  border-radius: 12px;
  border: none;
}

.logs-overview-card ::v-deep .card-header {
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
</style>


