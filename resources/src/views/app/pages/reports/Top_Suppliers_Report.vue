<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Top_Suppliers_Report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="shadow-soft border-0 mb-3">
      <div class="d-flex flex-wrap align-items-center">

        <!-- Date range (responsive) -->
        <div class="mr-3 mb-2 d-flex flex-column date-range-filter">
          <label class="mb-1 d-block text-muted">{{$t('DateRange')}}</label>
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
          <v-select class="w-280"
            v-model="warehouse_id"
            @input="fetchReport"
            :reduce="o => o.value"
            :options="warehouses.map(w => ({label:w.name, value:w.id}))"
            :clearable="true"
            :placeholder="$t('Choose_Warehouse')"
          />
        </div>

        <div class="ml-auto mb-2 d-flex">
          <b-button variant="primary" class="btn-pill mr-2" @click="fetchReport">
            <lucide-icon class="mr-1" name="refresh-cw" /> {{$t('Refresh')}}
          </b-button>
          <b-button variant="outline-secondary" class="btn-pill mr-2" @click="printTableOnly()">
            <lucide-icon class="mr-1" name="printer" />{{$t('print')}}
          </b-button>
          <b-button variant="success" class="btn-pill" @click="exportPDF">
            <lucide-icon class="mr-1" name="file-text" /> {{$t('Export_PDF')}}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="4" v-for="n in 6" :key="'skel-'+n">
          <b-skeleton-img class="rounded-xl shadow-soft" height="110px" />
        </b-col>
      </b-row>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- KPIs -->
      <b-row>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="users" :label="$t('Vendors')" :value="num(kpis.vendors_count)" theme="blue"/>
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="file-text" :label="$t('Purchases')" :value="num(kpis.total_purchases)" theme="teal"/>
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="package" :label="$t('QtyPurchased')" :value="formatQty(kpis.total_qty)" theme="indigo"/>
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="banknote" :label="$t('TotalSpend')" :value="money(kpis.total_spend)" theme="green"/>
        </b-col>
      </b-row>

      <!-- Charts -->
      <b-row>
        <b-col md="8" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{$t('TopSuppliersByValue')}}</h6>
              <small class="text-muted">{{labelRange}}</small>
            </div>
            <apexchart type="bar" height="320" :options="apexValueOptions" :series="apexValueSeries" />
          </b-card>
        </b-col>
        <b-col md="4" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{$t('TopSuppliersByQty')}}</h6>
              <small class="text-muted">{{$t('ByQuantity')}}</small>
            </div>
            <apexchart type="bar" height="320" :options="apexQtyOptions" :series="apexQtySeries" />
          </b-card>
        </b-col>
      </b-row>

      <!-- Table -->
      <b-card class="shadow-soft border-0 print-table-only">
        <vue-good-table
          mode="remote"
          :rows="rows"
          :columns="columns"
          :totalRows="totalRows"
          styleClass="tableOne table-hover vgt-table"
          :pagination-options="{enabled:true, mode:'records'}"
          :search-options="{enabled:true, placeholder:$t('Search_this_table')}"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
        >
          <template slot="table-row" slot-scope="p">
            <span v-if="p.column.field==='value_sum' || p.column.field==='avg_value'">{{ money(p.row[p.column.field]) }}</span>
            <span v-else-if="p.column.field==='qty_sum'">{{ formatQty(p.row.qty_sum) }}</span>
            <span v-else>{{ p.formattedRow[p.column.field] }}</span>
          </template>

          <!-- Totals footer -->
          <template slot="table-actions-bottom">
            <div class="d-flex justify-content-end w-100 pt-2">
              <div class="font-weight-bold">
                {{$t('Totals')}}:
                <span class="ml-2">{{$t('Purchases')}} = {{ num(sumField(rows, 'orders_count')) }}</span>
                <span class="ml-3">{{$t('QtyPurchased')}} = {{ formatQty(sumField(rows, 'qty_sum')) }}</span>
                <span class="ml-3">{{$t('TotalSpend')}} = {{ money(sumField(rows, 'value_sum')) }}</span>
              </div>
            </div>
          </template>
        </vue-good-table>
      </b-card>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";

