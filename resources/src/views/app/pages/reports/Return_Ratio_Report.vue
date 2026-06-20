<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Return_Ratio_Report')" :folder="$t('Reports')" />

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
            <b-button size="sm" variant="outline-primary" @click="applyQuick('today')">{{ $t('Today') }}</b-button>
            <b-button size="sm" variant="outline-primary" @click="applyQuick('7d')">7D</b-button>
            <b-button size="sm" variant="outline-primary" @click="applyQuick('30d')">30D</b-button>
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
          <b-button variant="primary" class="btn-pill" @click="fetchData">
            <lucide-icon class="mr-1" name="refresh-cw" />{{$t('Refresh')}}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="6" v-for="n in 2" :key="n" class="mb-3">
          <b-skeleton-img class="rounded-xl shadow-soft" height="120px" />
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

      <!-- Sales Ratios -->
      <b-col md="6" class="mb-3">
        <RatioCard
          icon="banknote"
          :title="$t('Sales')"
          :total="money(data.sales_sum)"
          :returns-title="$t('SalesReturn')"
          :returns="money(data.returns_sales_sum)"
          :ratio="data.sales_return_ratio_pct"
          theme="blue"
        />
      </b-col>

      <!-- Purchase Ratios -->
      <b-col md="6" class="mb-3">
        <RatioCard
          icon="receipt-text"
          :title="$t('Purchases')"
          :total="money(data.purchases_sum)"
          :returns-title="$t('PurchasesReturn')"
          :returns="money(data.returns_purchases_sum)"
          :ratio="data.purchase_return_ratio_pct"
          theme="teal"
        />
      </b-col>

      <!-- Charts -->
      <b-col md="6" class="mb-3">
        <b-card class="shadow-soft border-0">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="m-0">{{ $t('Report') }} — {{ $t('Returns') }}</h6>
            <small class="text-muted">{{ fmtDate(dateRange.startDate) }} — {{ fmtDate(dateRange.endDate) }}</small>
          </div>
          <apexchart type="radialBar" height="320" :options="apexRadialOptions" :series="apexRadialSeries" />
        </b-card>
      </b-col>

      <b-col md="6" class="mb-3">
        <b-card class="shadow-soft border-0">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="m-0">{{ $t('Report') }} — {{ $t('Totals') }}</h6>
            <small class="text-muted">{{ fmtDate(dateRange.startDate) }} — {{ fmtDate(dateRange.endDate) }}</small>
          </div>
          <apexchart type="bar" height="320" :options="apexBarOptions" :series="apexBarSeries" />
        </b-card>
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
import VueApexCharts from "vue-apexcharts";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

const RatioCard = {
  name: 'RatioCard',
  functional: true,
  props: { icon:String, title:String, total:[String,Number], returnsTitle:String, returns:[String,Number], ratio:[String,Number], theme:{type:String,default:'blue'} },
  render(h,{props}){
    return h('b-card',{class:['ratio-card','shadow-soft','rounded-xl',`theme-${props.theme}`]},[
      h('div',{class:'d-flex align-items-center'},[
        h('div',{class:'ratio-icon mr-3'},[ h('lucide-icon', { props: { name: props.icon } }) ]),
        h('div',{class:'flex-fill'},[
          h('div',{class:'h6 mb-1'}, props.title),
          h('div',{class:'text-muted small mb-2'}, props.total),
          h('div',{class:'d-flex justify-content-between align-items-center'},[
            h('div',[ h('div',{class:'small text-muted'}, props.returnsTitle), h('div',{class:'font-weight-bold'}, props.returns) ]),
            h('div',{class:'text-right'},[
              h('div',{class:'small text-muted'}, 'Return Ratio'),
              h('div',{class:'display-6 font-weight-bold'}, `${props.ratio || 0}%`)
            ])
          ])
        ])
      ])
    ]);
  }
};

