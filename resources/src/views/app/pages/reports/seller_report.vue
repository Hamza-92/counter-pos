<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Seller_report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="shadow-soft border-0 mb-3">
      <div class="d-flex flex-wrap align-items-center">
        <!-- Date/time range -->
        <div class="mr-3 mb-2 d-flex flex-column date-range-filter">
          <label class="mb-1 d-block text-muted">{{ $t('DateRange') }}</label>
          <date-range-picker
            v-model="dateRange"
            :locale-data="locale"
            :time-picker="true"
            :time-picker-seconds="true"
            :autoApply="true"
            :showDropdowns="true"
            :opens="isMobile ? 'center' : 'right'"
            :drops="'down'"
            @update="Submit_filter_dateRange"
          >
            <template v-slot:input="picker">
              <b-button
                variant="light"
                class="btn-pill date-btn"
                :class="{ 'w-100': isMobile }"
              >
                <lucide-icon class="mr-1" name="calendar-days" />
                <span class="d-none d-sm-inline">
                  {{ formatDateTime(picker.startDate) }} —
                  {{ formatDateTime(picker.endDate) }}
                </span>
                <span class="d-inline d-sm-none">
                  {{ formatDateTimeShort(picker.startDate) }}–{{ formatDateTimeShort(picker.endDate) }}
                </span>
              </b-button>
            </template>
          </date-range-picker>
        </div>

        <!-- Warehouse -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{ $t('warehouse') }}</label>
          <v-select
            class="w-280"
            v-model="warehouse_id"
            @input="Selected_Warehouse"
            :reduce="label => label.value"
            :placeholder="$t('Choose_Warehouse')"
            :options="warehouses.map(w => ({ label: w.name, value: w.id }))"
          />
        </div>

        <!-- Actions -->
        <div class="ml-auto mb-2 d-flex">
          <b-button
            variant="success"
            class="btn-pill mr-2"
            @click="Seller_report_pdf"
          >
            <lucide-icon class="mr-1" name="file-text" />
            {{ $t('Export_PDF') || 'PDF' }}
          </b-button>

          <vue-excel-xlsx
            class="btn btn-outline-danger btn-pill mr-2"
            :data="payments"
            :columns="columns"
            :file-name="'Seller_report'"
            :file-type="'xlsx'"
            :sheet-name="'Seller_report'"
          >
            <lucide-icon class="mr-1" name="file-spreadsheet" />
            <span>EXCEL</span>
          </vue-excel-xlsx>

          <b-button
            variant="primary"
            class="btn-pill"
            @click="Seller_report(serverParams.page)"
          >
            <lucide-icon class="mr-1" name="refresh-cw" />
            {{ $t('Refresh') || 'Refresh' }}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading -->
    <div v-if="isLoading" class="text-center my-5">
      <div class="loading_page spinner spinner-primary mr-3"></div>
    </div>

    <!-- Analytics + Table -->
    <div v-else>
      <!-- KPI Cards -->
      <b-row class="mb-3">
        <b-col md="3" sm="6" class="mb-3">
          <StatTile
            icon="users"
            :label="$t('Sellers') || 'Sellers'"
            :value="num(sellerStats.totalSellers)"
            theme="blue"
          />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile
            icon="banknote"
            :label="$t('TotalSales')"
            :value="money(sellerStats.totalSales)"
            theme="green"
          />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile
            icon="bar-chart"
            :label="$t('AvgPerSeller') || 'Avg per seller'"
            :value="money(sellerStats.avgSales)"
            theme="indigo"
          />
        </b-col>
        <b-col md="3" sm="6" class="mb-3">
          <StatTile
            icon="star"
            :label="$t('TopSeller') || 'Top seller'"
            :value="sellerStats.topSellerName || '-'"
            theme="teal"
          />
        </b-col>
      </b-row>

      <!-- Charts -->
      <b-row class="mb-3">
        <b-col lg="8" class="mb-3">
          <b-card class="shadow-soft border-0 h-100">
            <div
              class="d-flex align-items-center justify-content-between mb-2"
            >
              <h6 class="m-0">
                {{ $t('Seller') }} {{ $t('TotalSales') }}
              </h6>
              <small class="text-muted" v-if="topSellersChartData.length">
                {{ topSellersChartData.length }} {{ $t('Sellers') || 'sellers' }}
              </small>
            </div>

            <apexchart
              v-if="topSellersChartData.length"
              type="bar"
              height="320"
              :options="topSellersChartOptions"
              :series="topSellersChartSeries"
            />
            <div v-else class="text-muted text-center small py-4">
              {{ $t('No_Data') || 'No data to display' }}
            </div>

            <!-- Top seller listing -->
            <div
              v-if="topSellersChartData.length"
              class="mt-3 small top-seller-list"
            >
              <div
                class="d-flex justify-content-between align-items-center mb-1"
              >
                <span class="text-muted text-uppercase">
                  {{ $t('TopSeller') || 'Top seller' }}
                </span>
                <span class="font-weight-bold">
                  {{ sellerStats.topSellerName }}
                  ·
                  {{ money(sellerStats.topSellerSales) }}
                </span>
              </div>
              <ul class="list-unstyled mb-0">
                <li
                  v-for="(s, idx) in topSellersChartData.slice(0, 5)"
                  :key="s.name"
                  class="d-flex justify-content-between py-1"
                >
                  <span>{{ idx + 1 }}. {{ s.name }}</span>
                  <span>{{ money(s.sales) }}</span>
                </li>
              </ul>
            </div>
          </b-card>
        </b-col>

        <b-col lg="4" class="mb-3">
          <b-card class="shadow-soft border-0 h-100">
            <div
              class="d-flex align-items-center justify-content-between mb-2"
            >
              <h6 class="m-0">
                {{ $t('SalesByPaymentMethod') || 'Sales by payment method' }}
              </h6>
              <small class="text-muted">
                {{ $t('CurrentPage') || 'Current page' }}
              </small>
            </div>

            <apexchart
              v-if="paymentMethodsChartSeries.length && paymentMethodsChartSeries[0].data.length"
              type="bar"
              height="320"
              :options="paymentMethodsChartOptions"
              :series="paymentMethodsChartSeries"
            />
            <div v-else class="text-muted text-center small py-4">
              {{ $t('No_Data') || 'No data to display' }}
            </div>
          </b-card>
        </b-col>
      </b-row>

      <!-- Table -->
      <b-card class="shadow-soft border-0 print-table-only">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="rows"
          :group-options="{
            enabled: true,
            headerPosition: 'bottom',
          }"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
          :search-options="{
            placeholder: $t('Search_this_table'),
            enabled: true,
          }"
          :pagination-options="{
            enabled: true,
            mode: 'records',
            nextLabel: 'next',
            prevLabel: 'prev',
          }"
          styleClass="table-hover tableOne vgt-table"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
              <lucide-icon name="printer" /> {{ $t("print") }}
            </b-button>
          </div>
          <template slot="table-row" slot-scope="props">
            <!-- Format price columns (total_sales and all payment methods) -->
            <span v-if="isPriceField(props.column.field)">
              {{ formatPriceDisplay(props.row[props.column.field], 2) }}
            </span>
            <!-- Default rendering for other columns -->
            <span v-else>
              {{ props.formattedRow[props.column.field] }}
            </span>
          </template>
        </vue-good-table>
      </b-card>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import DateRangePicker from "vue2-daterange-picker";
