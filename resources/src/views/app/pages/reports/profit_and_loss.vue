<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('ProfitandLoss')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="toolbar-card shadow-soft mb-3 border-0">
      <div class="d-flex flex-wrap align-items-center">
        <!-- Date Range -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('DateRange')}}</label>
          <date-range-picker
            v-model="dateRange"
            :startDate="dateRange.startDate"
            :endDate="dateRange.endDate"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="picker.opens" 
            :drops="picker.drops" 
            :parentEl="'body'"
            @update="onDateChange"
          >
            <template v-slot:input="pickerSlot">
              <b-button variant="light" class="btn-pill">
                <lucide-icon class="mr-1" name="calendar-days" />
                {{ fmtDate(pickerSlot.startDate) }} — {{ fmtDate(pickerSlot.endDate) }}
              </b-button>
            </template>
          </date-range-picker>

        </div>

        <!-- Quick ranges -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('QuickRanges')}}</label>
          <div class="btn-group quick-ranges">
            <b-button size="sm" variant="outline-primary" @click="applyQuick('today')">{{ $t('Today') || 'Today' }}</b-button>
            <b-button size="sm" variant="outline-primary" @click="applyQuick('yesterday')">{{ $t('Yesterday') || 'Yesterday' }}</b-button>
            <b-button size="sm" variant="outline-primary" @click="applyQuick('7d')">7D</b-button>
            <b-button size="sm" variant="outline-primary" @click="applyQuick('30d')">30D</b-button>
            <b-button size="sm" variant="outline-primary" @click="applyQuick('90d')">90D</b-button>
            <b-button size="sm" variant="outline-primary" @click="applyQuick('mtd')">{{$t('MTD')}}</b-button>
            <b-button size="sm" variant="outline-primary" @click="applyQuick('ytd')">{{$t('YTD')}}</b-button>
          </div>
        </div>

        <!-- Warehouse -->
        <div class="mr-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('warehouse')}}</label>
          <v-select
            class="w-280"
            @input="onWarehouseChange"
            v-model="warehouse_id"
            :reduce="opt => opt.value"
            :placeholder="$t('Choose_Warehouse')"
            :options="warehouses.map(w => ({label: w.name, value: w.id}))"
            :clearable="true"
          />
        </div>

        <div class="ml-auto mb-2">
          <b-button @click="printTableOnly()" variant="outline-secondary" class="btn-pill mr-2">
            <lucide-icon class="mr-1" name="printer" /> {{ $t("print") }}
          </b-button>
          <b-button variant="primary" class="btn-pill" @click="fetchPnl">
            <lucide-icon class="mr-1" name="refresh-cw" />{{$t('Refresh')}}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading skeletons -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="4" v-for="n in 6" :key="n" class="mb-3">
          <b-skeleton-img class="rounded-xl shadow-soft" height="110px" />
        </b-col>
      </b-row>
    </div>

    <!-- Content -->
    <b-row v-else>
      <b-col md="12" class="mb-3">
        <b-alert show variant="light" class="shadow-soft border-0">
          <div class="d-flex align-items-center">
            <div class="mr-2"><lucide-icon class="text-primary" name="clock" /></div>
            <div>
              <strong>{{ fmtDate(dateRange.startDate) }}</strong> — <strong>{{ fmtDate(dateRange.endDate) }}</strong>
              <span v-if="warehouseLabel" class="ml-2 badge badge-light">{{ warehouseLabel }}</span>
            </div>
          </div>
        </b-alert>
      </b-col>

      <!-- KPI Tiles -->
      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="banknote" :label="$t('Sales')" :sub="`(${num(infos.sales_count)})`" :value="money(infos.sales_sum)" theme="blue" />
      </b-col>
      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="shopping-cart" :label="$t('Purchases')" :sub="`(${num(infos.purchases_count)})`" :value="money(infos.purchases_sum)" theme="teal" />
      </b-col>
      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="repeat" :label="$t('SalesReturn')" :sub="`(${num(infos.returns_sales_count)})`" :value="money(infos.returns_sales_sum)" theme="orange" />
      </b-col>
      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="undo" :label="$t('PurchasesReturn')" :sub="`(${num(infos.returns_purchases_count)})`" :value="money(infos.returns_purchases_sum)" theme="purple" />
      </b-col>

      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="trending-up" :label="$t('Revenue')" :value="money(infos.total_revenue)" theme="indigo"
                  :hint="`${$t('Sales')} – ${$t('SalesReturn')}`" />
      </b-col>
      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="wallet" :label="$t('PaiementsReceived')" :value="money(infos.payment_received)" theme="green"
                  :hint="`${$t('PaymentsSales')} + ${$t('PurchasesReturn')}`" />
      </b-col>
      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="user-minus" :label="$t('PaiementsSent')" :value="money(infos.payment_sent)" theme="rose"
                  :hint="`${$t('PaymentsPurchases')} + ${$t('SalesReturn')} + ${$t('Expenses')}`" />
      </b-col>
      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="receipt" :label="$t('Expenses')" :value="money(infos.expenses_sum)" theme="rose" />
      </b-col>
      <b-col md="6" sm="6" class="mb-3">
        <StatTile icon="banknote" :label="$t('PaiementsNet')" :value="money(infos.paiement_net)" theme="slate"
                  :hint="`${$t('Recieved')} – ${$t('Sent')}`" />
      </b-col>

      <!-- Profit cards -->
      <b-col md="6" class="mb-3">
        <StatTile icon="bar-chart" :label="$t('ProfitNet') + ' (FIFO)'" :value="money(infos.profit_fifo)" theme="cyan"
                  :hint="`${$t('Sales')} – ${$t('Product_Cost')} – ${$t('Expenses')}`" />
      </b-col>

      <b-col md="6" class="mb-3">
        <StatTile icon="bar-chart" :label="$t('ProfitNet') + ' (' + $t('AverageCost') + ')'" :value="money(infos.profit_average_cost)" theme="amber"
                  :hint="`${$t('Sales')} – ${$t('Product_Cost')} – ${$t('Expenses')}`" />
      </b-col>
    </b-row>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

