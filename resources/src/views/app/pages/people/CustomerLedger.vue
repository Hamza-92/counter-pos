<template>
  <div class="main-content">
    <breadcumb :page="$t('Customer_Ledger')" :folder="$t('Customers')" />

    <!-- Full Page Loading Overlay -->
    <div v-if="pageLoading" class="full-page-loading">
      <div class="loading-content">
        <div class="spinner spinner-primary"></div>
        <div class="loading-text mt-3">{{ $t('Loading') }}...</div>
      </div>
    </div>

    <!-- Header / Client Card -->
    <b-card v-show="!pageLoading" class="mb-3 p-0 overflow-hidden soft-shadow">
      <div class="header-hero d-flex align-items-center p-3">
        <div class="avatar-circle mr-3"><span>{{ clientInitials }}</span></div>
        <div class="flex-grow-1">
          <h4 class="mb-1">{{ client.name || '-' }}</h4>
          <div class="text-light small">
            <span class="mr-3">{{ $t('Code') }}: <b>{{ client.code || '-' }}</b></span>
            <span class="mr-3">{{ $t('City') }}: <b>{{ client.city || '-' }}</b></span>
            <span class="mr-3">{{ $t('Country') }}: <b>{{ client.country || '-' }}</b></span>
            <span class="mr-3">{{ $t('Tax_Number') }}: <b>{{ client.tax_number || '-' }}</b></span>
          </div>
        </div>
        <div class="text-right">
          <div class="text-white small mb-1">
            <lucide-icon class="mr-1" name="mail" />{{ client.email || '-' }} &nbsp;|&nbsp;
            <lucide-icon class="mr-1" name="phone" />{{ client.phone || '-' }}
          </div>
          <b-button size="sm" variant="light" class="mr-2" @click="$router.push({ name: 'Customers' })">
            <lucide-icon class="mr-1" name="chevron-left" /> {{ $t('Back') }}
          </b-button>
          <b-button size="sm" variant="primary" class="mr-2" :disabled="exportingPdf" @click="exportPdf">
            <lucide-icon class="mr-1" name="file-down" />
            <span v-if="!exportingPdf">{{ $t('Download_PDF') }}</span>
            <span v-else>{{ $t('Generating') }}</span>
          </b-button>
        </div>
      </div>

      <div class="p-3">
        <b-row>
          <b-col md="3" class="mb-2">
            <div class="kpi-card">
              <div class="kpi-label">{{ $t('Sales_Grand') }}</div>
              <div class="kpi-value">{{ money(stats.salesGrand) }}</div>
            </div>
          </b-col>
          <b-col md="3" class="mb-2">
            <div class="kpi-card">
              <div class="kpi-label">{{ $t('Sales_Paid') }}</div>
              <div class="kpi-value">{{ money(stats.salesPaid) }}</div>
            </div>
          </b-col>
          <b-col md="3" class="mb-2">
            <div class="kpi-card">
              <div class="kpi-label">{{ $t('Opening_Balance') }}</div>
              <div class="kpi-value" :class="stats.openingBalance > 0 ? 'text-danger' : 'text-success'">{{ money(stats.openingBalance) }}</div>
            </div>
          </b-col>
          <b-col md="3" class="mb-2">
            <div class="kpi-card">
              <div class="kpi-label">{{ $t('Sales_Due') }}</div>
              <div class="kpi-value" :class="stats.salesDue > 0 ? 'text-danger' : 'text-success'">{{ money(stats.salesDue) }}</div>
            </div>
          </b-col>
          <b-col md="3" class="mb-2">
            <div class="kpi-card">
              <div class="kpi-label">{{ $t('Total_Due') }}</div>
              <div class="kpi-value" :class="stats.totalDue > 0 ? 'text-danger' : 'text-success'">{{ money(stats.totalDue) }}</div>
            </div>
          </b-col>
          <b-col md="3" class="mb-2">
            <div class="kpi-card">
              <div class="kpi-label">{{ $t('Returns_Due') }}</div>
              <div class="kpi-value text-warning">{{ money(stats.returnsDue) }}</div>
            </div>
          </b-col>
          <b-col md="3" class="mb-2">
            <div class="kpi-card">
              <div class="kpi-label">{{ $t('Payments_Total') }}</div>
              <div class="kpi-value">{{ money(stats.paymentsTotal) }}</div>
            </div>
          </b-col>
          <b-col md="3" class="mb-2">
            <div class="kpi-card">
              <div class="kpi-label">{{ $t('Credit_Limit') }}</div>
              <div class="kpi-value text-info">{{ (client.credit_limit && client.credit_limit > 0) ? money(client.credit_limit) : $t('No_limit') }}</div>
            </div>
          </b-col>
        </b-row>
      </div>
    </b-card>

    <div v-show="!pageLoading">
      <b-card class="soft-shadow">
        <b-tabs v-model="activeTab" lazy>
          <!-- SALES -->
          <b-tab :title="$t('Sales')">
            <ListToolbar
              :placeholder="$t('Search_sales_ph')"
              v-model="sales.search"
              :limit.sync="sales.limit"
              :per-page-options="perPageOptions"
              @search="fetchSales"
              @reset="resetSales"
            />

            <div class="mb-2 small text-muted">
              {{ $t('Page_totals') }} —
              {{ $t('Grand') }}: <b>{{ money(sales.totals.grand) }}</b>,
              {{ $t('Paid') }}: <b>{{ money(sales.totals.paid) }}</b>,
              {{ $t('Due') }}: <b>{{ money(sales.totals.due) }}</b>
            </div>

            <div v-if="sales.loading" class="text-center p-3"><b-spinner /></div>
            <b-table v-else :items="sales.items" :fields="salesFields" striped hover responsive small head-variant="light" class="table-modern">
              <template #cell(GrandTotal)="{ item }">{{ money(item.GrandTotal) }}</template>
              <template #cell(paid_amount)="{ item }">{{ money(item.paid_amount) }}</template>
              <template #cell(due)="{ item }">{{ money(item.due) }}</template>
              <template #cell(payment_status)="{ item }"><b-badge :variant="paymentBadge(item.payment_status)">{{ item.payment_status }}</b-badge></template>
              <template #cell(statut)="{ item }"><b-badge :variant="docStatusBadge(item.statut)">{{ item.statut }}</b-badge></template>
            </b-table>

            <Pager
              :page.sync="sales.page"
              :limit="sales.limit"
              :total-rows="sales.totalRows"
              @change="fetchSales"
            />
          </b-tab>

          <!-- PAYMENTS -->
          <b-tab :title="$t('Payments')">
            <ListToolbar
              :placeholder="$t('Search_payments_ph')"
              v-model="payments.search"
              :limit.sync="payments.limit"
              :per-page-options="perPageOptions"
              @search="fetchPayments"
              @reset="resetPayments"
            />

            <div class="mb-2 small text-muted">
              {{ $t('Page_total') }} — {{ $t('Payments') }}: <b>{{ money(payments.pageTotal) }}</b>
            </div>

            <div v-if="payments.loading" class="text-center p-3"><b-spinner /></div>
            <b-table v-else :items="payments.items" :fields="paymentsFields" striped hover responsive small head-variant="light" class="table-modern">
              <template #cell(payment_type)="{ item }">
                <b-badge v-if="item.payment_type === 'opening_balance'" variant="info">{{ $t('Opening_Balance') }}</b-badge>
                <b-badge v-else variant="success">{{ $t('Sale') }}</b-badge>
              </template>
              <template #cell(Sale_Ref)="{ item }">
                <span v-if="item.Sale_Ref">{{ item.Sale_Ref }}</span>
                <span v-else class="text-muted">-</span>
              </template>
              <template #cell(montant)="{ item }">{{ money(item.montant) }}</template>
            </b-table>

            <Pager
              :page.sync="payments.page"
              :limit="payments.limit"
              :total-rows="payments.totalRows"
              @change="fetchPayments"
            />
          </b-tab>

          <!-- QUOTATIONS -->
          <b-tab :title="$t('Quotations')">
            <ListToolbar
              :placeholder="$t('Search_quotations_ph')"
              v-model="quotations.search"
              :limit.sync="quotations.limit"
              :per-page-options="perPageOptions"
              @search="fetchQuotations"
              @reset="resetQuotations"
            />

            <div class="mb-2 small text-muted">
              {{ $t('Page_total') }} — {{ $t('Grand') }}: <b>{{ money(quotations.pageGrand) }}</b>
            </div>

            <div v-if="quotations.loading" class="text-center p-3"><b-spinner /></div>
            <b-table v-else :items="quotations.items" :fields="quotationsFields" striped hover responsive small head-variant="light" class="table-modern">
              <template #cell(GrandTotal)="{ item }">{{ money(item.GrandTotal) }}</template>
              <template #cell(statut)="{ item }"><b-badge :variant="docStatusBadge(item.statut)">{{ item.statut }}</b-badge></template>
            </b-table>

            <Pager
              :page.sync="quotations.page"
              :limit="quotations.limit"
              :total-rows="quotations.totalRows"
              @change="fetchQuotations"
            />
          </b-tab>

          <!-- RETURNS -->
          <b-tab :title="$t('Returns')">
            <ListToolbar
              :placeholder="$t('Search_returns_ph')"
              v-model="returns.search"
              :limit.sync="returns.limit"
              :per-page-options="perPageOptions"
              @search="fetchReturns"
              @reset="resetReturns"
            />

            <div class="mb-2 small text-muted">
              {{ $t('Page_totals') }} —
              {{ $t('Grand') }}: <b>{{ money(returns.totals.grand) }}</b>,
              {{ $t('Paid') }}: <b>{{ money(returns.totals.paid) }}</b>,
              {{ $t('Due') }}: <b>{{ money(returns.totals.due) }}</b>
            </div>

            <div v-if="returns.loading" class="text-center p-3"><b-spinner /></div>
            <b-table v-else :items="returns.items" :fields="returnsFields" striped hover responsive small head-variant="light" class="table-modern">
              <template #cell(GrandTotal)="{ item }">{{ money(item.GrandTotal) }}</template>
              <template #cell(paid_amount)="{ item }">{{ money(item.paid_amount) }}</template>
              <template #cell(due)="{ item }">{{ money(item.due) }}</template>
              <template #cell(payment_status)="{ item }"><b-badge :variant="paymentBadge(item.payment_status)">{{ item.payment_status }}</b-badge></template>
              <template #cell(statut)="{ item }"><b-badge :variant="docStatusBadge(item.statut)">{{ item.statut }}</b-badge></template>
            </b-table>

            <Pager
              :page.sync="returns.page"
              :limit="returns.limit"
              :total-rows="returns.totalRows"
              @change="fetchReturns"
            />
          </b-tab>
        </b-tabs>
      </b-card>
    </div>
  </div>
