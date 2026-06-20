<template>
  <!-- NEW FEATURE - SAFE ADDITION -->
  <div class="main-content">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-1">{{ $t('Accounting_Dashboard_Title') }}</h4>
        <div class="text-muted small">{{ $t('Accounting_Dashboard_Subtitle') }}</div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-3">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Accounts') }}</div>
          <div class="h4 mb-0">{{ kpi.accounts }}</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Journal_Entries_30d') }}</div>
          <div class="h4 mb-0">{{ kpi.journals }}</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Income_30d') }}</div>
          <div class="h4 mb-0">{{ toMoney(kpi.income) }}</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Expense_30d') }}</div>
          <div class="h4 mb-0">{{ toMoney(kpi.expense) }}</div>
        </div>
      </div>
    </div>

    <div class="card p-3">
      <div class="row">
        <div class="col-md-3 mb-2">
          <router-link class="btn btn-outline-primary btn-block" to="/app/accounting-v2/chart-of-accounts">
            <lucide-icon class="mr-1" name="database" /> {{ $t('Chart_of_Accounts_Link') }}
          </router-link>
        </div>
        <div class="col-md-3 mb-2">
          <router-link class="btn btn-outline-primary btn-block" to="/app/accounting-v2/journal-entries">
            <lucide-icon class="mr-1" name="clipboard-list" /> {{ $t('Journal_Entries_Link') }}
          </router-link>
        </div>
        <div class="col-md-3 mb-2">
          <router-link class="btn btn-outline-primary btn-block" to="/app/accounting-v2/reports/trial-balance">
            <lucide-icon class="mr-1" name="scale" /> {{ $t('Trial_Balance_Link') }}
          </router-link>
        </div>
        <div class="col-md-3 mb-2">
          <router-link class="btn btn-outline-primary btn-block" to="/app/accounting-v2/reports/profit-and-loss">
            <lucide-icon class="mr-1" name="wallet" /> {{ $t('Profit_Loss_Link') }}
          </router-link>
        </div>
        <div class="col-md-3 mb-2">
          <router-link class="btn btn-outline-primary btn-block" to="/app/accounting-v2/reports/balance-sheet">
            <lucide-icon class="mr-1" name="pie-chart" /> {{ $t('Balance_Sheet_Link') }}
          </router-link>
        </div>
        <div class="col-md-3 mb-2">
          <router-link class="btn btn-outline-primary btn-block" to="/app/accounting-v2/reports/tax-report">
            <lucide-icon class="mr-1" name="receipt-text" /> {{ $t('Tax_Summary_Link') }}
          </router-link>
        </div>
      </div>
    </div>

    <div class="card p-3 mt-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">{{ $t('Income_30d') }} / {{ $t('Expense_30d') }}</h5>
      </div>
      <apexchart type="area" height="320" :options="chart.options" :series="chart.series" />
    </div>
  </div>
</template>

<script>

import VueApexCharts from 'vue-apexcharts';
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  name: "AccountingV2Dashboard",
  components: { apexchart: VueApexCharts },
  data() {
    return {
      kpi: { accounts: 0, journals: 0, income: 0, expense: 0 },
      // Optional price format key for frontend display (loaded from system settings/Vuex store)
      price_format_key: null,
      chart: {
        options: {
          chart: { type: 'area', toolbar: { show: false } },
          dataLabels: { enabled: false },
          stroke: { curve: 'smooth', width: 2 },
          fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 95, 100] } },
          xaxis: { categories: [], labels: { rotate: -45 } },
          legend: { position: 'top' },
          tooltip: { shared: true, intersect: false, y: { formatter: (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } },
          yaxis: [{ labels: { formatter: (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } }],
          colors: ['#4CAF50', '#EF5350'],
          noData: { text: '...' }
        },
        series: [
          { name: 'Income', data: [] },
          { name: 'Expense', data: [] }
        ]
      }
    };
  },
  created() { this.fetch(); },
  methods: {
    async fetch() {
      try {
        const { data } = await axios.get('/accounting/v2/dashboard');
        const kpi = (data && data.kpi) || {};
        const chart = (data && data.chart) || {};
        this.kpi.accounts = kpi.accounts || 0;
        this.kpi.journals = kpi.journals_30d || 0;
        this.kpi.income = kpi.income_30d || 0;
        this.kpi.expense = kpi.expense_30d || 0;

        const labels = chart.labels || [];
        const incomeSeries = chart.income || [];
        const expenseSeries = chart.expense || [];
        this.chart.series = [
          { name: this.$t('Income_30d'), data: incomeSeries },
          { name: this.$t('Expense_30d'), data: expenseSeries }
        ];
        this.chart.options = Object.assign({}, this.chart.options, {
          xaxis: Object.assign({}, this.chart.options.xaxis, { categories: labels }),
          noData: { text: this.$t('No_Data') || 'No data' },
          tooltip: Object.assign({}, this.chart.options.tooltip, {
            y: { formatter: (val) => this.toMoney(val) }
          }),
          yaxis: [{ labels: { formatter: (val) => this.toMoney(val) } }]
        });
      } catch (e) {}
    },
    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing toLocaleString behavior to preserve current behavior.
    toMoney(v) {
      try {
        const n = parseFloat(v || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, 2, effectiveKey);
      } catch (e) {
        const n = parseFloat(v || 0);
        return n.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
      }
    }
  }
};
</script>

<style scoped>
.kpi { border-left: 4px solid #663399; }
</style>