const StatTile = {
  name: "StatTile",
  functional: true,
  props: { icon:String, label:String, sub:String, value:[String,Number], hint:String, theme:{type:String,default:'blue'} },
  render(h,{props}) {
    return h('div',{class:['stat-card',`theme-${props.theme}`,'shadow-soft','rounded-xl','mb-2']},[
      h('div',{class:'stat-inner'},[
        h('div',{class:'stat-icon'},[ h('lucide-icon', { props: { name: props.icon } }) ]),
        h('div',{class:'stat-content'},[
          h('div',{class:'stat-label'},props.label),
          props.sub ? h('div',{class:'stat-sub text-muted'},props.sub) : null,
          h('div',{class:'stat-value'},props.value),
          props.hint ? h('div',{class:'stat-hint text-muted'},props.hint) : null
        ])
      ])
    ]);
  }
};

export default {
  metaInfo: { title: "Profit & Loss" },
  components: {
    "date-range-picker": DateRangePicker,
    StatTile
  },
  data() {
    const start = moment().startOf('day').toDate();
    const end   = moment().endOf('day').toDate();
    return {
      warehouses: [],
      warehouse_id: null,
      isLoading: true,
      infos: {},
      dateRange: { startDate: start, endDate: end }, // default: Today
      picker: { opens: 'right', drops: 'auto' },
      locale: {
        Label: this.$t("Apply") || "Apply",
        cancelLabel: this.$t("Cancel") || "Cancel",
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange") || "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },
    warehouseLabel() {
      const w = this.warehouses.find(w => w.id === this.warehouse_id);
      return w ? w.name : null;
    },
  },

  mounted() {
    this.updatePickerPlacement();
    window.addEventListener('resize', this.updatePickerPlacement);
  },
  beforeDestroy() { // or unmounted() in Vue 3
    window.removeEventListener('resize', this.updatePickerPlacement);
  },
  methods: {
    updatePickerPlacement() {
      const isXs = window.matchMedia('(max-width: 576px)').matches;
      this.picker.opens = isXs ? 'center' : 'right';
      this.picker.drops = 'auto'; // lets it choose up/down to stay visible
    },

    fmtDate(d){ return moment(d).format('YYYY-MM-DD'); },
    num(v){ const n = parseFloat(v || 0); return isNaN(n)?0:n; },
    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing Intl.NumberFormat behavior to preserve current behavior.
    money(v){
      try {
        const n = this.num(v);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        const formatted = formatPriceDisplayHelper(n, 2, effectiveKey);
        return `${this.currency} ${formatted}`;
      } catch(e){
        try {
          return new Intl.NumberFormat(undefined,{style:'currency',currency:this.currency}).format(this.num(v));
        } catch(e2) {
          return `${this.currency} ${this.num(v).toLocaleString()}`;
        }
      }
    },
    shortMoney(v){
      const n = this.num(v);
      return new Intl.NumberFormat(undefined,{ notation:'compact', maximumFractionDigits:1 }).format(n);
    },

    onDateChange(){ this.fetchPnl(); },
    onWarehouseChange(){ this.fetchPnl(); },

    applyQuick(kind){
      const now = moment();
      let start, end;

      if (kind === 'today')     { start = now.clone().startOf('day'); end = now.clone().endOf('day'); }
      if (kind === 'yesterday') { start = now.clone().subtract(1,'day').startOf('day'); end = now.clone().subtract(1,'day').endOf('day'); }
      if (kind === '7d')        { start = now.clone().subtract(6,'days').startOf('day'); end = now.clone().endOf('day'); }
      if (kind === '30d')       { start = now.clone().subtract(29,'days').startOf('day'); end = now.clone().endOf('day'); }
      if (kind === '90d')       { start = now.clone().subtract(89,'days').startOf('day'); end = now.clone().endOf('day'); }
      if (kind === 'mtd')       { start = now.clone().startOf('month'); end = now.clone().endOf('day'); }
      if (kind === 'ytd')       { start = now.clone().startOf('year'); end = now.clone().endOf('day'); }

      this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      this.fetchPnl();
    },

    //------ Print Table Only
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("ProfitandLoss")}`;
      const dateRangeText = `${this.fmtDate(this.dateRange.startDate)} — ${this.fmtDate(this.dateRange.endDate)}`;
      const warehouseText = this.warehouseLabel ? ` (${this.warehouseLabel})` : '';

      // Build table HTML with all KPI information
      let tableHtml = `<table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 16px;">`;
      
      // Header section
      tableHtml += `<thead><tr><th colspan="2" style="border: 1px solid #ddd; padding: 12px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${title}</th></tr>`;
      tableHtml += `<tr><td colspan="2" style="border: 1px solid #ddd; padding: 8px; background-color: #f9f9f9; font-size: 10px;">${dateRangeText}${warehouseText}</td></tr>`;
      tableHtml += `</thead>`;

      // Body with all KPIs
      tableHtml += `<tbody>`;
      
      // Sales
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 600;">${this.$t('Sales')} (${this.num(this.infos.sales_count)})</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.infos.sales_sum)}</td></tr>`;
      
      // Purchases
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 600;">${this.$t('Purchases')} (${this.num(this.infos.purchases_count)})</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.infos.purchases_sum)}</td></tr>`;
      
      // Sales Return
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 600;">${this.$t('SalesReturn')} (${this.num(this.infos.returns_sales_count)})</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.infos.returns_sales_sum)}</td></tr>`;
      
      // Purchases Return
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 600;">${this.$t('PurchasesReturn')} (${this.num(this.infos.returns_purchases_count)})</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.infos.returns_purchases_sum)}</td></tr>`;
      
      // Separator
      tableHtml += `<tr><td colspan="2" style="border: 1px solid #ddd; padding: 4px; background-color: #f5f5f5;"></td></tr>`;
      
      // Revenue
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 700; background-color: #eef0ff;">${this.$t('Revenue')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: 700; background-color: #eef0ff;">${this.money(this.infos.total_revenue)}</td></tr>`;
      
      // Payments Received
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 600;">${this.$t('PaiementsReceived')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.infos.payment_received)}</td></tr>`;
      
      // Payments Sent
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 600;">${this.$t('PaiementsSent')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.infos.payment_sent)}</td></tr>`;
      
      // Expenses
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 600;">${this.$t('Expenses')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.infos.expenses_sum)}</td></tr>`;
      
      // Payments Net
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 700; background-color: #eef2f7;">${this.$t('PaiementsNet')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: 700; background-color: #eef2f7;">${this.money(this.infos.paiement_net)}</td></tr>`;
      
      // Separator
      tableHtml += `<tr><td colspan="2" style="border: 1px solid #ddd; padding: 4px; background-color: #f5f5f5;"></td></tr>`;
      
      // Profit Net (FIFO)
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 700; background-color: #e6fbff;">${this.$t('ProfitNet')} (FIFO)</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: 700; background-color: #e6fbff;">${this.money(this.infos.profit_fifo)}</td></tr>`;
      
      // Profit Net (Average Cost)
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; font-weight: 700; background-color: #fff8e1;">${this.$t('ProfitNet')} (${this.$t('AverageCost')})</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: 700; background-color: #fff8e1;">${this.money(this.infos.profit_average_cost)}</td></tr>`;
      
      tableHtml += `</tbody></table>`;

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
      @media print { 
        body, body * { visibility: visible !important; }
        @page { size: A4; margin: 1cm; }
      }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 8px; font-size: 14px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f5f5f5; font-weight: bold; }
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

    fetchPnl(){
      NProgress.start(); NProgress.set(0.1);
      this.isLoading = true;
      const from = this.fmtDate(this.dateRange.startDate);
      const to   = this.fmtDate(this.dateRange.endDate);
      const wh   = this.warehouse_id || '';

      axios.get(`report/profit_and_loss?from=${from}&to=${to}&warehouse_id=${wh}`)
        .then(({data})=>{
          this.infos = data.data || {};
          this.warehouses = data.warehouses || [];
          this.isLoading = false; NProgress.done();
        })
        .catch(()=>{ this.isLoading = false; NProgress.done(); });
    }
  },
  created(){ this.fetchPnl(); }
};
</script>