</template>

<script>

// --- Small reusable toolbar for search + per-page ---
const ListToolbar = {
  name: 'ListToolbar',
  props: {
    placeholder: String,
    value: String, // v-model: search
    limit: Number,
    perPageOptions: { type: Array, default: () => ([10,25,50,100].map(v=>({value:v, text:String(v)}))) }
  },
  model: { prop: 'value', event: 'input' },
  methods: {
    emitSearch(){ this.$emit('search') },
    emitReset(){ this.$emit('input', ''); this.$emit('search'); this.$emit('reset') }
  },
  render(h){
    return h('div',{class:'toolbar'},[
      h('b-form-input',{
        class:'mr-2',
        props:{ value:this.value, placeholder:this.placeholder },
        on:{ input:v=>this.$emit('input', v), keyup:e=>{ if(e.key==='Enter') this.emitSearch() } }
      }),
      h('b-button',{class:'mr-2 mt-2',props:{size:'sm',variant:'primary'},on:{click:this.emitSearch}}, this.$parent.$t('Search')),
      h('b-button',{class:'mr-2 mt-2',props:{size:'sm',variant:'outline-secondary'},on:{click:this.emitReset}}, this.$parent.$t('Reset')),
      h('div',{class:'ml-auto d-flex align-items-center'},[
        h('span',{class:'mr-2 small text-muted'}, this.$parent.$t('Per_page')),
        h('b-form-select',{
          class:'w-auto',
          props:{ value:this.limit, options:this.perPageOptions, size:'sm' },
          on:{ input:v=>this.$emit('update:limit', v) }
        })
      ])
    ])
  }
}

