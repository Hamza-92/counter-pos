<template>
  <div class="commission-report-page">
    <breadcumb :page="$t('Commission_Report')" :folder="$t('Commissions')" />

    <!-- Filters -->
    <div class="table-card mb-4">
      <div class="table-card-header">
        <div class="d-flex align-items-center">
          <lucide-icon class="text-primary mr-2" name="filter" />
          <h5 class="mb-0">{{ $t('Filters') }}</h5>
        </div>
        <b-button variant="primary" size="sm" @click="applyFilters">
          <lucide-icon name="refresh-cw" /> {{ $t('Refresh') }}
        </b-button>
      </div>
      <div class="table-card-body">
        <b-row>
        <b-col md="3">
          <b-form-group :label="$t('Date_From')" label-class="font-weight-medium">
            <b-form-input type="date" v-model="filterDateFrom" class="form-control"></b-form-input>
          </b-form-group>
        </b-col>
        <b-col md="3">
          <b-form-group :label="$t('Date_To')" label-class="font-weight-medium">
            <b-form-input type="date" v-model="filterDateTo" class="form-control"></b-form-input>
          </b-form-group>
        </b-col>
        <b-col md="3">
          <b-form-group :label="$t('Sales_Agent')" label-class="font-weight-medium">
            <v-select v-model="filterAgentId" :reduce="a => a.id" :options="agentsList" label="name" :placeholder="$t('All')" clearable></v-select>
          </b-form-group>
        </b-col>
        <b-col md="3">
          <b-form-group :label="$t('Status')" label-class="font-weight-medium">
            <b-form-select v-model="filterStatus">
              <option value="">{{ $t('All') }}</option>
              <option value="pending">{{ $t('Pending') }}</option>
              <option value="approved">{{ $t('Approved') }}</option>
              <option value="paid">{{ $t('Paid') }}</option>
              <option value="cancelled">{{ $t('Cancelled') }}</option>
            </b-form-select>
          </b-form-group>
        </b-col>
        </b-row>
      </div>
    </div>

    <!-- Summary stat cards -->
    <div class="commission-stats mb-4" v-if="summary">
      <div class="stat-card stat-pending">
        <div class="stat-icon"><lucide-icon name="clock" /></div>
        <div class="stat-body">
          <div class="stat-value">{{ formatMoney(summary.totals && summary.totals.pending_total) }}</div>
          <div class="stat-label">{{ $t('Pending') }}</div>
        </div>
      </div>
      <div class="stat-card stat-approved">
        <div class="stat-icon"><lucide-icon name="check" /></div>
        <div class="stat-body">
          <div class="stat-value">{{ formatMoney(summary.totals && summary.totals.approved_total) }}</div>
          <div class="stat-label">{{ $t('Approved') }}</div>
        </div>
      </div>
      <div class="stat-card stat-paid">
        <div class="stat-icon"><lucide-icon name="wallet" /></div>
        <div class="stat-body">
          <div class="stat-value">{{ formatMoney(summary.totals && summary.totals.paid_total) }}</div>
          <div class="stat-label">{{ $t('Paid') }}</div>
        </div>
      </div>
      <div class="stat-card stat-total">
        <div class="stat-icon"><lucide-icon name="bar-chart" /></div>
        <div class="stat-body">
          <div class="stat-value">{{ formatMoney(summary.totals && summary.totals.grand_total) }}</div>
          <div class="stat-label">{{ $t('Total') }}</div>
        </div>
      </div>
    </div>

    <!-- Charts -->
    <b-row class="mb-4">
      <b-col lg="5" class="mb-3">
        <div class="chart-card">
          <div class="chart-card-header">
            <h4 class="chart-card-title">
              <lucide-icon class="mr-2 text-primary" name="pie-chart" />
              {{ $t('Commission_Report') }} — {{ $t('By_Status') }}
            </h4>
          </div>
          <div class="chart-card-body chart-card-body--compact">
            <div v-if="isChartsLoading" class="loading_page spinner spinner-primary mr-3"></div>
            <apexchart
              v-else-if="chartStatus.series.length && chartStatus.series.some(s => s > 0)"
              :key="chartStatusKey"
              type="donut"
              height="320"
              :options="chartStatus.options"
              :series="chartStatus.series"
            />
            <div v-else class="chart-empty">
              <div class="chart-empty-content">
                <div class="chart-empty-icon">
                  <lucide-icon name="pie-chart" />
                </div>
                <div class="chart-empty-message text-muted">{{ $t('No_Data') }}</div>
              </div>
            </div>
          </div>
        </div>
      </b-col>
      <b-col lg="7" class="mb-3">
        <div class="chart-card">
          <div class="chart-card-header">
            <h4 class="chart-card-title">
              <lucide-icon class="mr-2 text-primary" name="bar-chart-2" />
              {{ $t('Commission_Report') }} — {{ $t('By_Agent') }}
            </h4>
          </div>
          <div class="chart-card-body chart-card-body--compact">
            <div v-if="isChartsLoading" class="loading_page spinner spinner-primary mr-3"></div>
            <apexchart
              v-else-if="chartByAgent.series[0].data.length"
              :key="chartByAgentKey"
              type="bar"
              height="320"
              :options="chartByAgent.options"
              :series="chartByAgent.series"
            />
            <div v-else class="chart-empty">
              <div class="chart-empty-content">
                <div class="chart-empty-icon">
                  <lucide-icon name="bar-chart-2" />
                </div>
                <div class="chart-empty-message text-muted">{{ $t('No_Data') }}</div>
              </div>
            </div>
          </div>
        </div>
      </b-col>
    </b-row>

    <!-- Table -->
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <div class="table-card">
        <div class="table-card-header">
          <div class="d-flex align-items-center">
            <lucide-icon class="text-primary mr-2" name="database-zap" />
            <h5 class="mb-0">{{ $t('Commission_Report') }}</h5>
          </div>
        </div>
        <div class="table-card-body">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="commissions"
          :select-options="{ enabled: true }"
          @on-selected-rows-change="selectionChanged"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
          styleClass="tableOne table-hover vgt-table commission-table"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button v-if="selectedIds.length && (currentUserPermissions && currentUserPermissions.includes('commissions_edit'))" variant="success" size="sm" @click="approveSelected">
              <lucide-icon name="check" /> Approve
            </b-button>
            <b-button v-if="selectedIds.length && (currentUserPermissions && currentUserPermissions.includes('commissions_edit'))" variant="warning" size="sm" class="ml-1" @click="cancelSelected">
              <lucide-icon name="x" /> Cancel
            </b-button>
          </div>
          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field === 'sale_ref'">{{ props.row.sale ? props.row.sale.Ref : '—' }}</span>
            <span v-else-if="props.column.field === 'agent'">{{ props.row.sales_agent ? props.row.sales_agent.name : '—' }}</span>
            <span v-else-if="props.column.field === 'program'">{{ props.row.commission_program ? props.row.commission_program.name : '—' }}</span>
            <span v-else-if="props.column.field === 'base_amount' || props.column.field === 'commission_amount'">{{ formatMoney(props.row[props.column.field]) }}</span>
            <span v-else-if="props.column.field === 'status'">
              <b-badge :variant="statusVariant(props.row.status)">{{ props.row.status }}</b-badge>
            </span>
            <span v-else-if="props.column.field === 'calculated_at'">{{ formatDate(props.row.calculated_at) }}</span>
            <span v-else>{{ props.formattedRow[props.column.field] }}</span>
          </template>
        </vue-good-table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import NProgress from 'nprogress';
