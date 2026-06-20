<template>
  <div class="main-content">
    <breadcumb :page="$t('Stock_Aging_Report')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper print-table-only" v-if="!isLoading">
      <!-- Filters -->
      <div class="d-flex flex-wrap align-items-center mb-3">
        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2">{{$t('Dimension')}}:</label>
          <b-form-select
            v-model="dimension"
            :options="dimensionOptions"
            size="sm"
            class="w-auto"
            @change="onDimensionChange"
          />
        </div>

        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2">{{$t('warehouse')}}:</label>
          <b-form-select
            v-model="warehouse_id"
            :options="warehouseOptions"
            size="sm"
            class="w-auto"
            @change="refresh"
          />
        </div>

        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2">{{$t('Buckets')}}:</label>
          <b-form-input
            v-model="bucketsInput"
            size="sm"
            class="w-auto"
            @change="onBucketsChange"
            :placeholder="$t('e.g. 30,60,90')"
          />
          <small class="text-muted d-block" style="margin-top:0px">
            {{$t('Comma_separated_cutoffs_default')}}: 30,60,90
          </small>
        </div>

        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2">{{$t('Brand')}}:</label>
          <b-form-select
            v-model="brand_id"
            :options="brandOptions"
            size="sm"
            class="w-auto"
            @change="refresh"
          />
        </div>

        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2">{{$t('Category')}}:</label>
          <b-form-select
            v-model="category_id"
            :options="categoryOptions"
            size="sm"
            class="w-auto"
            @change="refresh"
          />
        </div>
      </div>

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
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'last_inbound_at'">
            {{ props.row.last_inbound_at || '—' }}
          </span>
          <span v-else-if="props.column.field === 'age_bucket'">
            <b-badge :variant="bucketVariant(props.row.age_bucket)">{{ props.row.age_bucket || '—' }}</b-badge>
          </span>
          <span v-else-if="props.column.field === 'age_days'">
            {{ formatAgeDays(props.row.age_days) }}
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

