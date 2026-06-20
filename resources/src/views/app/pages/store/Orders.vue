<template>
  <div class="main-content">
    <breadcumb :page="$t('Orders')" :folder="$t('Store')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else class="wrapper">
      <!-- Errors -->
      <b-alert
        variant="danger"
        :show="hasError"
        dismissible
        @dismissed="clearErrors"
        class="mb-3"
      >
        <!-- Only show a heading when the backend gave us one -->
        <div v-if="errorTitle" class="font-weight-bold mb-2">
          {{ errorTitle }}
        </div>

        <!-- Item/field errors list -->
        <ul v-if="errors.length" class="mb-0 pl-3">
          <li v-for="(e, i) in errors" :key="i">{{ e }}</li>
        </ul>
      </b-alert>


      <!-- Filters -->
      <div class="row mb-3">
        <div class="col-md-2">
          <b-form-input v-model="search" :placeholder="$t('Search')" @keyup.enter="reload" />
        </div>
        <div class="col-md-2">
          <b-form-select v-model="status" :options="statusOptions" @change="reload" />
        </div>
        <div class="col-md-2">
          <b-form-select v-model="preorderFilter" :options="preorderOptions" @change="reload" />
        </div>
        <div class="col-md-3">
          <b-form-datepicker v-model="dateFrom" :placeholder="$t('From')" @input="reload"/>
        </div>
        <div class="col-md-3">
          <b-form-datepicker v-model="dateTo" :placeholder="$t('To')" @input="reload"/>
        </div>
      </div>

      <!-- Table -->
      <vue-good-table
        mode="remote"
        :columns="columns"
        :rows="rows"
        :totalRows="totalRows"
        :pagination-options="{ enabled: true, perPage: serverParams.perPage }"
        :search-options="{ enabled: false }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button size="sm" class="btn-rounded" variant="btn btn-outline-secondary" @click="clearFilters">
            <lucide-icon name="refresh-cw" /> {{ $t('Clear') }}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <!-- Status -->
          <span v-if="props.column.field === 'status'">
            <b-badge :variant="badgeVariant(props.row.status)">{{ props.row.status }}</b-badge>
            <b-badge v-if="props.row.has_preorder_items" variant="warning" class="ml-1">
              {{ $t('PreOrder') }}
            </b-badge>
          </span>

          <!-- Payment Method -->
          <span v-else-if="props.column.field === 'payment_method'">
            <b-badge :variant="paymentMethodVariant(props.row.payment_method)">
              {{ paymentMethodLabel(props.row.payment_method) }}
            </b-badge>
          </span>

          <!-- Payment Status -->
          <span v-else-if="props.column.field === 'payment_status'">
            <b-badge :variant="props.row.payment_status === 'paid' ? 'success' : 'warning'">
              {{ props.row.payment_status || 'pending' }}
            </b-badge>
          </span>

          <!-- Money -->
          <span v-else-if="props.column.field === 'total'">
            {{ currency(props.row.total) }}
          </span>

          <!-- Actions -->
          <span v-else-if="props.column.field === 'actions'">
            <div class="btn-group btn-group-sm">
              <template v-if="props.row.status === 'pending'">
                <b-button
                  :disabled="actionBusyId === props.row.id"
                  variant="outline-success"
                  @click="confirmOrder(props.row)"
                >
                  <span v-if="actionBusyId === props.row.id" class="spinner-border spinner-border-sm"></span>
                  <span v-else>{{ $t('Confirm') }}</span>
                </b-button>

                <b-button
                  :disabled="actionBusyId === props.row.id"
                  variant="outline-danger"
                  @click="cancelOrder(props.row)"
                >
                  <span v-if="actionBusyId === props.row.id" class="spinner-border spinner-border-sm"></span>
                  <span v-else>{{ $t('Cancel') }}</span>
                </b-button>
              </template>

              <router-link
                class="btn btn-outline-primary btn-sm"
                :to="{ name:'StoreOrderShow', params:{ id: props.row.id } }"
                v-b-tooltip.hover
                :title="$t('Details')"
              >
                <lucide-icon name="eye" />
              </router-link>
            </div>
          </span>

          <!-- Default -->
          <span v-else>
            {{ props.formattedRow[props.column.field] }}
          </span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";

