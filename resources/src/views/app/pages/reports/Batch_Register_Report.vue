<template>
  <div class="main-content">
    <breadcumb :page="$t('Batch_Register_Report') || 'Batch Register'" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <!-- Toolbar / filters -->
      <div class="d-flex flex-wrap align-items-end mb-3">
        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2 d-block">{{ $t('Warehouse') }}</label>
          <b-form-select v-model="filters.warehouse_id" size="sm" class="w-auto" @change="onFilterChange">
            <option :value="''">{{ $t('All') }}</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </b-form-select>
        </div>
        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2 d-block">{{ $t('Supplier') || 'Supplier' }}</label>
          <b-form-select v-model="filters.supplier_id" size="sm" class="w-auto" @change="onFilterChange">
            <option :value="''">{{ $t('All') }}</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
          </b-form-select>
        </div>
        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2 d-block">{{ $t('Expiry_Window') }}</label>
          <b-form-select v-model="filters.expiry_window" size="sm" class="w-auto" @change="onFilterChange">
            <option value="all">{{ $t('All') }}</option>
            <option value="expired">{{ $t('Expired') }}</option>
            <option value="near">{{ $t('Near_Expiry') }}</option>
            <option value="valid">{{ $t('Valid') }}</option>
            <option value="expired_or_near">{{ $t('Expired') }} + {{ $t('Near_Expiry') }}</option>
          </b-form-select>
        </div>
        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2 d-block">{{ $t('Status') }}</label>
          <b-form-select v-model="filters.status" size="sm" class="w-auto" @change="onFilterChange">
            <option :value="''">{{ $t('All') }}</option>
            <option value="active">{{ $t('Batch_Status_active') }}</option>
            <option value="quarantined">{{ $t('Batch_Status_quarantined') }}</option>
            <option value="expired">{{ $t('Batch_Status_expired') }}</option>
            <option value="written_off">{{ $t('Batch_Status_written_off') }}</option>
          </b-form-select>
        </div>
        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2 d-block">{{ $t('From') || 'From' }}</label>
          <b-form-input type="date" size="sm" v-model="filters.purchase_date_from" @change="onFilterChange" style="width: 140px;" />
        </div>
        <div class="mr-3 mb-2">
          <label class="mb-0 mr-2 d-block">{{ $t('To') || 'To' }}</label>
          <b-form-input type="date" size="sm" v-model="filters.purchase_date_to" @change="onFilterChange" style="width: 140px;" />
        </div>

        <div class="ml-auto mb-2">
          <b-button size="sm" variant="outline-secondary" class="btn-pill" @click="printTableOnly()">
            <lucide-icon class="mr-1" name="printer" />{{ $t('print') }}
          </b-button>
        </div>
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ placeholder: $t('Search_this_table'), enabled: true }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          perPage: serverParams.perPage,
          perPageDropdown: [10, 25, 50, 100],
          dropdownAllowAll: false,
          nextLabel: 'next',
          prevLabel: 'prev'
        }"
        styleClass="tableOne table-hover vgt-table mt-2"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'product'">
            <div>
              <strong>{{ props.row.product_name }}</strong>
              <small v-if="props.row.product_code" class="text-muted ml-1">[{{ props.row.product_code }}]</small>
            </div>
            <small v-if="props.row.generic_name" class="text-muted">
              {{ props.row.generic_name }}
              <span v-if="props.row.strength"> · {{ props.row.strength }}</span>
              <span v-if="props.row.dosage_form"> · {{ props.row.dosage_form }}</span>
            </small>
            <div v-if="props.row.variant_name">
              <span class="badge badge-pill" style="background:#ede9fe; color:#6d28d9;">{{ props.row.variant_name }}</span>
            </div>
          </span>

          <span v-else-if="props.column.field === 'batch_no'">
            <a href="#" class="text-primary font-weight-bold" @click.prevent="openHistory(props.row)">
              {{ props.row.batch_no || '—' }}
            </a>
          </span>

          <span v-else-if="props.column.field === 'expiry_date'">
            <span v-if="props.row.expiry_date">
              {{ props.row.expiry_date }}
              <br>
              <small
                :class="{
                  'text-danger': props.row.expiry_bucket === 'expired',
                  'text-warning': props.row.expiry_bucket === 'near',
                  'text-success': props.row.expiry_bucket === 'valid'
                }"
              >
                <span v-if="props.row.expiry_bucket === 'expired'">
                  {{ $t('Expired') }} ({{ Math.abs(props.row.days_to_expiry) }}d)
                </span>
                <span v-else-if="props.row.expiry_bucket === 'near'">
                  {{ $t('Expires_in') }} {{ props.row.days_to_expiry }}d
                </span>
                <span v-else>
                  {{ $t('Valid') }}
                </span>
              </small>
            </span>
            <span v-else class="text-muted">—</span>
          </span>

          <span v-else-if="props.column.field === 'qty'">
            {{ formatNumber(props.row.qty) }}
          </span>

          <span v-else-if="props.column.field === 'unit_cost'">
            <span v-if="props.row.unit_cost !== null">
              {{ currencySymbol }} {{ formatNumber(props.row.unit_cost) }}
            </span>
            <span v-else class="text-muted">—</span>
          </span>

          <span v-else-if="props.column.field === 'value'">
            <span v-if="props.row.value !== null">
              {{ currencySymbol }} {{ formatNumber(props.row.value) }}
            </span>
            <span v-else class="text-muted">—</span>
          </span>

          <span v-else-if="props.column.field === 'origin_purchase_ref'">
            <router-link
              v-if="props.row.origin_purchase_id"
              :to="{ name: 'detail_purchase', params: { id: props.row.origin_purchase_id } }"
              class="text-primary"
            >
              {{ props.row.origin_purchase_ref }}
            </router-link>
            <span v-else class="text-muted">—</span>
            <div v-if="props.row.origin_purchase_date" class="small text-muted">
              {{ props.row.origin_purchase_date }}
            </div>
          </span>

          <span v-else-if="props.column.field === 'origin_supplier_name'">
            {{ props.row.origin_supplier_name || '—' }}
          </span>

          <span v-else-if="props.column.field === 'status'">
            <span class="badge" :class="statusBadge(props.row.status)">
              {{ $t('Batch_Status_' + props.row.status) || props.row.status }}
            </span>
          </span>

          <span v-else-if="props.column.field === 'actions'">
            <b-button size="sm" variant="outline-primary" class="btn-pill" @click="openHistory(props.row)">
              <lucide-icon class="mr-1" name="file-text" />{{ $t('History') || 'History' }}
            </b-button>
          </span>

          <span v-else>{{ props.formattedRow[props.column.field] }}</span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";

