<template>
    <div class="main-content">
      <breadcumb :page="$t('Deposits_Report')" :folder="$t('Reports')"/>
  
      <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

      <b-row v-if="!isLoading">
        <b-col md="12" class="text-center">
        <date-range-picker 
          v-model="dateRange" 
          :startDate="startDate" 
          :endDate="endDate" 
           @update="Submit_filter_dateRange"
          :locale-data="locale" > 

          <template v-slot:input="picker" style="min-width: 350px;">
              {{ fmt(picker.startDate) }} - {{ fmt(picker.endDate) }}
          </template>        
        </date-range-picker>
      </b-col>
      </b-row>

      <!-- Summary cards -->
      <b-row v-if="!isLoading" class="mb-4">
        <b-col md="4" class="mb-3">
          <b-card class="shadow-sm border-0 h-100 deposit-summary-card deposit-card-total">
            <div class="d-flex align-items-center">
              <div class="summary-icon rounded-circle mr-3">
                <lucide-icon name="dollar-sign" />
              </div>
              <div>
                <div class="text-muted small text-uppercase">{{ $t('Total_Deposits') }}</div>
                <h4 class="mb-0 font-weight-bold">{{ formatPriceWithSymbol(currentUser && currentUser.currency, totalDeposits, 2) }}</h4>
              </div>
            </div>
          </b-card>
        </b-col>
        <b-col md="4" class="mb-3">
          <b-card class="shadow-sm border-0 h-100 deposit-summary-card deposit-card-categories">
            <div class="d-flex align-items-center">
              <div class="summary-icon rounded-circle mr-3">
                <lucide-icon name="bar-chart" />
              </div>
              <div>
                <div class="text-muted small text-uppercase">{{ $t('Deposit_Category') }}</div>
                <h4 class="mb-0 font-weight-bold">{{ categoryCount }}</h4>
              </div>
            </div>
          </b-card>
        </b-col>
        <b-col md="4" class="mb-3">
          <b-card class="shadow-sm border-0 h-100 deposit-summary-card deposit-card-top">
            <div class="d-flex align-items-center">
              <div class="summary-icon rounded-circle mr-3">
                <lucide-icon name="banknote" />
              </div>
              <div>
                <div class="text-muted small text-uppercase">{{ $t('Top_Category') || 'Top Category' }}</div>
                <h4 class="mb-0 font-weight-bold text-truncate" :title="topCategoryName">{{ topCategoryName || '—' }}</h4>
                <small v-if="topCategoryAmount != null" class="text-muted">{{ formatPriceWithSymbol(currentUser && currentUser.currency, topCategoryAmount, 2) }}</small>
              </div>
            </div>
          </b-card>
        </b-col>
      </b-row>

      <!-- Charts -->
      <b-row v-if="!isLoading" class="mb-4">
        <b-col lg="6" class="mb-3">
          <b-card class="shadow-sm border-0 h-100">
            <h5 class="card-title mb-3">{{ $t('Deposits_by_Category') || 'Deposits by Category' }} ({{ $t('Distribution') || 'Distribution' }})</h5>
            <div v-if="chartDataLength" class="chart-wrapper">
              <apexchart type="donut" height="320" :options="apexPieOptions" :series="apexPieSeries" />
            </div>
            <div v-else class="text-center text-muted py-5">{{ $t('No_Data') || 'No data for the selected period' }}</div>
          </b-card>
        </b-col>
        <b-col lg="6" class="mb-3">
          <b-card class="shadow-sm border-0 h-100">
            <h5 class="card-title mb-3">{{ $t('Deposits_by_Category') || 'Deposits by Category' }} ({{ $t('Total_Deposits') }})</h5>
            <div v-if="chartDataLength" class="chart-wrapper">
              <apexchart type="bar" height="320" :options="apexBarOptions" :series="apexBarSeries" />
            </div>
            <div v-else class="text-center text-muted py-5">{{ $t('No_Data') || 'No data for the selected period' }}</div>
          </b-card>
        </b-col>
      </b-row>

      <b-card class="wrapper" v-if="!isLoading">
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
          styleClass="tableOne table-hover vgt-table mt-3"
        >
          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field == 'total_deposits'">
              {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.total_deposits, 2) }}
            </span>
            <span v-else>
              {{ props.formattedRow[props.column.field] }}
            </span>
          </template>
  
         <div slot="table-actions" class="mt-2 mb-3">
            <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
              <lucide-icon name="printer" /> {{ $t("print") }}
            </b-button>
            <b-button @click="deposits_report_pdf()" size="sm" variant="outline-success ripple m-1">
              <lucide-icon name="copy" /> PDF
            </b-button>
             <vue-excel-xlsx
                class="btn btn-sm btn-outline-danger ripple m-1"
                :data="reports"
                :columns="columns"
                :file-name="'deposits_report'"
                :file-type="'xlsx'"
                :sheet-name="'deposits_report'"
                >
                <lucide-icon name="file-spreadsheet" /> EXCEL
            </vue-excel-xlsx>
          </div>
        </vue-good-table>
      </b-card>
    </div>
  </template>
  
  
  <script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import VueApexCharts from "vue-apexcharts";