export default {  
  metaInfo: { title: 'Store Orders' },
  data () {
    return {
      isLoading: true,
      rows: [],
      totalRows: 0,

      // error state
      errorTitle: '',     // <- shows your backend "error" string here
      errors: [],         // <- item lines / field lines here

      search: '',
      status: '',
      preorderFilter: '',
      dateFrom: null,
      dateTo: null,
      actionBusyId: null,

      statusOptions: [
        { value: '',          text: this.$t('All_Status') },
        { value: 'pending',   text: this.$t('pending') },
        { value: 'confirmed', text: this.$t('confirmed') },
        { value: 'cancelled', text: this.$t('cancelled') },
      ],

      preorderOptions: [
        { value: '',    text: this.$t('All') + ' ' + this.$t('Orders') },
        { value: 'yes', text: this.$t('HasPreorderItems') },
        { value: 'no',  text: this.$t('No') + ' ' + this.$t('PreOrder') },
      ],

      columns: [
        { label: this.$t('Order'),          field: 'code',           sortable: true },
        { label: this.$t('Customer'),       field: 'customer_name',  sortable: true },
        { label: this.$t('Status'),         field: 'status',         sortable: true },
        { label: this.$t('PaymentMethod'),  field: 'payment_method', sortable: false },
        { label: this.$t('PaymentStatus'),  field: 'payment_status', sortable: false },
        { label: this.$t('Total'),          field: 'total',          sortable: true, type: 'number' },
        { label: this.$t('Date'),           field: 'created_at',     sortable: true },
        { label: this.$t('Actions'),        field: 'actions' }
      ],

      serverParams: {
        page: 1,
        perPage: 10,
        sort: [{ field: 'created_at', type: 'desc' }],
      }
    }
  },
  mounted () { this.fetch() },
  computed: {
    ...mapGetters(["currentUser"]),
    hasError () {
      return (this.errorTitle && this.errorTitle.length) || this.errors.length > 0
    }
  },

  methods: {
    // ------- helpers -------
    currency(n) {
      // Prefer currentUser.currency if available
      let code =
        this.currentUser.currency;

      try {
        return new Intl.NumberFormat(undefined, {
          style: 'currency',
          currency: code
        }).format(n || 0);
      } catch (e) {
        // fallback if currency code invalid
        return code + ' ' + Number(n || 0).toFixed(2);
      }
    },
    badgeVariant (s) {
      var map = { pending: 'warning', confirmed: 'success', cancelled: 'danger' }
      return map[s] || 'secondary'
    },
    paymentMethodLabel (m) {
      return { credit_card: this.$t('CreditCard'), mobile_money: this.$t('MobileMoney'), cod: this.$t('CashOnDelivery') }[m] || m || 'N/A'
    },
    paymentMethodVariant (m) {
      return { credit_card: 'primary', mobile_money: 'info', cod: 'secondary' }[m] || 'secondary'
    },
    toDateStr (d) {
      if (!d) return ''
      var dt = (d instanceof Date) ? d : new Date(d)
      if (isNaN(dt.getTime())) return ''
      var y = dt.getFullYear(), m = ('0' + (dt.getMonth() + 1)).slice(-2), day = ('0' + dt.getDate()).slice(-2)
      return y + '-' + m + '-' + day
    },

    _headers () {
      var h = { 'Accept': 'application/json' }
      var csrf = document.querySelector('meta[name="csrf-token"]')
      if (csrf && csrf.content) h['X-CSRF-TOKEN'] = csrf.content
      return h
    },
    _postPatch (url, data) {
      var body = data || {}; body._method = 'PATCH'
      return axios.post(url, body, {
        headers: this._headers(),
        withCredentials: true,
        validateStatus: function () { return true }
      })
    },

    // ------- error state helpers -------
    clearErrors () {
      this.errorTitle = ''
      this.errors = []
    },
    setErrorState (title, list) {
      this.errorTitle = title || ''
      this.errors = Array.isArray(list) ? list : []
    },

    _flattenErrors (e) {
      var out = []; if (!e) return out
      if (Array.isArray(e)) { for (var i=0;i<e.length;i++) if (e[i]) out.push(String(e[i])) }
      else if (typeof e === 'object') {
        var keys = Object.keys(e)
        for (var k=0;k<keys.length;k++) {
          var v = e[keys[k]]
          if (Array.isArray(v)) for (var j=0;j<v.length;j++) if (v[j]) out.push(String(v[j]))
          else if (v) out.push(String(v))
        }
      } else if (typeof e === 'string') { out.push(e) }
      // dedupe
      var seen = {}, res = []
      for (var m=0;m<out.length;m++) { var s = String(out[m]).trim(); if (s && !seen[s]) { seen[s]=1; res.push(s) } }
      return res
    },
    _parseItems (items) {
      var list = []
      if (Array.isArray(items)) {
        for (var i=0;i<items.length;i++) {
          var x = items[i] || {}
          var name = x.name ? x.name : ('#' + (typeof x.product_id !== 'undefined' ? x.product_id : ''))
          var need = (typeof x.required  !== 'undefined') ? x.required  : '-'
          var have = (typeof x.available !== 'undefined') ? x.available : '-'
          list.push(name + ': Required ' + need + ' — Available ' + have)
        }
      }
      return list
    },
    _stripHtml (s) {
      try { return String(s).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() } catch (_) { return String(s) }
    },

    // returns { title, list }
    _parsePayloadToState (data) {
      var title = '', list = []
      if (!data) return { title:'', list:[] }

      if (typeof data === 'string') {
        var txt = this._stripHtml(data)
        if (txt && txt.toLowerCase() !== 'validation failed') title = txt
        return { title: title, list: [] }
      }

      // 1) Laravel validation first
      if (data.errors) {
        title = (typeof data.message === 'string' && data.message.toLowerCase() !== 'validation failed')
          ? this._stripHtml(data.message) : (title || this.$t('Validation_failed') || 'Validation failed')
        list = this._flattenErrors(data.errors)
        return { title: title, list: list }
      }

      // 2) Stock/business error: backend sends { error, items }
      if (typeof data.error === 'string' && data.error.trim()) {
        title = data.error.trim()
      }
      if (data.items) {
        list = this._parseItems(data.items)
      }

      // 3) messages/details fallback
      if (!list.length && Array.isArray(data.messages)) {
        for (var i=0;i<data.messages.length;i++) if (data.messages[i]) list.push(String(data.messages[i]))
      }
      if (!list.length && data.details) {
        if (Array.isArray(data.details)) {
          for (var j=0;j<data.details.length;j++) if (data.details[j]) list.push(String(data.details[j]))
        } else if (typeof data.details === 'string') {
          list.push(data.details)
        }
      }

      // 4) message fallback (skip generic)
      if (!title && typeof data.message === 'string') {
        var msg = this._stripHtml(data.message)
        if (msg.toLowerCase() !== 'validation failed') title = msg
      }

      // dedupe list
      var seen = {}, res = []
      for (var k=0;k<list.length;k++) { var s = String(list[k]).trim(); if (s && !seen[s]) { seen[s]=1; res.push(s) } }

      return { title: title, list: res }
    },

    // parse axios response (using validateStatus:true)
    _asErrorState (resp) {
      var status = resp && resp.status ? resp.status : 0
      var data = resp && resp.data ? resp.data : null

      var looksError = data && (data.errors || data.items || (typeof data.error === 'string' && data.error.trim()) || data.status === false || data.ok === false)
      if (status === 0) return { title: this.$t('Network_error') || 'Network error', list: [] }
      if (looksError || status >= 400) return this._parsePayloadToState(data)
      return { title:'', list:[] }
    },

    // ------- data flows -------
    async fetch () {
      this.isLoading = true
      this.clearErrors()
      try {
        var s = (this.serverParams.sort && this.serverParams.sort[0]) ? this.serverParams.sort[0] : null
        var params = {
          page     : this.serverParams.page,
          per_page : this.serverParams.perPage,
          sort     : s ? s.field : 'created_at',
          dir      : s ? s.type  : 'desc',
          q        : this.search || '',
          status   : this.status || '',
          preorder : this.preorderFilter || '',
          from     : this.toDateStr(this.dateFrom),
          to       : this.toDateStr(this.dateTo)
        }
        var resp = await axios.get('/store/orders', { params: params, headers: this._headers(), withCredentials: true, validateStatus: function(){return true} })
        var errState = this._asErrorState(resp)
        if (errState.title || errState.list.length) {
          this.setErrorState(errState.title, errState.list)
        } else {
          var data = resp && resp.data ? resp.data : {}
          this.rows = (data.data || data.rows) || []
          this.totalRows = (data.meta && data.meta.total) ? data.meta.total : this.rows.length
        }
      } catch (_) {
        this.setErrorState(this.$t('Network_error') || 'Network error', [])
      } finally {
        this.isLoading = false
      }
    },
    reload () { this.serverParams.page = 1; this.fetch() },
    onPageChange (p) { this.serverParams.page = p && p.currentPage ? p.currentPage : 1; this.fetch() },
    onPerPageChange (p) { this.serverParams.perPage = p && p.currentPerPage ? p.currentPerPage : 10; this.fetch() },
    onSortChange (params) { this.serverParams.sort = (params && params[0]) ? [params[0]] : []; this.fetch() },
    clearFilters () { this.search=''; this.status=''; this.preorderFilter=''; this.dateFrom=null; this.dateTo=null; this.clearErrors(); this.reload() },

    // ------- actions (no native alert) -------
    async confirmOrder (row) {
      if (!row || row.status !== 'pending') return

      // SweetAlert2 if available, else BootstrapVue toast prompt fallback
      var proceed = true
      if (this.$swal) {
        var r = await this.$swal({
          title: this.$t('Confirm_this_order_Q') || 'Confirm this order?',
          text:  this.$t('Confirm_to_sale_hint') || '',
          icon:  'warning',
          showCancelButton: true,
          confirmButtonText: this.$t('Confirm') || 'Confirm',
          cancelButtonText:  this.$t('Cancel')  || 'Cancel'
        })
        proceed = !!(r && (r.isConfirmed || r.value === true))
      }
      if (!proceed) return

      this.actionBusyId = row.id
      try {
        var resp = await this._postPatch('/store/orders/' + row.id, { status: 'confirmed' })
        var errState = this._asErrorState(resp)
        if (errState.title || errState.list.length) {
          this.setErrorState(errState.title, errState.list)
          return
        }
        row.status = 'confirmed'
        this.clearErrors()
        // success: toast
        if (this.$bvToast) {
          this.$bvToast.toast(this.$t('Order_confirmed') || 'Order confirmed.', { title: this.$t('Success') || 'Success', variant: 'success', solid: true })
        } else if (this.$swal) {
          this.$swal({ icon: 'success', title: this.$t('Success') || 'Success', text: this.$t('Order_confirmed') || 'Order confirmed.' })
        }
      } catch (_) {
        this.setErrorState(this.$t('Network_error') || 'Network error', [])
      } finally {
        this.actionBusyId = null
      }
    },

    async cancelOrder (row) {
      if (!row || row.status !== 'pending') return

      var proceed = true
      if (this.$swal) {
        var r = await this.$swal({
          title: this.$t('Cancel_this_order_Q') || 'Cancel this order?',
          text:  this.$t('Cancel_order_hint') || '',
          icon:  'warning',
          showCancelButton: true,
          confirmButtonText: this.$t('Cancel') || 'Cancel',
          cancelButtonText:  this.$t('Keep')   || 'Keep'
        })
        proceed = !!(r && (r.isConfirmed || r.value === true))
      }
      if (!proceed) return

      this.actionBusyId = row.id
      try {
        var resp = await this._postPatch('/store/orders/' + row.id, { status: 'cancelled' })
        var errState = this._asErrorState(resp)
        if (errState.title || errState.list.length) {
          this.setErrorState(errState.title, errState.list)
          return
        }
        row.status = 'cancelled'
        this.clearErrors()
        if (this.$bvToast) {
          this.$bvToast.toast(this.$t('Order_cancelled') || 'Order cancelled.', { title: this.$t('Success') || 'Success', variant: 'success', solid: true })
        } else if (this.$swal) {
          this.$swal({ icon: 'success', title: this.$t('Success') || 'Success', text: this.$t('Order_cancelled') || 'Order cancelled.' })
        }
      } catch (_) {
        this.setErrorState(this.$t('Network_error') || 'Network error', [])
      } finally {
        this.actionBusyId = null
      }
    }
  }
}
</script>

<style scoped>
.wrapper { overflow: visible; }
</style>
