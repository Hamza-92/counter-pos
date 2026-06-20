<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('SalesInvoice')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else>
      <!-- Toolbar -->
      <b-card class="shadow-soft border-0 mb-3">
        <div class="d-flex flex-wrap align-items-center">
          <!-- Date range (responsive) -->
          <div class="mr-3 mb-2 d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center">
            <label class="mb-1 mb-sm-0 mr-sm-2 text-muted">{{$t('DateRange')}}</label>
            <date-range-picker
              v-model="dateRange"
              :locale-data="locale"
              :autoApply="true"
              :showDropdowns="true"
              @update="Submit_filter_dateRange"
            >
              <template v-slot:input="picker">
                <b-button variant="light" class="btn-pill w-100 text-left">
                  <lucide-icon class="mr-1" name="calendar-days" />
                  {{ fmt(picker.startDate) }} — {{ fmt(picker.endDate) }}
                </b-button>
              </template>
            </date-range-picker>
          </div>

          <!-- Quick ranges -->
          <div class="mr-3 mb-2">
            <label class="mb-1 d-block text-muted">{{$t('QuickRanges')}}</label>
            <div class="btn-group quick-ranges">
              <b-button size="sm" variant="outline-primary" @click="quick('7d')">7D</b-button>
              <b-button size="sm" variant="outline-primary" @click="quick('30d')">30D</b-button>
              <b-button size="sm" variant="outline-primary" @click="quick('90d')">90D</b-button>
              <b-button size="sm" variant="outline-primary" @click="quick('mtd')">{{$t('MTD')}}</b-button>
              <b-button size="sm" variant="outline-primary" @click="quick('ytd')">{{$t('YTD')}}</b-button>
            </div>
          </div>

          <div class="ml-auto mb-2">
            <b-button variant="primary" class="btn-pill mr-2" @click="Payments_Sales(serverParams.page)">
              <lucide-icon class="mr-1" name="refresh-cw" /> {{$t('Refresh')}}
            </b-button>
            <b-button @click="Payment_PDF" size="sm" variant="outline-success" class="btn-pill mr-2">
              <lucide-icon name="copy" /> PDF
            </b-button>
            <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger btn-pill"
              :data="payments"
              :columns="excelColumns"
              :file-name="'payments_sales'"
              :file-type="'xlsx'"
              :sheet-name="'payments_sales'"
            >
              <lucide-icon name="file-spreadsheet" /> EXCEL
            </vue-excel-xlsx>
          </div>
        </div>
      </b-card>

      <!-- Charts -->
      <b-row>
        <b-col md="8" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{$t('PaymentsOverTime')}}</h6>
              <small class="text-muted">{{ fmt(dateRange.startDate) }} → {{ fmt(dateRange.endDate) }}</small>
            </div>
            <apexchart type="line" height="320" :options="apexTimeOptions" :series="apexTimeSeries" />
          </b-card>
        </b-col>
        <b-col md="4" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{$t('PaymentsByMethod')}}</h6>
              <small class="text-muted">{{$t('ByAmount')}}</small>
            </div>
            <apexchart type="bar" height="320" :options="apexMethodOptions" :series="apexMethodSeries" />
          </b-card>
        </b-col>
      </b-row>

      <!-- Table -->
      <b-card class="wrapper shadow-soft border-0">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="rows"
          :group-options="{ enabled: true, headerPosition: 'bottom' }"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
          :search-options="{ placeholder: $t('Search_this_table'), enabled: true }"
          :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
          styleClass="table-hover tableOne vgt-table"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right class="btn-pill">
              <lucide-icon name="filter" /> {{ $t('Filter') }}
            </b-button>
            <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1" class="btn-pill">
              <lucide-icon name="printer" /> {{ $t("print") }}
            </b-button>
          </div>

          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field === 'montant'">
              {{ formatPriceDisplay(props.row.montant, 2) }}
            </span>
            <span v-else-if="props.column.field === 'Ref_Sale' && props.row.sale_id">
              <router-link :to="{ name: 'detail_sale', params: { id: props.row.sale_id } }" class="text-primary">
                {{ props.formattedRow[props.column.field] }}
              </router-link>
            </span>
            <span v-else>
              {{ props.formattedRow[props.column.field] }}
            </span>
          </template>
        </vue-good-table>
      </b-card>
    </div>

    <!-- Sidebar Filter -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- Reference -->
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input :placeholder="$t('Reference')" v-model="Filter_Ref" />
            </b-form-group>
          </b-col>

          <!-- Customers  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')">
              <v-select
                :reduce="o => o.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_client"
                :options="clients.map(c => ({label: c.name, value: c.id}))"
                :clearable="true"
              />
            </b-form-group>
          </b-col>

          <!-- Sale  -->
          <b-col md="12">
            <b-form-group :label="$t('Sale')">
              <v-select
                :reduce="o => o.value"
                :placeholder="$t('PleaseSelect')"
                v-model="Filter_sale"
                :options="sales.map(s => ({label: s.Ref, value: s.id}))"
                :clearable="true"
              />
            </b-form-group>
          </b-col>

          <!-- Payment choice -->
          <b-col md="12">
            <b-form-group :label="$t('Paymentchoice')">
              <v-select
                v-model="Filter_Reg"
                :reduce="o => o.value"
                :placeholder="$t('PleaseSelect')"
                :options="payment_methods.map(pm => ({label: pm.name, value: pm.id}))"
                :clearable="true"
              />
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Payments_Sales(1)"
              variant="primary"
              size="sm"
              block
              class="btn-pill"
            >
              <lucide-icon name="filter" /> {{ $t('Filter') }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter" variant="danger" size="sm" block class="btn-pill">
              <lucide-icon name="power" /> {{ $t('Reset') }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>
  </div>
</template>

<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";
import VueApexCharts from "vue-apexcharts";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../../utils/priceFormat";

export default {
  metaInfo: { title: "Payment Sales" },
  components: { DateRangePicker, apexchart: VueApexCharts },

  data() {
    const end = new Date();
    const start = new Date(); start.setDate(end.getDate() - 29);
    return {
      isLoading: true,

      // table state
      serverParams: { sort: { field: "id", type: "desc" }, page: 1, perPage: 10 },
      limit: "10",
      search: "",
      totalRows: 0,

      // filters
      Filter_client: "",
      Filter_Ref: "",
      Filter_sale: "",
      Filter_Reg: "",

      // data
      payments: [],
      clients: [],
      sales: [],
      payment_methods: [],

      // vgt rows (with footer group)
      rows: [{ children: [] }],

      // date range
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
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  computed: {
    columns() {
      return [
        { label: this.$t("date"),          field: "date",           tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Reference"),     field: "Ref",            tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Sale"),          field: "Ref_Sale",       tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Customer"),      field: "client_name",    tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("ModePaiement"),  field: "payment_method", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Account"),       field: "account_name",   tdClass: "text-left", thClass: "text-left", sortable:false },
        { label: this.$t("Amount"),        field: "montant",        // Let headerField return a formatted string; avoid vue-good-table's decimal re-formatting.
          headerField: this.sumCount, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("AddedBy"), field: "user_name", tdClass: "text-left", thClass: "text-left", sortable:false },
      ];
    },

    // for excel lib (simple mapping)
    excelColumns(){
      return [
        { label: this.$t("date"), field: "date" },
        { label: this.$t("Reference"), field: "Ref" },
        { label: this.$t("Sale"), field: "Ref_Sale" },
        { label: this.$t("Customer"), field: "client_name" },
        { label: this.$t("ModePaiement"), field: "payment_method" },
        { label: this.$t("Account"), field: "account_name" },
        { label: this.$t("Amount"), field: "montant" },
        { label: this.$t("AddedBy"), field: "user_name" },
      ];
    },

    // ApexCharts: time series (line)
    apexTimeOptions(){
      const map = new Map();
      (this.payments || []).forEach(p => {
        const d = p.date ? String(p.date).slice(0,10) : "";
        const amt = Number(p.montant || 0);
        if (!d) return;
        map.set(d, (map.get(d) || 0) + amt);
      });
      const dates = Array.from(map.keys()).sort();
      return {
        chart: { type: 'line', toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        xaxis: { categories: dates, labels: { rotate: -45 } },
        yaxis: { labels: { formatter: (v) => {
          try { return new Intl.NumberFormat(undefined,{ notation:'compact', maximumFractionDigits:1 }).format(Number(v||0)); }
          catch { return v; }
        } } },
        tooltip: { y: { formatter: (v) => {
          try { return this.formatPriceDisplay(v, 2); }
          catch { return v; }
        } } },
        grid: { padding: { left: 10, right: 10, top: 10, bottom: 10 } }
      };
    },
    apexTimeSeries(){
      const map = new Map();
      (this.payments || []).forEach(p => {
        const d = p.date ? String(p.date).slice(0,10) : "";
        const amt = Number(p.montant || 0);
        if (!d) return;
        map.set(d, (map.get(d) || 0) + amt);
      });
      const dates = Array.from(map.keys()).sort();
      const vals = dates.map(d => map.get(d));
      return [{ name: this.$t('Amount'), data: vals }];
    },

    // ApexCharts: by method (horizontal bar)
    apexMethodOptions(){
      const map = new Map();
      (this.payments || []).forEach(p => {
        const k = p.payment_method || this.$t('Unknown');
        map.set(k, (map.get(k) || 0) + Number(p.montant || 0));
      });
      const cats = Array.from(map.keys());
      return {
        chart: { type: 'bar', toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true } },
        dataLabels: { enabled: false },
        xaxis: { categories: cats },
        tooltip: { y: { formatter: (v) => {
          try { return this.formatPriceDisplay(v, 2); }
          catch { return v; }
        } } },
        grid: { padding: { left: 10, right: 10, top: 10, bottom: 10 } }
      };
    },
    apexMethodSeries(){
      const map = new Map();
      (this.payments || []).forEach(p => {
        const k = p.payment_method || this.$t('Unknown');
        map.set(k, (map.get(k) || 0) + Number(p.montant || 0));
      });
      const cats = Array.from(map.keys());
      const vals = cats.map(k => map.get(k));
      return [{ name: this.$t('Amount'), data: vals }];
    }
  },

  methods: {
    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing toLocaleString behavior to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 2;
        const n = Number(number || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, decimals, effectiveKey);
      } catch (e) {
        const n = Number(number || 0);
        return n.toLocaleString(undefined, { maximumFractionDigits: dec || 2 });
      }
    },
    // ---------- utils ----------
    fmt(d){ return moment(d).format("YYYY-MM-DD"); },
    // Group footer helper for vue-good-table.
    // Returns a formatted string so the footer row inside the table
    // looks like a normal data row, but uses the global price format.
    sumCount(rowObj){
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceDisplay(0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].montant) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceDisplay(sum, 2);
    },
    findLabel(list, id, key='name'){
      if (!id) return this.$t('All');
      const x = (list||[]).find(i => String(i.id) === String(id));
      return x ? (x[key] ?? this.$t('All')) : this.$t('All');
    },
    findSaleRef(id){
      if (!id) return this.$t('All');
      const x = (this.sales||[]).find(i => String(i.id) === String(id));
      return x ? (x.Ref || this.$t('All')) : this.$t('All');
    },

    // ---------- quick ranges ----------
    quick(kind){
      const now = moment(); let s, e = now.clone();
      if (kind==='7d')  s = now.clone().subtract(6,'days');
      if (kind==='30d') s = now.clone().subtract(29,'days');
      if (kind==='90d') s = now.clone().subtract(89,'days');
      if (kind==='mtd'){ s = now.clone().startOf('month'); e = now; }
      if (kind==='ytd'){ s = now.clone().startOf('year');  e = now; }
      this.dateRange = { startDate: s.toDate(), endDate: e.toDate() };
      this.Payments_Sales(1);
    },

    // ---------- table handlers ----------
    updateParams(newProps){ this.serverParams = Object.assign({}, this.serverParams, newProps); },
    onPageChange({ currentPage }){ if (this.serverParams.page !== currentPage){ this.updateParams({ page: currentPage }); this.Payments_Sales(currentPage); } },
    onPerPageChange({ currentPerPage }){ if (this.limit !== currentPerPage){ this.limit = String(currentPerPage); this.updateParams({ page: 1, perPage: currentPerPage }); this.Payments_Sales(1); } },
    onSortChange(params){
      if (params && params[0]) {
        const field = params[0].field === 'Ref_Sale' ? 'sale_id' : params[0].field;
        this.updateParams({ sort: { type: params[0].type, field } });
        this.Payments_Sales(this.serverParams.page);
      }
    },
    onSearch(value){ this.search = value.searchTerm || ""; this.Payments_Sales(this.serverParams.page); },

    // ---------- date picker ----------
    Submit_filter_dateRange(){
      // fetch with new range
      this.Payments_Sales(1);
    },

    // ---------- filters ----------
    Reset_Filter(){
      this.search = "";
      this.Filter_client = "";
      this.Filter_Ref = "";
      this.Filter_sale = "";
      this.Filter_Reg = "";
      this.Payments_Sales(1);
    },

    //------ Print Table Only - Print ALL payments data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("SalesInvoice")}`;
      const payments = Array.isArray(this.payments) ? this.payments : [];
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      payments.forEach(payment => {
        tableHTML += '<tr>';
        this.columns.forEach(col => {
          let cellValue = '';
          
          if (col.field === 'date') {
            cellValue = payment.date ? this.fmt(payment.date) : '';
          } else if (col.field === 'Ref') {
            cellValue = payment.Ref || '';
          } else if (col.field === 'Ref_Sale') {
            cellValue = payment.Ref_Sale || '';
          } else if (col.field === 'client_name') {
            cellValue = payment.client_name || '';
          } else if (col.field === 'payment_method') {
            cellValue = payment.payment_method || '';
          } else if (col.field === 'account_name') {
            cellValue = payment.account_name || '';
          } else if (col.field === 'montant') {
            cellValue = this.formatPriceDisplay(payment.montant, 2);
          } else if (col.field === 'user_name') {
            cellValue = payment.user_name || '';
          } else {
            // Default: get value directly from payment object
            cellValue = payment[col.field] || '';
          }
          
          tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; text-align: left;">${cellValue}</td>`;
        });
        tableHTML += '</tr>';
      });
      
      tableHTML += '</tbody></table>';

      const w = window.open("", "_blank");
      if (!w) {
        alert("Please allow popups to print");
        return;
      }

      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
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
    <style>
      /* Force visibility in print (some global POS print CSS hides body) */
      @media print { 
        body, body * { visibility: visible !important; }
        @page { size: A4 landscape; margin: 0.3cm; }
      }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 10px; font-size: 14px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 10px; }
      th { background-color: #f5f5f5; font-weight: bold; }
      tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    ${tableHTML}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    },

    // ---------- fetch ----------
    Payments_Sales(page){
      NProgress.start(); NProgress.set(0.1);

      // Normalize null -> ''
      const client_id  = this.Filter_client  || '';
      const sale_id    = this.Filter_sale    || '';
      const method_id  = this.Filter_Reg     || '';
      const ref        = this.Filter_Ref     || '';
      const from       = this.fmt(this.dateRange.startDate);
      const to         = this.fmt(this.dateRange.endDate);

      const url = "payment_sale?" + new URLSearchParams({
        page: String(page),
        Ref: ref,
        client_id,
        sale_id,
        payment_method_id: method_id,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.search || '',
        limit: this.limit,
        to, from
      }).toString();

      axios.get(url)
        .then(({data})=>{
          this.payments = data.payments || [];
          this.clients = data.clients || [];
          this.sales = data.sales || [];
          this.payment_methods = data.payment_methods || [];
          this.totalRows = Number(data.totalRows || 0);
          this.rows[0].children = this.payments;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(()=>{ NProgress.done(); setTimeout(()=>{ this.isLoading=false; }, 300); });
    },


    // ---------- shared font + RTL helpers ----------
    useVazirmatn(pdf){
      // Make sure this file exists and is publicly accessible:
      // /public/fonts/Vazirmatn-Bold.ttf
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        // Reuse the same TTF as "normal" and "bold"
        pdf.addFont(fontPath, "Vazirmatn", "normal");
        pdf.addFont(fontPath, "Vazirmatn", "bold");
      } catch(e){ /* ignore if already added */ }
      pdf.setFont("Vazirmatn", "normal");
    },
    isRTL(){
      // works with vue-i18n or <html dir="rtl">
      return (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale))
          || (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');
    },

    // ---------- lookups (safe if null) ----------
    findLabel(list, id, key='name'){
      if (!id) return this.$t('All');
      const x = (list||[]).find(i => String(i.id) === String(id));
      return x ? (x[key] ?? this.$t('All')) : this.$t('All');
    },
    findSaleRef(id){
      if (!id) return this.$t('All');
      const x = (this.sales||[]).find(i => String(i.id) === String(id));
      return x ? (x.Ref || this.$t('All')) : this.$t('All');
    },

    // ---------- EXPORT PDF (Payments Sales) ----------
    async Payment_PDF(){
      NProgress.start(); NProgress.set(0.2);
      try{
        // robust date formatting
        const fmtLocal = (d) => {
          if (!d) return '';
          if (this.fmt) return this.fmt(d);
          return (d instanceof Date) ? d.toISOString().slice(0,10) : String(d);
        };
        const from = this.startDate || fmtLocal(this.dateRange?.startDate);
        const to   = this.endDate   || fmtLocal(this.dateRange?.endDate);

        // fetch ALL rows using current filters/sort
        const qs = new URLSearchParams({
          page: '1',
          limit: '-1',
          SortField: this.serverParams?.sort?.field || 'id',
          SortType:  this.serverParams?.sort?.type  || 'desc',
          search: this.search || '',
          from, to,
          Ref: this.Filter_Ref || '',
          client_id: this.Filter_client || '',
          sale_id: this.Filter_sale || '',
          payment_method_id: this.Filter_Reg || ''
        }).toString();

        const { data } = await axios.get(`payment_sale?${qs}`).catch(()=>({data:{}}));
        const items = Array.isArray(data?.payments) ? data.payments : [];

        // PDF setup (landscape A4)
        const pdf = new jsPDF({ orientation:'landscape', unit:'pt', format:'a4' });
        this.useVazirmatn(pdf);
        const rtl = this.isRTL();
        const margin = 40;
        const pageW = pdf.internal.pageSize.getWidth();

        // Title
        pdf.setFont('Vazirmatn','bold'); pdf.setFontSize(16);
        const title = 'Payment Sales';
        rtl ? pdf.text(title, pageW - margin, 40, { align:'right' })
            : pdf.text(title, margin, 40);

        // Header (filters + date range) with auto-wrap
        pdf.setFont('Vazirmatn','normal'); pdf.setFontSize(10);
        const customerLabel = this.findLabel(this.clients, this.Filter_client, 'name');
        const saleLabel     = this.findSaleRef(this.Filter_sale);
        const methodLabel   = this.findLabel(this.payment_methods, this.Filter_Reg, 'name');
        const refFilter     = this.Filter_Ref || this.$t('All');
        const range         = `${from || '—'} — ${to || '—'}`;

        const headerText = [
          `${this.$t('DateRange')}: ${range}`,
          `${this.$t('Reference')}: ${refFilter}`,
          `${this.$t('Customer')}: ${customerLabel}`,
          `${this.$t('Sale')}: ${saleLabel}`,
          `${this.$t('ModePaiement')}: ${methodLabel}`
        ].join('   •   ');

        const wrapped = pdf.splitTextToSize(headerText, pageW - margin*2);
        rtl ? pdf.text(wrapped, pageW - margin, 58, { align:'right' })
            : pdf.text(wrapped, margin, 58);

        // Table
        const head = [[
          this.$t('date'),
          this.$t('Reference'),
          this.$t('Sale'),
          this.$t('Customer'),
          this.$t('ModePaiement'),
          this.$t('Account'),
          this.$t('Amount'),
          this.$t('AddedBy'),
        ]];

        const body = items.map(r => ([
          r.date || '',
          r.Ref || '',
          r.Ref_Sale || '',
          r.client_name || '',
          r.payment_method || '',
          r.account_name || '',
          Number(r.montant || 0).toFixed(2),
          r.user_name || '---'
        ]));

        const total = items.reduce((a,b)=> a + Number(b.montant || 0), 0);

        autoTable(pdf, {
          startY: 80,
          head, body,
          margin: { left: margin, right: margin },
          theme: 'striped',
          styles: {
            font: 'Vazirmatn',
            fontStyle: 'normal',
            fontSize: 9,
            cellPadding: 6,
            overflow: 'linebreak',
            halign: rtl ? 'right' : 'left',
          },
          headStyles: {
            font: 'Vazirmatn',
            fontStyle: 'bold',
            fillColor: [26,86,219],
            textColor: 255,
            halign: rtl ? 'right' : 'left',
          },
          columnStyles: {
            6: { halign:'right' }, // Amount column
          },
          foot: [[
            { content: this.$t('Totals'), colSpan: 7, styles:{ halign:'right', fontStyle:'bold' } },
            { content: total.toFixed(2),  styles:{ halign:'right', fontStyle:'bold' } }
          ]],
          didDrawPage: (d) => {
            pdf.setFont('Vazirmatn','normal'); pdf.setFontSize(8);
            pdf.text(`${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`,
                    pageW - margin, pdf.internal.pageSize.getHeight() - 14, { align:'right' });
          }
        });

        pdf.save(`payments_sales_${from || 'all'}_${to || 'all'}.pdf`);
      } finally {
        NProgress.done();
      }
    },




  },

  created(){ this.Payments_Sales(1); }
};
</script>

<style scoped>
.shadow-soft{box-shadow:0 12px 24px rgba(0,0,0,.06),0 2px 6px rgba(0,0,0,.05);}
.btn-pill{border-radius:999px;}

/* date-range responsiveness */
.date-range-filter { min-width: 240px; }
@media (max-width: 575.98px) {
  .date-range-filter { width: 100%; }
  .date-btn { justify-content: center; }
  .daterangepicker {
    left: 0 !important; right: 0 !important; margin: 0 !important;
    width: 100vw !important; max-width: 100vw !important;
  }
  .daterangepicker .ranges, .daterangepicker .drp-calendar {
    float: none !important; width: 100% !important;
  }

  /* Wrap quick range buttons into two columns */
  .quick-ranges { display:flex !important; flex-wrap:wrap; width:100%; }
  .quick-ranges .btn { flex:1 1 calc(50% - 6px); margin-bottom:6px; }
}
</style>
