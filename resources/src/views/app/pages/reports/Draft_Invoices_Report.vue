<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Draft_Invoices_Report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="toolbar-card shadow-soft mb-3 border-0">
      <div class="d-flex flex-wrap align-items-center">

        <!-- Date range (responsive) -->
        <div class="filter-block date-range-filter mr-3 mb-2 d-flex flex-column">
          <label class="mb-1 d-block text-muted">{{ $t('DateRange') }}</label>
          <date-range-picker
            v-model="dateRange"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="isMobile ? 'center' : 'right'"
            :drops="'down'"
            @update="fetchDrafts"
          >
            <template v-slot:input="picker">
              <b-button variant="light" class="btn-pill date-btn" :class="{ 'w-100': isMobile }">
                <lucide-icon class="mr-1" name="calendar-days" />
                <span class="d-none d-sm-inline">
                  {{ fmt(picker.startDate) }} — {{ fmt(picker.endDate) }}
                </span>
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

        <!-- Warehouse -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('warehouse')}}</label>
          <b-form-select
            v-model="warehouse_id"
            :options="warehouseOptions"
            size="sm"
            class="w-250"
            @change="fetchDrafts"
          />
        </div>

        <div class="ml-auto mb-2 d-flex">
          <b-button variant="success" class="btn-pill mr-2" @click="exportPDF">
            <lucide-icon class="mr-1" name="file-text" /> {{$t('Export_PDF')}}
          </b-button>
          <b-button variant="primary" class="btn-pill" @click="fetchDrafts">
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

    <!-- Table -->
    <b-card v-else class="wrapper shadow-soft border-0">
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
        styleClass="tableOne table-hover vgt-table mt-3"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>
        <template slot="table-row" slot-scope="props">
          <span v-if="['GrandTotal','TaxNet','discount','shipping'].includes(props.column.field)">
            {{ money(props.row[props.column.field]) }}
          </span>

          <span v-else-if="props.column.field === 'age_days'">
            <b-badge variant="warning" v-if="props.row.age_days >= 30">
              {{ props.row.age_days }} {{$t('days')}}
            </b-badge>
            <span v-else>{{ props.row.age_days }}</span>
          </span>

          <span v-else-if="props.column.field === 'actions'">
            <span class="text-muted">—</span>
          </span>

          <span v-else>
            {{ props.formattedRow[props.column.field] }}
          </span>
        </template>

        <!-- Footer totals -->
        <template slot="table-actions-bottom">
          <div class="d-flex justify-content-end w-100 pt-2">
            <div class="font-weight-bold">
              {{$t('Totals')}}:
              <span class="ml-2">{{$t('Amount')}} = {{ money(sumField(rows[0], 'GrandTotal')) }}</span>
              <span class="ml-3">{{$t('Tax')}} = {{ money(sumField(rows[0], 'TaxNet')) }}</span>
              <span class="ml-3">{{$t('Discount')}} = {{ money(sumField(rows[0], 'discount')) }}</span>
              <span class="ml-3">{{$t('Shipping')}} = {{ money(sumField(rows[0], 'shipping')) }}</span>
            </div>
          </div>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import moment from "moment";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: { title: "Draft Invoices Report" },
  components: { "date-range-picker": DateRangePicker },

  data() {
    const end = new Date(); const start = new Date(); start.setDate(end.getDate() - 29);
    return {
      isLoading: true,

      serverParams: { sort: { field: "age_days", type: "desc" }, page: 1, perPage: 10 },
      limit: "10",
      search: "",
      totalRows: 0,
      rows: [{ children: [] }],

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
      isMobile: false,

      warehouses: [],
      warehouse_id: null,
      warehouseOptions: [{ value: null, text: this.$t('All') }],
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },
    columns() {
      return [
        { label: this.$t("date"),       field: "date",       sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Number"),     field: "Ref",        sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Customer"),   field: "client",     sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("warehouse"),  field: "warehouse",  sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("User"),       field: "user",       sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Amount"),     field: "GrandTotal", type: "number", headerField: this.sumAmount,   sortable: true },
        { label: this.$t("Tax"),        field: "TaxNet",     type: "number", headerField: this.sumTax,      sortable: true },
        { label: this.$t("Discount"),   field: "discount",   type: "number", headerField: this.sumDiscount, sortable: true },
        { label: this.$t("Shipping"),   field: "shipping",   type: "number", headerField: this.sumShipping, sortable: true },
        { label: this.$t("AgeDays"),    field: "age_days",   type: "number", sortable: true },
        { label: this.$t("Action"),     field: "actions",    sortable: false, tdClass: "text-left", thClass: "text-left" },
      ];
    },
  },

  methods: {
    // responsiveness
    handleResize(){ this.isMobile = window.innerWidth < 576; },

    // formatters
    fmt(d){ return moment(d).format("YYYY-MM-DD"); },
    fmtShort(d){ return moment(d).format("MMM D"); },
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
        const n = Number(v||0);
        try {
          return new Intl.NumberFormat(undefined,{style:'currency',currency:this.currency}).format(n);
        } catch(e2) {
          return `${this.currency} ${n.toLocaleString()}`;
        }
      }
    },

    // footer sums
    sumAmount(rowObj)   { return this.sumField(rowObj, 'GrandTotal'); },
    sumTax(rowObj)      { return this.sumField(rowObj, 'TaxNet'); },
    sumDiscount(rowObj) { return this.sumField(rowObj, 'discount'); },
    sumShipping(rowObj) { return this.sumField(rowObj, 'shipping'); },
    sumField(rowObj, key) {
      let sum = 0;
      if (rowObj && Array.isArray(rowObj.children)) {
        for (const r of rowObj.children) {
          const v = Number(r[key] || 0);
          if (!isNaN(v)) sum += v;
        }
      }
      return sum;
    },

    //------ Print Table Only - Print ALL draft invoices data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Draft_Invoices_Report")}`;
      const items = Array.isArray(this.rows[0]?.children) ? this.rows[0].children : [];
      
      // Build table header with all columns (excluding actions)
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      // Filter out the actions column for printing
      this.columns.filter(col => col.field !== 'actions').forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      items.forEach(item => {
        tableHTML += '<tr>';
        this.columns.filter(col => col.field !== 'actions').forEach(col => {
          let cellValue = '';
          
          if (['GrandTotal','TaxNet','discount','shipping'].includes(col.field)) {
            // Format monetary values using the money() method
            cellValue = this.money(item[col.field] || 0);
          } else if (col.field === 'age_days') {
            // Age days - just show the number
            cellValue = item.age_days || '';
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

    // quick ranges
    quick(kind){
      const now = moment(); let s, e = now.clone();
      if(kind==='7d')  s = now.clone().subtract(6,'days');
      if(kind==='30d') s = now.clone().subtract(29,'days');
      if(kind==='90d') s = now.clone().subtract(89,'days');
      if(kind==='mtd') s = now.clone().startOf('month');
      if(kind==='ytd') s = now.clone().startOf('year');
      this.dateRange = { startDate: s.toDate(), endDate: e.toDate() };
      this.fetchDrafts();
    },

    // table events
    updateParams(newProps) { this.serverParams = Object.assign({}, this.serverParams, newProps); },
    onPageChange({ currentPage }) { if (this.serverParams.page !== currentPage) { this.updateParams({ page: currentPage }); this.fetchDrafts(currentPage); } },
    onPerPageChange({ currentPerPage }) { if (this.limit !== currentPerPage) { this.limit = currentPerPage.toString(); this.updateParams({ page: 1, perPage: currentPerPage }); this.fetchDrafts(1); } },
    onSortChange(params) { if (params && params[0]) { this.updateParams({ sort: { type: params[0].type, field: params[0].field } }); this.fetchDrafts(this.serverParams.page); } },
    onSearch(value) { this.search = value.searchTerm || ""; this.fetchDrafts(this.serverParams.page); },

    // export PDF (fetch all rows with same filters)
    async exportPDF(){
      try{
        NProgress.start(); NProgress.set(0.2);

        // Fetch ALL rows with current filters/sort
        const qs = new URLSearchParams({
          from: this.fmt(this.dateRange.startDate),
          to:   this.fmt(this.dateRange.endDate),
          warehouse_id: this.warehouse_id || '',
          limit: '-1', // all
          SortField: this.serverParams?.sort?.field || 'age_days',
          SortType:  this.serverParams?.sort?.type  || 'desc',
          search: this.search || ''
        }).toString();

        const { data } = await axios.get(`report/draft_invoices?${qs}`).catch(()=>({data:{}}));
        const items = Array.isArray(data.report) ? data.report : [];

        // --- PDF setup (with Vazirmatn for Arabic/RTL) ---
        const doc = new jsPDF({ orientation:'landscape', unit:'pt', format:'a4' });
        const fontPath = "/fonts/Vazirmatn-Bold.ttf";
        try {
          doc.addFont(fontPath, "Vazirmatn", "normal");
          doc.addFont(fontPath, "Vazirmatn", "bold");
        } catch (_) { /* ignore if already added */ }
        doc.setFont("Vazirmatn", "normal");

        const rtl =
          (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
          (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');
        const pageW = doc.internal.pageSize.getWidth();
        const marginX = 40;

        // Header text
        const title = 'Draft Invoices Report';
        const range = `${this.fmt(this.dateRange.startDate)} — ${this.fmt(this.dateRange.endDate)}`;

        // Resolve warehouse label from either warehouseOptions [{value,text}] or warehouses [{id,name}]
        const whLabel = (() => {
          const id = this.warehouse_id;
          if (!id) return this.$t('All');
          const opt = Array.isArray(this.warehouseOptions)
            ? this.warehouseOptions.find(o => String(o.value) === String(id))
            : null;
          if (opt && opt.text) return opt.text;
          const w = Array.isArray(this.warehouses)
            ? this.warehouses.find(w => String(w.id) === String(id))
            : null;
          return w ? (w.name || `#${id}`) : `#${id}`;
        })();

        // Draw header (RTL aware)
        doc.setFont("Vazirmatn", "bold"); doc.setFontSize(14);
        rtl ? doc.text(title, pageW - marginX, 40, { align:'right' })
            : doc.text(title, marginX, 40);

        doc.setFont("Vazirmatn", "normal"); doc.setFontSize(10);
        const headerLine = `${this.$t('DateRange')}: ${range}   •   ${this.$t('warehouse')}: ${whLabel}`;
        rtl ? doc.text(headerLine, pageW - marginX, 58, { align:'right' })
            : doc.text(headerLine, marginX, 58);

        // Table
        const head = [[
          this.$t('date'),
          this.$t('Number'),
          this.$t('Customer'),
          this.$t('warehouse'),
          this.$t('User'),
          this.$t('Amount'),
          this.$t('Tax'),
          this.$t('Discount'),
          this.$t('Shipping'),
          this.$t('AgeDays')
        ]];

        const body = items.map(r => ([
          r.date || '',
          r.Ref || '',
          r.client || '',
          r.warehouse || '',
          r.user || '',
          this.money ? this.money(Number(r.GrandTotal||0)) : Number(r.GrandTotal||0).toFixed(2),
          this.money ? this.money(Number(r.TaxNet||0))     : Number(r.TaxNet||0).toFixed(2),
          this.money ? this.money(Number(r.discount||0))   : Number(r.discount||0).toFixed(2),
          this.money ? this.money(Number(r.shipping||0))   : Number(r.shipping||0).toFixed(2),
          String(r.age_days ?? '')
        ]));

        const tAmount   = items.reduce((a,b)=>a+Number(b.GrandTotal||0),0);
        const tTax      = items.reduce((a,b)=>a+Number(b.TaxNet||0),0);
        const tDiscount = items.reduce((a,b)=>a+Number(b.discount||0),0);
        const tShip     = items.reduce((a,b)=>a+Number(b.shipping||0),0);

        autoTable(doc, {
          startY: 80,
          head, body,
          styles: { font: "Vazirmatn", fontSize: 9, cellPadding: 6, halign: rtl ? 'right' : 'left' },
          headStyles: { font: "Vazirmatn", fontStyle: "bold", fillColor: [26,86,219], textColor: 255, halign: rtl ? 'right' : 'left' },
          columnStyles: {
            5:{halign:'right'}, 6:{halign:'right'}, 7:{halign:'right'}, 8:{halign:'right'}, 9:{halign:'right'}
          },
          foot: [[
            { content: this.$t('Totals'), colSpan: 5, styles:{ font: 'Vazirmatn', fontStyle:'bold', halign: rtl ? 'right' : 'left' } },
            { content: this.money ? this.money(tAmount)   : tAmount.toFixed(2),   styles:{ halign:'right', fontStyle:'bold' } },
            { content: this.money ? this.money(tTax)      : tTax.toFixed(2),      styles:{ halign:'right', fontStyle:'bold' } },
            { content: this.money ? this.money(tDiscount) : tDiscount.toFixed(2), styles:{ halign:'right', fontStyle:'bold' } },
            { content: this.money ? this.money(tShip)     : tShip.toFixed(2),     styles:{ halign:'right', fontStyle:'bold' } }
          ]],
          margin: { left: marginX, right: marginX }
        });

        doc.save(`draft-invoices_${this.fmt(this.dateRange.startDate)}_${this.fmt(this.dateRange.endDate)}.pdf`);
      } finally {
        NProgress.done();
      }
    },


    // load
    fetchDrafts(page) {
      NProgress.start(); NProgress.set(0.1);
      this.isLoading = true;

      const params =
        "page=" + encodeURIComponent(page || this.serverParams.page) +
        "&SortField=" + encodeURIComponent(this.serverParams.sort.field) +
        "&SortType=" + encodeURIComponent(this.serverParams.sort.type) +
        "&search=" + encodeURIComponent(this.search || "") +
        "&limit=" + encodeURIComponent(this.serverParams.perPage || this.limit) +
        "&from=" + encodeURIComponent(this.fmt(this.dateRange.startDate)) +
        "&to=" + encodeURIComponent(this.fmt(this.dateRange.endDate)) +
        (this.warehouse_id ? "&warehouse_id=" + encodeURIComponent(this.warehouse_id) : "");

      axios.get("report/draft_invoices?" + params)
        .then(({ data }) => {
          const items = Array.isArray(data.report) ? data.report : [];
          this.totalRows = Number(data.totalRows || 0);
          this.rows[0].children = items;

          const list = Array.isArray(data.warehouses) ? data.warehouses : [];
          if (list.length) {
            this.warehouses = list;
            this.warehouseOptions = [{ value: null, text: this.$t('All') }]
              .concat(list.map(w => ({ value: w.id, text: w.name })));
          }

          this.isLoading = false; NProgress.done();
        })
        .catch(() => { this.isLoading = false; NProgress.done(); });
    },
  },

  mounted(){
    this.handleResize();
    window.addEventListener('resize', this.handleResize);
  },
  beforeDestroy(){
    window.removeEventListener('resize', this.handleResize);
  },

  created() {
    this.fetchDrafts(1);
  }
};
</script>

<style scoped>
.rounded-xl { border-radius: 1rem; }
.shadow-soft { box-shadow: 0 12px 24px rgba(0,0,0,.06), 0 2px 6px rgba(0,0,0,.05); }
.toolbar-card { background:#fff; }
.btn-pill { border-radius:999px; }
.w-250 { width:250px; }

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