export default {
  metaInfo: { title: "Stock Aging Report" },
  data() {
    return {
      isLoading: true,

      serverParams: {
        sort: { field: "age_days", type: "desc" },
        page: 1,
        perPage: 10,
      },
      limit: "10",
      search: "",
      totalRows: 0,
      rows: [{ statut: '', children: [] }],

      // filters
      dimension: "product",
      dimensionOptions: [
        { value: "product", text: "By Product" },
        { value: "variant", text: "By Variant" },
      ],

      buckets: [30, 60, 90],
      bucketsInput: "30,60,90",

      warehouse_id: null,
      brand_id: null,
      category_id: null,

      warehouseOptions: [{ value: null, text: this.$t('All') }],
      brandOptions: [{ value: null, text: this.$t('All') }],
      categoryOptions: [{ value: null, text: this.$t('All') }],
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns() {
      const base = [
        { label: this.$t("Code"), field: "code", sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Product"), field: "product_name", sortable: true, tdClass: "text-left", thClass: "text-left" },
      ];
      if (this.dimension === "variant") {
        base.push({ label: this.$t("Variant"), field: "variant_name", sortable: true, tdClass: "text-left", thClass: "text-left" });
      }
      base.push(
        { label: this.$t("OnHand"), field: "on_hand", headerField: this.sumOnHand, sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("LastInbound"), field: "last_inbound_at", sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("AgeDays"), field: "age_days", type: "number", sortable: true, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Bucket"), field: "age_bucket", sortable: true, tdClass: "text-left", thClass: "text-left" },
      );
      return base;
    },
  },

  methods: {
    formatAgeDays(v){
      const n = Number(v);
      if (isNaN(n)) return '—';
      return Math.floor(n);
    },

    // Group footer helpers for vue-good-table
    sumOnHand(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return '0';
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].on_hand) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return sum.toLocaleString();
    },
    // --- NEW: load select options
    async fetchFilters() {
      try {
        const { data } = await axios.get('report/stock_aging/filters');

        const first = { value: null, text: this.$t('All') };

        this.warehouseOptions = [first].concat(
          (data.warehouses || []).map(w => ({ value: Number(w.id), text: String(w.name) }))
        );

        this.brandOptions = [first].concat(
          (data.brands || []).map(b => ({ value: Number(b.id), text: String(b.name) }))
        );

        this.categoryOptions = [first].concat(
          (data.categories || []).map(c => ({ value: Number(c.id), text: String(c.name) }))
        );
      } catch (e) {
        // keep "All" only on failure
      }
    },

    bucketVariant(bucket) {
      if (!bucket) return "secondary";
      if (bucket.includes("0–")) return "success";
      if (bucket.includes("31–") || bucket.includes("30–")) return "info";
      if (bucket.includes("61–")) return "warning";
      return "danger";
    },

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.fetch(currentPage);
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage.toString();
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.fetch(1);
      }
    },
    onSortChange(params) {
      if (params && params[0]) {
        this.updateParams({
          sort: { type: params[0].type, field: params[0].field }
        });
        this.fetch(this.serverParams.page);
      }
    },
    onSearch(value) {
      this.search = value.searchTerm || "";
      this.fetch(this.serverParams.page);
    },
    onDimensionChange() {
      if (this.serverParams.sort.field === "variant_name" && this.dimension !== "variant") {
        this.serverParams.sort.field = "product_name";
      }
      this.updateParams({ page: 1 });
      this.fetch(1);
    },
    onBucketsChange() {
      const parts = (this.bucketsInput || "")
        .split(",")
        .map(s => parseInt(String(s).trim(), 10))
        .filter(n => !isNaN(n) && n > 0)
        .sort((a,b) => a - b);
      this.buckets = parts.length ? parts : [30,60,90];
      this.bucketsInput = this.buckets.join(",");
      this.refresh();
    },
    refresh() {
      this.updateParams({ page: 1 });
      this.fetch(1);
    },

    fetch(page) {
      NProgress.start(); NProgress.set(0.1);

      const params =
        "page=" + page +
        "&SortField=" + encodeURIComponent(this.serverParams.sort.field) +
        "&SortType=" + encodeURIComponent(this.serverParams.sort.type) +
        "&search=" + encodeURIComponent(this.search || "") +
        "&limit=" + encodeURIComponent(this.limit) +
        "&dimension=" + encodeURIComponent(this.dimension) +
        "&buckets=" + encodeURIComponent(this.buckets.join(",")) +
        (this.warehouse_id != null ? "&warehouse_id=" + encodeURIComponent(this.warehouse_id) : "") +
        (this.brand_id != null ? "&brand_id=" + encodeURIComponent(this.brand_id) : "") +
        (this.category_id != null ? "&category_id=" + encodeURIComponent(this.category_id) : "");

      axios.get("report/stock_aging?" + params)
        .then(({ data }) => {
          const items = Array.isArray(data.report) ? data.report : [];
          this.totalRows = Number(data.totalRows || 0);
          this.rows[0].children = items;
          NProgress.done(); this.isLoading = false;
        })
        .catch(() => {
          NProgress.done(); setTimeout(() => { this.isLoading = false; }, 400);
        });
    },

    async init() {
      this.isLoading = true;
      await this.fetchFilters();
      this.fetch(1);
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

      // Get rows data from rows[0].children
      const rowsData = Array.isArray(this.rows[0]?.children) && this.rows[0].children.length > 0 
        ? this.rows[0].children 
        : [];

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
          let cellContent = row[col.field];
          if (col.field === 'last_inbound_at') {
            cellContent = row.last_inbound_at || '—';
          } else if (col.field === 'age_bucket') {
            cellContent = row.age_bucket || '—';
          } else if (col.field === 'age_days') {
            cellContent = this.formatAgeDays(row.age_days);
          }
          tableHtml += `<td class="text-left">${cellContent || ''}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalOnHand = this.sumOnHand(this.rows[0]);
      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Total')}</td>`;
      tableHtml += `<td colspan="${this.dimension === 'variant' ? 2 : 1}"></td>`; // Span for Product (and Variant if dimension is variant)
      tableHtml += `<td class="text-left font-weight-bold">${totalOnHand}</td>`;
      tableHtml += `<td colspan="3"></td>`; // Span for LastInbound, AgeDays, Bucket
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Stock_Aging_Report")}`;
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
    }
  },

  created() {
    // Load options then data
    this.init();
  }
};
</script>
