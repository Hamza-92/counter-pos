<template>
  <!-- NEW FEATURE - SAFE ADDITION -->
  <div class="main-content">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-1">{{ $t('Balance_Sheet_Title') }}</h4>
        <div class="text-muted small">{{ $t('Balance_Sheet_Subtitle') }}</div>
      </div>
    </div>

    <div class="card p-3 mb-3">
      <div class="row align-items-end">
        <div class="col-md-6 mb-2">
          <label class="small text-muted mb-1">{{ $t('As_Of') }}</label>
          <input v-model="to" type="date" class="form-control" />
        </div>
        <div class="col-md-6 mb-2 text-right">
          <button class="btn btn-outline-primary" @click="fetch"><lucide-icon name="refresh-cw" /> {{ $t('Refresh') }}</button>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Assets') }}</div>
          <div class="h4 mb-0">{{ toMoney(data.assets) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Liabilities') }}</div>
          <div class="h4 mb-0">{{ toMoney(data.liabilities) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-3 mb-3 kpi">
          <div class="text-muted small">{{ $t('Equity') }}</div>
          <div class="h4 mb-0">{{ toMoney(data.equity) }}</div>
        </div>
      </div>
    </div>

    <div class="card p-3 text-right" :class="Math.abs(data.balance) < 0.01 ? 'border-success' : 'border-warning'">
      <span class="mr-3">{{ $t('Balance_Check') }}</span> <strong>{{ toMoney(data.balance) }}</strong>
    </div>
  </div>
</template>

<script>
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../../utils/priceFormat";

export default {
  name: "BalanceSheetV2",
  data() {
    return {
      data: { assets: 0, liabilities: 0, equity: 0, balance: 0 },
      to: "",
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },
  created() { this.fetch(); },
  methods: {
    async fetch() {
      try {
        const { data } = await axios.get("/accounting/v2/reports/balance-sheet", { params: { to: this.to || undefined } });
        this.data = data || this.data;
      } catch (e) {}
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
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
    }
  }
};
</script>

<style scoped>
.kpi { border-left: 4px solid #663399; }
</style>