// --- Simple pager wrapper ---
const Pager = {
  name: 'Pager',
  props: { page:Number, limit:Number, totalRows:Number },
  methods:{ onInput(){ this.$emit('change') } },
  render(h){
    const totalPages = Math.max(1, Math.ceil((this.totalRows||0) / (this.limit||10)))
    return h('div',{class:'pager'},[
      h('small',{class:'text-muted'}, `${this.$parent.$t('Page')} ${this.page} ${this.$parent.$t('Of')} ${totalPages}`),
      h('b-pagination',{
        props:{ value:this.page, totalRows:this.totalRows, perPage:this.limit, size:'sm', align:'right' },
        on:{ input:v=>{ this.$emit('update:page', v); this.onInput() } }
      })
    ])
  }
}

import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  name: 'CustomerLedgerRefactored',
  components: { ListToolbar, Pager },
  props: { id: [String, Number] },
  metaInfo () { return { title: this.$t('Customer_Ledger') } },
  data(){
    const makeList = () => ({ loading:false, items:[], totalRows:0, page:1, limit:10, search:'', totals:{}, pageTotal:0 })
    return {
      pageLoading:false,
      exportingPdf:false,
      activeTab:0,
      price_format_key: null,
      client:{ id:null, name:'', email:'', phone:'', code:'', adresse:'', country:'', city:'', tax_number:'', salesGrand:0, salesPaid:0, sale_due:0, paymentsTotal:0, return_due:0, opening_balance:0, credit_limit:0, netBalance:0 },
      sales: makeList(),
      payments: makeList(),
      quotations: makeList(),
      returns: makeList(),
      // timers for debounce
      _timers: { sales:null, payments:null, quotations:null, returns:null },
      perPageOptions:[{value:10,text:'10'},{value:25,text:'25'},{value:50,text:'50'},{value:100,text:'100'}],
      // Table fields (translated)
      salesFields: [
        { key:'date', label: this.$t('Date') },
        { key:'Ref', label: this.$t('Sale_Ref') },
        { key:'warehouse_name', label: this.$t('Warehouse') },
        { key:'statut', label: this.$t('Status') },
        { key:'GrandTotal', label: this.$t('Grand_Total'), class:'text-right' },
        { key:'paid_amount', label: this.$t('Paid'), class:'text-right' },
        { key:'due', label: this.$t('Due'), class:'text-right' },
        { key:'payment_status', label: this.$t('Payment_Status') },
        { key:'shipping_status', label: this.$t('Shipping_Status') },
      ],
      paymentsFields: [
        { key:'date', label: this.$t('Date') },
        { key:'Ref', label: this.$t('Payment_Ref') },
        { key:'payment_type', label: this.$t('Type') },
        { key:'Sale_Ref', label: this.$t('Sale_Ref') },
        { key:'payment_method', label: this.$t('Method') },
        { key:'montant', label: this.$t('Amount'), class:'text-right' },
      ],
      quotationsFields: [
        { key:'date', label: this.$t('Date') },
        { key:'Ref', label: this.$t('Quotation_Ref') },
        { key:'warehouse_name', label: this.$t('Warehouse') },
        { key:'statut', label: this.$t('Status') },
        { key:'GrandTotal', label: this.$t('Grand_Total'), class:'text-right' },
      ],
      returnsFields: [
        { key:'Ref', label: this.$t('Return_Ref') },
        { key:'statut', label: this.$t('Status') },
        { key:'client_name', label: this.$t('Customer') },
        { key:'sale_ref', label: this.$t('Sale_Ref') },
        { key:'warehouse_name', label: this.$t('Warehouse') },
        { key:'GrandTotal', label: this.$t('Grand_Total'), class:'text-right' },
        { key:'paid_amount', label: this.$t('Paid'), class:'text-right' },
        { key:'due', label: this.$t('Due'), class:'text-right' },
        { key:'payment_status', label: this.$t('Payment_Status') },
      ],
    }
  },
  computed:{
    clientInitials(){
      const n = (this.client.name || '').trim();
      if (!n) return 'C';
      const p = n.split(' ').filter(Boolean);
      return ((p[0]?.[0] || '') + (p.length>1 ? p[p.length-1][0] : '')).toUpperCase() || 'C';
    },
    stats(){
      return {
        salesGrand : this.client.salesGrand || 0,
        salesPaid  : this.client.salesPaid  || 0,
        salesDue   : this.client.sale_due   || 0,
        returnsDue : this.client.return_due || 0,
        paymentsTotal: this.client.paymentsTotal || 0,
        openingBalance: this.client.opening_balance || 0,
        totalDue: this.client.netBalance || 0
      }
    }
  },
  watch:{
    // per-page changes
    'sales.limit'(v){ this.sales.page = 1; this.fetchSales() },
    'payments.limit'(v){ this.payments.page = 1; this.fetchPayments() },
    'quotations.limit'(v){ this.quotations.page = 1; this.fetchQuotations() },
    'returns.limit'(v){ this.returns.page = 1; this.fetchReturns() },
    // debounced search
    'sales.search'(v){ this._debounce('sales', this.fetchSales) },
    'payments.search'(v){ this._debounce('payments', this.fetchPayments) },
    'quotations.search'(v){ this._debounce('quotations', this.fetchQuotations) },
    'returns.search'(v){ this._debounce('returns', this.fetchReturns) },
  },
  created(){
    this.pageLoading = true
    this.fetchClientBrief()
      .then(() => Promise.all([
        this.fetchSales(),
        this.fetchPayments(),
        this.fetchQuotations(),
        this.fetchReturns()
      ]))
      .finally(()=>{ this.pageLoading = false })
  },
  methods:{
    // ---- utils ----
    money(v){
      try{
        const decimals = 2;
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(v, decimals, effectiveKey);
      }catch(e){
        const n = Number(v||0);
        return n.toLocaleString(undefined,{ minimumFractionDigits:2, maximumFractionDigits:2 });
      }
    },
    paymentBadge(status){
      const s = String(status||'').toLowerCase().trim()
      if (s.includes('unpaid')) return 'danger'
      if (s.includes('partial')) return 'warning'
      if (s.includes('paid')) return 'success'
      return 'secondary'
    },
    docStatusBadge(st){
      const s = String(st||'').toLowerCase()
      if (s.includes('completed') || s.includes('approved')) return 'success'
      if (s.includes('pending')) return 'warning'
      if (s.includes('sent')) return 'info'
      if (s.includes('canceled') || s.includes('cancelled') || s.includes('rejected')) return 'danger'
      return 'secondary'
    },
    _debounce(key, fn, delay=350){
      clearTimeout(this._timers[key]);
      this._timers[key] = setTimeout(()=>fn.call(this), delay)
    },

    // ---- header ----
    async fetchClientBrief(){
      try {
        const { data } = await axios.get(`/clients/${this.id}/brief`)
        this.client = { ...this.client, ...(data || {}) }
      } catch(e){ /* silent */ }
    },

    // ---- list fetch helpers ----
    async _fetchList(endpoint, state, extraParams = {}, post = null){
      state.loading = true
      try{
        const { data } = await axios.get(endpoint, { params: { id: this.id, limit: state.limit, page: state.page, search: state.search, ...extraParams } })
        return data
      } finally { state.loading = false }
    },

    // Sales
    async fetchSales(){
      const data = await this._fetchList('/sales_client', this.sales)
      const items = Array.isArray(data?.sales) ? data.sales : []
      this.sales.items = items
      this.sales.totalRows = Number(data?.totalRows || 0)
      // page totals
      let grand=0, paid=0, due=0
      for (let i=0;i<items.length;i++){ grand+=Number(items[i].GrandTotal||0); paid+=Number(items[i].paid_amount||0); due+=Number(items[i].due||0) }
      this.sales.totals = { grand, paid, due }
      // backfill name if empty
      if (!this.client.name && items.length){ this.client.name = items[0].client_name || this.client.name }
    },
    resetSales(){ this.sales.search=''; this.sales.page=1; this.fetchSales() },

    // Payments
    async fetchPayments(){
      const data = await this._fetchList('/payments_client', this.payments)
      const items = Array.isArray(data?.payments) ? data.payments : []
      this.payments.items = items
      this.payments.totalRows = Number(data?.totalRows || 0)
      this.payments.pageTotal = items.reduce((t,x)=>t+Number(x.montant||0),0)
    },
    resetPayments(){ this.payments.search=''; this.payments.page=1; this.fetchPayments() },

    // Quotations
    async fetchQuotations(){
      const data = await this._fetchList('/quotations_client', this.quotations)
      const items = Array.isArray(data?.quotations) ? data.quotations : []
      this.quotations.items = items
      this.quotations.totalRows = Number(data?.totalRows || 0)
      this.quotations.pageGrand = items.reduce((t,x)=>t+Number(x.GrandTotal||0),0)
    },
    resetQuotations(){ this.quotations.search=''; this.quotations.page=1; this.fetchQuotations() },

    // Returns
    async fetchReturns(){
      const data = await this._fetchList('/returns_client', this.returns)
      const items = Array.isArray(data?.returns_customer) ? data.returns_customer : []
      this.returns.items = items
      this.returns.totalRows = Number(data?.totalRows || 0)
      let grand=0, paid=0, due=0
      for (let i=0;i<items.length;i++){ grand+=Number(items[i].GrandTotal||0); paid+=Number(items[i].paid_amount||0); due+=Number(items[i].due||0) }
      this.returns.totals = { grand, paid, due }
    },
    resetReturns(){ this.returns.search=''; this.returns.page=1; this.fetchReturns() },

    // PDF (full ledger)
    async exportPdf(){
      this.exportingPdf = true
      try{
        const res = await axios.get('/client_ledger_pdf', {
          params: { id: this.id },
          responseType: 'arraybuffer',
          headers: { Accept: 'application/pdf' }
        })
        const blob = new Blob([res.data], { type:'application/pdf' })
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `customer_ledger_${this.id}.pdf`
        document.body.appendChild(a)
        a.click()
        a.remove()
        window.URL.revokeObjectURL(url)
      } catch (err) {
        const fallbackMsg = (this.$t && this.$t('Failed_to_export') !== 'Failed_to_export') ? this.$t('Failed_to_export') : 'Failed to export PDF'
        if (this.$bvToast) {
          this.$bvToast.toast(fallbackMsg, { title: this.$t ? this.$t('Error') : 'Error', variant: 'danger', solid: true })
        } else {
          // eslint-disable-next-line no-alert
          alert(fallbackMsg)
        }
      } finally {
        this.exportingPdf = false
      }
    }
  }
}
</script>