import VueApexCharts from 'vue-apexcharts';

export default {
  components: { apexchart: VueApexCharts },
  data() {
    return {
      isLoading: true,
      isChartsLoading: true,
      commissions: [],
      totalRows: 0,
      summary: null,
      byAgent: [],
      serverParams: { sort: { field: 'calculated_at', type: 'desc' }, page: 1, perPage: 10 },
      limit: '10',
      filterDateFrom: '',
      filterDateTo: '',
      filterAgentId: null,
      filterStatus: '',
      agentsList: [],
      selectedIds: [],
      chartStatus: {
        series: [],
        options: {
          chart: { type: 'donut', fontFamily: 'inherit' },
          labels: [],
          colors: ['#f59e0b', '#3b82f6', '#10b981', '#6b7280'],
          legend: { position: 'bottom', fontSize: '14px' },
          dataLabels: { enabled: true, formatter: (val) => `${Number(val || 0).toFixed(1)}%` },
          tooltip: { y: { formatter: (val) => this.money(val) } },
          plotOptions: {
            pie: {
              donut: {
                size: '65%',
                labels: {
                  show: true,
                  name: { show: true, fontSize: '14px', fontWeight: 600 },
                  value: { show: true, fontSize: '18px', fontWeight: 700, formatter: (val) => this.money(val) },
                  total: { show: true, label: this.$t('Total'), formatter: () => this.money(this.statusTotal) },
                },
              },
            },
          },
        },
      },
      chartByAgent: {
        series: [{ name: 'Commission', data: [] }],
        options: {
          chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false } },
          plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '70%' } },
          dataLabels: { enabled: true, formatter: (val) => this.money(val) },
          xaxis: { categories: [] },
          colors: ['#6366f1'],
          grid: { xaxis: { lines: { show: false } } },
          tooltip: { y: { formatter: (val) => this.money(val) } },
        },
      },
    };
  },
  computed: {
    ...mapGetters(['currentUserPermissions']),
    statusTotal() {
      return (this.chartStatus.series || []).reduce((a, b) => a + (Number(b) || 0), 0);
    },
    chartStatusKey() {
      const parts = [
        this.filterDateFrom || '',
        this.filterDateTo || '',
        this.filterAgentId || '',
        (this.chartStatus.series || []).join(','),
      ];
      return `cs-${parts.join('|')}`;
    },
    chartByAgentKey() {
      const n = (this.byAgent || []).length;
      const names = (this.byAgent || []).slice(0, 10).map(a => a.name || a.code || '').join('|');
      const parts = [this.filterDateFrom || '', this.filterDateTo || '', n, names];
      return `ca-${parts.join('|')}`;
    },
    columns() {
      return [
        { label: this.$t('sale_ref'), field: 'sale_ref' },
        { label: this.$t('Sales_Agent'), field: 'agent' },
        { label: this.$t('Program'), field: 'program' },
        { label: this.$t('Base_Amount'), field: 'base_amount' },
        { label: this.$t('Commission'), field: 'commission_amount' },
        { label: this.$t('Status'), field: 'status' },
        { label: this.$t('Calculated_At'), field: 'calculated_at' },
      ];
    },
  },
  created() {
    axios.get('sales_agents_list_for_select').then((res) => {
      const d = res.data.data || res.data;
      this.agentsList = Array.isArray(d) ? d : (d.agents || []);
    });
    this.loadAllCharts();
    this.load();
  },
  methods: {
    applyFilters() {
      this.loadAllCharts();
      this.load(1);
    },
    loadAllCharts() {
      this.isChartsLoading = true;
      return Promise.all([this.loadSummary(), this.loadCharts()]).finally(() => {
        this.isChartsLoading = false;
      });
    },
    load(page) {
      page = page || 1;
      NProgress.start();
      const params = { page, limit: this.limit, SortField: this.serverParams.sort.field, SortType: this.serverParams.sort.type };
      if (this.filterDateFrom) params.date_from = this.filterDateFrom;
      if (this.filterDateTo) params.date_to = this.filterDateTo;
      if (this.filterAgentId) params.sales_agent_id = this.filterAgentId;
      if (this.filterStatus) params.status = this.filterStatus;
      axios.get('commission_report', { params }).then((res) => {
        const d = res.data.data || res.data;
        this.commissions = d.commissions || [];
        this.totalRows = d.totalRows || 0;
        NProgress.done();
        this.isLoading = false;
      }).catch(() => { NProgress.done(); this.isLoading = false; });
    },
    loadSummary() {
      const params = {};
      if (this.filterDateFrom) params.date_from = this.filterDateFrom;
      if (this.filterDateTo) params.date_to = this.filterDateTo;
      if (this.filterAgentId) params.sales_agent_id = this.filterAgentId;
      return axios.get('commission_report/summary', { params }).then((res) => {
        this.summary = res.data.data || res.data;
        this.buildStatusChart();
      });
    },
    loadCharts() {
      const params = {};
      if (this.filterDateFrom) params.date_from = this.filterDateFrom;
      if (this.filterDateTo) params.date_to = this.filterDateTo;
      return axios.get('commission_report/by_agent', { params }).then((res) => {
        const d = res.data.data || res.data;
        this.byAgent = d.by_agent || [];
        this.buildByAgentChart();
      });
    },
    buildStatusChart() {
      const t = this.summary && this.summary.totals;
      if (!t) {
        this.chartStatus.series = [];
        this.chartStatus.options.labels = [];
        return;
      }
      const labels = [this.$t('Pending'), this.$t('Approved'), this.$t('Paid'), this.$t('Cancelled')];
      this.chartStatus.series = [
        Number(t.pending_total) || 0,
        Number(t.approved_total) || 0,
        Number(t.paid_total) || 0,
        Number((this.summary.by_status && this.summary.by_status.cancelled && this.summary.by_status.cancelled.total) || 0),
      ];
      this.chartStatus.options = { ...this.chartStatus.options, labels };
    },
    buildByAgentChart() {
      const agents = this.byAgent || [];
      this.chartByAgent.series = [{ name: this.$t('Commission'), data: agents.map(a => Number(a.total_commission) || 0) }];
      this.chartByAgent.options = {
        ...this.chartByAgent.options,
        xaxis: { ...this.chartByAgent.options.xaxis, categories: agents.map(a => a.name || a.code || '—') },
      };
    },
    selectionChanged({ selectedRows }) {
      this.selectedIds = (selectedRows || []).map(r => r.id);
    },
    onPageChange(p) { this.load(p.currentPage); },
    onPerPageChange(p) { this.limit = String(p.currentPerPage); this.load(1); },
    onSortChange(params) { if (params.length) { this.serverParams.sort = { field: params[0].field, type: params[0].type }; this.load(1); } },
    formatDate(v) { return v ? new Date(v).toLocaleString() : '—'; },
    formatMoney(v) { return v != null ? Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00'; },
    money(v) { return this.formatMoney(v); },
    statusVariant(s) { return { pending: 'warning', approved: 'info', paid: 'success', cancelled: 'secondary' }[s] || 'secondary'; },
    approveSelected() {
      if (!this.selectedIds.length) return;
      axios.post('commissions/approve', { commission_ids: this.selectedIds }).then(() => { this.makeToast('success', this.$t('Success')); this.selectedIds = []; this.load(this.serverParams.page); this.loadSummary(); this.loadCharts(); }).catch((e) => this.makeToast('danger', (e.response && e.response.data && e.response.data.message) || this.$t('Error')));
    },
    cancelSelected() {
      if (!this.selectedIds.length) return;
      this.$bvModal.msgBoxConfirm(this.$t('Confirm_delete')).then((ok) => {
        if (ok) axios.post('commissions/cancel', { commission_ids: this.selectedIds }).then(() => { this.makeToast('success', this.$t('Success')); this.selectedIds = []; this.load(this.serverParams.page); this.loadSummary(); this.loadCharts(); }).catch((e) => this.makeToast('danger', (e.response && e.response.data && e.response.data.message) || this.$t('Error')));
      });
    },
    makeToast(variant, msg) { this.$bvToast.toast(msg, { title: this.$t('Notice'), variant, solid: true }); },
  },
};
</script>

