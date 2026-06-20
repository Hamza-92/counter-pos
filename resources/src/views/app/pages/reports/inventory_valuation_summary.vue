<template>
  <div class="main-content">
    <breadcumb :page="$t('Inventory_Valuation')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-else>
     
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

        <!-- Safe cell rendering (no v-html) -->
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'variant_name'" class="pre">{{ props.row.variant_name }}</span>
          <span v-else-if="props.column.field === 'stock_hand'" class="pre">{{ props.row.stock_hand }}</span>
          <span v-else-if="props.column.field === 'cost'" class="pre">
            {{ formatInventoryValue(props.row.cost) }}
          </span>
          <span v-else-if="props.column.field === 'inventory_value'" class="pre">
            {{ formatInventoryValue(props.row.inventory_value) }}
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
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: { title: "Inventory Valuation Summary" },

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
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    
    columns() {
      return [
        { label: this.$t("ITEM_NAME"), field: "name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("SKU"),       field: "code", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Variant_NAME"), field: "variant_name", tdClass: "text-left pre", thClass: "text-left", sortable: false },
        { label: this.$t("STOCK_ON_HAND"), field: "stock_hand", headerField: this.sumStockHand, tdClass: "text-left pre", thClass: "text-left", sortable: false },
        { label: this.$t("Cost"), field: "cost", tdClass: "text-left pre", thClass: "text-left", sortable: false },
        { label: this.$t("ASSET_VALUE"), field: "inventory_value", headerField: this.sumInventoryValue, tdClass: "text-left pre", thClass: "text-left", sortable: false },
      ];
    }
  },

  methods: {
    // PDF export (handles newline-delimited cells)
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
        { header: this.$t("ITEM_NAME"), dataKey: "name" },
        { header: this.$t("SKU"), dataKey: "code" },
        { header: this.$t("Variant_NAME"), dataKey: "variant_name" },
        { header: this.$t("STOCK_ON_HAND"), dataKey: "stock_hand" },
        { header: this.$t("Cost"), dataKey: "cost" },
        { header: this.$t("ASSET_VALUE"), dataKey: "inventory_value" },
      ];

      const report_pdf = JSON.parse(JSON.stringify(this.reports));

      // totals
      let totalStock = 0;
      let totalValue = 0;

      // compute totals by summing each line split by \n
      report_pdf.forEach(item => {
        const stocks = String(item.stock_hand || '').split('\n').map(v => parseFloat(v || 0));
        const values = String(item.inventory_value || '').split('\n').map(v => parseFloat(v || 0));

        const sumStock = stocks.reduce((s, n) => s + (isNaN(n) ? 0 : n), 0);
        const sumValue = values.reduce((s, n) => s + (isNaN(n) ? 0 : n), 0);

        totalStock += sumStock;
        totalValue += sumValue;
      });

      const footer = [{
        name: this.$t("Total"),
        code: '',
        variant_name: '',
        stock_hand: totalStock.toFixed(2),
        cost: '',
        inventory_value: totalValue.toFixed(2),
      }];

      autoTable(pdf, {
        columns,
        body: report_pdf,
        foot: footer,
        startY: 110,
        theme: 'grid',
        margin: { left: marginX, right: marginX },
        styles: { font: 'Vazirmatn', fontSize: 9, cellPadding: 4, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [26,86,219], textColor: 255 },
        footStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [26,86,219], textColor: 255, halign: rtl ? 'right' : 'left' },
        // ensure numeric columns are right-aligned in all sections (by index)
        columnStyles: { 3: { halign: 'right' }, 4: { halign: 'right' }, 5: { halign: 'right' } },
        didParseCell: (data) => {
          if (data.section === 'foot') {
            if (data.column.index === 3 || data.column.index === 4 || data.column.index === 5) {
              data.cell.styles.halign = 'right';
            } else {
              data.cell.styles.halign = rtl ? 'right' : 'left';
            }
          }
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
          const title = 'Inventory Valuation Summary';
          rtl ? pdf.text(title, pageW - marginX, 38, { align: 'right' })
              : pdf.text(title, marginX, 38);

          // Reset text color
          pdf.setTextColor(33);
          pdf.setFontSize(9);
        },
      });

      pdf.save("Inventory_Valuation_Summary.pdf");
    },

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing behavior to preserve current behavior.
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

    // Format inventory_value which may contain newline-separated values
    formatInventoryValue(value) {
      if (!value) return '';
      const str = String(value);
      const currency = (this.currentUser && this.currentUser.currency) || '';
      // If it contains newlines, format each line separately
      if (str.includes('\n')) {
        return str.split('\n').map(line => {
          const num = parseFloat(line || 0);
          return isNaN(num) ? line : this.formatPriceWithSymbol(currency, num, 2);
        }).join('\n');
      } else {
        const num = parseFloat(value || 0);
        return isNaN(num) ? value : this.formatPriceWithSymbol(currency, num, 2);
      }
    },

    //------ Print Table Only - Print ALL inventory valuation data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Inventory_Valuation")}`;
      const reports = Array.isArray(this.rows[0]?.children) ? this.rows[0].children : [];
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      reports.forEach(item => {
        tableHTML += '<tr>';
        this.columns.forEach(col => {
          let cellValue = '';
          
          if (col.field === 'name') {
            cellValue = item.name || '';
          } else if (col.field === 'code') {
            cellValue = item.code || '';
          } else if (col.field === 'variant_name') {
            // Variant name may contain newlines
            cellValue = (item.variant_name || '').replace(/\n/g, '<br>');
          } else if (col.field === 'stock_hand') {
            // Stock hand may contain newlines
            cellValue = (item.stock_hand || '').replace(/\n/g, '<br>');
          } else if (col.field === 'cost') {
            // Cost may contain newlines - format like inventory_value
            const formattedCost = this.formatInventoryValue(item.cost || '');
            cellValue = formattedCost.replace(/\n/g, '<br>');
          } else if (col.field === 'inventory_value') {
            // Inventory value may contain newlines - format using formatInventoryValue
            const formatted = this.formatInventoryValue(item.inventory_value || '');
            cellValue = formatted.replace(/\n/g, '<br>');
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

    // Group footer helpers for vue-good-table
    // Handle newline-separated values in stock_hand
    sumStockHand(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return '0.00';
      }
      let total = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const stockHand = String(rowObj.children[i].stock_hand || '');
        if (stockHand.includes('\n')) {
          const stocks = stockHand.split('\n').map(v => parseFloat(v || 0));
          const sumStock = stocks.reduce((s, n) => s + (isNaN(n) ? 0 : n), 0);
          total += sumStock;
        } else {
          const value = parseFloat(stockHand || 0);
          if (Number.isFinite(value)) {
            total += value;
          }
        }
      }
      return total.toFixed(2);
    },

    // Handle newline-separated values in inventory_value
    sumInventoryValue(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol((this.currentUser && this.currentUser.currency) || '', 0, 2);
      }
      let total = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const inventoryValue = String(rowObj.children[i].inventory_value || '');
        if (inventoryValue.includes('\n')) {
          const values = inventoryValue.split('\n').map(v => parseFloat(v || 0));
          const sumValue = values.reduce((s, n) => s + (isNaN(n) ? 0 : n), 0);
          total += sumValue;
        } else {
          const value = parseFloat(inventoryValue || 0);
          if (Number.isFinite(value)) {
            total += value;
          }
        }
      }
      const currency = (this.currentUser && this.currentUser.currency) || '';
      return this.formatPriceWithSymbol(currency, total, 2);
    },

    updateParams(newProps) { this.serverParams = Object.assign({}, this.serverParams, newProps); },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Stock_Report(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Stock_Report(1);
      }
    },

    onSortChange(params) {
      this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
      this.Get_Stock_Report(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Stock_Report(this.serverParams.page);
    },

    Selected_Warehouse(value) {
      if (value === null) this.warehouse_id = 0;
      this.Get_Stock_Report(1);
    },

    Get_Stock_Report(page) {
      NProgress.start(); NProgress.set(0.1);
      axios.get(
        "report/inventory_valuation_summary?page=" + page +
        "&SortField=" + encodeURIComponent(this.serverParams.sort.field) +
        "&SortType=" + encodeURIComponent(this.serverParams.sort.type) +
        "&warehouse_id=" + encodeURIComponent(this.warehouse_id) +
        "&search=" + encodeURIComponent(this.search || "") +
        "&limit=" + encodeURIComponent(this.limit)
      )
      .then(response => {
        this.reports    = response.data.reports;
        this.totalRows  = response.data.totalRows;
        this.warehouses = response.data.warehouses;
        this.rows[0].children = this.reports;
        NProgress.done(); this.isLoading = false;
      })
      .catch(() => {
        NProgress.done(); setTimeout(() => { this.isLoading = false; }, 500);
      });
    }
  },

  created() {
    this.Get_Stock_Report(1);
  }
};
</script>

<style scoped>
.pre { white-space: pre-line; }
</style>
