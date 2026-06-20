<template>
  <div class="main-content">
    <breadcumb :page="$t('Zero_Sales_Products_Report')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper print-table-only" v-if="!isLoading">
      <!-- Toolbar -->
      <div class="d-flex flex-wrap align-items-center mb-2">
        <!-- Period filter -->
        <div class="d-flex align-items-center mr-3 mb-2">
          <label class="mb-0 mr-2">{{ $t('Period') }}:</label>
          <b-form-select
            v-model="period"
            :options="periodOptions"
            size="sm"
            class="w-auto"
            @change="onPeriodChange"
          />
        </div>

        <div class="ml-auto mb-2">
          <b-button size="sm" variant="outline-secondary" class="btn-pill mr-2" @click="printTableOnly()">
            <lucide-icon class="mr-1" name="printer" />{{$t('print')}}
          </b-button>
          <b-button size="sm" variant="danger" class="btn-pill" @click="exportPdf">
            <lucide-icon class="mr-1" name="file-text" /> Export PDF
          </b-button>
        </div>
      </div>

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
        :pagination-options="{
          enabled: true,
          mode: 'records',
          perPage: serverParams.perPage,
          perPageDropdown: [10, 20, 50, 100],
          dropdownAllowAll: true,
          nextLabel: 'next',
          prevLabel: 'prev'
        }"
        styleClass="tableOne table-hover vgt-table mt-3"
      >
        <template slot="table-row" slot-scope="props">
          <!-- last sale (ever) -->
          <span v-if="props.column.field === 'last_sale_at'">
            {{ props.row.last_sale_at ? props.row.last_sale_at : '—' }}
          </span>

          <!-- days since last sale / never sold badge -->
          <span v-else-if="props.column.field === 'days_since_last_sale'">
            <b-badge variant="warning" v-if="!props.row.last_sale_at">
              {{ $t('Never_Sold') }}
            </b-badge>
            <span v-else>{{ props.row.days_since_last_sale }}</span>
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
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";
// axios assumed globally available

