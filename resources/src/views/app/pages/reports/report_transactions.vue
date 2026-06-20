<template>
  <div class="main-content">
    <breadcumb :page="$t('Report_Transactions')" :folder="$t('Reports')"/>

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
        styleClass="table-hover tableOne vgt-table"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'date'">
            {{ formatDisplayDate(props.row.date) }}
          </span>
          <span v-else-if="props.column.field === 'Ref_Sale' && props.row.type === 'sale' && props.row.sale_id">
            <router-link :to="{ name: 'detail_sale', params: { id: props.row.sale_id } }" class="text-primary">
              {{ props.formattedRow[props.column.field] }}
            </router-link>
          </span>
          <span v-else-if="props.column.field === 'Ref_Sale' && props.row.type === 'purchase' && props.row.purchase_id">
            <router-link :to="{ name: 'detail_purchase', params: { id: props.row.purchase_id } }" class="text-primary">
              {{ props.formattedRow[props.column.field] }}
            </router-link>
          </span>
          <span v-else-if="props.column.field == 'montant'">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.montant, 2) }}
          </span>
          <span v-else>{{ props.formattedRow[props.column.field] }}</span>
        </template>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
          <b-button @click="Payment_PDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="payments"
              :columns="columns"
              :file-name="'payments'"
              :file-type="'xlsx'"
              :sheet-name="'payments'"
              >
              <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
        </div>
      </vue-good-table>

       <!-- 🔽 Payment Summary Table Below -->
      <b-card class="mt-4" header="Summary by Payment Method">
          <!-- PDF Button -->
          <div class="mb-3 text-right">
            <b-button @click="Payment_Summary_PDF()" size="sm" variant="outline-primary ripple">
              <lucide-icon name="copy" /> Summary PDF
            </b-button>
          </div>

        <b-table striped hover small :items="payment_summary" :fields="[
          { key: 'payment_method', label: 'Payment Method' },
          { key: 'sale_total', label: 'Total Sales' },
          { key: 'sale_return_total', label: 'Sale Refunds' },
          { key: 'purchase_total', label: 'Total Purchases' },
          { key: 'purchase_return_total', label: 'Purchase Refunds' },
          { key: 'expense_total', label: 'Total Expenses' }
        ]" responsive>
          <template #cell(sale_total)="data">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, data.item.sale_total, 2) }}
          </template>
          <template #cell(sale_return_total)="data">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, data.item.sale_return_total || 0, 2) }}
          </template>
          <template #cell(purchase_total)="data">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, data.item.purchase_total, 2) }}
          </template>
          <template #cell(purchase_return_total)="data">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, data.item.purchase_return_total || 0, 2) }}
          </template>
          <template #cell(expense_total)="data">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, data.item.expense_total, 2) }}
          </template>
        </b-table>
      </b-card>
    </b-card>



    <!-- Sidebar Filter -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
         
          <!-- Customers  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_client"
                :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Sale  -->
          <b-col md="12">
            <b-form-group :label="$t('Sale')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('PleaseSelect')"
                v-model="Filter_sale"
                :options="sales.map(sales => ({label: sales.Ref, value: sales.id}))"
              />
            </b-form-group>
          </b-col>


           <!-- Supplier  -->
           <b-col md="12">
            <b-form-group :label="$t('Supplier')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Supplier')"
                v-model="Filter_provider"
                :options="suppliers.map(suppliers => ({label: suppliers.name, value: suppliers.id}))"
              />
            </b-form-group>
          </b-col>

       
           <!-- Purchase  -->
           <b-col md="12">
            <b-form-group :label="$t('Purchase')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('PleaseSelect')"
                v-model="Filter_purchase"
                :options="purchases.map(purchases => ({label: purchases.Ref, value: purchases.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Payment choice -->
          <b-col md="12">
            <b-form-group :label="$t('Paymentchoice')">
              <v-select
                v-model="Filter_Reg"
                :reduce="label => label.value"
                :placeholder="$t('PleaseSelect')"
                :options="payment_methods.map(payment_methods => ({label: payment_methods.name, value: payment_methods.id}))"

              ></v-select>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Payments_Sales(serverParams.page)"
              variant="primary ripple m-1"
              size="sm"
              block
            >
              <lucide-icon name="filter" />
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple m-1" size="sm" block>
              <lucide-icon name="power" />
              {{ $t("Reset") }}
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
import DateRangePicker from 'vue2-daterange-picker'
//you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'
import Util from '../../../../utils'
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: {
    title: "Report Transactions"
  },
  components: { DateRangePicker },

  data() {
    return {
      isLoading: true,
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
      Filter_client: "",
      Filter_sale: "",
      Filter_provider: "",
      Filter_purchase: "",
      Filter_Reg: "",
      payments: [],
      payment_methods:[],
      clients: [],
      suppliers: [],
      payment_summary: [],
      rows: [{
        payment_method: 'Total',
         
          children: [
             
          ],
      },],
      sales: [],
      purchases: [],
      today_mode: true,
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
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns() {
      return [
        {
          label: this.$t("Date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Sale_Purchase_Ref"),
          field: "Ref_Sale",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer_Provider"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Payment_Method"),
          field: "payment_method",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Account"),
          field: "account_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Amount"),
          field: "montant",
          // Let headerField return a formatted string; avoid vue-good-table's decimal re-formatting.
          headerField: this.sumCount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("AddedBy"),
          field: "user_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
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
        const value = Number(rowObj.children[i].montant) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Payments_Sales(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Payments_Sales(1);
      }
    },

    //---- Event on Sort Change
    onSortChange(params) {
      let field = "";
      if (params[0].field == "Ref_Sale") {
        field = "sale_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Payments_Sales(this.serverParams.page);
    },
    //----------------------------------------- Format Display Date (for tables) -------------------------------\\
    formatDisplayDate(value) {
      if (!value) return '';
      // Get date format from Vuex store (loaded from database) or fallback
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
      return Util.formatDisplayDate(value, dateFormat);
    },

    // Same as dashboard: format date for picker display (YYYY-MM-DD, local time via moment)
    fmt(d) {
      return moment(d).format("YYYY-MM-DD");
    },

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing behavior to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const n = Number(number || 0);
        if (isNaN(n)) {
          const n2 = Number(number || 0);
          return n2.toLocaleString(undefined, { maximumFractionDigits: dec || 2 });
        }
        
        const decimals = Number.isInteger(dec) ? dec : (dec ? parseInt(dec) : 2);
        // Always check store directly to ensure we get the latest value
        const key = getPriceFormatSetting({ store: this.$store });
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
      try {
        const safeSymbol = symbol || (this.currentUser && this.currentUser.currency) || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      } catch (e) {
        const safeSymbol = symbol || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      }
    },

    //---- Event on Search

    onSearch(value) {
      this.search = value.searchTerm;
      this.Payments_Sales(this.serverParams.page);
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_client = "";
      this.Filter_sale = "";
      this.Filter_provider = "";
      this.Filter_purchase = "";
      this.Filter_Reg = "";
      this.Payments_Sales(this.serverParams.page);
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
          if (col.field === 'date') {
            cellContent = this.formatDisplayDate(row.date);
          } else if (col.field === 'montant') {
            cellContent = this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, row.montant, 2);
          } else if (col.field === 'Ref_Sale') {
            // Just the ref text, not the router-link
            cellContent = row.Ref_Sale || '';
          }
          tableHtml += `<td class="text-left">${cellContent || ''}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalAmount = this.sumCount(this.rows[0]);
      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Total')}</td>`;
      tableHtml += `<td colspan="6"></td>`; // Span for other columns
      tableHtml += `<td class="text-left font-weight-bold">${totalAmount}</td>`;
      tableHtml += `<td colspan="1"></td>`; // Span for AddedBy column
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Report_Transactions")}`;
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

    //---------------------------------------- Set To Strings-------------------------\\
    setToStrings() {
      // Simply replaces null values with strings=''
      if (this.Filter_client === null) {
        this.Filter_client = "";
      } else if (this.Filter_sale === null) {
        this.Filter_sale = "";
      } else if (this.Filter_purchase === null) {
        this.Filter_purchase = "";
      } else if (this.Filter_provider === null) {
        this.Filter_provider = "";
      }
    },

    Payment_PDF() {
      const pdf = new jsPDF("p", "pt");

      // Use custom font
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold");
      pdf.setFont("VazirmatnBold");

      const columns = [
        { header: "Date", dataKey: "date" },
        { header: "Reference", dataKey: "Ref" },
        { header: "Sale / Purchase Ref", dataKey: "Ref_Sale" },
        { header: "Customer / Provider", dataKey: "client_name" },
        { header: "Payment Method", dataKey: "payment_method" },
        { header: "Account", dataKey: "account_name" },
        { header: "Amount", dataKey: "montant" },
        { header: "Added By", dataKey: "user_name" }
      ];

      // Calculate total amount
      const totalGrandTotal = this.payments.reduce(
        (sum, payment) => sum + parseFloat(payment.montant || 0),
        0
      );

      const footer = [{
        date: "Total",
        Ref: '',
        Ref_Sale: '',
        client_name: '',
        payment_method: '',
        account_name: '',
        montant: `${totalGrandTotal.toFixed(2)}`,
        user_name: ''
      }];

      autoTable(pdf, {
        columns: columns,
        body: this.payments,
        foot: footer,
        startY: 70,
        theme: "grid",
        didDrawPage: (data) => {
          pdf.setFont("VazirmatnBold");
          pdf.setFontSize(18);
          pdf.text("Report Transactions", 40, 25);
        },
        styles: {
          font: "VazirmatnBold",
          halign: "center"
        },
        headStyles: {
          fillColor: [26, 86, 219],
          textColor: 255,
          fontStyle: "bold"
        },
        footStyles: {
          fillColor: [26, 86, 219],
          textColor: 255,
          fontStyle: "bold"
        }
      });

      pdf.save("Report_Transactions.pdf");
    },


    Payment_Summary_PDF() {
      const pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold");
      pdf.setFont("VazirmatnBold");

      const summaryColumns = [
        { header: "Payment Method", dataKey: "payment_method" },
        { header: "Total Sales", dataKey: "sale_total" },
        { header: "Total Purchases", dataKey: "purchase_total" },
        { header: "Total Expenses", dataKey: "expense_total" }
      ];

      const summaryBody = this.payment_summary.map(item => ({
        payment_method: item.payment_method,
        sale_total: item.sale_total.toFixed(2),
        purchase_total: item.purchase_total.toFixed(2),
        expense_total: item.expense_total.toFixed(2)
      }));

      autoTable(pdf, {
        columns: summaryColumns,
        body: summaryBody,
        startY: 70,
        theme: "grid",
        didDrawPage: () => {
          pdf.setFontSize(18);
          pdf.text("Payment Summary Report", 40, 25);
        },
        styles: {
          font: "VazirmatnBold",
          halign: "center"
        },
        headStyles: {
          fillColor: [26, 86, 219],
          textColor: 255,
          fontStyle: "bold"
        }
      });

      pdf.save("Payment_Summary_Report.pdf");
    },


     //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      const pad = (n) => String(n).padStart(2, "0");
      const formatLocalDate = (d) =>
        `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
      this.startDate = formatLocalDate(new Date(this.dateRange.startDate));
      this.endDate = formatLocalDate(new Date(this.dateRange.endDate));
      this.Payments_Sales(1);
    },


    get_data_loaded() {
      const self = this;
      if (self.today_mode) {
        const pad = (n) => String(n).padStart(2, "0");
        const today = new Date();
        const todayStr = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
        const startOfDay = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const endOfDay = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 23, 59, 59, 999);

        self.startDate = todayStr;
        self.endDate = todayStr;
        self.dateRange.startDate = startOfDay;
        self.dateRange.endDate = endOfDay;
      }
    },

    //-------------------------------- Get All Payments Sales ---------------------\\
    Payments_Sales(page) {
      // Start the progress bar
      NProgress.start();
      NProgress.set(0.1);

      // Mark loading
      this.isLoading = true;
      this.get_data_loaded();

      axios
        .get("report/report_transactions", {
          params: {
            page: page,
            client_id: this.Filter_client,
            sale_id: this.Filter_sale,
            provider_id: this.Filter_provider,      // <-- added
            purchase_id: this.Filter_purchase,      // <-- added
            payment_method_id: this.Filter_Reg,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.limit,
            to: this.endDate,
            from: this.startDate,
          }
        })
        .then(response => {
          this.payments = response.data.payments;
          this.clients = response.data.clients;
          this.suppliers = response.data.suppliers;
          this.sales = response.data.sales;
          this.purchases = response.data.purchases;
          this.payment_methods = response.data.payment_methods;
          this.payment_summary = response.data.payment_summary;
          this.totalRows = response.data.totalRows;

          // if using a tree-table or nested row grouping
          if (this.rows && this.rows[0]) {
            this.rows[0].children = this.payments;
          }

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(error => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    }

  },

  //----------------------------- Created function-------------------\\
  created: function() {
    // Initialize price format key from Vuex store (get_user_auth API)
    try {
      const key = getPriceFormatSetting({ store: this.$store });
      if (key) {
        this.price_format_key = key;
      }
    } catch (e) {
      // ignore
    }
    this.Payments_Sales(1);
  }
};
</script>

<style>
@media print {
  /* Hide everything by default */
  body * {
    visibility: hidden !important;
  }

  /* Show only main-content and all its children */
  .main-content,
  .main-content * {
    visibility: visible !important;
  }

  /* Keep main-content with original positioning and styling */
  .main-content {
    position: static !important;
    width: 100% !important;
  }

  /* Hide only UI elements that shouldn't be printed - keep original design for everything else */
  .main-content .breadcumb,
  .main-content .loading_page,
  .main-content .spinner,
  .main-content button,
  .main-content .sidebar,
  .main-content #sidebar-right,
  .main-content .vgt-global-search,
  .main-content .vgt-pagination,
  .main-content .vgt-table-actions,
  .main-content .date-range-picker,
  .main-content .daterangepicker,
  .main-content .text-center {
    display: none !important;
    visibility: hidden !important;
  }

  /* Preserve original design for wrapper and cards */
  .main-content .wrapper,
  .main-content .b-card,
  .main-content .card {
    /* Keep original styling - don't override */
    display: block !important;
    visibility: visible !important;
  }

  /* Preserve vue-good-table original design */
  .main-content .vue-good-table,
  .main-content .vgt-table,
  .main-content .vgt-table-wrapper {
    display: block !important;
    visibility: visible !important;
    /* Keep original overflow behavior */
  }

  /* Preserve table original design */
  .main-content table,
  .main-content .vgt-table table,
  .main-content .table {
    display: table !important;
    visibility: visible !important;
    /* Keep original table styling */
  }

  /* Ensure table header is visible with original styling */
  .main-content thead,
  .main-content .vgt-table thead,
  .main-content .vgt-header-row {
    display: table-header-group !important;
    visibility: visible !important;
  }

  /* Ensure table body and rows are visible with original styling */
  .main-content tbody,
  .main-content .vgt-table tbody,
  .main-content .vgt-body {
    display: table-row-group !important;
    visibility: visible !important;
  }

  .main-content tbody tr,
  .main-content .vgt-table tbody tr,
  .main-content .vgt-row,
  .main-content .table tbody tr {
    display: table-row !important;
    visibility: visible !important;
  }

  /* Keep original table cells styling - don't override */
  .main-content th,
  .main-content td,
  .main-content .vgt-table th,
  .main-content .vgt-table td,
  .main-content .table th,
  .main-content .table td {
    display: table-cell !important;
    visibility: visible !important;
    /* Keep original padding, font-size, colors etc. */
  }

  /* Page setup - reasonable margins */
  body {
    margin: 0 !important;
  }

  @page {
    size: A4 landscape;
    margin: 1cm;
  }
}
</style>