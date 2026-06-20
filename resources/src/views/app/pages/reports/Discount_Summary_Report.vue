<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Discount_Summary_Report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="toolbar-card shadow-soft mb-3 border-0">
      <div class="d-flex flex-wrap align-items-center">
        <!-- Date range -->
        <div class="filter-block date-range-filter mr-3 mb-2">
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
              <b-button
                variant="light"
                class="btn-pill date-btn"
                :class="{ 'w-100': isMobile }"
              >
                <lucide-icon class="mr-1" name="calendar-days" />
                <!-- full text on ≥ sm -->
                <span class="d-none d-sm-inline">
                  {{ fmt(picker.startDate) }} — {{ fmt(picker.endDate) }}
                </span>
                <!-- compact on < sm -->
                <span class="d-inline d-sm-none">
                  {{ fmtShort(picker.startDate) }}–{{ fmtShort(picker.endDate) }}
                </span>
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
          <b-button variant="success" class="btn-pill mr-2" @click="exportPDF">
            <lucide-icon class="mr-1" name="file-text" /> {{$t('Export_PDF')}}
          </b-button>
          <b-button variant="primary" class="btn-pill" @click="fetchReport">
            <lucide-icon class="mr-1" name="refresh-cw" /> {{$t('Refresh')}}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="4" v-for="n in 6" :key="'skel-'+n" class="mb-3">
          <b-skeleton-img class="rounded-xl shadow-soft" height="110px" />
        </b-col>
      </b-row>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- ECharts -->
      <b-card class="shadow-soft border-0 mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="m-0">{{$t('DiscountsOverTime')}}</h6>
          <small class="text-muted">{{ fmt(dateRange.startDate) }} → {{ fmt(dateRange.endDate) }}</small>
        </div>
        <apexchart type="line" height="300" :options="apexLineOptions" :series="apexLineSeries" />
      </b-card>

      <!-- Table -->
      <b-card class="shadow-soft border-0">
        <vue-good-table
          mode="remote"
          :rows="rows"
          :columns="columns"
          :totalRows="totalRows"
          :group-options="{
            enabled: true,
            headerPosition: 'bottom',
          }"
          styleClass="tableOne table-hover vgt-table"
          :pagination-options="{enabled:true, mode:'records'}"
          :search-options="{enabled:true, placeholder:$t('Search_this_table')}"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
              <lucide-icon name="printer" /> {{ $t("print") }}
            </b-button>
          </div>
          <template slot="table-row" slot-scope="p">
            <span v-if="['line_discount','header_manual_discount','header_points_discount','total_discount'].includes(p.column.field)">
              {{ money(p.row[p.column.field]) }}
            </span>
            <span v-else>{{ p.formattedRow[p.column.field] }}</span>
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

/**
 * ECharts (vue-echarts v4 style)
 * Keep these side-effect imports so the series/components are registered.
 */
import VueApexCharts from "vue-apexcharts";