<style scoped>
.soft-shadow { box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
.header-hero { background: linear-gradient(135deg, #6b8dfc 0%, #88aafc 100%); color: #fff; }
.avatar-circle { width:56px; height:56px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:20px; color:#fff; backdrop-filter: saturate(120%); }
.kpi-card { background:#ffffff; border-radius:14px; padding:14px 16px; border:1px solid #eef2f7; box-shadow: 0 4px 16px rgba(17,24,39,0.06); }
.kpi-label { font-size:12px; color:#6b7280; }
.kpi-value { font-size:18px; font-weight:700; color:#111827; letter-spacing: 0.2px; }
.toolbar { display:flex; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap: 8px; }
.toolbar .form-control { min-width: 260px; }
.pager { display:flex; justify-content:space-between; align-items:center; margin-top:10px; }

/* Modern table appearance */
::v-deep .table-modern thead th { background: #f8fafc; color:#374151; font-weight:600; border-bottom: 1px solid #e5e7eb; }
::v-deep .table-modern tbody tr:hover { background: #f9fbff; }
::v-deep .table-modern td, ::v-deep .table-modern th { vertical-align: middle; }
::v-deep .badge { font-weight: 600; letter-spacing: .2px; }

/* Full Page Loading Overlay */
.full-page-loading {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.95);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(2px);
}

.loading-content {
  text-align: center;
}

.loading-text {
  color: #6b7280;
  font-size: 16px;
  font-weight: 500;
}

.full-page-loading .spinner {
  width: 50px;
  height: 50px;
  border-width: 4px;
}
</style>
