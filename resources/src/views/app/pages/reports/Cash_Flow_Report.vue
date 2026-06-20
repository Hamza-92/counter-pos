<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Cash_Flow_Report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="toolbar-card shadow-soft mb-3 border-0">
      <div class="d-flex flex-wrap align-items-end">
        <!-- Date range -->
        <div class="mr-3 mb-2 date-range-filter">
          <label class="mb-1 d-block text-muted">{{ $t('DateRange') }}</label>
          <date-range-picker
            v-model="dateRange"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="isMobile ? 'center' : 'right'"
            :drops="'down'"
            @update="fetchReport"
          >
            <template v-slot:input="picker">
              <b-button variant="light" class="btn-pill date-btn" :class="{ 'w-100': isMobile }">
                <lucide-icon class="mr-1" name="calendar-days" />
                <span class="d-none d-sm-inline">{{ fmt(picker.startDate) }} — {{ fmt(picker.endDate) }}</span>
                <span class="d-inline d-sm-none">{{ fmtShort(picker.startDate) }}–{{ fmtShort(picker.endDate) }}</span>
              </b-button>
            </template>
          </date-range-picker>
        </div>

        <!-- Group By -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{ $t('GroupBy') }}</label>
          <b-form-select v-model="groupBy" :options="groupOptions" @change="onGroupChange" class="min-160"></b-form-select>
        </div>

        <!-- Warehouse -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{ $t('warehouse') }}</label>
          <b-form-select v-model="warehouseId" :options="warehouseOptions" @change="fetchReport" class="min-200" />
        </div>

        <!-- Account/Method filter (depends on groupBy) -->
        <div class="mr-3 mb-2" v-if="groupBy==='account'">
          <label class="mb-1 d-block text-muted">{{ $t('Account') }}</label>
          <b-form-select v-model="accountId" :options="accountOptions" @change="fetchReport" class="min-200" />
        </div>
        <div class="mr-3 mb-2" v-else>
          <label class="mb-1 d-block text-muted">{{ $t('PaymentMethod') }}</label>
          <b-form-select v-model="paymentMethodId" :options="paymentMethodOptions" @change="fetchReport" class="min-200" />
        </div>

        <div class="ml-auto mb-2 d-flex">
          <b-button variant="success" class="btn-pill mr-2" @click="exportPDF">
            <lucide-icon class="mr-1" name="file-text" /> {{ $t('Export_PDF') }}
          </b-button>

          <vue-excel-xlsx
            class="btn btn-primary btn-pill"
            :data="excelRows"
            :columns="excelColumns"
            :file-name="'Cash_Flow_Report'"
            :file-type="'xlsx'"
            :sheet-name="'CashFlow'"
          >
            <lucide-icon class="mr-1" name="file-spreadsheet" /> {{ $t('EXCEL') }}
          </vue-excel-xlsx>
        </div>
      </div>
    </b-card>

    <!-- Loading skeletons -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="4" v-for="n in 6" :key="'skel-'+n" class="mb-3">
          <b-skeleton-img class="rounded-xl shadow-soft" height="110px" />
        </b-col>
      </b-row>
    </div>

    <div v-else>
      <!-- Totals tiles -->
      <b-row class="mb-3">
        <b-col md="4" class="mb-3">
          <b-card class="shadow-soft border-0 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted small">{{ $t('TotalInflow') }}</div>
                <div class="h5 mb-0">{{ money(totalInflow) }}</div>
              </div>
              <lucide-icon class="text-success" name="user-plus" style="font-size:28px" />
            </div>
          </b-card>
        </b-col>
        <b-col md="4" class="mb-3">
          <b-card class="shadow-soft border-0 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted small">{{ $t('TotalOutflow') }}</div>
                <div class="h5 mb-0">{{ money(totalOutflow) }}</div>
              </div>
              <lucide-icon class="text-danger" name="user-minus" style="font-size:28px" />
            </div>
          </b-card>
        </b-col>
        <b-col md="4" class="mb-3">
          <b-card class="shadow-soft border-0 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted small">{{ $t('NetCashFlow') }}</div>
                <div class="h5 mb-0">{{ money(netCashFlow) }}</div>
              </div>
              <lucide-icon class="text-primary" name="wallet" style="font-size:28px" />
            </div>
          </b-card>
        </b-col>
      </b-row>

      <!-- Charts -->
      <b-row>
        <b-col md="6" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{ $t('Inflow_vs_Outflow_by_Group') }}</h6>
              <small class="text-muted">{{ fmt(dateRange.startDate) }} → {{ fmt(dateRange.endDate) }}</small>
            </div>
            <apexchart type="bar" height="300" :options="apexBarOptions" :series="apexBarSeries" />
          </b-card>
        </b-col>
        <b-col md="6" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{ $t('NetCashFlowOverTime') }}</h6>
              <small class="text-muted">{{ fmt(dateRange.startDate) }} → {{ fmt(dateRange.endDate) }}</small>
            </div>
            <apexchart type="line" height="300" :options="apexLineOptions" :series="apexLineSeries" />
          </b-card>
        </b-col>
      </b-row>

      <!-- Table -->
      <b-card class="shadow-soft border-0 print-table-only">
        <vue-good-table
          :rows="rows"
          :columns="columns"
          styleClass="tableOne table-hover vgt-table"
          :search-options="{enabled:true, placeholder:$t('Search_this_table')}"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
              <lucide-icon name="printer" /> {{ $t("print") }}
            </b-button>
          </div>
          <template slot="table-row" slot-scope="p">
            <span v-if="['inflow','outflow','net'].includes(p.column.field)">
              {{ money(p.row[p.column.field]) }}
            </span>
            <span v-else>
              {{ p.formattedRow[p.column.field] }}
            </span>
          </template>

          <template slot="table-actions-bottom">
            <div class="d-flex justify-content-end w-100 pt-2 font-weight-bold">
              {{ $t('Totals') }}:
              <span class="ml-2">{{ $t('TotalInflow') }} = {{ money(totalInflow) }}</span>
              <span class="ml-3">{{ $t('TotalOutflow') }} = {{ money(totalOutflow) }}</span>
              <span class="ml-3">{{ $t('NetCashFlow') }} = {{ money(netCashFlow) }}</span>
            </div>
          </template>
        </vue-good-table>
      </b-card>
    </div>
  </div>
  </template>