export default {
  metaInfo: { title: "Zero Sales Products Report" },
  data() {
    return {
      isLoading: true,

      serverParams: {
        sort: { field: "last_sale_at", type: "asc" }, // NULL last first (handled server-side)
        page: 1,
        perPage: 10,
      },

      limit: 10,
      search: "",
      totalRows: 0,
      products: [],
      rows: [{ statut: '', children: [] }],

      // filters
      period: 30,
      periodOptions: [
        { value: 'all', text: this.$t('All_time') || 'All time' }, // "never sold ever"
        { value: 30,    text: this.$t('Last_30_days') || 'Last 30 days' },
        { value: 60,    text: this.$t('Last_60_days') || 'Last 60 days' },
        { value: 90,    text: this.$t('Last_90_days') || 'Last 90 days' },
      ],

      // optional extra filters (wire later if needed)
      warehouse_id: null,
      brand_id: null,
      category_id: null,
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns() {
      return [
        { label: this.$t("Code"),   field: "code",   tdClass: "text-left", thClass: "text-left", sortable: true },
        { label: this.$t("Product"),field: "name",   tdClass: "text-left", thClass: "text-left", sortable: true },
        { label: this.$t("Price"),  field: "price",  headerField: this.sumPrice, tdClass: "text-left", thClass: "text-left", sortable: true },
        { label: this.$t("LastSale"), field: "last_sale_at", tdClass: "text-left", thClass: "text-left", sortable: true },
        { label: this.$t("DaysSinceLastSale"), field: "days_since_last_sale", type: "number", tdClass: "text-left", thClass: "text-left", sortable: true },
      ];
    },
  },

  methods: {
    // ---- table param helpers
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Zero_Sales_Products(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Zero_Sales_Products(1);
      }
    },

    onSortChange(params) {
      const p = (Array.isArray(params) && params.length) ? params[0] : null;
      if (!p) return;
      this.updateParams({ sort: { type: p.type, field: p.field } });
      this.Get_Zero_Sales_Products(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm || "";
      this.updateParams({ page: 1 });
      this.Get_Zero_Sales_Products(1);
    },

    onPeriodChange() {
      this.updateParams({ page: 1 });
      this.Get_Zero_Sales_Products(1);
    },

    formatPriceDisplay(number, decimals = 2) {
      return formatPriceDisplayHelper(
        number,
        decimals,
        getPriceFormatSetting(this.currentUser)
      );
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    // Group footer helpers for vue-good-table
    sumPrice(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, 0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].price) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },

    //--------------------------- Fetch -------------------------------\\
    Get_Zero_Sales_Products(page) {
      NProgress.start();
      NProgress.set(0.1);

      const qp = new URLSearchParams({
        page: String(page),
        SortField: this.serverParams.sort.field || 'last_sale_at',
        SortType: this.serverParams.sort.type || 'asc',
        search: this.search || '',
        limit: String(this.limit),
        period: String(this.period),
      });

      if (this.warehouse_id) qp.append('warehouse_id', this.warehouse_id);
      if (this.brand_id)     qp.append('brand_id', this.brand_id);
      if (this.category_id)  qp.append('category_id', this.category_id);

      axios.get(`report/zero_sales_products?${qp.toString()}`)
        .then(({ data }) => {
          this.products = data.report || [];
          this.totalRows = data.totalRows || 0;
          this.rows[0].children = this.products;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => { this.isLoading = false; }, 300);
        });
    },

    // ------------------------ Export PDF (NO i18n calls) ------------------------ \\
    async exportPdf () {
      try {
        NProgress.start(); NProgress.set(0.2);

        // Lazy-load libs (keeps your current pattern for this page)
        const { jsPDF } = await import('jspdf');
        const autoTable = (await import('jspdf-autotable')).default;

        // --- gather current filters & fetch ALL rows (limit=-1)
        const qp = new URLSearchParams({
          page: '1',
          limit: '-1',
          SortField: this.serverParams?.sort?.field || 'last_sale_at',
          SortType:  this.serverParams?.sort?.type  || 'asc',
          search: this.search || '',
          period: String(this.period),
        });
        if (this.warehouse_id) qp.append('warehouse_id', this.warehouse_id);
        if (this.brand_id)     qp.append('brand_id', this.brand_id);
        if (this.category_id)  qp.append('category_id', this.category_id);

        const { data } = await axios.get(`report/zero_sales_products?${qp.toString()}`).catch(()=>({data:{}}));
        const items = Array.isArray(data?.report) ? data.report : [];

        // --- PDF setup (landscape A4) + RTL + font
        const pdf = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

        // Use your only font file for both normal & bold (Arabic-safe)
        const fontPath = '/fonts/Vazirmatn-Bold.ttf';
        try {
          pdf.addFont(fontPath, 'Vazirmatn', 'normal');
          pdf.addFont(fontPath, 'Vazirmatn', 'bold');
        } catch (e) { /* ignore if already added */ }
        pdf.setFont('Vazirmatn', 'normal');

        const rtl =
          (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
          (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

        const margin = 40;
        const pageW  = pdf.internal.pageSize.getWidth();

        // --- Header (title + filters)
        pdf.setFont('Vazirmatn','bold'); pdf.setFontSize(16);
        const title = 'Zero Sales Products Report';
        rtl ? pdf.text(title, pageW - margin, 36, { align:'right' })
            : pdf.text(title, margin, 36);

        pdf.setFont('Vazirmatn','normal'); pdf.setFontSize(10);

        // Period label from your dropdown text (already localized)
        const periodLabel = (this.periodOptions || []).find(o => String(o.value) === String(this.period))?.text
                            || String(this.period);

        const filtersLine = [
          `${this.$t ? this.$t('Period') : 'Period'}: ${periodLabel}`,
          this.warehouse_id ? `${this.$t ? this.$t('warehouse') : 'Warehouse'}: ${this.warehouse_id}` : null,
          this.brand_id     ? `${this.$t ? this.$t('Brand') : 'Brand'}: ${this.brand_id}`             : null,
          this.category_id  ? `${this.$t ? this.$t('Category') : 'Category'}: ${this.category_id}`     : null,
          this.search       ? `${this.$t ? this.$t('Search_this_table') : 'Search'}: "${this.search}"` : null,
        ].filter(Boolean).join('   •   ');

        const wrapped = pdf.splitTextToSize(filtersLine, pageW - margin*2);
        rtl ? pdf.text(wrapped, pageW - margin, 56, { align:'right' })
            : pdf.text(wrapped, margin, 56);

        // --- Table
        const hCode   = this.$t ? this.$t('Code') : 'Code';
        const hProd   = this.$t ? this.$t('Product') : 'Product';
        const hPrice  = this.$t ? this.$t('Price') : 'Price';
        const hLast   = this.$t ? this.$t('LastSale') : 'Last sale';
        const hDays   = this.$t ? this.$t('DaysSinceLastSale') : 'Days since last sale';
        const neverTx = this.$t ? this.$t('Never_Sold') : 'Never Sold';

        const head = [[hCode, hProd, hPrice, hLast, hDays]];

        const body = items.map(r => ([
          r.code || '',
          r.name || '',
          (typeof r.price === 'number') ? r.price.toFixed(2) : (r.price ?? ''),
          r.last_sale_at ?? '—',
          r.last_sale_at ? (r.days_since_last_sale ?? '') : neverTx,
        ]));

        autoTable(pdf, {
          startY: 74,
          head, body,
          margin: { left: margin, right: margin },
          theme: 'striped',
          styles: {
            font: 'Vazirmatn',
            fontStyle: 'normal',
            fontSize: 9,
            cellPadding: 6,
            overflow: 'linebreak',
            halign: rtl ? 'right' : 'left',
          },
          headStyles: {
            font: 'Vazirmatn',
            fontStyle: 'bold',
            fillColor: [26, 86, 219],
            textColor: 255,
            halign: rtl ? 'right' : 'left',
          },
          columnStyles: {
            2: { halign: 'right' }, // Price
            4: { halign: 'right' }, // Days since last sale
          },
          didDrawPage: (d) => {
            pdf.setFont('Vazirmatn','normal'); pdf.setFontSize(8);
            const ph = pdf.internal.pageSize.getHeight();
            const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
            rtl ? pdf.text(pn, margin, ph - 14, { align:'left' })
                : pdf.text(pn, pageW - margin, ph - 14, { align:'right' });
          },
        });

        const stamp = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
        const fname = `zero_sales_products_${String(this.period)}_${stamp}.pdf`;
        pdf.save(fname);
      } catch (e) {
        // optional toast
        this.$bvToast && this.$bvToast.toast(this.$t ? this.$t('Export_Failed') : 'Export failed', { variant: 'danger', solid: true });
        // console.error(e);
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

      // Get rows data from grouped structure
      const rowsData = (this.rows && this.rows[0] && this.rows[0].children) 
        ? this.rows[0].children 
        : (this.products || []);

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
          if (col.field === 'price') {
            cellContent = this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, row.price, 2);
          } else if (col.field === 'last_sale_at') {
            cellContent = row.last_sale_at || '—';
          } else if (col.field === 'days_since_last_sale') {
            if (!row.last_sale_at) {
              cellContent = this.$t('Never_Sold');
            } else {
              cellContent = row.days_since_last_sale || '';
            }
          } else {
            cellContent = row[col.field] || '';
          }
          tableHtml += `<td class="text-left">${cellContent}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalPrice = rowsData.reduce((sum, row) => sum + parseFloat(row.price || 0), 0);
      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold" colspan="2">${this.$t('Total')}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, totalPrice, 2)}</td>`;
      tableHtml += `<td colspan="2"></td>`; // Span for LastSale and DaysSinceLastSale
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Zero_Sales_Products_Report")}`;
      const periodLabel = (this.periodOptions || []).find(o => String(o.value) === String(this.period))?.text || String(this.period);
      const periodText = `${this.$t('Period')}: ${periodLabel}`;

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
    <div class="print-meta">${periodText}</div>
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

  created() {
    this.Get_Zero_Sales_Products(1);
  },
};
</script>

<style scoped>
.btn-pill { border-radius: 999px; }
</style>