// ECharts v4 + vue-echarts v4
import VueApexCharts from "vue-apexcharts";

// PDF
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

const StatTile = {
  name: "StatTile",
  functional: true,
  props: { icon:String, label:String, value:[String,Number], theme:{type:String,default:'blue'} },
  render(h,{props}){
    return h("div",{class:["stat-card",`theme-${props.theme}`,"shadow-soft","rounded-xl"]},[
      h("div",{class:"stat-inner"},[
        h("div",{class:"stat-icon"},[h('lucide-icon', { props: { name: props.icon } })]),
        h("div",{class:"stat-content"},[
          h("div",{class:"stat-label"},props.label),
          h("div",{class:"stat-value"},props.value)
        ])
      ])
    ])
  }
};

export default {
  metaInfo: { title: "Top Suppliers Report" },
  components: { apexchart: VueApexCharts, "date-range-picker": DateRangePicker, StatTile },
  data(){
    const end = new Date(), start = new Date(); start.setDate(end.getDate()-29);
    return {
      // state
      warehouses: [], warehouse_id:null,
      isLoading:true,
      kpis:{vendors_count:0,total_purchases:0,total_qty:0,total_spend:0},
      topByValue:[], topByQty:[],
      rows:[], totalRows:0,
      serverParams:{page:1, perPage:10, sort:{field:'value_sum', type:'desc'}},
      limit:10, search:'',
      // date range
      dateRange:{startDate:start, endDate:end},
      locale:{
        Label: this.$t("Apply") || "Apply",
        cancelLabel: this.$t("Cancel") || "Cancel",
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange") || "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },
      isMobile:false,
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    }
  },
  computed:{
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },
    labelRange(){ return `${this.fmt(this.dateRange.startDate)} → ${this.fmt(this.dateRange.endDate)}`; },
    columns(){
      return [
        {label:this.$t('Supplier'),        field:'supplier',      sortable:true, tdClass:'text-left', thClass:'text-left'},
        {label:this.$t('Purchases'),       field:'orders_count',  type:'number', sortable:true},
        {label:this.$t('QtyPurchased'),    field:'qty_sum',       type:'number', sortable:true},
        {label:this.$t('TotalSpend'),      field:'value_sum',     type:'number', sortable:true},
        {label:this.$t('AvgPerPurchase'),  field:'avg_value',     type:'number', sortable:true}
      ]
    },
    apexValueOptions(){
      return {
        chart: { toolbar: { show: false } },
        xaxis: { categories: this.topByValue.map(x => x.supplier) },
        yaxis: { labels: { formatter: (v) => this.shortMoney(v) } },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (v) => this.money(v) } },
      };
    },
    apexValueSeries(){
      return [{ name: this.$t('TotalSpend'), data: this.topByValue.map(x => Number(x.value_sum || 0)) }];
    },
    apexQtyOptions(){
      return {
        chart: { toolbar: { show: false } },
        xaxis: { labels: { formatter: (v) => this.shortMoney(v) } },
        yaxis: { categories: this.topByQty.map(x => x.supplier) },
        plotOptions: { bar: { horizontal: true } },
        dataLabels: { enabled: false },
      };
    },
    apexQtySeries(){
      return [{ name: this.$t('QtyPurchased'), data: this.topByQty.map(x => Number(x.qty_sum || 0)) }];
    }
  },
  methods:{
    // formatters
    fmt(d){ return moment(d).format('YYYY-MM-DD'); },
    fmtShort(d){ return moment(d).format('MMM D'); },
    num(v){ const n = Number(v||0); return isNaN(n)?'0':n.toLocaleString(); },
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
          return `${this.currency} ${Number(v||0).toLocaleString()}`;
        }
      }
    },
    shortMoney(v){ return new Intl.NumberFormat(undefined,{notation:'compact',maximumFractionDigits:1}).format(Number(v||0)); },
    formatQty(v){ return Number(v||0).toLocaleString(undefined,{maximumFractionDigits:2}); },

    // responsive behavior
    handleResize(){ this.isMobile = window.innerWidth < 576; },

    // quick range helpers
    quick(kind){
      const now = moment(); let s = now.clone().subtract(29,'days'), e = now.clone();
      if(kind==='7d')  s = now.clone().subtract(6,'days');
      if(kind==='30d') s = now.clone().subtract(29,'days');
      if(kind==='90d') s = now.clone().subtract(89,'days');
      if(kind==='mtd') s = now.clone().startOf('month');
      if(kind==='ytd') s = now.clone().startOf('year');
      this.dateRange = { startDate: s.toDate(), endDate: e.toDate() };
      this.fetchReport();
    },

    // table events
    onPageChange({currentPage}){ this.serverParams.page = currentPage; this.fetchReport(); },
    onPerPageChange({currentPerPage}){ this.serverParams.perPage = currentPerPage; this.limit=currentPerPage; this.serverParams.page=1; this.fetchReport(); },
    onSortChange(params){ if(params && params[0]) this.serverParams.sort = params[0]; this.fetchReport(); },
    onSearch(v){ this.search = v.searchTerm || ''; this.fetchReport(); },

    // footer sum helper over current rows array
    sumField(rows, key){
      if(!Array.isArray(rows)) return 0;
      return rows.reduce((acc,r)=> acc + Number(r[key]||0), 0);
    },

    // API
    fetchReport(){
      NProgress.start(); NProgress.set(0.1); this.isLoading=true;
      const qs = new URLSearchParams({
        from:this.fmt(this.dateRange.startDate),
        to:this.fmt(this.dateRange.endDate),
        warehouse_id:this.warehouse_id || '',
        page:String(this.serverParams.page),
        limit:String(this.serverParams.perPage || this.limit),
        SortField:this.serverParams.sort?.field || 'value_sum',
        SortType:this.serverParams.sort?.type || 'desc',
        search:this.search || ''
      }).toString();

      axios.get(`report/top_suppliers?${qs}`)
        .then(({data})=>{
          const d = data.data || {};
          this.kpis = d.kpis || this.kpis;
          this.topByValue = d.topByValue || [];
          this.topByQty   = d.topByQty || [];
          this.rows = d.rows || [];
          this.totalRows = d.totalRows || 0;
          this.warehouses = data.warehouses || [];
          this.isLoading=false; NProgress.done();
        })
        .catch(()=>{ this.isLoading=false; NProgress.done(); });
    },

    async exportPDF(){
      try{
        NProgress.start(); NProgress.set(0.2);

        // Fetch ALL rows using current filters/sort
        const qs = new URLSearchParams({
          from: this.fmt(this.dateRange.startDate),
          to:   this.fmt(this.dateRange.endDate),
          warehouse_id: this.warehouse_id || '',
          page: '1',
          limit: '100000', // export all
          SortField: this.serverParams?.sort?.field || 'value_sum',
          SortType:  this.serverParams?.sort?.type  || 'desc',
          search: this.search || ''
        }).toString();

        const { data } = await axios.get(`report/top_suppliers?${qs}`).catch(()=>({data:{}}));
        const d = data?.data || {};
        const items = Array.isArray(d.rows) ? d.rows : [];
        const k = d.kpis || this.kpis;

        // --- PDF (with Vazirmatn for Arabic/RTL) ---
        const doc = new jsPDF({ orientation:'landscape', unit:'pt', format:'a4' });
        const fontPath = "/fonts/Vazirmatn-Bold.ttf";
        try {
          // Use the same bold TTF for both weights if you only have Bold
          doc.addFont(fontPath, "Vazirmatn", "normal");
          doc.addFont(fontPath, "Vazirmatn", "bold");
        } catch(_) { /* ignore if already added */ }
        doc.setFont("Vazirmatn", "normal");

        const rtl =
          (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
          (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

        const pageW   = doc.internal.pageSize.getWidth();
        const marginX = 40;

        const title = 'Top Suppliers Report';
        const range = `${this.fmt(this.dateRange.startDate)} — ${this.fmt(this.dateRange.endDate)}`;
        const whText = (() => {
          const id = this.warehouse_id;
          if (!id) return this.$t('All');
          const w = (this.warehouses||[]).find(x => String(x.id) === String(id));
          return w ? (w.name || `#${id}`) : `#${id}`;
        })();

        // Header (RTL aware)
        doc.setFont("Vazirmatn","bold"); doc.setFontSize(14);
        rtl ? doc.text(title, pageW - marginX, 40, { align:'right' })
            : doc.text(title, marginX, 40);

        doc.setFont("Vazirmatn","normal"); doc.setFontSize(10);
        const line1 = `${this.$t('DateRange')}: ${range}   •   ${this.$t('warehouse')}: ${whText}`;
        const line2 = `${this.$t('Vendors')}: ${k.vendors_count}   •   ${this.$t('Purchases')}: ${k.total_purchases}   •   ${this.$t('QtyPurchased')}: ${Number(k.total_qty||0).toLocaleString()}   •   ${this.$t('TotalSpend')}: ${this.money(k.total_spend)}`;

        rtl ? doc.text(line1, pageW - marginX, 58, { align:'right' })
            : doc.text(line1, marginX, 58);
        rtl ? doc.text(line2, pageW - marginX, 74, { align:'right' })
            : doc.text(line2, marginX, 74);

        // Table
        const head = [[
          this.$t('Supplier'),
          this.$t('Purchases'),
          this.$t('QtyPurchased'),
          this.$t('TotalSpend'),
          this.$t('AvgPerPurchase')
        ]];

        const body = items.map(r => [
          r.supplier || '',
          Number(r.orders_count||0).toLocaleString(),
          Number(r.qty_sum||0).toLocaleString(undefined,{maximumFractionDigits:2}),
          this.money(r.value_sum),
          this.money(r.avg_value),
        ]);

        const tPurchases = items.reduce((a,b)=>a+Number(b.orders_count||0),0);
        const tQty       = items.reduce((a,b)=>a+Number(b.qty_sum||0),0);
        const tValue     = items.reduce((a,b)=>a+Number(b.value_sum||0),0);

        autoTable(doc, {
          startY: 90,
          head, body,
          styles: { font: "Vazirmatn", fontSize: 9, cellPadding: 6, halign: rtl ? 'right' : 'left' },
          headStyles: { font: "Vazirmatn", fontStyle: "bold", fillColor: [26,86,219], textColor: 255, halign: rtl ? 'right' : 'left' },
          columnStyles: { 1:{halign:'right'}, 2:{halign:'right'}, 3:{halign:'right'}, 4:{halign:'right'} },
          foot: [[
            { content: this.$t('Totals'), styles:{ font: "Vazirmatn", fontStyle:'bold' } },
            { content: tPurchases.toLocaleString(), styles:{ halign:'right', fontStyle:'bold' } },
            { content: tQty.toLocaleString(undefined,{maximumFractionDigits:2}), styles:{ halign:'right', fontStyle:'bold' } },
            { content: this.money(tValue), styles:{ halign:'right', fontStyle:'bold' } },
            '' // avg blank
          ]],
          margin: { left: marginX, right: marginX }
        });

        doc.save(`top-suppliers_${this.fmt(this.dateRange.startDate)}_${this.fmt(this.dateRange.endDate)}.pdf`);
      } finally {
        NProgress.done();
      }
    },

    //------ Print Table Only
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

      // Get rows data (this.rows is already a flat array)
      const rowsData = this.rows || [];

      // Manually construct the table HTML from rows data
      let tableHtml = `<table class="vgt-table table table-hover tableOne">`;

      // Table Header
      tableHtml += `<thead><tr>`;
      this.columns.forEach(col => {
        tableHtml += `<th class="text-left">${col.label}</th>`;
      });
      tableHtml += `</tr></thead>`;

      // Table Body
      tableHtml += `<tbody>`;
      rowsData.forEach(row => {
        tableHtml += `<tr>`;
        this.columns.forEach(col => {
          let cellContent = '';
          if (col.field === 'value_sum' || col.field === 'avg_value') {
            cellContent = this.money(row[col.field]);
          } else if (col.field === 'qty_sum') {
            cellContent = this.formatQty(row.qty_sum);
          } else {
            cellContent = row[col.field] || '';
          }
          tableHtml += `<td class="text-left">${cellContent}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalPurchases = rowsData.reduce((sum, row) => sum + parseFloat(row.orders_count || 0), 0);
      const totalQty = rowsData.reduce((sum, row) => sum + parseFloat(row.qty_sum || 0), 0);
      const totalValue = rowsData.reduce((sum, row) => sum + parseFloat(row.value_sum || 0), 0);
      
      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Totals')}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalPurchases.toLocaleString()}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatQty(totalQty)}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.money(totalValue)}</td>`;
      tableHtml += `<td></td>`; // AvgPerPurchase column
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Top_Suppliers_Report")}`;
      const dateRangeText = `${this.fmt(this.dateRange.startDate)} — ${this.fmt(this.dateRange.endDate)}`;
      const warehouseText = (() => {
        const id = this.warehouse_id;
        if (!id) return this.$t('All');
        const w = (this.warehouses || []).find(x => String(x.id) === String(id));
        return w ? (w.name || `#${id}`) : `#${id}`;
      })();

      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
        .join("\n");

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
      @media print { body, body * { visibility: visible !important; } }
      body { margin: 0.3cm; }
      .print-header { font-weight: 600; margin-bottom: 8px; }
      .print-meta { font-size: 10px; margin-bottom: 15px; color: #666; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f2f2f2; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    <div class="print-meta">
      ${dateRangeText} | ${this.$t('warehouse')}: ${warehouseText}
    </div>
    ${tableHtml}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    },

   
  },
  created(){ this.fetchReport(); },
  mounted(){
    this.handleResize();
    window.addEventListener('resize', this.handleResize);
  },
  beforeDestroy(){
    window.removeEventListener('resize', this.handleResize);
  }
};
</script>

<style scoped>
.rounded-xl{border-radius:1rem;}
.shadow-soft{box-shadow:0 12px 24px rgba(0,0,0,.06),0 2px 6px rgba(0,0,0,.05);}
.btn-pill{border-radius:999px;}
.w-280{width:280px;}

.stat-card{padding:14px 16px;min-height:100px;background:#fff;}
.stat-inner{display:flex;align-items:center;}
.stat-icon{width:48px;height:48px;border-radius:12px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;margin-right:12px;}
.stat-icon i{font-size:22px;}
.stat-label{font-size:.85rem;font-weight:600;color:#666;}
.stat-value{font-size:1.3rem;font-weight:700;color:#333;}
.theme-blue .stat-icon{color:#0b5fff;}
.theme-teal .stat-icon{color:#138f7a;}
.theme-indigo .stat-icon{color:#3949ab;}
.theme-green .stat-icon{color:#2e7d32;}

/* Responsive date range picker */
.date-range-filter { min-width: 240px; }
.date-btn { display: inline-flex; align-items: center; }
@media (max-width: 575.98px) {
  .date-range-filter { width: 100%; }
  .daterangepicker {
    left: 0 !important;
    right: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
  }
  .daterangepicker .ranges, .daterangepicker .drp-calendar {
    float: none !important; width: 100% !important;
  }

  /* Wrap quick range buttons into two columns */
  .quick-ranges { display:flex !important; flex-wrap:wrap; width:100%; }
  .quick-ranges .btn { flex: 1 1 calc(50% - 6px); margin-bottom:6px; }
}
</style>
