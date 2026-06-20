<template>
  <div class="main-content">
    <breadcumb :page="$t('Cash_Register_Report')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <div class="row align-items-end">
        <div class="col-md-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('DateRange')}}</label>
          <date-range-picker
            v-model="dateRange"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="'right'"
            :drops="'down'"
            :parentEl="'body'"
            :linkedCalendars="false"
            @update="onDateRangeUpdate"
          >
            <template v-slot:input="picker">
              <b-button variant="light" class="btn-pill">
                <lucide-icon class="mr-1" name="calendar-days" />
                {{ fmt(picker.startDate) }} - {{ fmt(picker.endDate) }}
              </b-button>
            </template>
          </date-range-picker>
        </div>
        <div class="col-md-3 mb-2">
          <label>{{$t('Cashier')}}</label>
          <b-form-select v-model="filters.user_id" :options="userOptions"></b-form-select>
        </div>
        <div class="col-md-3 mb-2">
          <label>{{$t('warehouse')}}</label>
          <b-form-select v-model="filters.warehouse_id" :options="warehouseOptions"></b-form-select>
        </div>
        <div class="col-md-2 mb-2">
          <label>{{$t('Status')}}</label>
          <b-form-select v-model="filters.status" :options="statusOptions"></b-form-select>
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
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
        styleClass="tableOne table-hover vgt-table mt-3"
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
          <span v-if="props.column.field === 'cashier'">
            {{ formatCashier(props.row) }}
          </span>
          <span v-else-if="props.column.field === 'warehouse'">
            {{ props.row.warehouse_name }}
          </span>
          <span v-else-if="props.column.field === 'opened_at' || props.column.field === 'closed_at'">
            {{ formatDate(props.row[props.column.field]) }}
          </span>
          <span v-else-if="['opening_balance','cash_in','cash_out','total_sales','closing_balance','difference'].includes(props.column.field)">
            {{ formatMoney(props.row[props.column.field]) }}
          </span>
          <span v-else-if="props.column.field === 'status'">
            <span class="badge" :class="props.row.status === 'open' ? 'badge-success' : 'badge-danger'">{{ props.row.status }}</span>
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
import NProgress from 'nprogress'
import moment from 'moment'
import DateRangePicker from 'vue2-daterange-picker'
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import Util from '../../../../utils'
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: { title: 'Cash Register Report' },
  components: { 'date-range-picker': DateRangePicker },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: { field: 'opened_at', type: 'desc' },
        page: 1,
        perPage: 10,
        searchTerm: ''
      },
      totalRows: 0,
      rows: [{
        statut: '',
        children: [],
      }],
      userOptions: [{ value: '', text: this.$t('All') }],
      warehouseOptions: [{ value: '', text: this.$t('All') }],
      statusOptions: [
        { value: '', text: this.$t('All') },
        { value: 'open', text: 'Open' },
        { value: 'closed', text: 'Closed' }
      ],
      filters: { user_id: '', warehouse_id: '', status: '' },
      dateRange: { startDate: new Date(new Date().setDate(new Date().getDate()-6)), endDate: new Date() },
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null,
      locale: {
        Label: this.$t('Apply') || 'Apply',
        cancelLabel: this.$t('Cancel') || 'Cancel',
        weekLabel: 'W',
        customRangeLabel: this.$t('CustomRange') || 'Custom Range',
        daysOfWeek: ['Su','Mo','Tu','We','Th','Fr','Sa'],
        monthNames: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        firstDay: 1,
      },
    }
  },
  computed: {
    columns() {
      return [
        { label: this.$t('Cashier'), field: 'cashier', thClass: 'text-left', tdClass: 'text-left', sortable: false },
        { label: this.$t('warehouse'), field: 'warehouse', thClass: 'text-left', tdClass: 'text-left', sortable: false },
        { label: this.$t('Opened'), field: 'opened_at', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        // Omit type:'decimal' on columns with headerField - headerField returns pre-formatted string;
        // vue-good-table re-formats decimal type, causing NaN when parsing strings like "1,234.56"
        { label: this.$t('Opening'), field: 'opening_balance', headerField: this.sumOpeningBalance, thClass: 'text-right', tdClass: 'text-right', sortable: true },
        { label: this.$t('Cash In'), field: 'cash_in', headerField: this.sumCashIn, thClass: 'text-right', tdClass: 'text-right', sortable: true },
        { label: this.$t('Cash Out'), field: 'cash_out', headerField: this.sumCashOut, thClass: 'text-right', tdClass: 'text-right', sortable: true },
        { label: this.$t('TotalSales'), field: 'total_sales', headerField: this.sumTotalSales, thClass: 'text-right', tdClass: 'text-right', sortable: true },
        { label: this.$t('Closed'), field: 'closed_at', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        { label: this.$t('Closing'), field: 'closing_balance', headerField: this.sumClosingBalance, thClass: 'text-right', tdClass: 'text-right', sortable: true },
        { label: this.$t('Difference'), field: 'difference', headerField: this.sumDifference, thClass: 'text-right', tdClass: 'text-right', sortable: true },
        { label: this.$t('Status'), field: 'status', thClass: 'text-left', tdClass: 'text-left', sortable: true },
      ]
    }
  },
  created() {
    this.bootstrapFilters().finally(() => {
      this.getData(1)
    })
  },
  methods: {
    // Same as dashboard: format date for picker display and API params (YYYY-MM-DD, local time via moment)
    fmt(d) { try { return moment(d).format('YYYY-MM-DD') } catch(e) { return '' } },
    onDateRangeUpdate() { this.getData(1) },
    bootstrapFilters() {
      // Load from the same endpoint (like sales_report) to avoid extra requests
      return this.getData(1, true)
    },
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps)
    },
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage })
        this.getData(currentPage)
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.serverParams.perPage !== currentPerPage) {
        this.updateParams({ page: 1, perPage: currentPerPage })
        this.getData(1)
      }
    },
    onSortChange(params) {
      if (!params || !params[0]) return
      const { field, type } = params[0]
      this.updateParams({ sort: { field, type } })
      this.getData(1)
    },
    onSearch(value) {
      this.updateParams({ searchTerm: value })
      this.getData(1)
    },
    getData(page = 1, preloadOnly = false) {
      NProgress.start(); NProgress.set(0.1)
      this.isLoading = true
      const params = {
        page: page,
        limit: this.serverParams.perPage,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.serverParams.searchTerm,
        user_id: this.filters.user_id || undefined,
        warehouse_id: this.filters.warehouse_id || undefined,
        status: this.filters.status || undefined,
        from: this.fmt(this.dateRange.startDate),
        to: this.fmt(this.dateRange.endDate),
      }
      return axios.get('report/cash_registers', { params }).then(res => {
        // Mirrors sales_report payload style
        const payload = res.data || {}
        const data = payload.registers || []
        if (!preloadOnly) {
          this.rows[0].children = data
          this.totalRows = payload.totalRows || data.length
        }
        // Preload users & warehouses for filters
        if (Array.isArray(payload.users)) {
          const users = payload.users.map(x => ({ value: x.id, text: (x.firstname && x.lastname) ? (x.firstname + ' ' + x.lastname) : (x.username || x.name || ('User #' + x.id)) }))
          this.userOptions = [{ value: '', text: this.$t('All') }, ...users]
        }
        if (Array.isArray(payload.warehouses)) {
          const warehouses = payload.warehouses.map(x => ({ value: x.id, text: x.name }))
          this.warehouseOptions = [{ value: '', text: this.$t('All') }, ...warehouses]
        }
      }).catch(() => {
        if (this.$bvToast && this.$bvToast.toast) this.$bvToast.toast(this.$t('OperationFailed'), { title: this.$t('Failed'), variant: 'danger', solid: true })
      }).finally(() => {
        this.isLoading = false
        setTimeout(() => NProgress.done(), 300)
      })
    },
    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing toFixed behavior to preserve current behavior.
    formatMoney(x) {
      try {
        const n = parseFloat(x || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, 2, effectiveKey);
      } catch (e) {
        const n = parseFloat(x || 0);
        return n.toFixed(2);
      }
    },
    formatDate(x) {
      if (!x) return '-'
      // Get date format from Vuex store (loaded from database) or fallback
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store)
      // formatDisplayDate now handles time preservation automatically
      return Util.formatDisplayDate(x, dateFormat)
    },
    formatCashier(row) {
      const first = row.cashier_firstname || ''
      const last = row.cashier_lastname || ''
      const full = [first, last].filter(Boolean).join(' ')
      return full || row.cashier_username || row.cashier_name || ('User #' + row.cashier_id)
    },

    // Group footer helpers for vue-good-table
    sumOpeningBalance(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatMoney(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = parseFloat(rowObj.children[i].opening_balance || 0);
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatMoney(sum);
    },

    sumCashIn(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatMoney(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = parseFloat(rowObj.children[i].cash_in || 0);
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatMoney(sum);
    },

    sumCashOut(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatMoney(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = parseFloat(rowObj.children[i].cash_out || 0);
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatMoney(sum);
    },

    sumTotalSales(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatMoney(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = parseFloat(rowObj.children[i].total_sales || 0);
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatMoney(sum);
    },

    sumClosingBalance(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatMoney(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = parseFloat(rowObj.children[i].closing_balance || 0);
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatMoney(sum);
    },

    sumDifference(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatMoney(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = parseFloat(rowObj.children[i].difference || 0);
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatMoney(sum);
    },
    resetFilters() {
      this.filters = { user_id: '', warehouse_id: '', status: '' }
      this.dateRange = { startDate: new Date(new Date().setDate(new Date().getDate()-6)), endDate: new Date() }
      this.getData(1)
    },

    //------ Print Table Only - Print ALL cash register data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Cash_Register_Report")}`;
      const registers = Array.isArray(this.rows[0]?.children) ? this.rows[0].children : [];
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      registers.forEach(register => {
        tableHTML += '<tr>';
        this.columns.forEach(col => {
          let cellValue = '';
          
          if (col.field === 'cashier') {
            cellValue = this.formatCashier(register);
          } else if (col.field === 'warehouse') {
            cellValue = register.warehouse_name || '';
          } else if (col.field === 'opened_at' || col.field === 'closed_at') {
            cellValue = this.formatDate(register[col.field]);
          } else if (['opening_balance','cash_in','cash_out','total_sales','closing_balance','difference'].includes(col.field)) {
            cellValue = this.formatMoney(register[col.field]);
          } else if (col.field === 'status') {
            cellValue = register.status || '';
          } else {
            // Default: get value directly from register object
            cellValue = register[col.field] || '';
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
    }
  }
}
</script>

<style scoped>
</style>




