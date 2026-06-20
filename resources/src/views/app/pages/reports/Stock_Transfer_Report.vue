<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Stock_Transfer_Report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="toolbar-card shadow-soft mb-3 border-0">
      <div class="d-flex flex-wrap align-items-center">
        <!-- Date range (responsive) -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('DateRange')}}</label>
          <date-range-picker
            v-model="dateRange"
            :startDate="dateRange.startDate"
            :endDate="dateRange.endDate"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="isMobile ? 'center' : 'right'"
            :drops="'down'"
            :parentEl="parentEl"
            :linkedCalendars="false"
            @update="fetchReport"
          >
            <!-- Vue 2.6+ slot syntax; for Vue 2.5 use slot="input" slot-scope="picker" -->
            <template v-slot:input="picker">
              <b-button variant="light" class="btn-pill">
                <lucide-icon class="mr-1" name="calendar-days" />
                {{ isMobile
                    ? (fmtShort(picker.startDate) + ' - ' + fmtShort(picker.endDate))
                    : (fmt(picker.startDate)      + ' - ' + fmt(picker.endDate))
                }}
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

        <!-- Warehouse (single) -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('warehouse')}}</label>
          <v-select class="w-280"
            v-model="warehouse_id"
            @input="fetchReport"
            :reduce="o => o.value"
            :options="warehouses.map(w => ({label: w.name, value: w.id}))"
            :clearable="true"
            :placeholder="$t('Choose_Warehouse')"
          />
        </div>

        <!-- Direction -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('Direction')}}</label>
          <b-button-group size="sm">
            <b-button :variant="direction==='all'?'primary':'outline-primary'" @click="direction='all'; fetchReport()">{{$t('All')}}</b-button>
            <b-button :variant="direction==='inbound'?'primary':'outline-primary'" @click="direction='inbound'; fetchReport()">{{$t('Inbound')}}</b-button>
            <b-button :variant="direction==='outbound'?'primary':'outline-primary'" @click="direction='outbound'; fetchReport()">{{$t('Outbound')}}</b-button>
          </b-button-group>
        </div>

        <div class="ml-auto mb-2">
          <b-button variant="primary" class="btn-pill mr-2" @click="fetchReport">
            <lucide-icon class="mr-1" name="refresh-cw" />{{$t('Refresh')}}
          </b-button>
          <b-button variant="outline-secondary" class="btn-pill mr-2" @click="printTableOnly()">
            <lucide-icon class="mr-1" name="printer" />{{$t('print')}}
          </b-button>
          <!-- Export PDF -->
          <b-button variant="danger" class="btn-pill" @click="exportPDF">
            <lucide-icon class="mr-1" name="file-text" />{{$t('Export_PDF')}}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="4" v-for="n in 6" :key="n" class="mb-3">
          <b-skeleton-img class="rounded-xl shadow-soft" height="110px" />
        </b-col>
      </b-row>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- KPIs -->
      <b-row>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="arrow-right-left" :label="$t('Transfers')" :value="num(kpis.transfers_count)" theme="blue" :sub="$t('Documents')" />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="file-text" :label="$t('Lines')" :value="num(kpis.lines_count)" theme="teal" />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="package" :label="$t('QtyMoved')" :value="formatQty(kpis.qty_sum)" theme="indigo" />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="banknote" :label="$t('ValueMoved')" :value="money(kpis.value_sum)" theme="green" />
        </b-col>

        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="trending-up" :label="$t('AvgItemsPerTransfer')" :value="formatQty(kpis.avg_items_per_transfer)" theme="orange" />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile icon="coins" :label="$t('AvgValuePerTransfer')" :value="money(kpis.avg_value_per_transfer)" theme="purple" />
        </b-col>
      </b-row>

      <!-- Charts -->
      <b-row>
        <b-col md="8" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{$t('TransfersOverTime')}}</h6>
              <small class="text-muted">{{ labelRange }}</small>
            </div>
            <apexchart type="line" height="300" :options="apexTimeOptions" :series="apexTimeSeries" />
          </b-card>
        </b-col>

        <b-col md="4" class="mb-3">
          <b-card class="shadow-soft border-0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h6 class="m-0">{{$t('TopRoutes')}}</h6>
              <small class="text-muted">{{$t('ByValue')}}</small>
            </div>
            <apexchart type="bar" height="300" :options="apexRouteOptions" :series="apexRouteSeries" />
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
          <template slot="table-row" slot-scope="p">
            <span v-if="p.column.field==='value'">{{ money(p.row.value) }}</span>
            <span v-else>{{ p.formattedRow[p.column.field] }}</span>
          </template>
        </vue-good-table>
      </b-card>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import VueApexCharts from "vue-apexcharts";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";