export default {
  metaInfo: { title: "Batch Register" },

  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: { field: 'expiry_date', type: 'asc' },
        page: 1,
        perPage: 25
      },
      rows: [],
      totalRows: 0,
      search: '',
      limit: 25,
      warehouses: [],
      suppliers: [],
      expiryWarningDays: 90,
      filters: {
        warehouse_id: '',
        supplier_id: '',
        expiry_window: 'all',
        status: '',
        purchase_date_from: '',
        purchase_date_to: ''
      }
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),

    currencySymbol() {
      return (this.currentUser && this.currentUser.currency) || '';
    },

    columns() {
      return [
        { label: this.$t('Product'), field: 'product', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Batch_No'), field: 'batch_no', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Warehouse'), field: 'warehouse_name', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Mfg_Date'), field: 'mfg_date', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Expiry_Date'), field: 'expiry_date', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Quantity'), field: 'qty', tdClass: 'text-right', thClass: 'text-right' },
        { label: this.$t('Unit_Cost') || 'Unit Cost', field: 'unit_cost', sortable: false, tdClass: 'text-right', thClass: 'text-right' },
        { label: this.$t('Value'), field: 'value', sortable: false, tdClass: 'text-right', thClass: 'text-right' },
        { label: this.$t('Status'), field: 'status', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Purchase_Ref') || 'Purchase Ref', field: 'origin_purchase_ref', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Supplier') || 'Supplier', field: 'origin_supplier_name', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: '', field: 'actions', sortable: false, tdClass: 'text-center', thClass: 'text-center' }
      ];
    }
  },

  methods: {
    formatNumber(v) {
      if (v === null || v === undefined || v === '') return '';
      const n = Number(v);
      if (Number.isNaN(n)) return v;
      return Number.isInteger(n) ? n.toString() : n.toFixed(2);
    },

    statusBadge(status) {
      switch (status) {
        case 'active': return 'badge-success';
        case 'quarantined': return 'badge-warning';
        case 'expired': return 'badge-danger';
        case 'written_off': return 'badge-secondary';
        default: return 'badge-light';
      }
    },

    openHistory(row) {
      this.$router.push({ name: 'batch_history_report', params: { id: row.id } });
    },

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange({ currentPage }) {
      this.updateParams({ page: currentPage });
      this.fetch(currentPage);
    },
    onPerPageChange({ currentPerPage }) {
      this.limit = currentPerPage;
      this.updateParams({ perPage: currentPerPage, page: 1 });
      this.fetch(1);
    },
    onSortChange(params) {
      if (params && params[0]) {
        this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
        this.fetch(this.serverParams.page);
      }
    },
    onSearch(value) {
      this.search = value.searchTerm || '';
      this.updateParams({ page: 1 });
      this.fetch(1);
    },
    onFilterChange() {
      this.updateParams({ page: 1 });
      this.fetch(1);
    },

    fetch(page) {
      NProgress.start();
      NProgress.set(0.1);
      const params = {
        page: page || 1,
        limit: this.limit,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.search || '',
        expiry_window: this.filters.expiry_window
      };
      if (this.filters.warehouse_id !== '') params.warehouse_id = this.filters.warehouse_id;
      if (this.filters.supplier_id !== '') params.supplier_id = this.filters.supplier_id;
      if (this.filters.status !== '') params.status = this.filters.status;
      if (this.filters.purchase_date_from) params.purchase_date_from = this.filters.purchase_date_from;
      if (this.filters.purchase_date_to) params.purchase_date_to = this.filters.purchase_date_to;

      axios
        .get('report/batches/register', { params })
        .then(response => {
          this.rows = response.data.batches || [];
          this.totalRows = response.data.totalRows || 0;
          this.warehouses = response.data.warehouses || [];
          this.suppliers = response.data.suppliers || [];
          if (response.data.expiry_warning_days) {
            this.expiryWarningDays = response.data.expiry_warning_days;
          }
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => { this.isLoading = false; }, 500);
        });
    },

    printTableOnly() {
      const title = `${this.$t('Reports')} / ${this.$t('Batch_Register_Report') || 'Batch Register'}`;
      const items = this.rows || [];

      let html = '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
      html += '<thead><tr>';
      const headers = ['Product','Batch No','Warehouse','Mfg','Expiry','Qty','Unit Cost','Value','Status','Purchase Ref','Supplier'];
      headers.forEach(h => {
        html += `<th style="border:1px solid #ddd; padding:6px; background:#f5f5f5; text-align:left;">${h}</th>`;
      });
      html += '</tr></thead><tbody>';
      items.forEach(r => {
        html += '<tr>';
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.product_name || ''}${r.product_code ? ' ['+r.product_code+']' : ''}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.batch_no || ''}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.warehouse_name || ''}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.mfg_date || '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.expiry_date || '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px; text-align:right;">${this.formatNumber(r.qty)}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px; text-align:right;">${r.unit_cost !== null ? this.formatNumber(r.unit_cost) : '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px; text-align:right;">${r.value !== null ? this.formatNumber(r.value) : '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.status || ''}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.origin_purchase_ref || '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.origin_supplier_name || '—'}</td>`;
        html += '</tr>';
      });
      html += '</tbody></table>';

      const w = window.open('', '_blank');
      if (!w) { alert('Please allow popups to print'); return; }
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML).join('\n');
      const doc = w.document;
      doc.open();
      doc.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <base href="${window.location.origin}/" />
    <title>${title}</title>
    ${links}
    <style>
      @media print { body, body * { visibility: visible !important; } @page { size: A4 landscape; margin: 0.3cm; } }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 10px; font-size: 14px; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    ${html}
  </body>
</html>`);
      doc.close();
      w.focus();
      setTimeout(() => { w.print(); w.close(); }, 400);
    }
  },

  created() {
    this.fetch(1);
  }
};
</script>
