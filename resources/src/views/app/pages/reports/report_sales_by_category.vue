<template>
    <div class="main-content">
      <breadcumb :page="$t('Sales_by_Category')" :folder="$t('Reports')"/>
  
      <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

      <b-col md="12" class="text-center" v-if="!isLoading">
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

      <b-card class="wrapper print-table-only" v-if="!isLoading">
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
            <span v-if="props.column.field == 'total_sales'">
              {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.total_sales, 2) }}
            </span>
            <span v-else>
              {{ props.formattedRow[props.column.field] }}
            </span>
          </template>
  
         <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
            <b-button @click="report_pdf()" size="sm" variant="outline-success ripple m-1">
              <lucide-icon name="copy" /> PDF
            </b-button>
             <vue-excel-xlsx
                class="btn btn-sm btn-outline-danger ripple m-1"
                :data="reports"
                :columns="columns"
                :file-name="'sales_by_category_report'"
                :file-type="'xlsx'"
                :sheet-name="'sales_by_category_report'"
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
  import { mapGetters } from "vuex";

  import DateRangePicker from 'vue2-daterange-picker'
  //you need to import the CSS manually
  import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
  import moment from 'moment'
  import {
    formatPriceDisplay as formatPriceDisplayHelper,
    getPriceFormatSetting
  } from "../../../../utils/priceFormat";
  
  export default {
    components: { DateRangePicker },
    metaInfo: {
      title: "Sales By Category"
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
        currency: "",
        reports: [],
        report: {},
        warehouse_id: 0,
        // Optional price format key for frontend display (loaded from system settings/Vuex store)
        price_format_key: null
      };
    },
  
    computed: {
      ...mapGetters(["currentUser"]),
      columns() {
        return [
          {
            label: this.$t("Categorie"),
            field: "category_name",
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },
         
          {
            label: this.$t("total_sales"),
            field: "total_sales",
            // type: "decimal",
            headerField: this.sumCount,
            tdClass: "text-left",
            thClass: "text-left",
            sortable: false
          },

         
        ];
      }
    },
  
    methods: {

      
    // Group footer helper for vue-good-table.
    // Returns a formatted string so the footer row inside the table
    // looks like a normal data row, but uses the global price format & currency.
    sumCount(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, 0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total_sales) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },




  
       //----------------------------------- Sales PDF ------------------------------\\
      report_pdf() {
        var self = this;
        let pdf = new jsPDF("p", "pt");

        const fontPath = "/fonts/Vazirmatn-Bold.ttf";
        pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
        pdf.setFont("VazirmatnBold"); 

        let columns = [
          { header: self.$t("Categorie"), dataKey: "category_name" },
          { header: self.$t("total_sales"), dataKey: "total_sales" },
        ];

        // Calculate totals
        let totalGrandTotal = self.reports.reduce((sum, report) => sum + parseFloat(report.total_sales || 0), 0);
        
        let footer = [{
          category_name: self.$t("Total"),
          total_sales: `${totalGrandTotal.toFixed(2)}`,
          
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
               pdf.text("Sales by Category", 40, 25);   
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

        pdf.save("Sales_by_Category.pdf");
        
      },
  
      //---- update Params Table
      updateParams(newProps) {
        this.serverParams = Object.assign({}, this.serverParams, newProps);
      },
  
      //---- Event Page Change
      onPageChange({ currentPage }) {
        if (this.serverParams.page !== currentPage) {
          this.updateParams({ page: currentPage });
          this.get_sales_by_category(currentPage);
        }
      },
  
      //---- Event Per Page Change
      onPerPageChange({ currentPerPage }) {
        if (this.limit !== currentPerPage) {
          this.limit = currentPerPage;
          this.updateParams({ page: 1, perPage: currentPerPage });
          this.get_sales_by_category(1);
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
        this.get_sales_by_category(this.serverParams.page);
      },
  
      //---- Event on Search
  
      onSearch(value) {
        this.search = value.searchTerm;
        this.get_sales_by_category(this.serverParams.page);
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

      formatPriceWithSymbol(symbol, number, dec) {
        const safeSymbol = symbol || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
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

      // Get reports data from rows[0].children or this.reports
      const reportsData = Array.isArray(this.rows[0]?.children) && this.rows[0].children.length > 0 
        ? this.rows[0].children 
        : (this.reports || []);

      // Manually construct the table HTML from reports data
      let tableHtml = `<table class="vgt-table table table-hover tableOne">`;

      // Table Header
      tableHtml += `<thead><tr>`;
      this.columns.forEach(col => {
        tableHtml += `<th class="text-left">${col.label}</th>`;
      });
      tableHtml += `</tr></thead>`;

      // Table Body
      tableHtml += `<tbody>`;
      reportsData.forEach(row => {
        tableHtml += `<tr>`;
        this.columns.forEach(col => {
          let cellContent = row[col.field];
          if (col.field === 'total_sales') {
            // Format with currency symbol using formatPriceWithSymbol
            cellContent = this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, row.total_sales, 2);
          }
          tableHtml += `<td class="text-left">${cellContent || ''}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalSales = reportsData.reduce((sum, report) => sum + parseFloat(report.total_sales || 0), 0);
      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Total')}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, totalSales, 2)}</td>`;
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Sales_by_Category")}`;
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
  
    //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      const pad = (n) => String(n).padStart(2, "0");
      const formatLocalDate = (d) =>
        `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
      this.startDate = formatLocalDate(new Date(this.dateRange.startDate));
      this.endDate = formatLocalDate(new Date(this.dateRange.endDate));
      this.get_sales_by_category(1);
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
  
      get_sales_by_category(page) {
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        this.get_data_loaded();
        axios
          .get(
            "report/sales_by_category_report?page=" +
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
            this.currency = response.data.currency;
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
      this.get_sales_by_category(1);
    }
  };
  </script>