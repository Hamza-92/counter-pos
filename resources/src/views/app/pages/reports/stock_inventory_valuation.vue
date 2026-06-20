<template>
  <div class="main-content">
    <breadcumb :page="$t('Stock_Inventory_Valuation')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="mb-3 text-center">
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
    </div>

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
        :search-options="{ placeholder: $t('Search_this_table'), enabled: true }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
        styleClass="tableOne table-hover vgt-table mt-3"
      >

        <!-- Filters -->
        <div slot="table-actions" class="mt-2 mb-3 quantity_alert_warehouse">
          <b-form-group :label="$t('warehouse')">
            <v-select
              @input="Selected_Warehouse"
              v-model="warehouse_id"
              :reduce="label => label.value"
              :placeholder="$t('Choose_Warehouse')"
              :options="[
                { label: $t('All_Warehouses'), value: 0 },
                ...warehouses.map(w => ({ label: w.name, value: w.id }))
              ]"
            />
          </b-form-group>
        </div>

        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
          <b-button @click="stock_report_PDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
        </div>

        <!-- Custom cell rendering -->
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'selling_price'">
            {{ formatPrice(props.row.selling_price) }}
          </span>
          <span v-else-if="props.column.field === 'current_quantity'">
            {{ formatQuantity(props.row.current_quantity, props.row.current_quantity_unit) }}
          </span>
          <span v-else-if="props.column.field === 'stock_value_cost'">
            {{ formatPrice(props.row.stock_value_cost) }}
          </span>
          <span v-else-if="props.column.field === 'stock_value_selling'">
            {{ formatPrice(props.row.stock_value_selling) }}
          </span>
          <span v-else-if="props.column.field === 'potential_profit'">
            {{ formatPrice(props.row.potential_profit) }}
          </span>
          <span v-else-if="props.column.field === 'total_units_sold'">
            {{ formatQuantity(props.row.total_units_sold, props.row.total_units_sold_unit) }}
          </span>
          <span v-else-if="props.column.field === 'total_units_transferred'">
            {{ formatQuantity(props.row.total_units_transferred, props.row.total_units_transferred_unit) }}
          </span>
          <span v-else-if="props.column.field === 'total_units_adjusted'">
            {{ formatQuantity(props.row.total_units_adjusted, props.row.total_units_adjusted_unit) }}
          </span>
          <span v-else>
            {{ props.formattedRow[props.column.field] }}
          </span>
        </template>

      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import DateRangePicker from 'vue2-daterange-picker'
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: { title: "Stock Inventory Valuation Report" },
  components: { DateRangePicker },

  data() {
    return {
      isLoading: true,
      serverParams: { sort: { field: "id", type: "desc" }, page: 1, perPage: 10 },
      limit: "10",
      search: "",
      totalRows: "",
      reports: [],
      rows: [{
        statut: '',
        children: [],
      }],
      warehouses: [],
      warehouse_id: 0,
      price_format_key: null,
      startDate: "",
      endDate: "",
      dateRange: {
        startDate: "",
        endDate: ""
      },
      locale: {
        Label: "Apply",
        cancelLabel: "Cancel",
        weekLabel: "W",
        customRangeLabel: "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      }
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    
    columns() {
      return [
        { label: this.$t("SKU"), field: "sku", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("product_name"), field: "product_name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Variant"), field: "variant", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Category"), field: "category", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Warehouse"), field: "warehouse", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Selling_Price_Unit"), field: "selling_price", headerField: this.sumSellingPrice, tdClass: "text-right", thClass: "text-right", sortable: false },
        { label: this.$t("Current_Quantity"), field: "current_quantity", headerField: this.sumCurrentQuantity, tdClass: "text-right", thClass: "text-right", sortable: false },
        { label: this.$t("Stock_Value_Cost"), field: "stock_value_cost", headerField: this.sumStockValueCost, tdClass: "text-right", thClass: "text-right", sortable: false },
        { label: this.$t("Stock_Value_Selling"), field: "stock_value_selling", headerField: this.sumStockValueSelling, tdClass: "text-right", thClass: "text-right", sortable: false },
        { label: this.$t("Potential_Profit"), field: "potential_profit", headerField: this.sumPotentialProfit, tdClass: "text-right", thClass: "text-right", sortable: false },
        { label: this.$t("Total_Units_Sold"), field: "total_units_sold", headerField: this.sumTotalUnitsSold, tdClass: "text-right", thClass: "text-right", sortable: false },
        { label: this.$t("Total_Units_Transferred"), field: "total_units_transferred", headerField: this.sumTotalUnitsTransferred, tdClass: "text-right", thClass: "text-right", sortable: false },
        { label: this.$t("Total_Units_Adjusted"), field: "total_units_adjusted", headerField: this.sumTotalUnitsAdjusted, tdClass: "text-right", thClass: "text-right", sortable: false },
      ];
    },

    totals() {
      return this.reports.reduce((acc, row) => {
        acc.current_quantity += parseFloat(row.current_quantity || 0);
        acc.stock_value_cost += parseFloat(row.stock_value_cost || 0);
        acc.stock_value_selling += parseFloat(row.stock_value_selling || 0);
        acc.potential_profit += parseFloat(row.potential_profit || 0);
        acc.total_units_sold += parseFloat(row.total_units_sold || 0);
        acc.total_units_transferred += parseFloat(row.total_units_transferred || 0);
        acc.total_units_adjusted += parseFloat(row.total_units_adjusted || 0);
        return acc;
      }, {
        current_quantity: 0,
        stock_value_cost: 0,
        stock_value_selling: 0,
        potential_profit: 0,
        total_units_sold: 0,
        total_units_transferred: 0,
        total_units_adjusted: 0
      });
    }
  },

  methods: {
    // Price formatting
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

    formatPrice(number) {
      const currency = (this.currentUser && this.currentUser.currency) || '';
      const value = this.formatPriceDisplay(number, 2);
      return currency ? `${currency} ${value}` : value;
    },

    formatQuantity(number, unit) {
      const qty = parseFloat(number || 0);
      const unitLabel = unit || 'Pcs';
      return `${qty.toFixed(3)} ${unitLabel}`;
    },

    // Same as dashboard: format date for picker display (YYYY-MM-DD, local time via moment)
    fmt(d) {
      return moment(d).format('YYYY-MM-DD');
    },

    // Group footer helpers for vue-good-table
    sumSellingPrice(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPrice(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].selling_price) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPrice(sum);
    },

    sumCurrentQuantity(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatQuantity(0, 'Pcs');
      }
      let sum = 0;
      let unit = 'Pcs';
      if (rowObj.children.length > 0 && rowObj.children[0].current_quantity_unit) {
        unit = rowObj.children[0].current_quantity_unit;
      }
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].current_quantity) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatQuantity(sum, unit);
    },

    sumStockValueCost(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPrice(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].stock_value_cost) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPrice(sum);
    },

    sumStockValueSelling(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPrice(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].stock_value_selling) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPrice(sum);
    },

    sumPotentialProfit(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPrice(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].potential_profit) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPrice(sum);
    },

    sumTotalUnitsSold(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatQuantity(0, 'Pcs');
      }
      let sum = 0;
      let unit = 'Pcs';
      if (rowObj.children.length > 0 && rowObj.children[0].total_units_sold_unit) {
        unit = rowObj.children[0].total_units_sold_unit;
      }
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total_units_sold) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatQuantity(sum, unit);
    },

    sumTotalUnitsTransferred(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatQuantity(0, 'Pcs');
      }
      let sum = 0;
      let unit = 'Pcs';
      if (rowObj.children.length > 0 && rowObj.children[0].total_units_transferred_unit) {
        unit = rowObj.children[0].total_units_transferred_unit;
      }
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total_units_transferred) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatQuantity(sum, unit);
    },

    sumTotalUnitsAdjusted(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatQuantity(0, 'Pcs');
      }
      let sum = 0;
      let unit = 'Pcs';
      if (rowObj.children.length > 0 && rowObj.children[0].total_units_adjusted_unit) {
        unit = rowObj.children[0].total_units_adjusted_unit;
      }
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total_units_adjusted) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatQuantity(sum, unit);
    },

    // PDF export
    stock_report_PDF() {
      const pdf = new jsPDF("p", "pt");
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        pdf.addFont(fontPath, "Vazirmatn", "normal");
        pdf.addFont(fontPath, "Vazirmatn", "bold");
      } catch(e) { /* ignore if already added */ }
      pdf.setFont("Vazirmatn", "normal");

      const marginX = 40;
      const rtl =
        (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      const columns = [
        { header: this.$t("SKU"), dataKey: "sku" },
        { header: this.$t("product_name"), dataKey: "product_name" },
        { header: this.$t("Variant"), dataKey: "variant" },
        { header: this.$t("Category"), dataKey: "category" },
        { header: this.$t("Warehouse"), dataKey: "warehouse" },
        { header: this.$t("Selling_Price_Unit"), dataKey: "selling_price" },
        { header: this.$t("Current_Quantity"), dataKey: "current_quantity" },
        { header: this.$t("Stock_Value_Cost"), dataKey: "stock_value_cost" },
        { header: this.$t("Stock_Value_Selling"), dataKey: "stock_value_selling" },
        { header: this.$t("Potential_Profit"), dataKey: "potential_profit" },
        { header: this.$t("Total_Units_Sold"), dataKey: "total_units_sold" },
        { header: this.$t("Total_Units_Transferred"), dataKey: "total_units_transferred" },
        { header: this.$t("Total_Units_Adjusted"), dataKey: "total_units_adjusted" },
      ];

      const report_pdf = JSON.parse(JSON.stringify(this.reports));
      const currency = (this.currentUser && this.currentUser.currency) || '';

      // Format data for PDF
      report_pdf.forEach(item => {
        item.selling_price = this.formatPriceDisplay(item.selling_price, 2);
        const currentQtyUnit = item.current_quantity_unit || 'Pcs';
        item.current_quantity = `${parseFloat(item.current_quantity || 0).toFixed(3)} ${currentQtyUnit}`;
        item.stock_value_cost = this.formatPriceDisplay(item.stock_value_cost, 2);
        item.stock_value_selling = this.formatPriceDisplay(item.stock_value_selling, 2);
        item.potential_profit = this.formatPriceDisplay(item.potential_profit, 2);
        const soldUnit = item.total_units_sold_unit || 'Pcs';
        item.total_units_sold = `${parseFloat(item.total_units_sold || 0).toFixed(3)} ${soldUnit}`;
        const transferredUnit = item.total_units_transferred_unit || 'Pcs';
        item.total_units_transferred = `${parseFloat(item.total_units_transferred || 0).toFixed(3)} ${transferredUnit}`;
        const adjustedUnit = item.total_units_adjusted_unit || 'Pcs';
        item.total_units_adjusted = `${parseFloat(item.total_units_adjusted || 0).toFixed(3)} ${adjustedUnit}`;
      });

      // Footer totals
      const footer = [{
        sku: this.$t("Total"),
        product_name: '',
        variant: '',
        category: '',
        warehouse: '',
        selling_price: '',
        current_quantity: `${this.totals.current_quantity.toFixed(3)} Pc(s)`,
        stock_value_cost: this.formatPriceDisplay(this.totals.stock_value_cost, 2),
        stock_value_selling: this.formatPriceDisplay(this.totals.stock_value_selling, 2),
        potential_profit: this.formatPriceDisplay(this.totals.potential_profit, 2),
        total_units_sold: `${this.totals.total_units_sold.toFixed(3)} Pc(s)`,
        total_units_transferred: `${this.totals.total_units_transferred.toFixed(3)} Pc(s)`,
        total_units_adjusted: `${this.totals.total_units_adjusted.toFixed(3)} Pc(s)`,
      }];

      autoTable(pdf, {
        columns,
        body: report_pdf,
        foot: footer,
        startY: 110,
        theme: 'grid',
        margin: { left: marginX, right: marginX },
        styles: { font: 'Vazirmatn', fontSize: 7, cellPadding: 3, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [26,86,219], textColor: 255, fontSize: 8 },
        footStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [26,86,219], textColor: 255, halign: rtl ? 'right' : 'left' },
        columnStyles: { 
          5: { halign: 'right' }, 
          6: { halign: 'right' }, 
          7: { halign: 'right' }, 
          8: { halign: 'right' }, 
          9: { halign: 'right' }, 
          10: { halign: 'right' }, 
          11: { halign: 'right' }, 
          12: { halign: 'right' } 
        },
        didDrawPage: (d) => {
          const pageW = pdf.internal.pageSize.getWidth();
          const pageH = pdf.internal.pageSize.getHeight();

          // Header banner
          pdf.setFillColor(26,86,219);
          pdf.rect(0, 0, pageW, 60, 'F');

          // Title
          pdf.setTextColor(255);
          pdf.setFont('Vazirmatn', 'bold');
          pdf.setFontSize(16);
          const title = 'Stock Inventory Valuation Report';
          rtl ? pdf.text(title, pageW - marginX, 38, { align: 'right' })
              : pdf.text(title, marginX, 38);

          // Reset text color
          pdf.setTextColor(33);
          pdf.setFontSize(9);
        },
      });

      pdf.save("Stock_Inventory_Valuation_Report.pdf");
    },

    updateParams(newProps) { 
      this.serverParams = Object.assign({}, this.serverParams, newProps); 
    },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Stock_Inventory_Valuation(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Stock_Inventory_Valuation(1);
      }
    },

    onSortChange(params) {
      this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
      this.Get_Stock_Inventory_Valuation(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Stock_Inventory_Valuation(this.serverParams.page);
    },

    Selected_Warehouse(value) {
      if (value === null) this.warehouse_id = 0;
      this.Get_Stock_Inventory_Valuation(1);
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
          // Format price fields
          if (['selling_price', 'stock_value_cost', 'stock_value_selling', 'potential_profit'].includes(col.field)) {
            cellContent = this.formatPrice(row[col.field]);
          } 
          // Format quantity fields
          else if (col.field === 'current_quantity') {
            cellContent = this.formatQuantity(row.current_quantity, row.current_quantity_unit);
          }
          else if (col.field === 'total_units_sold') {
            cellContent = this.formatQuantity(row.total_units_sold, row.total_units_sold_unit);
          }
          else if (col.field === 'total_units_transferred') {
            cellContent = this.formatQuantity(row.total_units_transferred, row.total_units_transferred_unit);
          }
          else if (col.field === 'total_units_adjusted') {
            cellContent = this.formatQuantity(row.total_units_adjusted, row.total_units_adjusted_unit);
          }
          tableHtml += `<td class="text-left">${cellContent || ''}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalSellingPrice = this.sumSellingPrice(this.rows[0]);
      const totalCurrentQuantity = this.sumCurrentQuantity(this.rows[0]);
      const totalStockValueCost = this.sumStockValueCost(this.rows[0]);
      const totalStockValueSelling = this.sumStockValueSelling(this.rows[0]);
      const totalPotentialProfit = this.sumPotentialProfit(this.rows[0]);
      const totalUnitsSold = this.sumTotalUnitsSold(this.rows[0]);
      const totalUnitsTransferred = this.sumTotalUnitsTransferred(this.rows[0]);
      const totalUnitsAdjusted = this.sumTotalUnitsAdjusted(this.rows[0]);

      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Total')}</td>`;
      tableHtml += `<td colspan="4"></td>`; // Span for Product_Name, Variant, Category, Warehouse
      tableHtml += `<td class="text-left font-weight-bold">${totalSellingPrice}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalCurrentQuantity}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalStockValueCost}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalStockValueSelling}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalPotentialProfit}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalUnitsSold}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalUnitsTransferred}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${totalUnitsAdjusted}</td>`;
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Stock_Inventory_Valuation")}`;
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
      @media print { 
        body, body * { visibility: visible !important; }
        @page { size: A4 landscape; margin: 0.3cm; }
      }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 8px; }
      table { width: 100%; border-collapse: collapse; font-size: 9px; }
      th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
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

    Submit_filter_dateRange() {
      const s = moment(this.dateRange.startDate);
      const e = moment(this.dateRange.endDate);
      this.startDate = s.format("YYYY-MM-DD");
      this.endDate = e.format("YYYY-MM-DD");
      this.Get_Stock_Inventory_Valuation(1);
    },

    Get_Stock_Inventory_Valuation(page) {
      NProgress.start(); 
      NProgress.set(0.1);
      axios.get(
        "report/stock_inventory_valuation?page=" + page +
        "&SortField=" + encodeURIComponent(this.serverParams.sort.field) +
        "&SortType=" + encodeURIComponent(this.serverParams.sort.type) +
        "&warehouse_id=" + encodeURIComponent(this.warehouse_id) +
        "&search=" + encodeURIComponent(this.search || "") +
        "&limit=" + encodeURIComponent(this.limit) +
        "&date_from=" + encodeURIComponent(this.startDate || "") +
        "&date_to=" + encodeURIComponent(this.endDate || "")
      )
      .then(response => {
        this.reports    = response.data.reports;
        this.totalRows  = response.data.totalRows;
        this.warehouses = response.data.warehouses;
        this.rows[0].children = this.reports;
        NProgress.done(); 
        this.isLoading = false;
      })
      .catch(() => {
        NProgress.done(); 
        setTimeout(() => { this.isLoading = false; }, 500);
      });
    }
  },

  created() {
    // Initialize date range to last 30 days
    const end = moment().endOf("day");
    const start = moment().subtract(29, "days").startOf("day");
    this.startDate = start.format("YYYY-MM-DD");
    this.endDate = end.format("YYYY-MM-DD");
    this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
    
    this.Get_Stock_Inventory_Valuation(1);
  }
};
</script>

<style scoped>
.pre { white-space: pre-line; }
</style>