import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: { title: "Discount Summary Report" },
  components: { "date-range-picker": DateRangePicker, apexchart: VueApexCharts },

  data() {
    const end = new Date(); const start = new Date(); start.setDate(end.getDate() - 29);
    return {
      isLoading: true,
      isMobile: false,
      // date filters
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

      // table state
      serverParams: { page: 1, perPage: 10, sort: { field: "date_time", type: "desc" } },
      limit: 10,
      search: "",
      totalRows: 0,
      rows: [{ statut: '', children: [] }],
      overallTotal: 0,

      // chart source
      timeseries: [],
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },

    columns() {
      return [
        { label: this.$t('ID'),             field:'sale_id',               sortable:true },
        { label: this.$t('date'),           field:'date_time',             sortable:true },
        { label: this.$t('User'),           field:'user_name',             sortable:true, tdClass:'text-left', thClass:'text-left' },
        { label: this.$t('Line_Discount'),  field:'line_discount',         headerField: this.sumLineDiscount, sortable:true },
        { label: this.$t('Header_Discount'),field:'header_manual_discount',headerField: this.sumHeaderDiscount, sortable:true },
        { label: this.$t('Discount_from_Points'), field:'header_points_discount', headerField: this.sumPointsDiscount, sortable:true },
        { label: this.$t('Total_Discount'), field:'total_discount',        headerField: this.sumTotalDiscount, sortable:true },
      ];
    },

    // ApexCharts options/series
    apexLineOptions(){
      const dates = this.timeseries.map(x => x.d);
      return {
        chart: { type: 'line', toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        xaxis: { categories: dates, labels: { rotate: -45 } },
        yaxis: { labels: { formatter: (v) => {
          try { return new Intl.NumberFormat(undefined,{notation:'compact',maximumFractionDigits:1}).format(Number(v||0)); }
          catch { return v; }
        } } },
        tooltip: { y: { formatter: (v) => {
          try { return new Intl.NumberFormat(undefined,{style:'currency',currency:this.currency}).format(Number(v||0)); }
          catch { return `${this.currency} ${(Number(v||0)).toLocaleString()}`; }
        } } },
        legend: { show: false },
        grid: { padding: { left: 10, right: 10, top: 10, bottom: 10 } }
      };
    },
    apexLineSeries(){
      const vals  = this.timeseries.map(x => Number(x.total_discount || 0));
      return [ { name: this.$t('Total_Discount'), data: vals } ];
    }
  },

  methods: {
    fmt(d){ return moment(d).format('YYYY-MM-DD'); },
    fmtShort(d){ return moment(d).format('MMM D'); },
    handleResize() { this.isMobile = window.innerWidth < 576; },
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
          return new Intl.NumberFormat(undefined,{style:'currency',currency:this.currency}).format(Number(v||0));
        } catch(e2) {
          return `${this.currency} ${(Number(v||0)).toLocaleString()}`;
        }
      }
    },

    quick(kind){
      const now = moment(); let s, e = now.clone();
      if(kind==='7d')  s = now.clone().subtract(6,'days');
      if(kind==='30d') s = now.clone().subtract(29,'days');
      if(kind==='90d') s = now.clone().subtract(89,'days');
      if(kind==='mtd') s = now.clone().startOf('month');
      if(kind==='ytd') s = now.clone().startOf('year');
      this.dateRange = { startDate: s.toDate(), endDate: e.toDate() };
      this.fetchReport();
    },

    // table events
    onPageChange({ currentPage }) { this.serverParams.page = currentPage; this.fetchReport(); },
    onPerPageChange({ currentPerPage }) { this.serverParams.perPage = currentPerPage; this.limit = currentPerPage; this.serverParams.page = 1; this.fetchReport(); },
    onSortChange(params){ if (params && params[0]) this.serverParams.sort = params[0]; this.fetchReport(); },
    onSearch(v){ this.search = v.searchTerm || ''; this.fetchReport(); },

    // Group footer helpers for vue-good-table
    sumLineDiscount(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.money(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].line_discount) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.money(sum);
    },

    sumHeaderDiscount(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.money(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].header_manual_discount) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.money(sum);
    },

    sumPointsDiscount(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.money(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].header_points_discount) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.money(sum);
    },

    sumTotalDiscount(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.money(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total_discount) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.money(sum);
    },

    //------ Print Table Only - Print ALL discount summary data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Discount_Summary_Report")}`;
      const items = Array.isArray(this.rows[0]?.children) ? this.rows[0].children : [];
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      items.forEach(item => {
        tableHTML += '<tr>';
        this.columns.forEach(col => {
          let cellValue = '';
          
          if (['line_discount','header_manual_discount','header_points_discount','total_discount'].includes(col.field)) {
            // Format monetary values using the money() method
            cellValue = this.money(item[col.field] || 0);
          } else {
            // Default: get value directly from item object
            cellValue = item[col.field] || '';
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

    // Export PDF (RTL + Vazirmatn + per-column totals)
    exportPDF() {
      // collect items (supports either plain array or [{children:[]}] shape)
      const items =
        Array.isArray(this.rows?.[0]?.children) && this.rows.length === 1
          ? this.rows[0].children
          : (this.rows || []);

      // totals
      const tLine     = items.reduce((a,b)=> a + Number(b.line_discount           || 0), 0);
      const tHeader   = items.reduce((a,b)=> a + Number(b.header_manual_discount || 0), 0);
      const tPoints   = items.reduce((a,b)=> a + Number(b.header_points_discount || 0), 0);
      const tTotal    = items.reduce((a,b)=> a + Number(b.total_discount         || 0), 0);

      const doc = new jsPDF({ orientation:'portrait', unit:'pt', format:'a4' });
      const pageW = doc.internal.pageSize.getWidth();
      const marginX = 40;

      // Font: use your single Vazirmatn-Bold for normal + bold
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        doc.addFont(fontPath, "Vazirmatn", "normal");
        doc.addFont(fontPath, "Vazirmatn", "bold");
      } catch(_) { /* ignore if already added */ }
      doc.setFont("Vazirmatn", "normal");

      // RTL detection
      const rtl =
        (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      const title = 'Discount Summary Report';
      const range = `${this.fmt(this.dateRange.startDate)} — ${this.fmt(this.dateRange.endDate)}`;

      // Header
      doc.setFont("Vazirmatn", "bold"); doc.setFontSize(14);
      rtl ? doc.text(title, pageW - marginX, 40, { align:'right' })
          : doc.text(title, marginX, 40);
      doc.setFont("Vazirmatn", "normal"); doc.setFontSize(10);
      rtl ? doc.text(range, pageW - marginX, 58, { align:'right' })
          : doc.text(range, marginX, 58);

      // Table
      const head = [[
        this.$t('ID'),
        this.$t('date'),
        this.$t('User'),
        this.$t('Line_Discount'),
        this.$t('Header_Discount'),
        this.$t('Discount_from_Points'),
        this.$t('Total_Discount')
      ]];

      const body = items.map(r => ([
        r.sale_id ?? '',
        r.date_time ?? '',
        r.user_name ?? '',
        Number(r.line_discount           || 0).toFixed(2),
        Number(r.header_manual_discount || 0).toFixed(2),
        Number(r.header_points_discount || 0).toFixed(2),
        Number(r.total_discount         || 0).toFixed(2),
      ]));

      autoTable(doc, {
        startY: 80,
        head, body,
        styles: {
          font: "Vazirmatn",
          fontSize: 9,
          cellPadding: 6,
          halign: rtl ? 'right' : 'left'
        },
        headStyles: {
          font: "Vazirmatn",
          fontStyle: "bold",
          fillColor: [26,86,219],
          textColor: 255,
          halign: rtl ? 'right' : 'left'
        },
        columnStyles: {
          3: { halign: 'right' }, // Line_Discount
          4: { halign: 'right' }, // Header_Manual_Discount
          5: { halign: 'right' }, // Discount_from_Points
          6: { halign: 'right' }, // Total_Discount
        },
        foot: [[
          { content: this.$t('Totals'), styles:{ font: 'Vazirmatn', fontStyle:'bold', halign: rtl ? 'right' : 'left' } },
          '', '',
          { content: tLine.toFixed(2),   styles:{ halign:'right', fontStyle:'bold' } },
          { content: tHeader.toFixed(2), styles:{ halign:'right', fontStyle:'bold' } },
          { content: tPoints.toFixed(2), styles:{ halign:'right', fontStyle:'bold' } },
          { content: tTotal.toFixed(2),  styles:{ halign:'right', fontStyle:'bold' } },
        ]],
        margin: { left: marginX, right: marginX }
      });

      doc.save(`discount-sales_${this.fmt(this.dateRange.startDate)}_${this.fmt(this.dateRange.endDate)}.pdf`);
    },



    // data load
    fetchReport(){
      NProgress.start(); NProgress.set(0.1); this.isLoading = true;

      const qs = new URLSearchParams({
        from: this.fmt(this.dateRange.startDate),
        to:   this.fmt(this.dateRange.endDate),
        page: String(this.serverParams.page),
        limit: String(this.serverParams.perPage || this.limit),
        SortField: this.serverParams.sort?.field || 'date_time',
        SortType:  this.serverParams.sort?.type || 'desc',
        search: this.search || ''
      }).toString();

      axios.get(`report/discount_summary?${qs}`)
        .then(({data})=>{
          this.rows[0].children = Array.isArray(data.report) ? data.report : [];
          this.totalRows = Number(data.totalRows || 0);
          this.overallTotal = Number(data.overall_total || 0);
          this.timeseries = Array.isArray(data.timeseries) ? data.timeseries : [];
          this.isLoading = false; NProgress.done();
        })
        .catch(()=>{ this.isLoading = false; NProgress.done(); });
    }
  },
  mounted() {
    this.handleResize();
    window.addEventListener('resize', this.handleResize);
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.handleResize);
  },

  created(){ this.fetchReport(); }
};
</script>

<style scoped>

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

.rounded-xl { border-radius:1rem; }
.shadow-soft { box-shadow:0 12px 24px rgba(0,0,0,.06), 0 2px 6px rgba(0,0,0,.05); }
.toolbar-card { background:#fff; }
.btn-pill { border-radius:999px; }
</style>
