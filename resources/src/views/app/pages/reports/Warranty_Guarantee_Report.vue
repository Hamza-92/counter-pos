<template>
  <div class="main-content">
    <breadcumb :page="$t('Warranty_Guarantee_Report')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <div class="row align-items-end mb-3">
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-2">
          <label class="mb-1 d-block text-muted">{{ $t('DateRange') }}</label>
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
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-2">
          <label>{{ $t('Customer') }}</label>
          <v-select
            v-model="filters.client_id"
            :reduce="o => o.id"
            :options="customerOptions"
            :placeholder="$t('All')"
            label="name"
          />
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-2">
          <label>{{ $t('warehouse') }}</label>
          <v-select
            v-model="filters.warehouse_id"
            :reduce="o => o.id"
            :options="warehouseOptions"
            :placeholder="$t('All')"
            label="name"
          />
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-2">
          <label>{{ $t('Product') }}</label>
          <v-select
            v-model="filters.product_id"
            :reduce="o => o.id"
            :options="productOptions"
            :placeholder="$t('All')"
            label="name"
          />
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-2">
          <label>{{ $t('Status') }}</label>
          <b-form-select v-model="filters.status" :options="statusOptions" class="form-control"/>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-2">
          <b-button variant="primary" size="sm" @click="getData(1)">
            <lucide-icon class="mr-1" name="filter" /> {{ $t('Filter') }}
          </b-button>
          <b-button variant="outline-secondary" size="sm" class="ml-1" @click="resetFilters">
            {{ $t('Reset') }}
          </b-button>
        </div>
      </div>

      <div class="table-responsive">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
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
            <lucide-icon name="printer" /> {{ $t('print') }}
          </b-button>
          <b-button @click="exportPDF()" size="sm" variant="outline-primary ripple m-1">
            <lucide-icon name="file-text" /> PDF
          </b-button>
          <vue-excel-xlsx
            class="btn btn-sm btn-outline-success ripple m-1"
            :data="exportRows"
            :columns="exportColumns"
            :file-name="'warranty_guarantee_report'"
            :file-type="'xlsx'"
            :sheet-name="'Warranty Guarantee'"
          >
            <lucide-icon name="file-spreadsheet" /> Excel
          </vue-excel-xlsx>
        </div>
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'Ref'">
            <router-link v-if="props.row.sale_id" :to="{ name: 'detail_sale', params: { id: props.row.sale_id } }" class="text-primary">
              {{ props.row.Ref }}
            </router-link>
            <span v-else>{{ props.row.Ref }}</span>
          </span>
          <span v-else-if="props.column.field === 'sale_date'">
            {{ formatDate(props.row.sale_date) }}
          </span>
          <span v-else-if="['warranty_date','guarantee_date'].includes(props.column.field)">
            {{ props.row[props.column.field] ? formatDate(props.row[props.column.field]) : '—' }}
          </span>
          <span v-else-if="props.column.field === 'days_remaining'">
            <span v-if="props.row.days_remaining === null">—</span>
            <span v-else-if="props.row.days_remaining === 0" class="text-danger">{{ $t('Expired') }}</span>
            <span v-else>{{ props.row.days_remaining }}</span>
          </span>
          <span v-else-if="props.column.field === 'status'">
            <span class="badge" :class="statusBadgeClass(props.row.status)">{{ statusLabel(props.row.status) }}</span>
          </span>
          <span v-else>
            {{ props.formattedRow[props.column.field] }}
          </span>
        </template>
      </vue-good-table>
      </div>
    </b-card>
  </div>
</template>

<script>
import NProgress from 'nprogress'
import moment from 'moment'
import DateRangePicker from 'vue2-daterange-picker'
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import Util from '../../../../utils'