<style scoped>
.commission-report-page {
  padding-bottom: 1.5rem;
}

/* Table Cards (same structure as dashboard) */
.table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  transition: all 0.3s ease;
}
.table-card:hover {
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}
.table-card-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e0e6ed;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}
.table-card-body {
  padding: 1.25rem 1.5rem;
}

.commission-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}
.commission-stats .stat-card {
  flex: 1;
  min-width: 160px;
  padding: 1.25rem;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.commission-stats .stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.commission-stats .stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}
.commission-stats .stat-body { flex: 1; min-width: 0; }
.commission-stats .stat-value { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
.commission-stats .stat-label { font-size: 0.8rem; opacity: 0.9; margin-top: 0.15rem; }

.stat-pending { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e; }
.stat-pending .stat-icon { background: rgba(146, 64, 14, 0.2); }
.stat-approved { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af; }
.stat-approved .stat-icon { background: rgba(30, 64, 175, 0.2); }
.stat-paid { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; }
.stat-paid .stat-icon { background: rgba(6, 95, 70, 0.2); }
.stat-total { background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #3730a3; }
.stat-total .stat-icon { background: rgba(55, 48, 163, 0.2); }

/* Chart Cards (adapted from dashboard styles) */
.chart-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  overflow: visible;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.chart-card:hover {
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}
.chart-card-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e0e6ed;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}
.chart-card-title {
  font-size: 1.05rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}
.chart-card-body {
  padding: 1.25rem 1.5rem;
  flex: 1;
  min-height: 360px;
}
.chart-card-body--compact {
  min-height: 360px;
}
.chart-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 320px;
  padding: 1rem;
}

.chart-empty-content {
  width: 100%;
  max-width: 520px;
  padding: 1.25rem 1.5rem;
  border-radius: 12px;
  border: 1px dashed rgba(107, 114, 128, 0.35);
  background: rgba(107, 114, 128, 0.08);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.chart-empty-icon {
  font-size: 2.2rem;
  line-height: 1;
  color: rgba(107, 114, 128, 0.9);
}

.chart-empty-message {
  font-size: 0.9rem;
  line-height: 1.35;
  text-align: center;
  max-width: 420px;
  margin: 0;
}

.table-card >>> .vgt-wrap { border-radius: 8px; }
.table-card >>> .vgt-inner-wrap { box-shadow: none; }
</style>