// you need to import the CSS manually
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";
import { mapGetters } from "vuex";
import VueApexCharts from "vue-apexcharts";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

const StatTile = {
  name: "StatTile",
  functional: true,
  props: {
    icon: String,
    label: String,
    value: [String, Number],
    theme: { type: String, default: "blue" },
  },
  render(h, { props }) {
    return h(
      "div",
      {
        class: ["stat-card", `theme-${props.theme}`, "shadow-soft", "rounded-xl"],
      },
      [
        h("div", { class: "stat-inner" }, [
          h("div", { class: "stat-icon" }, [h('lucide-icon', { props: { name: props.icon } })]),
          h("div", { class: "stat-content" }, [
            h("div", { class: "stat-label" }, props.label),
            h("div", { class: "stat-value" }, props.value),
          ]),
        ]),
      ]
    );
  },
};

export default {
  metaInfo: {
    title: "Report Seller",
  },
  components: {
    apexchart: VueApexCharts,
    "date-range-picker": DateRangePicker,
    StatTile,
  },

  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc",
        },
        page: 1,
        perPage: 10,
      },
      limit: "10",
      search: "",
      totalRows: "",
      start_time: "",
      end_time: "",
      payments: [],
      rows: [{
        statut: '',
        children: [],
      }],
      paymentMethods: [],
      warehouses: [],
      warehouse_id: "",
      today_mode: true,
      startDate: "",
      endDate: "",
      dateRange: {
        startDate: "",
        endDate: "",
      },
      locale: {
        // separator between the two ranges apply
        Label: "Apply",
        cancelLabel: "Cancel",
        weekLabel: "W",
        customRangeLabel: "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        // array of days - see moment documentation for details
        monthNames: moment.monthsShort(), // array of month names
        firstDay: 1, // ISO first day of week
      },
      isMobile: false,
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),

    currency() {
      return (this.currentUser && this.currentUser.currency) || "";
    },

    columns() {
      const base = [
        {
          label: this.$t("Seller"),
          field: "username",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: true,
        },
        {
          label: this.$t("TotalSales"),
          field: "total_sales",
          headerField: this.sumTotalSales,
          tdClass: "text-center",
          thClass: "text-center",
          sortable: false,
        },
      ];

      const dynamic = this.paymentMethods.map((method) => ({
        label: method,
        field: method,
        headerField: (rowObj) => this.sumPaymentMethod(rowObj, method),
        tdClass: "text-right",
        thClass: "text-right",
        sortable: false,
      }));

      return [...base, ...dynamic];
    },

    // Aggregated stats over the currently visible sellers (current page)
    sellerStats() {
      const rows = Array.isArray(this.payments) ? this.payments : [];
      if (!rows.length) {
        return {
          totalSellers: 0,
          totalSales: 0,
          avgSales: 0,
          topSellerName: "",
          topSellerSales: 0,
        };
      }

      let totalSales = 0;
      let topSeller = null;

      rows.forEach((row) => {
        const sales = this.toNumber(row.total_sales);
        totalSales += sales;
        if (!topSeller || sales > topSeller.sales) {
          topSeller = {
            name: row.username,
            sales,
          };
        }
      });

      const totalSellers = rows.length;
      const avgSales = totalSellers ? totalSales / totalSellers : 0;

      return {
        totalSellers,
        totalSales,
        avgSales,
        topSellerName: topSeller ? topSeller.name : "",
        topSellerSales: topSeller ? topSeller.sales : 0,
      };
    },

    // Top sellers chart data (current page only)
    topSellersChartData() {
      const rows = Array.isArray(this.payments) ? this.payments : [];
      const mapped = rows
        .map((r) => ({
          name: r.username,
          sales: this.toNumber(r.total_sales),
        }))
        .filter((r) => r.sales > 0);

      mapped.sort((a, b) => b.sales - a.sales);
      return mapped.slice(0, 10);
    },

    topSellersChartOptions() {
      return {
        chart: { toolbar: { show: false } },
        xaxis: {
          categories: this.topSellersChartData.map((x) => x.name),
          labels: { rotate: -45 },
        },
        yaxis: {
          labels: {
            formatter: (v) => this.shortMoney(v),
          },
        },
        dataLabels: { enabled: false },
        tooltip: {
          y: {
            formatter: (v) => this.money(v),
          },
        },
      };
    },

    topSellersChartSeries() {
      return [
        {
          name: this.$t("TotalSales"),
          data: this.topSellersChartData.map((x) => x.sales),
        },
      ];
    },

    // Payment method totals aggregated over current page
    paymentMethodTotals() {
      const methods = Array.isArray(this.paymentMethods)
        ? this.paymentMethods
        : [];
      const rows = Array.isArray(this.payments) ? this.payments : [];

      return methods.map((method) => {
        let total = 0;
        rows.forEach((row) => {
          total += this.toNumber(row[method]);
        });
        return { method, total };
      });
    },

    paymentMethodsChartOptions() {
      // Vertical bar chart, same pattern as Top_Suppliers value chart
      const categories = this.paymentMethodTotals.map((x) => x.method);
      return {
        chart: { toolbar: { show: false } },
        xaxis: {
          categories,
        },
        yaxis: {
          labels: {
            formatter: (v) => this.shortMoney(v),
          },
        },
        dataLabels: { enabled: false },
        tooltip: {
          y: {
            formatter: (v) => this.money(v),
          },
        },
      };
    },

    paymentMethodsChartSeries() {
      return [
        {
          name: this.$t("TotalSales"),
          data: this.paymentMethodTotals.map((x) => x.total),
        },
      ];
    },
  },

  methods: {
    // Helper to check if a field is a price field
    isPriceField(field) {
      return field === 'total_sales' || (this.paymentMethods && this.paymentMethods.includes(field));
    },

    // Group footer helpers for vue-good-table
    sumTotalSales(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceDisplay(0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = this.toNumber(rowObj.children[i].total_sales) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceDisplay(sum, 2);
    },

    sumPaymentMethod(rowObj, method) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceDisplay(0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = this.toNumber(rowObj.children[i][method]) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceDisplay(sum, 2);
    },

    //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      if (value === null) {
        this.warehouse_id = "";
      }
      this.Seller_report(1);
    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Seller_report(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Seller_report(1);
      }
    },

    //---- Event on Sort Change
    onSortChange(params) {
      let field = "";
      field = params[0].field;
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field,
        },
      });
      this.Seller_report(this.serverParams.page);
    },

    //---- Event on Search

    onSearch(value) {
      this.search = value.searchTerm;
      this.Seller_report(this.serverParams.page);
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

      // Get payments data from rows[0].children or this.payments
      const paymentsData = Array.isArray(this.rows[0]?.children) && this.rows[0].children.length > 0 
        ? this.rows[0].children 
        : (this.payments || []);

      // Manually construct the table HTML from payments data
      let tableHtml = `<table class="vgt-table table table-hover tableOne">`;

      // Table Header
      tableHtml += `<thead><tr>`;
      this.columns.forEach(col => {
        tableHtml += `<th class="text-left">${col.label}</th>`;
      });
      tableHtml += `</tr></thead>`;

      // Table Body
      tableHtml += `<tbody>`;
      paymentsData.forEach(row => {
        tableHtml += `<tr>`;
        this.columns.forEach(col => {
          let cellContent = row[col.field];
          // Format price fields (total_sales and all payment methods)
          if (this.isPriceField(col.field)) {
            cellContent = this.formatPriceDisplay(row[col.field], 2);
          }
          tableHtml += `<td class="text-left">${cellContent || ''}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalSales = this.sumTotalSales(this.rows[0]);
      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Total')}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalSales}</td>`;
      // Add totals for each payment method
      (this.paymentMethods || []).forEach(method => {
        const total = this.sumPaymentMethod(this.rows[0], method);
        tableHtml += `<td class="text-left font-weight-bold">${total}</td>`;
      });
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Seller_report")}`;
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
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f2f2f2; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
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

    Seller_report_pdf() {
      const doc = new jsPDF("p", "pt");

      // Load custom font
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        doc.addFont(fontPath, "VazirmatnBold", "bold");
        doc.setFont("VazirmatnBold");
      } catch (e) {
        // Fallback silently if font is already registered or missing
      }

      // 1. Base headers
      const headers = [
        { title: this.$t("Seller"), dataKey: "username" },
        { title: this.$t("TotalSales"), dataKey: "total_sales" },
        ...(this.paymentMethods || []).map((method) => ({
          // Show the payment method name exactly as defined
          title: method,
          dataKey: method,
        })),
      ];

      // 2. Build rows
      const rows = Array.isArray(this.payments) ? this.payments : [];

      // 4. Generate PDF table using jspdf-autotable helper
      autoTable(doc, {
        head: [headers.map((h) => h.title)],
        body: rows.map((row) => headers.map((h) => row[h.dataKey] ?? "")),
        startY: 70,
        theme: "grid",
        didDrawPage: () => {
          doc.setFontSize(18);
          doc.text("Seller Payment Report", 40, 25);
        },
        styles: {
          halign: "center",
        },
        headStyles: {
          fillColor: [200, 200, 200],
          textColor: [0, 0, 0],
          fontStyle: "bold",
        },
      });

      // 5. Save file
      doc.save("Seller_Payment_Report.pdf");
    },

    //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      // Ensure we have valid start/end dates before formatting
      const start = this.dateRange.startDate
        ? moment(this.dateRange.startDate)
        : null;
      const end = this.dateRange.endDate
        ? moment(this.dateRange.endDate)
        : null;

      if (start && end) {
        // Send separate date and time parts to backend
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate = end.format("YYYY-MM-DD");

        this.start_time = start.format("HH:mm:ss");
        this.end_time = end.format("HH:mm:ss");

        this.Seller_report(1);
      }
    },

    get_data_loaded() {
      const self = this;
      if (self.today_mode) {
        // Default range: from 2000-01-01 until today
        const startDate = new Date("2000-01-01");
        const endDate = new Date(); // Set end date to current date

        // Values used for backend filtering (YYYY-MM-DD)
        self.startDate = moment(startDate).format("YYYY-MM-DD");
        self.endDate = moment(endDate).format("YYYY-MM-DD");

        // Values used by the date-range-picker (Date objects)
        self.dateRange.startDate = startDate;
        self.dateRange.endDate = endDate;
      }
    },

    // Format helper for displaying in the picker input
    formatDateTime(date) {
      return date ? moment(date).format("YYYY-MM-DD HH:mm:ss") : "";
    },

    formatDateTimeShort(date) {
      return date ? moment(date).format("YYYY-MM-DD") : "";
    },

    // Robust numeric conversion from formatted strings ('1,234.50') or numbers
    toNumber(val) {
      if (typeof val === "number") {
        return isNaN(val) ? 0 : val;
      }
      if (!val) return 0;
      const num = parseFloat(val.toString().replace(/,/g, ""));
      return isNaN(num) ? 0 : num;
    },

    num(v) {
      const n = Number(this.toNumber(v) || 0);
      return isNaN(n) ? "0" : n.toLocaleString();
    },

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing toLocaleString behavior to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 2;
        // Convert to number first (handles strings like "1,234.56" or numbers)
        const n = this.toNumber(number);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, decimals, effectiveKey);
      } catch (e) {
        const n = this.toNumber(number);
        return n.toLocaleString(undefined, { maximumFractionDigits: dec || 2 });
      }
    },

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing Intl.NumberFormat behavior to preserve current behavior.
    money(v) {
      try {
        const n = this.toNumber(v);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        const formatted = formatPriceDisplayHelper(n, 2, effectiveKey);
        return `${this.currency} ${formatted}`;
      } catch (e) {
        const n = this.toNumber(v);
        try {
          const currency = this.currency || "USD";
          return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency,
          }).format(n);
        } catch (e2) {
          return `${this.currency} ${n.toLocaleString()}`;
        }
      }
    },

    shortMoney(v) {
      const n = this.toNumber(v);
      return new Intl.NumberFormat(undefined, {
        notation: "compact",
        maximumFractionDigits: 1,
      }).format(n);
    },

    handleResize() {
      this.isMobile = window.innerWidth < 576;
    },

    //-------------------------------- Get All Payments Sales ---------------------\\
    Seller_report(page) {
      // Start the progress bar
      NProgress.start();
      NProgress.set(0.1);

      // Mark loading
      this.get_data_loaded();
      this.isLoading = true;

      axios
        .get("report/seller_report", {
          params: {
            page: page,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.limit,
            warehouse_id: this.warehouse_id,
            end_date: this.endDate,
            start_date: this.startDate,
            start_time: this.start_time,
            end_time: this.end_time,
          },
        })
        .then((response) => {
          this.payments = response.data.report;
          this.paymentMethods = response.data.paymentMethods || [];
          this.warehouses = response.data.warehouses || [];
          this.totalRows = response.data.totalRows;
          this.rows[0].children = this.payments;

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },
  },

  //----------------------------- Lifecycle hooks -------------------\\
  created() {
    this.Seller_report(1);
  },
  mounted() {
    this.handleResize();
    window.addEventListener("resize", this.handleResize);
  },
  beforeDestroy() {
    window.removeEventListener("resize", this.handleResize);
  },
};
</script>