export default {
  metaInfo: { title: 'Warranty / Guarantee Report' },
  components: { 'date-range-picker': DateRangePicker },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: { field: 'sale_date', type: 'desc' },
        page: 1,
        perPage: 10,
        searchTerm: ''
      },
      totalRows: 0,
      rows: [],
      filters: {
        client_id: null,
        warehouse_id: null,
        product_id: null,
        status: ''
      },
      dateRange: {
        startDate: new Date(new Date().setDate(new Date().getDate() - 30)),
        endDate: new Date()
      },
      customerOptions: [],
      warehouseOptions: [],
      productOptions: [],
      statusOptions: [
        { value: '', text: this.$t('All') },
        { value: 'active', text: this.$t('Active') },
        { value: 'expiring_soon', text: this.$t('Expiring_Soon') || 'Expiring Soon' },
        { value: 'expired', text: this.$t('Expired') }
      ],
      locale: {
        Label: this.$t('Apply') || 'Apply',
        cancelLabel: this.$t('Cancel') || 'Cancel',
        weekLabel: 'W',
        customRangeLabel: this.$t('CustomRange') || 'Custom Range',
        daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
        monthNames: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        firstDay: 1
      }
    }
  },
  computed: {
    columns() {
      return [
        { label: this.$t('Invoice') || 'Invoice', field: 'Ref', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        { label: this.$t('date') || 'Sale date', field: 'sale_date', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        { label: this.$t('Product') || 'Product', field: 'product_name', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        { label: this.$t('qty') || 'Qty', field: 'quantity', thClass: 'text-right', tdClass: 'text-right', sortable: true },
        { label: this.$t('Customer'), field: 'client_name', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        { label: this.$t('warehouse'), field: 'warehouse_name', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        { label: this.$t('Warranty_Date') || 'Warranty date', field: 'warranty_date', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        { label: this.$t('Guarantee_Date') || 'Guarantee date', field: 'guarantee_date', thClass: 'text-left', tdClass: 'text-left', sortable: true },
        { label: this.$t('Days_Remaining') || 'Days remaining', field: 'days_remaining', thClass: 'text-center', tdClass: 'text-center', sortable: false },
        { label: this.$t('Status'), field: 'status', thClass: 'text-left', tdClass: 'text-left', sortable: false }
      ]
    },
    exportRows() {
      return (this.rows || []).map(r => ({
        Ref: r.Ref,
        sale_date: this.formatDate(r.sale_date),
        product_name: r.product_name,
        quantity: r.quantity,
        client_name: r.client_name,
        warehouse_name: r.warehouse_name,
        warranty_date: r.warranty_date ? this.formatDate(r.warranty_date) : '—',
        guarantee_date: r.guarantee_date ? this.formatDate(r.guarantee_date) : '—',
        days_remaining: r.days_remaining === null ? '—' : (r.days_remaining === 0 ? this.$t('Expired') : r.days_remaining),
        status: this.statusLabel(r.status)
      }))
    },
    exportColumns() {
      return [
        { label: this.$t('Invoice') || 'Invoice', field: 'Ref' },
        { label: this.$t('date') || 'Sale date', field: 'sale_date' },
        { label: this.$t('Product') || 'Product', field: 'product_name' },
        { label: this.$t('qty') || 'Qty', field: 'quantity' },
        { label: this.$t('Customer'), field: 'client_name' },
        { label: this.$t('warehouse'), field: 'warehouse_name' },
        { label: this.$t('Warranty_Date') || 'Warranty date', field: 'warranty_date' },
        { label: this.$t('Guarantee_Date') || 'Guarantee date', field: 'guarantee_date' },
        { label: this.$t('Days_Remaining') || 'Days remaining', field: 'days_remaining' },
        { label: this.$t('Status'), field: 'status' }
      ]
    }
  },
  created() {
    this.getData(1, true)
  },
  methods: {
    // Same as dashboard: format date for picker display and API params (YYYY-MM-DD, local time via moment)
    fmt(d) {
      try {
        return moment(d).format('YYYY-MM-DD')
      } catch (e) {
        return ''
      }
    },
    onDateRangeUpdate() {
      this.getData(1)
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
      NProgress.start()
      NProgress.set(0.1)
      this.isLoading = true
      const params = {
        page,
        limit: this.serverParams.perPage,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.serverParams.searchTerm || undefined,
        from: this.fmt(this.dateRange.startDate),
        to: this.fmt(this.dateRange.endDate),
        client_id: this.filters.client_id || undefined,
        warehouse_id: this.filters.warehouse_id || undefined,
        product_id: this.filters.product_id || undefined,
        status: this.filters.status || undefined
      }
      return window.axios
        .get('report/warranty_guarantee', { params })
        .then(res => {
          const payload = res.data || {}
          const data = payload.rows || []
          if (!preloadOnly) {
            this.rows = data
            this.totalRows = payload.totalRows || data.length
          }
          if (Array.isArray(payload.customers)) {
            this.customerOptions = [{ id: null, name: this.$t('All') }, ...payload.customers]
          }
          if (Array.isArray(payload.warehouses)) {
            this.warehouseOptions = [{ id: null, name: this.$t('All') }, ...payload.warehouses]
          }
          if (Array.isArray(payload.products)) {
            this.productOptions = [{ id: null, name: this.$t('All') }, ...payload.products]
          }
        })
        .catch(() => {
          if (this.$bvToast && this.$bvToast.toast) {
            this.$bvToast.toast(this.$t('OperationFailed'), { title: this.$t('Failed'), variant: 'danger', solid: true })
          }
        })
        .finally(() => {
          this.isLoading = false
          setTimeout(() => NProgress.done(), 300)
        })
    },
    resetFilters() {
      this.filters = { client_id: null, warehouse_id: null, product_id: null, status: '' }
      this.dateRange = { startDate: new Date(new Date().setDate(new Date().getDate() - 30)), endDate: new Date() }
      this.getData(1)
    },
    formatDate(x) {
      if (!x) return '—'
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store)
      return Util.formatDisplayDate(x, dateFormat)
    },
    statusLabel(status) {
      const map = { active: this.$t('Active'), expiring_soon: this.$t('Expiring_Soon') || 'Expiring Soon', expired: this.$t('Expired'), no_coverage: '—' }
      return map[status] || status
    },
    statusBadgeClass(status) {
      const map = { active: 'badge-success', expiring_soon: 'badge-warning', expired: 'badge-danger', no_coverage: 'badge-secondary' }
      return map[status] || 'badge-secondary'
    },
    exportPDF() {
      const headers = [
        this.$t('Invoice') || 'Invoice',
        this.$t('date') || 'Sale date',
        this.$t('Product') || 'Product',
        this.$t('qty') || 'Qty',
        this.$t('Customer'),
        this.$t('warehouse'),
        this.$t('Warranty_Date') || 'Warranty date',
        this.$t('Guarantee_Date') || 'Guarantee date',
        this.$t('Days_Remaining') || 'Days remaining',
        this.$t('Status')
      ]
      const body = (this.rows || []).map(r => [
        r.Ref,
        this.formatDate(r.sale_date),
        r.product_name,
        r.quantity,
        r.client_name,
        r.warehouse_name,
        r.warranty_date ? this.formatDate(r.warranty_date) : '—',
        r.guarantee_date ? this.formatDate(r.guarantee_date) : '—',
        r.days_remaining === null ? '—' : (r.days_remaining === 0 ? this.$t('Expired') : String(r.days_remaining)),
        this.statusLabel(r.status)
      ])
      const pdf = new jsPDF('p', 'pt', 'a4')
      const pageWidth = pdf.internal.pageSize.getWidth()
      pdf.setFontSize(14)
      pdf.text(this.$t('Warranty_Guarantee_Report') || 'Warranty / Guarantee Report', pageWidth / 2, 30, { align: 'center' })
      pdf.setFontSize(9)
      autoTable(pdf, {
        head: [headers],
        body,
        startY: 50,
        theme: 'striped',
        styles: { fontSize: 8, cellPadding: 3 }
      })
      pdf.save('warranty_guarantee_report.pdf')
      if (this.$bvToast && this.$bvToast.toast) {
        this.$bvToast.toast(this.$t('Export_PDF') || 'PDF exported', { title: this.$t('Success'), variant: 'success', solid: true })
      }
    },
    printTableOnly() {
      const title = `${this.$t('Reports')} / ${this.$t('Warranty_Guarantee_Report')}`
      const list = Array.isArray(this.rows) ? this.rows : []
      let tableHTML = '<table style="width:100%;border-collapse:collapse;font-size:10px;"><thead><tr>'
      this.columns.forEach(col => {
        tableHTML += `<th style="border:1px solid #ddd;padding:6px 8px;background:#f5f5f5;font-weight:bold;">${col.label}</th>`
      })
      tableHTML += '</tr></thead><tbody>'
      list.forEach(row => {
        tableHTML += '<tr>'
        tableHTML += `<td>${row.Ref || ''}</td>`
        tableHTML += `<td>${this.formatDate(row.sale_date)}</td>`
        tableHTML += `<td>${row.product_name || ''}</td>`
        tableHTML += `<td>${row.quantity}</td>`
        tableHTML += `<td>${row.client_name || ''}</td>`
        tableHTML += `<td>${row.warehouse_name || ''}</td>`
        tableHTML += `<td>${row.warranty_date ? this.formatDate(row.warranty_date) : '—'}</td>`
        tableHTML += `<td>${row.guarantee_date ? this.formatDate(row.guarantee_date) : '—'}</td>`
        tableHTML += `<td>${row.days_remaining === null ? '—' : (row.days_remaining === 0 ? this.$t('Expired') : row.days_remaining)}</td>`
        tableHTML += `<td>${this.statusLabel(row.status)}</td>`
        tableHTML += '</tr>'
      })
      tableHTML += '</tbody></table>'
      const w = window.open('', '_blank')
      if (!w) {
        alert('Please allow popups to print')
        return
      }
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.outerHTML).join('\n')
      w.document.open()
      w.document.write(`<!doctype html><html><head><meta charset="utf-8"/><title>${title}</title>${links}<style>body{margin:0.3cm;font-family:Arial,sans-serif;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ddd;padding:6px 8px;font-size:10px;} th{background:#f5f5f5;font-weight:bold;}</style></head><body><div class="print-header">${title}</div>${tableHTML}</body></html>`)
      w.document.close()
      w.focus()
      setTimeout(() => { w.print(); w.close() }, 400)
    }
  }
}
</script>

<style scoped>
.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  min-width: 0;
}
</style>