import DateRangePicker from 'vue2-daterange-picker'
//you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";
  
  export default {
    components: { DateRangePicker, apexchart: VueApexCharts },
    metaInfo: {
      title: "Deposits Report"
    },
    data() {
      return {
        startDate: "", 
        endDate: "", 
        dateRange: { 
          startDate: "", 
          endDate: "" 
        }, 
        locale:{ 
            //separator between the two ranges apply
            Label: "Apply", 
            cancelLabel: "Cancel", 
            weekLabel: "W", 
            customRangeLabel: "Custom Range", 
            daysOfWeek: moment.weekdaysMin(), 
            //array of days - see moment documenations for details 
            monthNames: moment.monthsShort(), //array of month names - see moment documenations for details 
            firstDay: 1 //ISO first day of week - see moment documenations for details
          },
          today_mode: true,
          to: "",
          from: "",
        isLoading: true,
        rows: [{
          category_name: 'Total',
         
          children: [
             
          ],
      },],
        serverParams: {
          sort: {
            field: "id",
            type: "desc"
          },
          page: 1,
          perPage: 10
        },
        limit: "10",
        search: "",
        totalRows: "",
        reports: [],
        report: {},
        warehouse_id: 0
      };
    },
  
    computed: {
      ...mapGetters(["currentUser"]),
      columns() {
        return [
          {
            label: this.$t("Deposit_Category"),
            field: "category_name",
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
          {
            label: this.$t("Total_Deposits"),
            field: "total_deposits",
            type: "decimal",
            headerField: this.sumCount,
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
        ];
      },
      totalDeposits() {
        return (this.reports || []).reduce((sum, r) => sum + parseFloat(r.total_deposits || 0), 0);
      },
      categoryCount() {
        return (this.reports || []).length;
      },
      topCategoryName() {
        const reports = this.reports || [];
        if (!reports.length) return '';
        const top = reports.reduce((best, r) => {
          const val = parseFloat(r.total_deposits || 0);
          return val > (best ? parseFloat(best.total_deposits || 0) : 0) ? r : best;
        }, null);
        return top ? top.category_name : '';
      },
      topCategoryAmount() {
        const reports = this.reports || [];
        if (!reports.length) return null;
        const top = reports.reduce((best, r) => {
          const val = parseFloat(r.total_deposits || 0);
          return val > (best ? parseFloat(best.total_deposits || 0) : 0) ? r : best;
        }, null);
        return top ? parseFloat(top.total_deposits || 0) : null;
      },
      chartDataLength() {
        return (this.reports || []).length;
      },
      apexPieSeries() {
        return (this.reports || []).map(r => parseFloat(r.total_deposits || 0));
      },
      apexPieOptions() {
        const totalStr = this.currentUser && this.currentUser.currency
          ? `${this.currentUser.currency} ${Number(this.totalDeposits).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
          : String(this.totalDeposits);
        return {
          chart: { type: 'donut', toolbar: { show: false } },
          labels: (this.reports || []).map(r => r.category_name || ''),
          legend: { position: 'bottom', fontSize: '12px' },
          colors: ['#0ea5e9', '#06b6d4', '#14b8a6', '#10b981', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#f97316', '#ef4444'],
          dataLabels: { enabled: true, formatter(val) { return val ? Number(val).toFixed(1) + '%' : ''; } },
          plotOptions: { pie: { donut: { size: '55%', labels: { show: true, total: { show: true, label: this.$t('Total_Deposits'), formatter: () => totalStr } } } } },
        };
      },
      apexBarSeries() {
        return [{ name: this.$t('Total_Deposits'), data: (this.reports || []).map(r => parseFloat(r.total_deposits || 0)) }];
      },
      apexBarOptions() {
        return {
          chart: { type: 'bar', toolbar: { show: false }, stacked: false },
          plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
          dataLabels: { enabled: true },
          xaxis: { categories: (this.reports || []).map(r => r.category_name || ''), labels: { rotate: -45, style: { fontSize: '11px' } } },
          yaxis: { labels: { formatter: (val) => (this.currentUser && this.currentUser.currency ? `${this.currentUser.currency} ${Number(val).toLocaleString(undefined, { maximumFractionDigits: 0 })}` : String(val)) } },
          colors: ['#0ea5e9'],
          legend: { show: false },
          grid: { borderColor: '#e5e7eb', strokeDashArray: 4, xaxis: { lines: { show: false } } },
        };
      },
    },
  
    methods: {

      
      sumCount(rowObj) {
     
        let sum = 0;
        for (let i = 0; i < rowObj.children.length; i++) {
          sum += rowObj.children[i].total_deposits;
        }
        return sum;
      },
  
       //----------------------------------- Sales PDF ------------------------------\\
      deposits_report_pdf() {
        var self = this;
        let pdf = new jsPDF("p", "pt");

        const fontPath = "/fonts/Vazirmatn-Bold.ttf";
        pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
        pdf.setFont("VazirmatnBold"); 

        let columns = [
          { header: self.$t("Deposit_Category"), dataKey: "category_name" },
          { header: self.$t("Total_Deposits"), dataKey: "total_deposits" },
        ];

        // Calculate totals
        let totalGrandTotal = self.reports.reduce((sum, report) => sum + parseFloat(report.total_deposits || 0), 0);
        
        let footer = [{
          category_name: self.$t("Total"),
          total_deposits: `${totalGrandTotal.toFixed(2)}`,
          
        }];

        autoTable(pdf, {
             columns: columns,
             body: self.reports,
             foot: footer,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Deposits Report", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
             footStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
        });

        pdf.save("deposits_report.pdf");
        
      },
  
      //---- update Params Table
      updateParams(newProps) {
        this.serverParams = Object.assign({}, this.serverParams, newProps);
      },
  
      //---- Event Page Change
      onPageChange({ currentPage }) {
        if (this.serverParams.page !== currentPage) {
          this.updateParams({ page: currentPage });
          this.get_deposits_report(currentPage);
        }
      },
  
      //---- Event Per Page Change
      onPerPageChange({ currentPerPage }) {
        if (this.limit !== currentPerPage) {
          this.limit = currentPerPage;
          this.updateParams({ page: 1, perPage: currentPerPage });
          this.get_deposits_report(1);
        }
      },
  
      //---- Event on Sort Change
      onSortChange(params) {
        this.updateParams({
          sort: {
            type: params[0].type,
            field: params[0].field
          }
        });
        this.get_deposits_report(this.serverParams.page);
      },
  
      //---- Event on Search
  
      onSearch(value) {
        this.search = value.searchTerm;
        this.get_deposits_report(this.serverParams.page);
      },
  
      //------------------------------Formetted Numbers -------------------------\\
      formatNumber(number, dec) {
        const value = (typeof number === "string"
          ? number
          : number.toString()
        ).split(".");
        if (dec <= 0) return value[0];
        let formated = value[1] || "";
        if (formated.length > dec)
          return `${value[0]}.${formated.substr(0, dec)}`;
        while (formated.length < dec) formated += "0";
        return `${value[0]}.${formated}`;
      },

      // Price formatting for display only (does NOT affect calculations or stored values)
      // Uses the global/system price_format setting when available; otherwise falls back
      // to the existing formatNumber helper to preserve current behavior.
      formatPriceDisplay(number, dec) {
        try {
          const decimals = Number.isInteger(dec) ? dec : 0;
          const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
          if (key) {
            this.price_format_key = key;
          }
          const effectiveKey = key || null;
          return formatPriceDisplayHelper(number, decimals, effectiveKey);
        } catch (e) {
          return this.formatNumber(number, dec);
        }
      },

      formatPriceWithSymbol(symbol, number, dec) {
        const safeSymbol = symbol || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      },

    //------ Print Table Only - Print ALL deposits data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Deposits_Report")}`;
      const reports = Array.isArray(this.rows[0]?.children) ? this.rows[0].children : [];
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      reports.forEach(report => {
        tableHTML += '<tr>';
        this.columns.forEach(col => {
          let cellValue = '';
          
          if (col.field === 'category_name') {
            cellValue = report.category_name || '';
          } else if (col.field === 'total_deposits') {
            cellValue = this.formatPriceWithSymbol(this.currentUser?.currency, report.total_deposits, 2);
          } else {
            // Default: get value directly from report object
            cellValue = report[col.field] || '';
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
  
    //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      const pad = (n) => String(n).padStart(2, "0");
      const formatLocalDate = (d) =>
        `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
      this.startDate = formatLocalDate(new Date(this.dateRange.startDate));
      this.endDate = formatLocalDate(new Date(this.dateRange.endDate));
      this.get_deposits_report(1);
    },


    get_data_loaded() {
      const self = this;
      if (self.today_mode) {
        const startDate = new Date("01/01/2000");
        const endDate = new Date();
        const pad = (n) => String(n).padStart(2, "0");
        const formatLocalDate = (d) =>
          `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        self.startDate = formatLocalDate(startDate);
        self.endDate = formatLocalDate(endDate);
        self.dateRange.startDate = startDate;
        self.dateRange.endDate = endDate;
      }
    },

    // Same as dashboard: format date for picker display (YYYY-MM-DD, local time via moment)
    fmt(d) {
      return moment(d).format("YYYY-MM-DD");
    },

  
      //--------------------------- Get Customer Report -------------\\
  
      get_deposits_report(page) {
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        this.get_data_loaded();
        axios
          .get(
            "report/deposits_report?page=" +
              page +
              "&SortField=" +
              this.serverParams.sort.field +
              "&SortType=" +
              this.serverParams.sort.type +
              "&search=" +
              this.search +
              "&limit=" +
              this.limit +
              "&to=" +
            this.endDate +
            "&from=" +
            this.startDate
          )
          .then(response => {
            this.reports = response.data.reports;
            this.totalRows = response.data.totalRows;
            this.rows[0].children = this.reports;

            // Complete the animation of theprogress bar.
            NProgress.done();
            this.isLoading = false;
            this.today_mode = false;
          })
          .catch(response => {
            // Complete the animation of theprogress bar.
            NProgress.done();
            setTimeout(() => {
              this.isLoading = false;
              this.today_mode = false;
            }, 500);
          });
      }
    }, //end Methods
  
    //----------------------------- Created function------------------- \\
  
    created: function() {
      this.get_deposits_report(1);
    }
  };
  </script>

  <style scoped>
  .deposit-summary-card .summary-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: #fff;
  }
  .deposit-card-total .summary-icon { background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%); }
  .deposit-card-categories .summary-icon { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); }
  .deposit-card-top .summary-icon { background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); }
  .chart-wrapper { min-height: 280px; }
  </style>