// No ECharts, using ApexCharts

/* PDF export */
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

const StatTile = {
  name: "StatTile",
  functional: true,
  props: { icon:String, label:String, sub:String, value:[String,Number], theme:{type:String,default:'blue'} },
  render(h,{props}) {
    return h('div',{class:['stat-card',`theme-${props.theme}`,'shadow-soft','rounded-xl','mb-2']},[
      h('div',{class:'stat-inner'},[
        h('div',{class:'stat-icon'},[ h('lucide-icon', { props: { name: props.icon } }) ]),
        h('div',{class:'stat-content'},[
          h('div',{class:'stat-label'},props.label),
          props.sub ? h('div',{class:'stat-sub text-muted'},props.sub) : null,
          h('div',{class:'stat-value'},props.value),
        ])
      ])
    ]);
  }
};

export default {
  metaInfo: { title: "Stock Transfer Report" },
  components: { apexchart: VueApexCharts, "date-range-picker": DateRangePicker, StatTile },
  data() {
    const end = new Date(); const start = new Date(); start.setDate(end.getDate()-6);
    return {
      warehouses: [],
      warehouse_id: null,
      direction: 'all', // all | inbound | outbound
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

      // responsive picker helpers
      isMobile: false,
      parentEl: 'body',

      isLoading: true,
      // data
      kpis: { transfers_count:0, lines_count:0, qty_sum:0, value_sum:0, avg_items_per_transfer:0, avg_value_per_transfer:0 },
      timeseries: [],
      routes: [],
      rows: [{ statut: '', children: [] }],
      totalRows: 0,
      // table state
      serverParams: { page:1, perPage:10, sort:{ field:'dt', type:'desc' } },
      limit: 10,
      search: '',
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },
    labelRange(){ return `${this.fmt(this.dateRange.startDate)} - ${this.fmt(this.dateRange.endDate)}`; },
    columns(){
      return [
        { label: this.$t('ID'), field:'transfer_id', sortable:true, tdClass:'text-left', thClass:'text-left' },
        { label: this.$t('date'), field:'date_time',  sortable:true },
        { label: this.$t('From'), field:'from',       sortable:true },
        { label: this.$t('to'),   field:'to',         sortable:true },
        { label: this.$t('Qty'),  field:'qty',        headerField: this.sumQty, sortable:true },
        { label: this.$t('Value'),field:'value',      headerField: this.sumValue, sortable:true },
        { label: this.$t('Status'),field:'statut',    sortable:true },
      ];
    },
    // ApexCharts options/series
    apexTimeOptions(){
      return {
        chart: { toolbar: { show: false } },
        xaxis: { categories: this.timeseries.map(x => x.d) },
        yaxis: [
          { title: { text: this.$t('Qty') } },
          { opposite: true, title: { text: this.$t('Value') }, labels: { formatter: (v) => this.shortMoney(v) } },
        ],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth' },
        tooltip: { y: { formatter: (v, { seriesIndex }) => seriesIndex === 1 ? this.money(v) : this.formatQty(v) } },
        legend: { show: true },
      };
    },
    apexTimeSeries(){
      return [
        { name: this.$t('Qty'),   type: 'line', data: this.timeseries.map(x => Number(x.qty || 0)) },
        { name: this.$t('Value'), type: 'column', data: this.timeseries.map(x => Number(x.val || 0)) },
      ];
    },
    apexRouteOptions(){
      return {
        chart: { toolbar: { show: false } },
        xaxis: { labels: { formatter: (v) => this.shortMoney(v) } },
        yaxis: { categories: this.routes.slice(0,7).map(r => `${r.from_name} → ${r.to_name}`) },
        plotOptions: { bar: { horizontal: true } },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (v) => this.money(v) } },
      };
    },
    apexRouteSeries(){
      return [{ name: this.$t('Value'), data: this.routes.slice(0,7).map(r => Number(r.val || 0)) }];
    }
  },
  methods: {
    // responsive flag
    onResize(){ this.isMobile = window.innerWidth < 768; },

    fmt(d){ return moment(d).format('YYYY-MM-DD'); },
    fmtShort(d){ return moment(d).format('MMM D'); },
    num(v){ const n = parseFloat(v||0); return isNaN(n)?0:n; },
    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing Intl.NumberFormat behavior to preserve current behavior.
    money(v){
      try {
        const n = this.num(v);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        const formatted = formatPriceDisplayHelper(n, 2, effectiveKey);
        return `${this.currency} ${formatted}`;
      } catch(e) {
        try {
          return new Intl.NumberFormat(undefined,{style:'currency',currency:this.currency}).format(this.num(v));
        } catch(e2) {
          return `${this.currency} ${this.num(v).toLocaleString()}`;
        }
      }
    },
    shortMoney(v){ return new Intl.NumberFormat(undefined,{notation:'compact',maximumFractionDigits:1}).format(this.num(v)); },
    formatQty(v){
      const n = parseFloat(v||0);
      return isNaN(n) ? "0" : n.toLocaleString(undefined,{maximumFractionDigits:2});
    },

    // Group footer helpers for vue-good-table
    sumQty(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return '0';
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].qty) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return sum.toLocaleString(undefined, {maximumFractionDigits: 2});
    },

    sumValue(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.money(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].value) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.money(sum);
    },
    getWarehouseName(){
      if (!this.warehouse_id) return this.$t("All");
      const w = (this.warehouses || []).find(x => Number(x.id) === Number(this.warehouse_id));
      return w ? w.name : `#${this.warehouse_id}`;
    },

    quick(kind){
      const now = moment(); let s,e;
      if(kind==='7d'){ s=now.clone().subtract(6,'days'); e=now; }
      if(kind==='30d'){ s=now.clone().subtract(29,'days'); e=now; }
      if(kind==='90d'){ s=now.clone().subtract(89,'days'); e=now; }
      if(kind==='mtd'){ s=now.clone().startOf('month'); e=now; }
      if(kind==='ytd'){ s=now.clone().startOf('year'); e=now; }
      this.dateRange = { startDate: s.toDate(), endDate: e.toDate() };
      this.fetchReport();
    },

    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.fetchReport();
    },
    onPerPageChange({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage;
      this.limit = currentPerPage;
      this.serverParams.page = 1;
      this.fetchReport();
    },
    onSortChange(params) {
      if (params && params[0]) {
        this.serverParams.sort = params[0];
        this.fetchReport();
      }
    },
    onSearch(v) {
      this.search = v.searchTerm || '';
      this.fetchReport();
    },

    fetchReport() {
      NProgress.start(); NProgress.set(0.1);
      this.isLoading = true;

      const sortField = (this.serverParams && this.serverParams.sort && this.serverParams.sort.field)
        ? this.serverParams.sort.field : 'dt';
      const sortType  = (this.serverParams && this.serverParams.sort && this.serverParams.sort.type)
        ? this.serverParams.sort.type : 'desc';

      const qs = new URLSearchParams({
        from: this.fmt(this.dateRange.startDate),
        to:   this.fmt(this.dateRange.endDate),
        warehouse_id: this.warehouse_id || '',
        direction: this.direction,
        page: String(this.serverParams.page),
        limit: String(this.serverParams.perPage || this.limit),
        SortField: sortField,
        SortType: sortType,
        search: this.search || ''
      }).toString();

      axios.get(`report/stock_transfer?${qs}`)
        .then(({data})=>{
          const d = data.data || {};
          this.kpis = d.kpis || this.kpis;
          this.timeseries = d.timeseries || [];
          this.routes = d.routes || [];
          this.rows[0].children = d.rows || [];
          this.totalRows = d.totalRows || 0;
          this.warehouses = data.warehouses || this.warehouses;
          this.isLoading = false; NProgress.done();
        })
        .catch(()=>{ this.isLoading = false; NProgress.done(); });
    },

    // ---- Export PDF (RTL + Vazirmatn) ----
    async exportPDF() {
      try {
        // get every page first
        const { rows: allRows } = await this.fetchAllRowsForExport();

        const { jsPDF } = await import('jspdf');
        const autoTable = (await import('jspdf-autotable')).default;

        const doc = new jsPDF({ orientation: "portrait", unit: "pt", format: "a4" });
        const marginX = 40;
        const pageW   = doc.internal.pageSize.getWidth();

        // Load Arabic-compatible font (use your single file for normal+bold)
        const fontPath = "/fonts/Vazirmatn-Bold.ttf";
        try {
          doc.addFont(fontPath, "Vazirmatn", "normal");
          doc.addFont(fontPath, "Vazirmatn", "bold");
        } catch (e) { /* ignore if already added */ }
        doc.setFont("Vazirmatn", "normal");

        // RTL?
        const rtl =
          (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
          (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

        // Title & divider
        let y = 48;
        doc.setFont("Vazirmatn", "bold");
        doc.setFontSize(16);
        const title = 'Stock Transfer Report';
        rtl ? doc.text(title, pageW - marginX, y, { align: "right" })
            : doc.text(title, marginX, y);
        y += 8;
        doc.setDrawColor(220);
        doc.line(marginX, y, pageW - marginX, y);
        y += 10;

        // Meta box (wraps cleanly)
        const k = this.kpis || {};
        const meta = [
          [this.$t("DateRange"), `${this.fmt(this.dateRange.startDate)} ${this.$t("to") || "to"} ${this.fmt(this.dateRange.endDate)}`],
          [this.$t("warehouse"), this.getWarehouseName()],
          [this.$t("Direction"), this.$t(this.direction?.charAt(0)?.toUpperCase() + this.direction?.slice(1))],
          [this.$t("Transfers"), String(k.transfers_count || 0)],
          [this.$t("Lines"), String(k.lines_count || 0)],
          [this.$t("QtyMoved"), this.formatQty(k.qty_sum)],
          [this.$t("ValueMoved"), this.money(k.value_sum)],
          [this.$t("AvgItemsPerTransfer"), this.formatQty(k.avg_items_per_transfer)],
          [this.$t("AvgValuePerTransfer"), this.money(k.avg_value_per_transfer)],
        ];

        doc.setFont("Vazirmatn", "normal");
        doc.setFontSize(10);
        autoTable(doc, {
          startY: y,
          theme: "plain",
          styles: { font: "Vazirmatn", fontSize: 10, cellPadding: 3, overflow: "linebreak", halign: rtl ? "right" : "left" },
          columnStyles: {
            0: { fontStyle: "bold", cellWidth: 150, halign: rtl ? "right" : "left" },
            1: { cellWidth: pageW - (marginX * 2) - 150, halign: rtl ? "right" : "left" }
          },
          body: meta,
          margin: { left: marginX, right: marginX },
          didDrawPage: (d) => {
            // footer page number
            const pw = doc.internal.pageSize.getWidth();
            const ph = doc.internal.pageSize.getHeight();
            doc.setFont("Vazirmatn", "normal");
            doc.setFontSize(8);
            const pn = `${d.pageNumber} / ${doc.internal.getNumberOfPages()}`;
            rtl ? doc.text(pn, marginX, ph - 14, { align: "left" })
                : doc.text(pn, pw - marginX, ph - 14, { align: "right" });
          }
        });

        const prevTable = doc.lastAutoTable || (doc.autoTable && doc.autoTable.previous);
        y = prevTable && prevTable.finalY ? (prevTable.finalY + 12) : (y + 12);

        // Table
        const head = [[
          this.$t('ID'),
          this.$t('date'),
          this.$t('From'),
          this.$t('to'),
          this.$t('Qty'),
          this.$t('Value'),
          this.$t('Status')
        ]];

        const body = (allRows || []).map(r => ([
          r.transfer_id,
          r.date_time,
          r.from,
          r.to,
          this.formatQty(r.qty),
          this.money(r.value),
          r.statut
        ]));

        autoTable(doc, {
          head, body,
          startY: y,
          margin: { left: marginX, right: marginX },
          theme: "striped",
          styles: { font: "Vazirmatn", fontSize: 9, cellPadding: 4, overflow: "linebreak", halign: rtl ? "right" : "left" },
          headStyles: { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255, halign: rtl ? "right" : "left" },
          columnStyles: {
            4: { halign: "right" }, // Qty
            5: { halign: "right" }, // Value
          }
        });

        const fname = `Stock_Transfers_${this.fmt(this.dateRange.startDate)}_${this.fmt(this.dateRange.endDate)}.pdf`;
        doc.save(fname);
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
      }
    },

    // ---- Fetch all pages for PDF (unchanged logic) ----
    async fetchAllRowsForExport(){
      const perPage = 500;
      let page = 1;
      let totalRows = Infinity;
      const allRows = [];

      while (allRows.length < totalRows) {
        const sortField = (this.serverParams && this.serverParams.sort && this.serverParams.sort.field)
          ? this.serverParams.sort.field : 'dt';
        const sortType  = (this.serverParams && this.serverParams.sort && this.serverParams.sort.type)
          ? this.serverParams.sort.type : 'desc';

        const qs = new URLSearchParams({
          from: this.fmt(this.dateRange.startDate),
          to:   this.fmt(this.dateRange.endDate),
          warehouse_id: this.warehouse_id || '',
          direction: this.direction,
          page: String(page),
          limit: String(perPage),
          SortField: sortField,
          SortType: sortType,
          search: this.search || ''
        }).toString();

        const { data } = await axios.get(`report/stock_transfer?${qs}`);
        const d = data && data.data ? data.data : {};
        const rows = d.rows || [];
        totalRows = Number(d.totalRows || rows.length || 0);

        allRows.push(...rows);
        if (rows.length < perPage) break; // last chunk
        page += 1;
      }

      return { rows: allRows };
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

      // Get rows data from grouped structure
      const rowsData = (this.rows && this.rows[0] && this.rows[0].children) ? this.rows[0].children : [];

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
          if (col.field === 'value') {
            cellContent = this.money(row.value);
          } else if (col.field === 'qty') {
            cellContent = this.formatQty(row.qty);
          } else {
            cellContent = row[col.field] || '';
          }
          tableHtml += `<td class="text-left">${cellContent}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalQty = rowsData.reduce((sum, row) => sum + parseFloat(row.qty || 0), 0);
      const totalValue = rowsData.reduce((sum, row) => sum + parseFloat(row.value || 0), 0);
      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Total')}</td>`;
      tableHtml += `<td colspan="3"></td>`; // Span for ID, date, From, to
      tableHtml += `<td class="text-left font-weight-bold">${this.formatQty(totalQty)}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.money(totalValue)}</td>`;
      tableHtml += `<td></td>`; // Status column
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Stock_Transfer_Report")}`;
      const dateRangeText = `${this.fmt(this.dateRange.startDate)} — ${this.fmt(this.dateRange.endDate)}`;
      const warehouseText = this.getWarehouseName();
      const directionText = this.$t(this.direction?.charAt(0)?.toUpperCase() + this.direction?.slice(1));

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
      ${dateRangeText} | ${this.$t('warehouse')}: ${warehouseText} | ${this.$t('Direction')}: ${directionText}
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
  created(){
    this.fetchReport();
  },
  mounted(){
    this.onResize();
    window.addEventListener('resize', this.onResize, { passive: true });
  },
  beforeDestroy(){
    window.removeEventListener('resize', this.onResize);
  }
};
</script>

<style scoped>
.rounded-xl { border-radius: 1rem; }
.shadow-soft { box-shadow: 0 12px 24px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.05); }
.toolbar-card { background:#fff; }
.btn-pill { border-radius:999px; }
.w-280 { width:280px; }

.stat-card {
  background: linear-gradient(135deg, var(--gradA,#f7f9ff), var(--gradB,#ffffff));
  padding:14px 16px; min-height:110px; position:relative;
}
.stat-inner { display:flex; align-items:center; }
.stat-icon {
  width:48px; height:48px; border-radius:12px; margin-right:12px;
  display:flex; align-items:center; justify-content:center;
  background: rgba(255,255,255,0.75);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.7), 0 1px 2px rgba(0,0,0,0.05);
}
.stat-icon i { font-size:22px; }
.stat-label { font-size:.85rem; font-weight:600; }
.stat-sub { font-size:.75rem; margin-top:-2px; }
.stat-value { font-size:1.35rem; font-weight:700; line-height:1.2; margin-top:2px; }

.theme-blue   { --gradA:#e6f0ff; --gradB:#ffffff; color:#0b5fff; }
.theme-teal   { --gradA:#e6fbf6; --gradB:#ffffff; color:#138f7a; }
.theme-indigo { --gradA:#eef0ff; --gradB:#ffffff; color:#3949ab; }
.theme-green  { --gradA:#edf9ee; --gradB:#ffffff; color:#2e7d32; }
.theme-orange { --gradA:#fff4e6; --gradB:#ffffff; color:#cc6b00; }
.theme-purple { --gradA:#f5e6ff; --gradB:#ffffff; color:#6a2ecc; }
</style>

<!-- Global (not scoped) styles for the daterangepicker popup -->
<style>
/* Keep popup above cards/modals */
.daterangepicker { z-index: 2050 !important; }

/* Phones: stretch + single calendar */
@media (max-width: 575.98px) {
  .daterangepicker {
    left: 12px !important;
    right: 12px !important;
    width: calc(100% - 24px) !important;
  }
  .daterangepicker .drp-calendar.left,
  .daterangepicker .drp-calendar.right {
    float: none !important;
    width: 100% !important;
  }
  .daterangepicker .drp-calendar.right {
    display: none !important; /* hide 2nd calendar on small screens */
  }
  .daterangepicker .ranges {
    float: none !important;
    width: 100% !important;
    margin: 8px 0 0 0 !important;
  }

  /* Wrap quick range buttons into two columns */
  .quick-ranges { display:flex !important; flex-wrap:wrap; width:100%; }
  .quick-ranges .btn { flex: 1 1 calc(50% - 6px); margin-bottom:6px; }
}
</style>