<script>
import NProgress from "nprogress";
import moment from "moment";
import { mapGetters } from "vuex";

import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import VueApexCharts from "vue-apexcharts";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: { title: "Cash Flow Report" },
  components: { "date-range-picker": DateRangePicker, apexchart: VueApexCharts },

  data() {
    const end = new Date(); const start = new Date(); start.setDate(end.getDate() - 29);
    return {
      isLoading: true,
      isMobile: false,

      dateRange: { startDate: start, endDate: end },
      locale: {
        Label: this.$t("Apply") || "Apply",
        cancelLabel: this.$t("Cancel") || "Cancel",
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange") || "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },

      groupBy: 'account',
      warehouseId: null,
      accountId: null,
      paymentMethodId: null,

      warehouses: [],
      accounts: [],
      payment_methods: [],

      rows: [],
      totalInflow: 0,
      totalOutflow: 0,
      netCashFlow: 0,
      timeseries: [], // [{d, inflow, outflow, net}]
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },

    groupOptions(){
      return [
        { value:'account', text: this.$t('Account') },
        { value:'method',  text: this.$t('PaymentMethod') }
      ];
    },
    warehouseOptions(){
      return [{ value: null, text: this.$t('AllWarehouses') }].concat(
        (this.warehouses||[]).map(w => ({ value:w.id, text:w.name }))
      );
    },
    accountOptions(){
      return [{ value: null, text: this.$t('AllAccounts') }].concat(
        (this.accounts||[]).map(a => ({ value:a.id, text:a.account_name }))
      );
    },
    paymentMethodOptions(){
      return [{ value: null, text: this.$t('AllPaymentMethods') }].concat(
        (this.payment_methods||[]).map(m => ({ value:m.id, text:m.name }))
      );
    },

    columns(){
      return [
        { label: this.$t('Group'), field:'group', sortable:true, tdClass:'text-left', thClass:'text-left' },
        { label: this.$t('Inflow'), field:'inflow', type:'number', sortable:true },
        { label: this.$t('Outflow'), field:'outflow', type:'number', sortable:true },
        { label: this.$t('Net'), field:'net', type:'number', sortable:true }
      ];
    },

    // Apex: Bar (grouped inflow/outflow)
    apexBarOptions(){
      const cats = (this.rows||[]).map(r => r.group);
      return {
        chart: { type:'bar', stacked:true, toolbar:{ show:false } },
        plotOptions: { bar: { horizontal:true } },
        dataLabels: { enabled:false },
        xaxis: { categories: cats, labels:{ formatter:(v)=> this.compact(v) } },
        yaxis: { labels:{ show:true } },
        legend: { position:'top' },
        tooltip: { y: { formatter: (v)=> this.money(v) } },
        grid: { padding: { left: 8, right: 8 } }
      };
    },
    apexBarSeries(){
      const inflow = (this.rows||[]).map(r => Number(r.inflow||0));
      const outflow = (this.rows||[]).map(r => Number(r.outflow||0));
      return [
        { name: this.$t('Inflow'), data: inflow },
        { name: this.$t('Outflow'), data: outflow }
      ];
    },

    // Apex: Line (net over time)
    apexLineOptions(){
      const dates = (this.timeseries||[]).map(x => x.d);
      return {
        chart: { type:'line', toolbar:{ show:false } },
        stroke: { curve:'smooth', width:3 },
        dataLabels: { enabled:false },
        xaxis: { categories: dates, labels: { rotate:-45 } },
        yaxis: { labels: { formatter: (v) => this.compact(v) } },
        tooltip: { y: { formatter: (v)=> this.money(v) } },
        legend: { show: true }
      };
    },
    apexLineSeries(){
      return [
        { name: this.$t('Inflow'),  data: (this.timeseries||[]).map(x => Number(x.inflow||0)) },
        { name: this.$t('Outflow'), data: (this.timeseries||[]).map(x => Number(x.outflow||0)) },
        { name: this.$t('Net'),     data: (this.timeseries||[]).map(x => Number(x.net||0)) }
      ];
    },

    // Excel export data
    excelColumns(){
      return [
        { label: 'Group', field: 'group' },
        { label: 'Inflow', field: 'inflow' },
        { label: 'Outflow', field: 'outflow' },
        { label: 'Net', field: 'net' }
      ];
    },
    excelRows(){ return (this.rows||[]).map(r => ({ ...r })); }
  },

  methods: {
    // Print ONLY the table (as on screen) in a clean window to avoid blank/extra pages
    printTableOnly() {
      const root = this.$el;
      if (!root) {
        window.print();
        return;
      }

      const tableCard = root.querySelector(".print-table-only");
      if (!tableCard) {
        window.print();
        return;
      }

      const clone = tableCard.cloneNode(true);
      // Remove interactive UI inside cloned table area
      clone
        .querySelectorAll(".vgt-table-actions, .vgt-global-search, .vgt-pagination, button")
        .forEach(el => el.remove());

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Cash_Flow_Report")}`;
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
        .join("\n");

      // Copy inline styles EXCEPT print styles (to avoid global print rules hiding body)
      const inlineStyles = Array.from(document.querySelectorAll("style"))
        .filter(s => !((s.textContent || "").includes("@media print")))
        .map(s => s.outerHTML)
        .join("\n");

      const doc = w.document;
      doc.open();
      doc.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <base href="${window.location.origin}/" />
    <title>${title}</title>
    ${links}
    ${inlineStyles}
    <style>
      /* Force visibility in print (some global POS print CSS hides body) */
      @media print { body, body * { visibility: visible !important; } }
      body { margin: 0.3cm; }
      .print-header { font-weight: 600; margin-bottom: 8px; }
      /* Hide any table-action wrappers if they remain */
      .vgt-table-actions, .vgt-global-search, .vgt-pagination { display: none !important; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
  </body>
</html>`);
      doc.close();

      w.document.body.appendChild(clone);
      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    },
    handleResize(){ this.isMobile = window.innerWidth < 576; },
    fmt(d){ return moment(d).format('YYYY-MM-DD'); },
    fmtShort(d){ return moment(d).format('MMM D'); },
    compact(v){
      try { return new Intl.NumberFormat(undefined,{ notation:'compact', maximumFractionDigits:1 }).format(Number(v||0)); }
      catch { return v; }
    },
    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing Intl.NumberFormat behavior to preserve current behavior.
    money(v){
      try {
        const n = Number(v || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        const formatted = formatPriceDisplayHelper(n, 2, effectiveKey);
        return `${this.currency} ${formatted}`;
      } catch(e) {
        try {
          return new Intl.NumberFormat(undefined,{ style:'currency', currency:this.currency }).format(Number(v||0));
        } catch(e2) {
          return `${this.currency} ${(Number(v||0)).toLocaleString()}`;
        }
      }
    },

    onGroupChange(){ this.accountId = null; this.paymentMethodId = null; this.fetchReport(); },

    exportPDF(){
      const doc = new jsPDF({ orientation:'portrait', unit:'pt', format:'a4' });
      const pageW = doc.internal.pageSize.getWidth(); const marginX = 40;
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try { doc.addFont(fontPath, "Vazirmatn", "normal"); doc.addFont(fontPath, "Vazirmatn", "bold"); } catch(_) {}
      doc.setFont("Vazirmatn", "normal");
      const rtl = (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) || (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');
      const title = 'Cash Flow Report'; const range = `${this.fmt(this.dateRange.startDate)} — ${this.fmt(this.dateRange.endDate)}`;
      doc.setFont("Vazirmatn", "bold"); doc.setFontSize(14);
      rtl ? doc.text(title, pageW - marginX, 40, { align:'right' }) : doc.text(title, marginX, 40);
      doc.setFont("Vazirmatn", "normal"); doc.setFontSize(10);
      rtl ? doc.text(range, pageW - marginX, 58, { align:'right' }) : doc.text(range, marginX, 58);

      const head = [[ this.$t('Group'), this.$t('Inflow'), this.$t('Outflow'), this.$t('Net') ]];
      const body = (this.rows||[]).map(r => ([ r.group, Number(r.inflow||0).toFixed(2), Number(r.outflow||0).toFixed(2), Number(r.net||0).toFixed(2) ]));

      autoTable(doc, {
        startY: 80,
        head, body,
        styles: { font:"Vazirmatn", fontSize:9, cellPadding:6, halign: rtl ? 'right':'left' },
        headStyles: { font:"Vazirmatn", fontStyle:'bold', fillColor:[26,86,219], textColor:255, halign: rtl ? 'right':'left' },
        columnStyles: { 1:{ halign:'right' }, 2:{ halign:'right' }, 3:{ halign:'right' } },
        foot: [[
          { content: this.$t('Totals'), styles:{ font:'Vazirmatn', fontStyle:'bold', halign: rtl ? 'right':'left' } },
          { content: Number(this.totalInflow||0).toFixed(2), styles:{ halign:'right', fontStyle:'bold' } },
          { content: Number(this.totalOutflow||0).toFixed(2), styles:{ halign:'right', fontStyle:'bold' } },
          { content: Number(this.netCashFlow||0).toFixed(2), styles:{ halign:'right', fontStyle:'bold' } },
        ]],
        margin: { left: marginX, right: marginX }
      });

      doc.save(`cash-flow_${this.fmt(this.dateRange.startDate)}_${this.fmt(this.dateRange.endDate)}.pdf`);
    },

    fetchReport(){
      NProgress.start(); NProgress.set(0.1); this.isLoading = true;
      const qs = new URLSearchParams({
        from: this.fmt(this.dateRange.startDate),
        to:   this.fmt(this.dateRange.endDate),
        group_by: this.groupBy,
        warehouse_id: this.warehouseId || '',
        account_id: this.groupBy==='account' ? (this.accountId || '') : '',
        payment_method_id: this.groupBy==='method' ? (this.paymentMethodId || '') : ''
      }).toString();

      axios.get(`report/cash_flow_report?${qs}`).then(({data}) => {
        this.rows = Array.isArray(data.rows) ? data.rows : [];
        this.totalInflow  = Number(data.total_inflow || 0);
        this.totalOutflow = Number(data.total_outflow || 0);
        this.netCashFlow  = Number(data.net_cash_flow || 0);
        this.timeseries   = Array.isArray(data.timeseries) ? data.timeseries : [];
        this.warehouses   = Array.isArray(data.warehouses) ? data.warehouses : [];
        this.payment_methods = Array.isArray(data.payment_methods) ? data.payment_methods : [];
        this.accounts     = Array.isArray(data.accounts) ? data.accounts : [];
        this.isLoading = false; NProgress.done();
      }).catch(() => { this.isLoading = false; NProgress.done(); });
    }
  },

  mounted(){ this.handleResize(); window.addEventListener('resize', this.handleResize); },
  beforeDestroy(){ window.removeEventListener('resize', this.handleResize); },
  created(){ this.fetchReport(); }
};
</script>

<style scoped>
.rounded-xl { border-radius: 1rem; }
.shadow-soft { box-shadow: 0 12px 24px rgba(0,0,0,.06), 0 2px 6px rgba(0,0,0,.05); }
.toolbar-card { background: #fff; }
.btn-pill { border-radius: 999px; }
.min-160 { min-width: 160px; }
.min-200 { min-width: 200px; }

.date-range-filter { min-width: 240px; }
@media (max-width: 575.98px) {
  .date-range-filter { width: 100%; }
  .date-btn { justify-content: center; }
  .quick-ranges { display:flex !important; flex-wrap:wrap; width:100%; }
  .quick-ranges .btn { flex:1 1 calc(50% - 6px); margin-bottom:6px; }
}
</style>




