<style scoped>
.rounded-xl { border-radius: 1rem; }
.shadow-soft { box-shadow: 0 12px 24px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.05); }
.toolbar-card { background: #fff; }
.btn-pill { border-radius: 999px; }
.w-280 { width: 280px; }

.stat-card {
  background: linear-gradient(135deg, var(--gradA,#f7f9ff), var(--gradB,#ffffff));
  padding: 14px 16px; min-height: 110px; position: relative;
}
.stat-inner { display: flex; align-items: center; }
.stat-icon {
  width: 48px; height: 48px; border-radius: 12px; margin-right: 12px;
  display:flex; align-items:center; justify-content:center;
  background: rgba(255,255,255,0.75);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.7), 0 1px 2px rgba(0,0,0,0.05);
}
.stat-icon i{ font-size: 22px; }
.stat-label{ font-size: .85rem; font-weight: 600; }
.stat-sub{ font-size: .75rem; margin-top: -2px; }
.stat-value{ font-size: 1.35rem; font-weight: 700; line-height: 1.2; margin-top: 2px; }
.stat-hint{ font-size: .75rem; margin-top: 2px; }

.theme-blue   { --gradA:#e6f0ff; --gradB:#ffffff; color:#0b5fff; }
.theme-teal   { --gradA:#e6fbf6; --gradB:#ffffff; color:#138f7a; }
.theme-orange { --gradA:#fff4e6; --gradB:#ffffff; color:#cc6b00; }
.theme-purple { --gradA:#f5e6ff; --gradB:#ffffff; color:#6a2ecc; }
.theme-indigo { --gradA:#eef0ff; --gradB:#ffffff; color:#3949ab; }
.theme-green  { --gradA:#edf9ee; --gradB:#ffffff; color:#2e7d32; }
.theme-rose   { --gradA:#ffe8f0; --gradB:#ffffff; color:#c2185b; }
.theme-slate  { --gradA:#eef2f7; --gradB:#ffffff; color:#455a64; }
.theme-cyan   { --gradA:#e6fbff; --gradB:#ffffff; color:#00838f; }
.theme-amber  { --gradA:#fff8e1; --gradB:#ffffff; color:#b28704; }

.formula { background: #fafbff; }

/* Keep the picker above navbars/modals/offcanvas */
.daterangepicker { z-index: 2055 !important; }

/* Mobile layout: full-width-ish and stacked */
@media (max-width: 576px) {
  .daterangepicker {
    left: 8px !important;
    right: 8px !important;
    width: auto !important;
    max-width: calc(100vw - 16px) !important;
  }
  .daterangepicker .drp-calendar,
  .daterangepicker .ranges {
    float: none !important;
    width: 100% !important;
  }

  /* Make Quick ranges wrap into two columns on small screens */
  .quick-ranges {
    display: flex !important;
    flex-wrap: wrap;
    width: 100%;
  }
  .quick-ranges .btn {
    flex: 1 1 calc(50% - 6px);
    margin-bottom: 6px;
  }
}


</style>