export default {
  metaInfo: { title: "Return Ratio Report" },
  components: { 'date-range-picker': DateRangePicker, RatioCard, apexchart: VueApexCharts },
  data(){
    const start = moment().startOf('day').toDate();
    const end   = moment().endOf('day').toDate();
    return {
      isLoading: true,
      dateRange: { startDate: start, endDate: end },
      picker: { opens: 'right', drops: 'auto' },
      locale: {
        Label: this.$t("Apply"),
        cancelLabel: this.$t("Cancel"),
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange"),
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },
      warehouses: [],
      warehouse_id: null,
      data: {
        sales_sum: 0,
        returns_sales_sum: 0,
        sales_return_ratio_pct: 0,
        purchases_sum: 0,
        returns_purchases_sum: 0,
        purchase_return_ratio_pct: 0,
      },
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    currency(){ return (this.currentUser && this.currentUser.currency) || "USD"; },
    warehouseLabel(){ const w = this.warehouses.find(w=>w.id===this.warehouse_id); return w ? w.name : null; },

    // ApexCharts: radial gauges for ratios
    apexRadialOptions(){
      return {
        chart: { type: 'radialBar' },
        plotOptions: {
          radialBar: {
            hollow: { size: '45%' },
            dataLabels: {
              name: { fontSize: '14px' },
              value: { formatter: (v)=> `${Number(v||0).toFixed(2)}%` }
            }
          }
        },
        labels: [ this.$t('Sales'), this.$t('Purchases') ],
        colors: ['#2563eb','#14b8a6']
      };
    },
    apexRadialSeries(){
      return [
        Number(this.data.sales_return_ratio_pct || 0),
        Number(this.data.purchase_return_ratio_pct || 0)
      ];
    },

    // ApexCharts: bar comparing totals vs returns
    apexBarOptions(){
      return {
        chart: { type: 'bar', stacked: false, toolbar: { show:false } },
        plotOptions: { bar: { horizontal: false, columnWidth: '45%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: [ this.$t('Sales'), this.$t('Purchases') ] },
        yaxis: { labels: { formatter: (v)=> this.shortMoney(v) } },
        tooltip: { y: { formatter: (v)=> this.money(v) } },
        legend: { position: 'top' }
      };
    },
    apexBarSeries(){
      return [
        { name: this.$t('Total'), data: [ Number(this.data.sales_sum||0), Number(this.data.purchases_sum||0) ] },
        { name: this.$t('Returns'), data: [ Number(this.data.returns_sales_sum||0), Number(this.data.returns_purchases_sum||0) ] }
      ];
    }
  },
  mounted(){ this.updatePickerPlacement(); window.addEventListener('resize', this.updatePickerPlacement); },
  beforeDestroy(){ window.removeEventListener('resize', this.updatePickerPlacement); },
  methods: {
    updatePickerPlacement(){ const isXs = window.matchMedia('(max-width: 576px)').matches; this.picker.opens = isXs ? 'center':'right'; this.picker.drops = 'auto'; },
    fmtDate(d){ return moment(d).format('YYYY-MM-DD'); },
    num(v){ const n = parseFloat(v||0); return isNaN(n)?0:n; },
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
      } catch(e) {
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
    onDateChange(){ this.fetchData(); },
    onWarehouseChange(){ this.fetchData(); },
    applyQuick(kind){
      const now = moment(); let start,end;
      if(kind==='today'){ start = now.clone().startOf('day'); end = now.clone().endOf('day'); }
      if(kind==='7d'){ start = now.clone().subtract(6,'days').startOf('day'); end = now.clone().endOf('day'); }
      if(kind==='30d'){ start = now.clone().subtract(29,'days').startOf('day'); end = now.clone().endOf('day'); }
      if(kind==='ytd'){ start = now.clone().startOf('year'); end = now.clone().endOf('day'); }
      this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      this.fetchData();
    },
    //------ Print Table Only
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Return_Ratio_Report")}`;
      const dateRangeText = `${this.fmtDate(this.dateRange.startDate)} — ${this.fmtDate(this.dateRange.endDate)}`;
      const warehouseText = this.warehouseLabel ? ` (${this.warehouseLabel})` : '';

      // Build table HTML with all return ratio information
      let tableHtml = `<table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 16px;">`;
      
      // Header section
      tableHtml += `<thead><tr><th colspan="4" style="border: 1px solid #ddd; padding: 12px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${title}</th></tr>`;
      tableHtml += `<tr><td colspan="4" style="border: 1px solid #ddd; padding: 8px; background-color: #f9f9f9; font-size: 10px;">${dateRangeText}${warehouseText}</td></tr>`;
      tableHtml += `</thead>`;

      // Body with all KPIs
      tableHtml += `<tbody>`;
      
      // Separator
      tableHtml += `<tr><td colspan="4" style="border: 1px solid #ddd; padding: 4px; background-color: #f5f5f5;"></td></tr>`;
      
      // Sales Section
      tableHtml += `<tr><td colspan="4" style="border: 1px solid #ddd; padding: 8px; font-weight: 700; background-color: #e6f0ff;">${this.$t('Sales')}</td></tr>`;
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; padding-left: 24px;">${this.$t('Sales')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.data.sales_sum)}</td><td style="border: 1px solid #ddd; padding: 8px;"></td><td style="border: 1px solid #ddd; padding: 8px;"></td></tr>`;
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; padding-left: 24px;">${this.$t('SalesReturn')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.data.returns_sales_sum)}</td><td style="border: 1px solid #ddd; padding: 8px;">${this.$t('Return_Ratio')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: 700;">${Number(this.data.sales_return_ratio_pct || 0).toFixed(2)}%</td></tr>`;
      
      // Separator
      tableHtml += `<tr><td colspan="4" style="border: 1px solid #ddd; padding: 4px; background-color: #f5f5f5;"></td></tr>`;
      
      // Purchases Section
      tableHtml += `<tr><td colspan="4" style="border: 1px solid #ddd; padding: 8px; font-weight: 700; background-color: #e6fbf6;">${this.$t('Purchases')}</td></tr>`;
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; padding-left: 24px;">${this.$t('Purchases')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.data.purchases_sum)}</td><td style="border: 1px solid #ddd; padding: 8px;"></td><td style="border: 1px solid #ddd; padding: 8px;"></td></tr>`;
      tableHtml += `<tr><td style="border: 1px solid #ddd; padding: 8px; padding-left: 24px;">${this.$t('PurchasesReturn')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${this.money(this.data.returns_purchases_sum)}</td><td style="border: 1px solid #ddd; padding: 8px;">${this.$t('Return_Ratio')}</td><td style="border: 1px solid #ddd; padding: 8px; text-align: right; font-weight: 700;">${Number(this.data.purchase_return_ratio_pct || 0).toFixed(2)}%</td></tr>`;
      
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

    fetchData(){
      NProgress.start(); NProgress.set(0.1); this.isLoading = true;
      const from = this.fmtDate(this.dateRange.startDate);
      const to   = this.fmtDate(this.dateRange.endDate);
      const wh   = this.warehouse_id || '';
      axios.get(`report/return_ratio_report?from=${from}&to=${to}&warehouse_id=${wh}`)
        .then(({data})=>{
          this.data = data.data || this.data;
          this.warehouses = data.warehouses || [];
          this.isLoading = false; NProgress.done();
        })
        .catch(()=>{ this.isLoading = false; NProgress.done(); });
    }
  },
  created(){ this.fetchData(); }
};
</script>

<style scoped>
.rounded-xl { border-radius: 1rem; }
.shadow-soft { box-shadow: 0 12px 24px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.05); }
.toolbar-card { background: #fff; }
.btn-pill { border-radius: 999px; }
.w-280 { width: 280px; }

.ratio-card { background: #fff; }
.ratio-icon { width: 48px; height: 48px; border-radius: 12px; display:flex; align-items:center; justify-content:center; background: rgba(0,0,0,0.04); }
.display-6 { font-size: 1.6rem; }

/* Keep the picker above navbars/modals/offcanvas */
.daterangepicker { z-index: 2055 !important; }

/* Mobile layout */
@media (max-width: 576px) {
  .daterangepicker { left: 8px !important; right: 8px !important; width: auto !important; max-width: calc(100vw - 16px) !important; }
  .daterangepicker .drp-calendar, .daterangepicker .ranges { float: none !important; width: 100% !important; }
  .quick-ranges { display:flex !important; flex-wrap:wrap; width:100%; }
  .quick-ranges .btn { flex:1 1 calc(50% - 6px); margin-bottom:6px; }
}
</style>


