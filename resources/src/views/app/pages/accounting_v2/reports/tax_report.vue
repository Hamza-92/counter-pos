<template>
  <!-- NEW FEATURE - SAFE ADDITION -->
  <div class="main-content">
    <breadcumb :page="$t('Tax_Summary_Report')" :folder="$t('Reports')" />

   
    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="alert alert-danger" role="alert">
      <lucide-icon name="x" /> {{ error }}
    </div>

    <template v-if="!isLoading">
      <div class="card p-3 mb-3">
        <div class="row align-items-end">
          <div class="col-md-4 mb-2">
            <label class="small text-muted mb-1">{{ $t('From') }}</label>
            <input v-model="filters.from" type="date" class="form-control" @change="fetch" />
          </div>
          <div class="col-md-4 mb-2">
            <label class="small text-muted mb-1">{{ $t('To') }}</label>
            <input v-model="filters.to" type="date" class="form-control" @change="fetch" />
          </div>
          <div class="col-md-4 mb-2 text-right">
            <button class="btn btn-outline-primary" @click="fetch" :disabled="isLoading">
              <lucide-icon name="refresh-cw" /> {{ $t('Refresh') }}
            </button>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="card p-3 mb-3">
            <h6 class="mb-3 text-primary">{{ $t('Sales_Tax') }} ({{ $t('Output_Tax') }})</h6>
            <div class="d-flex justify-content-between mb-2 text-muted small">
              <span>{{ $t('Total_Sales') }}</span>
              <span>{{ toMoney(data.sales) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2 text-muted small">
              <span>{{ $t('Sale_Returns') }}</span>
              <span class="text-danger">- {{ toMoney(data.sale_returns) }}</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between mb-2">
              <strong>{{ $t('Net_Sales') }}</strong>
              <strong>{{ toMoney(data.taxable_sales) }}</strong>
            </div>
            <div class="d-flex justify-content-between">
              <strong>{{ $t('Output_Tax') }}</strong>
              <strong class="text-success">{{ toMoney(data.output_tax) }}</strong>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card p-3 mb-3">
            <h6 class="mb-3 text-primary">{{ $t('Purchase_Tax') }} ({{ $t('Input_Tax') }})</h6>
            <div class="d-flex justify-content-between mb-2 text-muted small">
              <span>{{ $t('Total_Purchases') }}</span>
              <span>{{ toMoney(data.purchases) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2 text-muted small">
              <span>{{ $t('Purchase_Returns') }}</span>
              <span class="text-danger">- {{ toMoney(data.purchase_returns) }}</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between mb-2">
              <strong>{{ $t('Net_Purchases') }}</strong>
              <strong>{{ toMoney(data.taxable_purchases) }}</strong>
            </div>
            <div class="d-flex justify-content-between">
              <strong>{{ $t('Input_Tax') }}</strong>
              <strong class="text-info">{{ toMoney(data.input_tax) }}</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="card p-3" :class="data.net_tax >= 0 ? 'border-warning' : 'border-success'">
        <div class="d-flex justify-content-between">
          <strong>{{ $t('Net_Tax') }}</strong>
          <span :class="data.net_tax >= 0 ? 'text-warning' : 'text-success'">
            {{ toMoney(data.net_tax) }}
          </span>
        </div>
        <small class="text-muted mt-2 d-block">
          {{ data.net_tax >= 0 ? $t('Tax_Payable') : $t('Tax_Refund') }}
        </small>
      </div>
    </template>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../../utils/priceFormat";

export default {
  name: "TaxReportV2",
  metaInfo: {
    title: "Tax Summary Report"
  },
  data() {
    return {
      data: {
        sales: 0,
        sale_returns: 0,
        taxable_sales: 0,
        output_tax: 0,
        purchases: 0,
        purchase_returns: 0,
        taxable_purchases: 0,
        input_tax: 0,
        net_tax: 0
      },
      filters: {
        from: "",
        to: ""
      },
      isLoading: false,
      error: null,
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"])
  },
  created() {
    this.initializeDates();
    this.fetch();
  },
  methods: {
    initializeDates() {
      // Set default date range to current month
      const now = new Date();
      const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
      const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      
      this.filters.from = firstDay.toISOString().split('T')[0];
      this.filters.to = lastDay.toISOString().split('T')[0];
    },
    async fetch() {
      this.isLoading = true;
      this.error = null;
      
      try {
        const { data } = await axios.get("/accounting/v2/reports/tax-summary", {
          params: this.filters
        });
        
        if (data) {
          this.data = {
            sales: parseFloat(data.sales || 0),
            sale_returns: parseFloat(data.sale_returns || 0),
            taxable_sales: parseFloat(data.taxable_sales || 0),
            output_tax: parseFloat(data.output_tax || 0),
            purchases: parseFloat(data.purchases || 0),
            purchase_returns: parseFloat(data.purchase_returns || 0),
            taxable_purchases: parseFloat(data.taxable_purchases || 0),
            input_tax: parseFloat(data.input_tax || 0),
            net_tax: parseFloat(data.net_tax || 0)
          };
        }
      } catch (e) {
        console.error("Tax Summary Error:", e);
        this.error = e.response?.data?.message || this.$t('Failed_Load_Tax_Summary');
        
        // Show toast notification
        this.$root.$bvToast.toast(this.error, {
          title: this.$t('Error'),
          variant: 'danger',
          solid: true
        });
      } finally {
        this.isLoading = false;
      }
    },
    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing toLocaleString behavior to preserve current behavior.
    toMoney(v) {
      try {
        const n = parseFloat(v || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, 2, effectiveKey);
      } catch (e) {
        const n = parseFloat(v || 0);
        return n.toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }
    }
  }
};
</script>

<style scoped>
</style>