<style scoped>
.rounded-xl {
  border-radius: 1rem;
}

.shadow-soft {
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06), 0 2px 6px rgba(0, 0, 0, 0.05);
}

.btn-pill {
  border-radius: 999px;
}

.w-280 {
  width: 280px;
  max-width: 100%;
}

.stat-card {
  padding: 14px 16px;
  min-height: 100px;
  background: #fff;
}

.stat-inner {
  display: flex;
  align-items: center;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: #f8f9fa;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
}

.stat-icon i {
  font-size: 22px;
}

.stat-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #666;
}

.stat-value {
  font-size: 1.3rem;
  font-weight: 700;
  color: #333;
}

.theme-blue .stat-icon {
  color: #0b5fff;
}

.theme-teal .stat-icon {
  color: #138f7a;
}

.theme-indigo .stat-icon {
  color: #3949ab;
}

.theme-green .stat-icon {
  color: #2e7d32;
}

.date-range-filter {
  min-width: 240px;
}

.date-btn {
  display: inline-flex;
  align-items: center;
}

@media (max-width: 575.98px) {
  .date-range-filter {
    width: 100%;
  }

  .daterangepicker {
    left: 0 !important;
    right: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
  }

  .daterangepicker .ranges,
  .daterangepicker .drp-calendar {
    float: none !important;
    width: 100% !important;
  }
}
</style>