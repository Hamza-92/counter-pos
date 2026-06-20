<template>
  <div class="main-content">
    <breadcumb :page="$t('Dead_Stock_Report')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-else>
      <!-- Filters -->
      <div class="d-flex flex-wrap align-items-center mb-2">
        <!-- Period -->
        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2">{{ $t('Period') }}:</label>
          <b-form-select
            v-model="period"
            :options="periodOptions"
            size="sm"
            class="w-auto"
            @change="onPeriodChange"
          />
        </div>

        <!-- (Optional) extra filters kept for parity; plug your own pickers here -->
        <!--
        <div class="mr-3 mb-2">
          <b-form-select v-model="warehouse_id" :options="warehouses" size="sm" class="w-auto" @change="resetToFirstPageAndFetch" />
        </div>
        -->

        <!-- Export buttons -->
        <div class="ml-auto mb-2">
          <b-button
            size="sm"
            variant="outline-primary"
            class="mr-2"
            :disabled="disableExport"
            @click="exportPdf"
          >
            {{ $t('Export') }} PDF
          </b-button>

          <b-button
            size="sm"
            variant="outline-secondary"
            :disabled="disableExport"
            @click="exportPdfAll"
          >
            {{ $t('Export') }} PDF ({{ $t('All') }})
          </b-button>
        </div>
      </div>

      <!-- Range label from backend (always correct, including “All”) -->
      <div class="small text-muted mb-2">
        {{ $t('Showing') }} {{ range.from }} – {{ range.to }} {{ $t('of') }} {{ totalRows }}
      </div>

      <vue-good-table
        ref="vgt"
        :key="tableKey"
        mode="remote"
        :columns="columns"
        :rows="rows"
        :totalRows="totalRows"
        :group-options="{ enabled: true, headerPosition: 'bottom' }"
        :pagination-options="paginationOptions"
        :search-options="{ placeholder: $t('Search_this_table'), enabled: true }"
        styleClass="tableOne table-hover vgt-table mt-2"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'last_movement_at'">
            {{ props.row.last_movement_at || '—' }}
          </span>

          <span v-else-if="props.column.field === 'days_since_last_movement'">
            <b-badge variant="warning" v-if="!props.row.last_movement_at">
              {{ $t('Never_Moved') }}
            </b-badge>
            <span v-else>{{ props.row.days_since_last_movement }}</span>
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
import NProgress from 'nprogress';
import { mapGetters } from 'vuex';
// axios assumed globally available

