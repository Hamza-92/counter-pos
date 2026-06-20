<template>
  <!-- NEW FEATURE - SAFE ADDITION -->
  <div class="main-content">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-1">{{ $t('Profit_Loss_Title') }}</h4>
        <div class="text-muted small">{{ $t('Profit_Loss_Subtitle') }}</div>
      </div>
    </div>

    

    <div class="row">
      <div class="col-md-4">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Income') }}</div>
          <div class="h4 mb-0">{{ toMoney(summary.income) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Expense') }}</div>
          <div class="h4 mb-0">{{ toMoney(summary.expense) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-3 mb-3 kpi" :class="summary.net_profit>=0 ? 'border-success' : 'border-danger'">
          <div class="text-muted small">{{ $t('Net_Profit') }}</div>
          <div class="h4 mb-0">{{ toMoney(summary.net_profit) }}</div>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div class="card wrapper" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="tableRows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ enabled: true, placeholder: $t('Search_accounts') }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: $t('Next'), prevLabel: $t('Prev') }"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <div class="form-inline">
            <label class="mr-2 small text-muted">{{ $t('From') }}</label>
            <input v-model="filters.from" type="date" class="form-control mr-2"/>
            <label class="mr-2 small text-muted">{{ $t('To') }}</label>
            <input v-model="filters.to" type="date" class="form-control mr-2"/>
            <label class="mr-2 small text-muted">{{ $t('Type') }}</label>
            <select v-model="filters.type" class="form-control mr-2">
              <option value="">{{ $t('All') }}</option>
              <option value="income">{{ $t('Income') }}</option>
              <option value="expense">{{ $t('Expense') }}</option>
            </select>
            <b-button @click="Get_PL(1)" class="btn-rounded" variant="btn btn-outline-primary btn-icon m-1">
              <lucide-icon name="refresh-cw" />
              {{ $t('Apply') }}
            </b-button>
          </div>
        </div>

        <template slot="table-row" slot-scope="props">
          <template v-if="props.row && props.row.isTotal">
            <span v-if="props.column.field == 'code'"></span>
            <span v-else-if="props.column.field == 'name'" class="font-weight-bold d-block text-right">{{ $t(props.row.totalLabel) }}</span>
            <span v-else-if="props.column.field == 'type'"></span>
            <span v-else-if="props.column.field == 'amount'" class="text-right d-block font-weight-bold">{{ toMoney(props.row.totalValue) }}</span>
          </template>
          <template v-else>
            <span v-if="props.column.field == 'amount'" class="text-right d-block">{{ toMoney(props.row.amount) }}</span>
          </template>
        </template>
      </vue-good-table>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../../utils/priceFormat";

export default {
  name: "ProfitLossV2",
  data() {
    return {
      isLoading: true,
      rows: [],
      totalRows: "",
      serverParams: {
        columnFilters: {},
        sort: { field: "code", type: "asc" },
        page: 1,
        perPage: 10
      },
      search: "",
      limit: "10",
      filters: { from: "", to: "", type: "" },
      summary: { income: 0, expense: 0, net_profit: 0 },
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },
  computed: {
    columns() {
      return [
        { label: this.$t('Code'), field: 'code', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Name'), field: 'name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Type'), field: 'type', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Amount'), field: 'amount', tdClass: 'text-right', thClass: 'text-right', sortable: true },
      ];
    },
    tableRows() {
      const rows = this.rows || [];
      const footer = [];
      footer.push({ isTotal: true, totalLabel: 'Total_Income', totalValue: this.summary.income });
      footer.push({ isTotal: true, totalLabel: 'Total_Expense', totalValue: this.summary.expense });
      footer.push({ isTotal: true, totalLabel: 'Net_Profit', totalValue: this.summary.net_profit });
      return [...rows, ...footer];
    }
  },
  created() { this.Get_PL(1); },
  methods: {
    updateParams(newProps) { this.serverParams = Object.assign({}, this.serverParams, newProps); },
    onPageChange({ currentPage }) { if (this.serverParams.page !== currentPage) { this.updateParams({ page: currentPage }); this.Get_PL(currentPage); } },
    onPerPageChange({ currentPerPage }) { if (this.limit !== currentPerPage) { this.limit = currentPerPage; this.updateParams({ page: 1, perPage: currentPerPage }); this.Get_PL(1); } },
    onSortChange(params) { if (!params || !params.length) return; this.updateParams({ sort: { type: params[0].type, field: params[0].field } }); this.Get_PL(this.serverParams.page); },
    onSearch(value) { this.search = value.searchTerm; this.Get_PL(this.serverParams.page); },
    async Get_PL(page) {
      NProgress.start(); NProgress.set(0.1);
      axios.get(
        "/accounting/v2/reports/profit-loss?page=" + page +
        "&SortField=" + this.serverParams.sort.field +
        "&SortType=" + this.serverParams.sort.type +
        "&search=" + this.search +
        "&limit=" + this.limit +
        "&from=" + (this.filters.from || "") +
        "&to=" + (this.filters.to || "") +
        "&type=" + (this.filters.type || "")
      )
      .then(({data}) => {
        this.rows = (data && (data.data || [])) || [];
        this.totalRows = (data && (data.totalRows ?? data.total ?? this.rows.length)) || 0;
        if (data && data.summary) this.summary = data.summary;
        NProgress.done(); this.isLoading = false;
      })
      .catch(() => { NProgress.done(); this.isLoading = false; });
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
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
    }
  }
};
</script>

<style scoped>
.kpi { border-left: 4px solid #663399; }
</style>