export default {
  metaInfo: { title: 'Dead Stock Report' },

  data() {
    return {
      isLoading: true,
      exporting: false,

      // vue-good-table state
      serverParams: {
        sort: { field: 'days_since_last_movement', type: 'desc' },
        page: 1,
        perPage: 10, // -1 when All is chosen
      },

      tableKey: 0,       // force re-render on stubborn VGT builds
      limit: 10,         // mirrors perPage for server (–1 for All)
      search: '',
      totalRows: 0,
      range: { from: 0, to: 0 },  // provided by backend

      rows: [{ children: [] }],

      period: 60,
      periodOptions: [
        { value: 30, text: this.$t('Last_30_days') },
        { value: 60, text: this.$t('Last_60_days') },
        { value: 90, text: this.$t('Last_90_days') },
      ],

      // optional filters
      warehouse_id: null,
      brand_id: null,
      category_id: null,

      // axios cancel between rapid interactions
      _cancelSource: null,
    };
  },

  computed: {
    ...mapGetters(['currentUser']),

    columns() {
      return [
        { label: this.$t('Code'), field: 'code', sortable: true, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Product'), field: 'product_name', sortable: true, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('OnHand'), field: 'on_hand', type: 'number', headerField: this.sumOnHand, sortable: true, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('LastMovement'), field: 'last_movement_at', sortable: true, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('DaysSinceLastMovement'), field: 'days_since_last_movement', type: 'number', sortable: true, tdClass: 'text-left', thClass: 'text-left' },
      ];
    },

    paginationOptions() {
      return {
        enabled: true,
        mode: 'records',
        perPage: this.serverParams.perPage,       // -1 when All
        perPageDropdown: [10, 20, 50, 100],
        dropdownAllowAll: true,                   // adds “All” (value -1)
        allText: 'All',
        allLabel: 'All',
        nextLabel: 'next',
        prevLabel: 'prev',
        setCurrentPage: this.serverParams.page,
      };
    },

    disableExport() {
      return this.isLoading || this.exporting || !this.totalRows;
    },
  },

  methods: {
    sumOnHand(groupRow) {
      let sum = 0;
      const rows = groupRow?.children || [];
      for (let i = 0; i < rows.length; i++) {
        const v = Number(rows[i].on_hand || 0);
        if (!isNaN(v)) sum += v;
      }
      return sum;
    },

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    resetToFirstPageAndFetch() {
      this.updateParams({ page: 1 });
      if (this.$refs.vgt?.changePage) this.$refs.vgt.changePage(1);
      this.fetchDeadStock(1);
    },

    onPageChange({ currentPage }) {
      this.updateParams({ page: currentPage });
      this.fetchDeadStock(currentPage);
    },

    onPerPageChange({ currentPerPage }) {
      this.limit = currentPerPage; // -1 means All (server)
      this.updateParams({ perPage: currentPerPage, page: 1 });
      if (this.$refs.vgt?.changePage) this.$refs.vgt.changePage(1);
      this.fetchDeadStock(1);
    },

    onSortChange(params) {
      if (params && params[0]) {
        this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
        this.fetchDeadStock(this.serverParams.page);
      }
    },

    onSearch(value) {
      this.search = value.searchTerm || '';
      this.resetToFirstPageAndFetch();
    },

    onPeriodChange() {
      this.resetToFirstPageAndFetch();
    },

    //------ Print Table Only - Print ALL dead stock data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Dead_Stock_Report")}`;
      const items = Array.isArray(this.rows[0]?.children) ? this.rows[0].children : [];
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      items.forEach(item => {
        tableHTML += '<tr>';
        this.columns.forEach(col => {
          let cellValue = '';
          
          if (col.field === 'code') {
            cellValue = item.code || '';
          } else if (col.field === 'product_name') {
            cellValue = item.product_name || item.product || '';
          } else if (col.field === 'on_hand') {
            cellValue = typeof item.on_hand === 'number' ? String(item.on_hand) : (item.on_hand || '');
          } else if (col.field === 'last_movement_at') {
            cellValue = item.last_movement_at || '—';
          } else if (col.field === 'days_since_last_movement') {
            if (!item.last_movement_at) {
              cellValue = this.$t('Never_Moved') || 'Never Moved';
            } else {
              cellValue = item.days_since_last_movement || '';
            }
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

    _buildQuery({ page, limitOverride = null } = {}) {
      const qp = new URLSearchParams({
        page: String(page ?? this.serverParams.page ?? 1),
        SortField: this.serverParams.sort.field || 'days_since_last_movement',
        SortType: this.serverParams.sort.type || 'desc',
        search: this.search || '',
        limit: String(limitOverride !== null ? limitOverride : this.limit), // -1 means All
        period: String(this.period),
      });
      if (this.warehouse_id) qp.append('warehouse_id', this.warehouse_id);
      if (this.brand_id)     qp.append('brand_id', this.brand_id);
      if (this.category_id)  qp.append('category_id', this.category_id);
      return qp;
    },

    async fetchDeadStock(page = 1) {
      // cancel previous in-flight request (if any)
      if (this._cancelSource && typeof this._cancelSource.cancel === 'function') {
        this._cancelSource.cancel('New request triggered');
      }
      this._cancelSource = axios.CancelToken ? axios.CancelToken.source() : null;

      NProgress.start(); NProgress.set(0.1);
      this.isLoading = true;

      try {
        const qp = this._buildQuery({ page });
        const config = this._cancelSource ? { cancelToken: this._cancelSource.token } : {};
        const { data } = await axios.get(`report/dead_stock?${qp.toString()}`, config);

        const items = Array.isArray(data.report) ? data.report : [];
        this.totalRows = Number(data.totalRows || 0);
        this.rows[0].children = items;

        const r = data.range || {};
        this.range = {
          from: Number(r.from || (this.totalRows ? 1 : 0)),
          to:   Number(r.to   || Math.min(
            this.totalRows,
            (this.serverParams.page - 1) * (this.serverParams.perPage === -1 ? this.totalRows : this.serverParams.perPage) + items.length
          )),
        };

        // repaint in case VGT caches header/footer labels
        this.tableKey += 1;
      } catch (err) {
        // ignore cancellations
        if (!axios.isCancel?.(err)) {
          this.$bvToast?.toast(this.$t('UnexpectedError') || 'Unexpected error.', { variant: 'danger', solid: true });
          // eslint-disable-next-line no-console
          console.error('Dead stock fetch error:', err);
        }
      } finally {
        NProgress.done();
        this.isLoading = false;
      }
    },

    // -------- PDF Export (current page) --------
    async exportPdf() {
      if (this.disableExport) return;
      this.exporting = true;
      try {
        const items = (this.rows?.[0]?.children || []);
        await this._buildAndSavePdf(items, { suffix: 'page' });
      } catch (e) {
        this.$bvToast?.toast(this.$t ? this.$t('Export_Failed') : 'Export failed. Please try again.', { variant: 'danger', solid: true });
        // eslint-disable-next-line no-console
        console.error('PDF export error:', e);
      } finally {
        this.exporting = false;
      }
    },

    // -------- PDF Export (ALL rows) --------
    async exportPdfAll() {
      if (this.disableExport) return;
      this.exporting = true;
      NProgress.start(); NProgress.set(0.2);

      try {
        const qp = this._buildQuery?.({ page: 1, limitOverride: -1 }) || new URLSearchParams({
          page: '1', limit: '-1',
          SortField: this.serverParams?.sort?.field || 'last_movement_at',
          SortType:  this.serverParams?.sort?.type  || 'asc',
          search: this.search || '',
          period: String(this.period),
          warehouse_id: this.warehouse_id || '',
          brand_id: this.brand_id || '',
          category_id: this.category_id || '',
        });
        const { data } = await axios.get(`report/dead_stock?${qp.toString()}`);
        const items = Array.isArray(data?.report) ? data.report : [];
        await this._buildAndSavePdf(items, { suffix: 'all' });
      } catch (e) {
        this.$bvToast?.toast(this.$t ? this.$t('Export_Failed') : 'Export failed. Please try again.', { variant: 'danger', solid: true });
        // eslint-disable-next-line no-console
        console.error('PDF export all error:', e);
      } finally {
        NProgress.done();
        this.exporting = false;
      }
    },

    // -------- Shared PDF builder (RTL + Vazirmatn-Bold) --------
    async _buildAndSavePdf(items, { suffix = 'page' } = {}) {
      // Lazy-load libs (remove if you already import them globally)
      const { jsPDF } = await import('jspdf');
      const autoTable = (await import('jspdf-autotable')).default;

      const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'A4' });

      // Font: you said you only have Vazirmatn-Bold -> use it for normal & bold
      const fontPath = '/fonts/Vazirmatn-Bold.ttf';
      try {
        doc.addFont(fontPath, 'Vazirmatn', 'normal');
        doc.addFont(fontPath, 'Vazirmatn', 'bold');
      } catch (e) { /* ignore if already added */ }
      doc.setFont('Vazirmatn', 'normal');

      // RTL detection
      const rtl =
        (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      // Localized labels (fallback to English)
      const t = (k, d) => (this.$t ? this.$t(k) : (d || k));
      const title = t('Dead_Stock_Report', 'Dead Stock Report');

      // Period label from your dropdown (already localized in UI)
      const periodLabel =
        (this.periodOptions || []).find(o => String(o.value) === String(this.period))?.text
        || ({ '30':'Last 30 days','60':'Last 60 days','90':'Last 90 days' }[String(this.period)] || String(this.period));

      // Try to resolve names for filters if lists exist; fallback to ID
      const getLabel = (list, id, key='name') => {
        if (!id) return t('All','All');
        const found = (list || []).find(i => String(i.id) === String(id));
        return (found && (found[key] ?? found.name)) || String(id);
      };

      const warehouseLabel = this.warehouses ? getLabel(this.warehouses, this.warehouse_id, 'name') : (this.warehouse_id || t('All','All'));
      const brandLabel     = this.brands     ? getLabel(this.brands,     this.brand_id,     'name') : (this.brand_id     || null);
      const categoryLabel  = this.categories ? getLabel(this.categories, this.category_id,  'name') : (this.category_id  || null);

      // Header
      const margin = 40;
      const pageW  = doc.internal.pageSize.getWidth();

      doc.setFont('Vazirmatn','bold'); doc.setFontSize(16);
      rtl ? doc.text(title, pageW - margin, 36, { align: 'right' })
          : doc.text(title, margin, 36);

      doc.setFont('Vazirmatn','normal'); doc.setFontSize(10);
      const filtersLine = [
        `${t('Period','Period')}: ${periodLabel}`,
        this.warehouse_id ? `${t('warehouse','Warehouse')}: ${warehouseLabel}` : `${t('warehouse','Warehouse')}: ${t('All','All')}`,
        this.brand_id ? `${t('Brand','Brand')}: ${brandLabel}` : null,
        this.category_id ? `${t('Category','Category')}: ${categoryLabel}` : null,
        this.search ? `${t('Search_this_table','Search')}: "${this.search}"` : null,
      ].filter(Boolean).join('   •   ');
      const wrapped = doc.splitTextToSize(filtersLine, pageW - margin*2);
      rtl ? doc.text(wrapped, pageW - margin, 56, { align: 'right' })
          : doc.text(wrapped, margin, 56);

      // Columns (localized headers)
      const hCode  = t('Code','Code');
      const hProd  = t('Product','Product');
      const hOH    = t('OnHand','On hand');
      const hLast  = t('LastMovement','Last movement');
      const hDays  = t('DaysSinceLastMovement','Days since last movement');
      const never  = t('Never_Moved','Never Moved');

      const head = [[hCode, hProd, hOH, hLast, hDays]];

      // Rows
      const body = (items || []).map(r => ([
        r.code || '',
        r.product_name || r.product || '',
        (typeof r.on_hand === 'number') ? String(r.on_hand) : (r.on_hand ?? ''),
        r.last_movement_at ?? '—',
        r.last_movement_at ? (r.days_since_last_movement ?? '') : never,
      ]));

      autoTable(doc, {
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
          2: { halign: 'right' }, // On hand (numeric)
          4: { halign: 'right' }, // Days since last movement (numeric-ish)
        },
        didDrawPage: (d) => {
          doc.setFont('Vazirmatn','normal'); doc.setFontSize(8);
          const ph = doc.internal.pageSize.getHeight();
          const pn = `${d.pageNumber} / ${doc.internal.getNumberOfPages()}`;
          // Footer page number mirrored for RTL
          rtl ? doc.text(pn, margin, ph - 14, { align:'left' })
              : doc.text(pn, pageW - margin, ph - 14, { align:'right' });
        },
      });

      const stamp = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
      const file  = `dead_stock_${String(this.period)}_${suffix}_${stamp}.pdf`;
      doc.save(file);
    },



  },

  created() {
    this.fetchDeadStock(1);
  },

  beforeDestroy() {
    if (this._cancelSource && typeof this._cancelSource.cancel === 'function') {
      this._cancelSource.cancel('Component destroyed');
    }
  },
};
</script>
