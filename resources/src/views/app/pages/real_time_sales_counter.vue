<template>
  <div class="main-content">
    <breadcumb :page="$t('Real_time_Sales_Counter')" folder="" />

    <div v-if="loading" class="loading_page spinner spinner-primary mr-3"></div>

    <div
      v-else-if="!loading && currentUserPermissions && currentUserPermissions.includes('real_time_sales_counter')"
      class="real-time-sales-counter-page"
      :class="{ 'rts-flash': flashPulse }"
    >
      <!-- Header -->
      <div class="rts-header mb-4">
        <div class="rts-header-inner">
          <div class="rts-title-row">
            <div class="rts-title-block">
              <h1 class="rts-title">{{ $t('Real_time_Sales_Counter') }}</h1>
              <p class="rts-subtitle">{{ $t('Real_time_Sales_Counter_Help') }}</p>
            </div>
            <div class="rts-status-block">
              <span class="rts-live-badge" :class="{ 'rts-live-badge--paused': paused, 'rts-live-badge--error': hasError }">
                <span class="rts-live-dot"></span>
                <span v-if="hasError">{{ $t('Connection_error') }}</span>
                <span v-else-if="paused">{{ $t('Paused') }}</span>
                <span v-else>{{ $t('Live') }}</span>
              </span>
              <span class="rts-server-time">{{ serverClock }}</span>
            </div>
          </div>

          <div class="rts-controls">
            <div class="rts-control-item rts-control-warehouse">
              <label class="rts-control-label">{{ $t('Warehouse') }}</label>
              <v-select
                v-model="warehouseId"
                :reduce="opt => opt.value"
                :placeholder="$t('All') + ' ' + $t('Warehouses')"
                :options="warehouseOptions"
                :clearable="true"
                @input="onWarehouseChange"
              />
            </div>

            <div class="rts-control-item">
              <label class="rts-control-label">{{ $t('Updates_every') }}</label>
              <select v-model.number="refreshSeconds" class="form-control rts-select" @change="restartTimer">
                <option :value="10">10 {{ $t('Seconds') }}</option>
                <option :value="30">30 {{ $t('Seconds') }}</option>
                <option :value="60">60 {{ $t('Seconds') }}</option>
                <option :value="120">120 {{ $t('Seconds') }}</option>
              </select>
            </div>

            <div class="rts-control-actions">
              <button type="button" class="rts-btn rts-btn--ghost" @click="togglePause" :title="paused ? $t('Resume') : $t('Pause')">
                <lucide-icon :name="paused ? 'play' : 'pause'" />
                <span class="d-none d-md-inline ml-1">{{ paused ? $t('Resume') : $t('Pause') }}</span>
              </button>
              <button type="button" class="rts-btn rts-btn--primary" @click="manualRefresh" :disabled="isFetching" :title="$t('Refresh')">
                <lucide-icon name="refresh-cw" :class="{ 'rts-spin': isFetching }" />
                <span class="d-none d-md-inline ml-1">{{ $t('Refresh') }}</span>
              </button>
              <button type="button" class="rts-btn rts-btn--ghost" :class="{ 'rts-btn--active': soundEnabled }" @click="toggleSound" :title="$t('Sound')">
                <lucide-icon :name="soundEnabled ? 'volume-2' : 'volume-x'" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Stat cards -->
      <b-row class="rts-cards-row">
        <b-col md="6" lg="3" class="mb-3">
          <div class="rts-card rts-card-count" :class="{ 'rts-card--bump': bumpCount }">
            <div class="rts-card-icon"><lucide-icon name="shopping-cart" /></div>
            <div class="rts-card-body">
              <p class="rts-card-label">{{ $t('Sales_today') }}</p>
              <div class="rts-card-value">{{ todayCount }}</div>
              <p v-if="todayCount === 0" class="rts-card-hint">{{ $t('No_sales_today') }}</p>
            </div>
          </div>
        </b-col>
        <b-col md="6" lg="3" class="mb-3">
          <div class="rts-card rts-card-total" :class="{ 'rts-card--bump': bumpTotal }">
            <div class="rts-card-icon"><lucide-icon name="dollar-sign" /></div>
            <div class="rts-card-body">
              <p class="rts-card-label">{{ $t('Total_Today') }}</p>
              <div class="rts-card-value rts-card-value--price">
                {{ formatPriceWithSymbol(currencySymbol, todayTotal, 2) }}
              </div>
              <p class="rts-card-hint">
                <span :class="trendClass">
                  <lucide-icon :name="trendIcon" />
                  {{ trendLabel }}
                </span>
                <span class="rts-card-hint-muted">{{ $t('vs') }} {{ $t('yesterday') }}</span>
              </p>
            </div>
          </div>
        </b-col>
        <b-col md="6" lg="3" class="mb-3">
          <div class="rts-card rts-card-avg">
            <div class="rts-card-icon"><lucide-icon name="trending-up" /></div>
            <div class="rts-card-body">
              <p class="rts-card-label">{{ $t('Average_Sale') }}</p>
              <div class="rts-card-value rts-card-value--price">
                {{ formatPriceWithSymbol(currencySymbol, averageSale, 2) }}
              </div>
              <p class="rts-card-hint">{{ formatPriceWithSymbol(currencySymbol, todayPaid, 2) }} {{ $t('paid') }}</p>
            </div>
          </div>
        </b-col>
        <b-col md="6" lg="3" class="mb-3">
          <div class="rts-card rts-card-last">
            <div class="rts-card-icon"><lucide-icon name="clock" /></div>
            <div class="rts-card-body">
              <p class="rts-card-label">{{ $t('Last_Sale') }}</p>
              <div class="rts-card-value rts-card-value--time">{{ lastSaleRelative }}</div>
              <p v-if="lastSaleAbsolute" class="rts-card-hint">{{ lastSaleAbsolute }}</p>
            </div>
          </div>
        </b-col>
      </b-row>

      <!-- Payment status mini stats -->
      <b-row class="rts-payment-row mb-3">
        <b-col cols="12">
          <div class="rts-payment-bar">
            <div class="rts-payment-item rts-payment-item--paid">
              <span class="rts-payment-dot"></span>
              <span class="rts-payment-label">{{ $t('paid') }}</span>
              <span class="rts-payment-value">{{ paymentStatus.paid }}</span>
            </div>
            <div class="rts-payment-item rts-payment-item--partial">
              <span class="rts-payment-dot"></span>
              <span class="rts-payment-label">{{ $t('partial') }}</span>
              <span class="rts-payment-value">{{ paymentStatus.partial }}</span>
            </div>
            <div class="rts-payment-item rts-payment-item--unpaid">
              <span class="rts-payment-dot"></span>
              <span class="rts-payment-label">{{ $t('unpaid') }}</span>
              <span class="rts-payment-value">{{ paymentStatus.unpaid }}</span>
            </div>
            <div class="rts-payment-item rts-payment-item--due">
              <span class="rts-payment-label">{{ $t('Sales_Due') }}</span>
              <span class="rts-payment-value">{{ formatPriceWithSymbol(currencySymbol, todayDue, 2) }}</span>
            </div>
          </div>
        </b-col>
      </b-row>

      <!-- Hourly chart -->
      <b-row class="mb-3">
        <b-col cols="12">
          <div class="rts-panel">
            <div class="rts-panel-header">
              <h3 class="rts-panel-title">
                <lucide-icon name="bar-chart-3" class="mr-2" />
                {{ $t('Hourly_Sales_Today') }}
              </h3>
            </div>
            <div class="rts-panel-body">
              <apexchart
                v-if="hourlySeries[0] && hourlySeries[0].data.length"
                type="bar"
                :height="260"
                :options="hourlyOptions"
                :series="hourlySeries"
              />
              <div v-else class="rts-empty">{{ $t('No_sales_today') }}</div>
            </div>
          </div>
        </b-col>
      </b-row>

      <b-row class="mb-3">
        <b-col lg="7" class="mb-3 mb-lg-0">
          <div class="rts-panel h-100">
            <div class="rts-panel-header">
              <h3 class="rts-panel-title">
                <lucide-icon name="receipt" class="mr-2" />
                {{ $t('Recent_Sales') }}
              </h3>
              <span class="rts-panel-meta">{{ recentSales.length }}</span>
            </div>
            <div class="rts-panel-body p-0">
              <div v-if="recentSales.length === 0" class="rts-empty p-4">
                {{ $t('No_sales_today') }}
              </div>
              <div v-else class="rts-table-wrap">
                <table class="rts-table">
                  <thead>
                    <tr>
                      <th>{{ $t('Reference') }}</th>
                      <th>{{ $t('Customer') }}</th>
                      <th class="text-right">{{ $t('Total') }}</th>
                      <th>{{ $t('payment_status') }}</th>
                      <th>{{ $t('Time') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="sale in recentSales" :key="sale.id" :class="{ 'rts-row-new': isNewSale(sale.id) }">
                      <td class="rts-ref">
                        <span class="rts-pos-pill" v-if="sale.is_pos">POS</span>
                        {{ sale.ref || '-' }}
                      </td>
                      <td>{{ sale.client_name || '-' }}</td>
                      <td class="text-right">{{ formatPriceWithSymbol(currencySymbol, sale.grand_total, 2) }}</td>
                      <td>
                        <span class="rts-status-pill" :class="paymentClass(sale.payment_status)">
                          {{ $t(sale.payment_status) || sale.payment_status }}
                        </span>
                      </td>
                      <td class="rts-time-cell">{{ formatHM(sale.date) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </b-col>

        <b-col lg="5">
          <div class="rts-panel h-100">
            <div class="rts-panel-header">
              <h3 class="rts-panel-title">
                <lucide-icon name="package" class="mr-2" />
                {{ $t('Top_Products_Today') }}
              </h3>
            </div>
            <div class="rts-panel-body">
              <div v-if="topProducts.length === 0" class="rts-empty">{{ $t('No_sales_today') }}</div>
              <ul v-else class="rts-top-list">
                <li v-for="(p, idx) in topProducts" :key="p.product_id || idx" class="rts-top-item">
                  <span class="rts-top-rank">{{ idx + 1 }}</span>
                  <div class="rts-top-info">
                    <div class="rts-top-name" :title="p.product_name">{{ p.product_name }}</div>
                    <div class="rts-top-meta">
                      <span>{{ p.quantity }} {{ $t('units') }}</span>
                      <span>{{ formatPriceWithSymbol(currencySymbol, p.total, 2) }}</span>
                    </div>
                    <div class="rts-top-bar">
                      <div class="rts-top-bar-fill" :style="{ width: topBarWidth(p) + '%' }"></div>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </b-col>
      </b-row>

      <!-- Sales by Location (per-warehouse rollup of today's sales) -->
      <b-row class="mb-3">
        <b-col cols="12">
          <div class="rts-panel">
            <div class="rts-panel-header rts-panel-header--accent">
              <h3 class="rts-panel-title">
                <lucide-icon name="map-pin" class="mr-2" />
                {{ $t('Sales_by_Location') }}
              </h3>
              <span class="rts-panel-meta">{{ salesByLocation.length }}</span>
            </div>
            <div class="rts-panel-body p-0">
              <div v-if="salesByLocation.length === 0" class="rts-empty p-4">
                {{ $t('No_sales_today') }}
              </div>
              <div v-else class="rts-table-wrap">
                <table class="rts-table rts-table--location">
                  <thead>
                    <tr>
                      <th class="rts-loc-sn">S/N</th>
                      <th>{{ $t('Name') }}</th>
                      <th class="text-right">{{ $t('Total_Invoice') }}</th>
                      <th class="text-right">{{ $t('Amount') }}</th>
                      <th class="text-right">{{ $t('Last_Sale') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(loc, idx) in salesByLocation" :key="loc.warehouse_id || idx">
                      <td class="rts-loc-sn">{{ idx + 1 }}</td>
                      <td class="rts-loc-name">{{ loc.name }}</td>
                      <td class="text-right">{{ loc.total_invoice }}</td>
                      <td class="text-right">{{ formatPriceWithSymbol(currencySymbol, loc.amount, 2) }}</td>
                      <td class="text-right rts-time-cell">{{ formatDateTime(loc.last_sale) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </b-col>
      </b-row>

      <!-- Footer -->
      <div class="rts-footer">
        <span class="rts-refresh-text">
          <lucide-icon name="clock" class="mr-1" />
          {{ $t('Last_updated') }}:
          {{ lastUpdatedRelative }}
        </span>
      </div>
    </div>

    <!-- No permission -->
    <div v-else class="rts-no-access">
      <div class="rts-no-access-card">
        <div class="rts-no-access-icon"><lucide-icon name="lock" /></div>
        <h5 class="rts-no-access-title">{{ $t('No_permission') }}</h5>
        <p class="rts-no-access-text">{{ $t('You_do_not_have_access_to_this_page') }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import VueApexCharts from "vue-apexcharts";

export default {
  metaInfo: { title: "Real-time Sales Counter" },
  components: {
    apexchart: VueApexCharts,
  },
  data() {
    return {
      loading: true,
      isFetching: false,
      paused: false,
      hasError: false,
      soundEnabled: false,

      todayCount: 0,
      todayTotal: 0,
      todayPaid: 0,
      todayDue: 0,
      yesterdayTotal: 0,
      lastSaleAt: null,
      paymentStatus: { paid: 0, partial: 0, unpaid: 0 },
      hourly: [],
      recentSales: [],
      topProducts: [],
      // Per-warehouse rollup for the "Sales by Location" panel.
      salesByLocation: [],
      warehouses: [],

      warehouseId: null,

      refreshSeconds: 30,
      refreshTimer: null,
      tickTimer: null,

      lastUpdatedAt: null,
      // Server clock anchor: timestamp the server reported on the last
      // fetch + the local timestamp at the moment we received it. The
      // displayed clock is computed from these plus `now`, so it ticks
      // every second instead of freezing between 30s refreshes.
      serverTimeAt: null,
      serverTimeFetchedAt: 0,
      now: Date.now(),

      knownSaleIds: new Set(),
      newSaleIds: new Set(),
      newSaleClearTimer: null,
      previousCount: 0,

      bumpCount: false,
      bumpTotal: false,
      flashPulse: false,
      audioCtx: null,
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    ...mapGetters("config", ["getThemeMode"]),
    isDarkMode() {
      return !!(this.getThemeMode && this.getThemeMode.dark);
    },
    currencySymbol() {
      return (this.currentUser && this.currentUser.currency) || "";
    },
    averageSale() {
      if (!this.todayCount) return 0;
      return this.todayTotal / this.todayCount;
    },
    serverClock() {
      // Tick every second by depending on `this.now`. We anchor on the
      // last server timestamp and add the elapsed local milliseconds
      // since that fetch, so the clock advances smoothly while still
      // tracking the server (re-anchored on each refresh).
      if (this.serverTimeAt && this.serverTimeFetchedAt) {
        const elapsed = Math.max(0, this.now - this.serverTimeFetchedAt);
        const t = new Date(this.serverTimeAt.getTime() + elapsed);
        return t.toLocaleTimeString();
      }
      // Fall back to local clock until the first fetch lands.
      return new Date(this.now).toLocaleTimeString();
    },
    trendPct() {
      if (!this.yesterdayTotal) return this.todayTotal > 0 ? 100 : 0;
      return ((this.todayTotal - this.yesterdayTotal) / this.yesterdayTotal) * 100;
    },
    trendClass() {
      if (this.trendPct > 0) return "rts-trend-up";
      if (this.trendPct < 0) return "rts-trend-down";
      return "rts-trend-flat";
    },
    trendIcon() {
      if (this.trendPct > 0) return "trending-up";
      if (this.trendPct < 0) return "trending-down";
      return "minus";
    },
    trendLabel() {
      const v = this.trendPct;
      if (!isFinite(v)) return "—";
      const abs = Math.abs(v);
      const formatted = abs >= 100 ? abs.toFixed(0) : abs.toFixed(1);
      return `${v >= 0 ? "+" : "-"}${formatted}%`;
    },
    lastSaleRelative() {
      if (!this.lastSaleAt) return "—";
      return this.relativeFromNow(this.lastSaleAt);
    },
    lastSaleAbsolute() {
      if (!this.lastSaleAt) return "";
      try {
        const d = new Date(this.lastSaleAt);
        if (isNaN(d.getTime())) return "";
        return d.toLocaleString();
      } catch (e) {
        return "";
      }
    },
    lastUpdatedRelative() {
      if (!this.lastUpdatedAt) return "—";
      return this.relativeFromNow(this.lastUpdatedAt);
    },
    warehouseOptions() {
      return (this.warehouses || []).map((w) => ({ label: w.name, value: w.id }));
    },
    hourlySeries() {
      const counts = (this.hourly || []).map((h) => Number(h.count) || 0);
      return [{ name: this.$t("Sales"), data: counts }];
    },
    hourlyOptions() {
      const totals = (this.hourly || []).map((h) => Number(h.total) || 0);
      const symbol = this.currencySymbol;
      const formatPrice = (n) =>
        Number(n || 0).toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });
      const dark = this.isDarkMode;
      const labelColor = dark ? "rgba(216, 216, 216, 0.7)" : "#6b7280";
      const gridColor = dark ? "#2a2a2a" : "#e5e7eb";
      return {
        chart: {
          toolbar: { show: false },
          animations: { enabled: true, speed: 300 },
          fontFamily: "inherit",
        },
        plotOptions: {
          bar: { columnWidth: "55%", borderRadius: 6, distributed: false },
        },
        dataLabels: { enabled: false },
        colors: ["#8b5cf6"],
        xaxis: {
          categories: Array.from({ length: 24 }, (_, h) => `${String(h).padStart(2, "0")}h`),
          labels: { style: { colors: labelColor, fontSize: "11px" } },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: {
          labels: {
            style: { colors: labelColor, fontSize: "11px" },
            formatter: (val) => Math.round(val),
          },
        },
        grid: { borderColor: gridColor, strokeDashArray: 4 },
        tooltip: {
          theme: dark ? "dark" : "light",
          y: {
            formatter: (val, { dataPointIndex }) => {
              const t = totals[dataPointIndex] || 0;
              const sym = symbol ? symbol + " " : "";
              return `${val} (${sym}${formatPrice(t)})`;
            },
          },
        },
      };
    },
  },
  methods: {
    formatPriceWithSymbol(symbol, number, dec) {
      const n = Number(number || 0);
      const value = n.toLocaleString(undefined, {
        minimumFractionDigits: dec || 2,
        maximumFractionDigits: dec || 2,
      });
      const safeSymbol = symbol || "";
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },
    relativeFromNow(dateStr) {
      if (!dateStr) return "—";
      try {
        const d = new Date(dateStr);
        const ts = d.getTime();
        if (isNaN(ts)) return dateStr;
        const diff = Math.max(0, this.now - ts) / 1000;
        const ago = this.$t("ago");
        if (diff < 5) return this.$t("just_now");
        if (diff < 60) return `${Math.floor(diff)}s ${ago}`;
        if (diff < 3600) return `${Math.floor(diff / 60)}m ${ago}`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ${ago}`;
        return `${Math.floor(diff / 86400)}d ${ago}`;
      } catch (e) {
        return dateStr;
      }
    },
    formatHM(dateStr) {
      if (!dateStr) return "";
      try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return "";
        return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
      } catch (e) {
        return "";
      }
    },
    // Used by the Sales by Location panel — produces e.g.
    // "4/26/2026, 6:00:00 PM" so the column matches the requested
    // mockup formatting.
    formatDateTime(dateStr) {
      if (!dateStr) return "—";
      try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return "—";
        return d.toLocaleString();
      } catch (e) {
        return "—";
      }
    },
    paymentClass(status) {
      if (status === "paid") return "rts-status-pill--paid";
      if (status === "partial") return "rts-status-pill--partial";
      return "rts-status-pill--unpaid";
    },
    isNewSale(id) {
      return this.newSaleIds.has(id);
    },
    topBarWidth(p) {
      const max = (this.topProducts || []).reduce((m, x) => Math.max(m, Number(x.quantity) || 0), 0);
      if (!max) return 0;
      return Math.min(100, (Number(p.quantity) / max) * 100);
    },
    fetchCounterData(showSpinner = false) {
      if (this.isFetching) return;
      this.isFetching = true;
      if (showSpinner) this.loading = this.loading || false;

      const params = {};
      if (this.warehouseId) params.warehouse_id = this.warehouseId;

      axios
        .get("/real_time_sales_counter_data", { params })
        .then((response) => {
          const d = response.data || {};
          const previousCount = this.todayCount;
          const previousTotal = this.todayTotal;
          const previousIds = this.knownSaleIds;

          this.todayCount = Number(d.today_count) || 0;
          this.todayTotal = Number(d.today_total) || 0;
          this.todayPaid = Number(d.today_paid) || 0;
          this.todayDue = Number(d.today_due) || 0;
          this.yesterdayTotal = Number(d.yesterday_total) || 0;
          this.lastSaleAt = d.last_sale_at || null;
          this.paymentStatus = {
            paid: Number((d.payment_status_counts || {}).paid) || 0,
            partial: Number((d.payment_status_counts || {}).partial) || 0,
            unpaid: Number((d.payment_status_counts || {}).unpaid) || 0,
          };
          this.hourly = Array.isArray(d.hourly) ? d.hourly : [];
          this.recentSales = Array.isArray(d.recent_sales) ? d.recent_sales : [];
          this.topProducts = Array.isArray(d.top_products) ? d.top_products : [];
          this.salesByLocation = Array.isArray(d.sales_by_location) ? d.sales_by_location : [];
          this.warehouses = Array.isArray(d.warehouses) ? d.warehouses : [];
          // Re-anchor the live server clock. We store the parsed Date
          // (or fall back to "now" if the backend omitted server_time)
          // along with the local Date.now() so the computed `serverClock`
          // can advance it every second.
          const parsedServerTime = d.server_time ? new Date(d.server_time) : null;
          this.serverTimeAt =
            parsedServerTime && !isNaN(parsedServerTime.getTime())
              ? parsedServerTime
              : new Date();
          this.serverTimeFetchedAt = Date.now();
          this.lastUpdatedAt = new Date().toISOString();
          this.hasError = false;

          // detect new sales
          const currentIds = new Set(this.recentSales.map((s) => s.id));
          const fresh = [];
          if (previousIds.size > 0) {
            this.recentSales.forEach((s) => {
              if (!previousIds.has(s.id)) fresh.push(s.id);
            });
          }
          this.knownSaleIds = currentIds;

          if (fresh.length > 0 || (previousCount > 0 && this.todayCount > previousCount)) {
            this.triggerPulse(fresh, previousCount, previousTotal);
          }
        })
        .catch(() => {
          this.hasError = true;
        })
        .finally(() => {
          this.loading = false;
          this.isFetching = false;
        });
    },
    triggerPulse(freshIds, previousCount, previousTotal) {
      this.flashPulse = true;
      this.bumpCount = this.todayCount > previousCount;
      this.bumpTotal = this.todayTotal > previousTotal;

      if (freshIds.length > 0) {
        this.newSaleIds = new Set(freshIds);
        if (this.newSaleClearTimer) clearTimeout(this.newSaleClearTimer);
        this.newSaleClearTimer = setTimeout(() => {
          this.newSaleIds = new Set();
        }, 6000);
      }

      setTimeout(() => {
        this.flashPulse = false;
        this.bumpCount = false;
        this.bumpTotal = false;
      }, 1200);

      if (this.soundEnabled) this.playBeep();
    },
    playBeep() {
      try {
        if (!this.audioCtx) {
          const Ctx = window.AudioContext || window.webkitAudioContext;
          if (!Ctx) return;
          this.audioCtx = new Ctx();
        }
        const ctx = this.audioCtx;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = "sine";
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.4);
      } catch (e) {
        // ignore audio failures
      }
    },
    manualRefresh() {
      this.fetchCounterData(true);
    },
    togglePause() {
      this.paused = !this.paused;
      if (this.paused) {
        this.stopTimer();
      } else {
        this.fetchCounterData();
        this.startTimer();
      }
    },
    toggleSound() {
      this.soundEnabled = !this.soundEnabled;
      try {
        localStorage.setItem("rts_sound_enabled", this.soundEnabled ? "1" : "0");
      } catch (e) {
        // ignore
      }
      if (this.soundEnabled) {
        // try to unlock audio context with a silent tick
        this.playBeep();
      }
    },
    onWarehouseChange() {
      this.knownSaleIds = new Set();
      this.fetchCounterData();
    },
    startTimer() {
      this.stopTimer();
      const ms = Math.max(5, Number(this.refreshSeconds) || 30) * 1000;
      this.refreshTimer = setInterval(() => {
        if (!this.paused) this.fetchCounterData();
      }, ms);
    },
    stopTimer() {
      if (this.refreshTimer) {
        clearInterval(this.refreshTimer);
        this.refreshTimer = null;
      }
    },
    restartTimer() {
      try {
        localStorage.setItem("rts_refresh_seconds", String(this.refreshSeconds));
      } catch (e) {
        // ignore
      }
      if (!this.paused) this.startTimer();
    },
  },
  mounted() {
    try {
      const stored = localStorage.getItem("rts_refresh_seconds");
      const parsed = Number(stored);
      if ([10, 30, 60, 120].includes(parsed)) this.refreshSeconds = parsed;
      this.soundEnabled = localStorage.getItem("rts_sound_enabled") === "1";
    } catch (e) {
      // ignore storage errors
    }

    this.fetchCounterData();
    this.startTimer();
    this.tickTimer = setInterval(() => {
      this.now = Date.now();
    }, 1000);
  },
  beforeDestroy() {
    this.stopTimer();
    if (this.tickTimer) clearInterval(this.tickTimer);
    if (this.newSaleClearTimer) clearTimeout(this.newSaleClearTimer);
    if (this.audioCtx && typeof this.audioCtx.close === "function") {
      try { this.audioCtx.close(); } catch (e) { /* ignore */ }
    }
  },
};
</script>

<style scoped>
.real-time-sales-counter-page {
  max-width: 1300px;
  margin: 0 auto;
  transition: background 0.4s ease;
}

.rts-flash {
  animation: rts-flash-bg 1s ease;
}

@keyframes rts-flash-bg {
  0% { background: rgba(74, 222, 128, 0); }
  30% { background: rgba(74, 222, 128, 0.08); }
  100% { background: rgba(74, 222, 128, 0); }
}

/* Header */
.rts-header {
  background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
  border-radius: 16px;
  padding: 1.5rem 1.75rem;
  color: #fff;
  box-shadow: 0 4px 20px rgba(30, 41, 59, 0.25);
}

.rts-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.rts-title-block { flex: 1 1 280px; min-width: 0; }

.rts-title {
  font-size: 1.6rem;
  font-weight: 700;
  margin: 0;
  letter-spacing: -0.02em;
  color: #fff;
}

.rts-subtitle {
  margin: 0.4rem 0 0 0;
  font-size: 0.9rem;
  opacity: 0.85;
}

.rts-status-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.35rem;
}

.rts-live-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: rgba(74, 222, 128, 0.18);
  padding: 0.35rem 0.8rem;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #bbf7d0;
}

.rts-live-badge--paused {
  background: rgba(251, 191, 36, 0.18);
  color: #fde68a;
}

.rts-live-badge--error {
  background: rgba(239, 68, 68, 0.18);
  color: #fecaca;
}

.rts-live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  animation: rts-pulse 1.5s ease-in-out infinite;
}

.rts-live-badge--paused .rts-live-dot,
.rts-live-badge--error .rts-live-dot { animation: none; }

@keyframes rts-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.55; transform: scale(1.2); }
}

.rts-server-time {
  font-size: 0.78rem;
  opacity: 0.75;
  font-variant-numeric: tabular-nums;
}

.rts-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.75rem;
  margin-top: 1.25rem;
}

.rts-control-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 160px;
}

.rts-control-warehouse { min-width: 220px; flex: 1 1 220px; max-width: 320px; }

/* v-select inside the dark header */
.rts-control-warehouse >>> .v-select {
  width: 100%;
  background: transparent;
}

.rts-control-warehouse >>> .v-select .vs__dropdown-toggle {
  height: 38px;
  background: rgba(255, 255, 255, 0.12) !important;
  border: 1px solid rgba(255, 255, 255, 0.22) !important;
  border-radius: 10px !important;
  padding: 0 0.6rem !important;
  cursor: pointer;
  display: flex;
  align-items: center;
  transition: background 0.2s ease, border-color 0.2s ease;
}

.rts-control-warehouse >>> .v-select .vs__dropdown-toggle:hover {
  background: rgba(255, 255, 255, 0.2) !important;
  border-color: rgba(255, 255, 255, 0.35) !important;
}

.rts-control-warehouse >>> .v-select.vs--open .vs__dropdown-toggle {
  background: rgba(255, 255, 255, 0.22) !important;
  border-color: rgba(255, 255, 255, 0.45) !important;
  box-shadow: none !important;
}

.rts-control-warehouse >>> .v-select .vs__selected-options {
  padding: 0 !important;
  margin: 0 !important;
  flex: 1;
  display: flex;
  align-items: center;
  min-width: 0;
}

.rts-control-warehouse >>> .v-select .vs__selected {
  color: #fff !important;
  font-weight: 600;
  font-size: 0.9rem;
  margin: 0 !important;
  padding: 0 !important;
  background: transparent !important;
  border: none !important;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.rts-control-warehouse >>> .v-select .vs__search,
.rts-control-warehouse >>> .v-select .vs__search:focus {
  color: #fff !important;
  font-size: 0.9rem;
  margin: 0 !important;
  padding: 0 !important;
  background: transparent !important;
  border: none !important;
  height: auto !important;
  line-height: 1.5 !important;
  box-shadow: none !important;
}

.rts-control-warehouse >>> .v-select .vs__search::placeholder {
  color: rgba(255, 255, 255, 0.75) !important;
}

.rts-control-warehouse >>> .v-select .vs__actions {
  padding: 0 0 0 0.4rem !important;
}

.rts-control-warehouse >>> .v-select .vs__clear,
.rts-control-warehouse >>> .v-select .vs__open-indicator {
  fill: rgba(255, 255, 255, 0.85) !important;
}

.rts-control-warehouse >>> .v-select .vs__dropdown-menu {
  z-index: 2056 !important;
  background: #fff !important;
  border-radius: 10px !important;
  border: 1px solid #e5e7eb !important;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18) !important;
  margin-top: 0.4rem !important;
  padding: 0.35rem 0 !important;
  max-height: 320px;
  overflow-y: auto;
}

.rts-control-warehouse >>> .v-select .vs__dropdown-option {
  padding: 0.55rem 0.85rem !important;
  color: #1f2937 !important;
  background: transparent !important;
  font-size: 0.88rem !important;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.rts-control-warehouse >>> .v-select .vs__dropdown-option--highlight,
.rts-control-warehouse >>> .v-select .vs__dropdown-option:hover {
  background: #ede9fe !important;
  color: #4c1d95 !important;
}

.rts-control-warehouse >>> .v-select .vs__no-options {
  color: #6b7280 !important;
  padding: 0.6rem 0.85rem !important;
  font-size: 0.85rem;
}

.rts-control-label {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
  color: rgba(255, 255, 255, 0.75);
}

.rts-select {
  height: 38px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.95);
  color: #1f2937;
  font-size: 0.9rem;
}

.rts-control-actions {
  display: flex;
  gap: 0.5rem;
  margin-left: auto;
}

.rts-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  padding: 0.55rem 0.9rem;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.1s ease;
}

.rts-btn:hover { background: rgba(255, 255, 255, 0.18); }
.rts-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.rts-btn--primary { background: #8b5cf6; border-color: #8b5cf6; }
.rts-btn--primary:hover { background: #7c3aed; }
.rts-btn--active { background: #4ade80; border-color: #4ade80; color: #052e16; }
.rts-btn--active:hover { background: #22c55e; }

.rts-spin { animation: rts-spin 0.9s linear infinite; }
@keyframes rts-spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }

/* Cards */
.rts-cards-row { margin-top: 0.5rem; }

.rts-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  background: #fff;
  border-radius: 16px;
  padding: 1.4rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  border: 1px solid #e5e7eb;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  height: 100%;
  min-height: 130px;
}

.rts-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
}

.rts-card--bump { animation: rts-bump 0.7s ease; }

@keyframes rts-bump {
  0% { transform: scale(1); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06); }
  35% { transform: scale(1.04); box-shadow: 0 8px 24px rgba(74, 222, 128, 0.35); }
  100% { transform: scale(1); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06); }
}

.rts-card-icon {
  flex-shrink: 0;
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  color: #fff;
}

.rts-card-count .rts-card-icon { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); }
.rts-card-total .rts-card-icon { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
.rts-card-avg .rts-card-icon { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
.rts-card-last .rts-card-icon { background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%); }

.rts-card-body { flex: 1; min-width: 0; }

.rts-card-label {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 0 0 0.3rem 0;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.rts-card-value {
  font-size: 1.6rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

.rts-card-value--price {
  font-size: 1.35rem;
  word-break: break-word;
}

.rts-card-value--time {
  font-size: 1rem;
  font-weight: 600;
  color: #4b5563;
}

.rts-card-hint {
  font-size: 0.8rem;
  color: #9ca3af;
  margin: 0.4rem 0 0 0;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.rts-card-hint-muted { color: #9ca3af; }

.rts-trend-up { color: #10b981; font-weight: 600; display: inline-flex; align-items: center; gap: 0.2rem; }
.rts-trend-down { color: #ef4444; font-weight: 600; display: inline-flex; align-items: center; gap: 0.2rem; }
.rts-trend-flat { color: #9ca3af; font-weight: 600; display: inline-flex; align-items: center; gap: 0.2rem; }

/* Payment row */
.rts-payment-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  background: #fff;
  border-radius: 12px;
  padding: 0.85rem 1rem;
  border: 1px solid #e5e7eb;
}

.rts-payment-item {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.75rem;
  border-radius: 8px;
  background: #f9fafb;
  font-size: 0.85rem;
  color: #4b5563;
}

.rts-payment-item--due { margin-left: auto; background: #fef3c7; color: #92400e; font-weight: 600; }

.rts-payment-dot { width: 8px; height: 8px; border-radius: 50%; }
.rts-payment-item--paid .rts-payment-dot { background: #10b981; }
.rts-payment-item--partial .rts-payment-dot { background: #f59e0b; }
.rts-payment-item--unpaid .rts-payment-dot { background: #ef4444; }

.rts-payment-label { text-transform: capitalize; }
.rts-payment-value { font-weight: 700; color: #1f2937; }

/* Panels */
.rts-panel {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.rts-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f3f4f6;
}

.rts-panel-title {
  display: inline-flex;
  align-items: center;
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1f2937;
}

.rts-panel-meta {
  font-size: 0.78rem;
  background: #f3f4f6;
  color: #4b5563;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  font-weight: 600;
}

.rts-panel-body { padding: 1rem 1.25rem; flex: 1; }
.rts-panel-body.p-0 { padding: 0; }

.rts-empty {
  text-align: center;
  color: #9ca3af;
  font-size: 0.9rem;
  padding: 1.5rem 0;
}

/* Recent sales table */
.rts-table-wrap { width: 100%; overflow-x: auto; }
.rts-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}

.rts-table thead th {
  text-align: left;
  padding: 0.65rem 1rem;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
  font-weight: 600;
  background: #f9fafb;
  border-bottom: 1px solid #f3f4f6;
}

.rts-table tbody td {
  padding: 0.7rem 1rem;
  border-bottom: 1px solid #f3f4f6;
  color: #374151;
  vertical-align: middle;
}

.rts-table tbody tr:last-child td { border-bottom: none; }
.rts-table tbody tr:hover { background: #f9fafb; }

.rts-row-new { animation: rts-row-flash 4s ease; background: rgba(74, 222, 128, 0.08); }
@keyframes rts-row-flash {
  0% { background: rgba(74, 222, 128, 0.32); }
  100% { background: rgba(74, 222, 128, 0.08); }
}

.text-right { text-align: right; }

.rts-ref { display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; color: #1f2937; }
.rts-pos-pill {
  display: inline-block;
  background: #ede9fe;
  color: #6d28d9;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  letter-spacing: 0.05em;
}

.rts-time-cell { font-variant-numeric: tabular-nums; color: #6b7280; }

.rts-status-pill {
  display: inline-block;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: capitalize;
}

.rts-status-pill--paid { background: #d1fae5; color: #065f46; }
.rts-status-pill--partial { background: #fef3c7; color: #92400e; }
.rts-status-pill--unpaid { background: #fee2e2; color: #991b1b; }

/* Sales by Location panel — light-blue accent header to match the
   requested mockup; row layout reuses the existing .rts-table styling. */
.rts-panel-header--accent {
  background: linear-gradient(180deg, #e0f2fe 0%, #cfe9fb 100%);
  border-bottom-color: #b6dcf6;
}
.rts-panel-header--accent .rts-panel-title {
  color: #0c4a6e;
}
.rts-panel-header--accent .rts-panel-meta {
  background: #ffffff;
  color: #0c4a6e;
}
.rts-table--location tbody td { font-size: 0.9rem; }
.rts-loc-sn {
  width: 56px;
  text-align: center;
  font-variant-numeric: tabular-nums;
  color: #6b7280;
}
.rts-loc-name { font-weight: 600; color: #1f2937; }

/* Top products */
.rts-top-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.rts-top-item {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}

.rts-top-rank {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: #ede9fe;
  color: #6d28d9;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
}

.rts-top-info { flex: 1; min-width: 0; }

.rts-top-name {
  font-weight: 600;
  color: #1f2937;
  font-size: 0.9rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rts-top-meta {
  display: flex;
  justify-content: space-between;
  font-size: 0.78rem;
  color: #6b7280;
  margin: 0.25rem 0;
}

.rts-top-bar {
  height: 6px;
  background: #f3f4f6;
  border-radius: 999px;
  overflow: hidden;
}

.rts-top-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #8b5cf6, #a78bfa);
  border-radius: 999px;
  transition: width 0.4s ease;
}

/* Footer */
.rts-footer {
  text-align: center;
  margin-top: 1.25rem;
  padding: 0.5rem;
}

.rts-refresh-text {
  font-size: 0.85rem;
  color: #9ca3af;
  display: inline-flex;
  align-items: center;
}

/* No access */
.rts-no-access {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 320px;
  padding: 2rem;
}

.rts-no-access-card {
  text-align: center;
  max-width: 380px;
  padding: 2.5rem;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.rts-no-access-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 1.5rem;
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.25rem;
  color: #6b7280;
}

.rts-no-access-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 0.5rem 0;
}

.rts-no-access-text {
  font-size: 0.95rem;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

@media (max-width: 768px) {
  .rts-header { padding: 1.25rem 1rem; }
  .rts-title { font-size: 1.3rem; }
  .rts-status-block { align-items: flex-start; }
  .rts-controls { flex-direction: column; align-items: stretch; }
  .rts-control-actions { margin-left: 0; justify-content: flex-end; }
  .rts-card { flex-direction: row; align-items: flex-start; min-height: auto; padding: 1.1rem; }
  .rts-card-value { font-size: 1.4rem; }
  .rts-card-value--price { font-size: 1.15rem; }
  .rts-payment-item--due { margin-left: 0; width: 100%; justify-content: space-between; }
  .rts-table thead th, .rts-table tbody td { padding: 0.5rem 0.6rem; }
}
</style>

<!-- Non-scoped dark-mode overrides. Scoped styles above carry a
     [data-v-xxxx] attribute that beats the global dark theme. Re-paint
     this page's surfaces here, scoped under
     .dark-theme .real-time-sales-counter-page so they don't leak. -->
<style>
.dark-theme .real-time-sales-counter-page .rts-header {
  background: linear-gradient(135deg, #1a1a1a 0%, #292929 100%);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  border: 1px solid #2a2a2a;
}

.dark-theme .real-time-sales-counter-page .rts-card {
  background: #202020;
  border-color: #2a2a2a;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}
.dark-theme .real-time-sales-counter-page .rts-card:hover {
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.45);
}
.dark-theme .real-time-sales-counter-page .rts-card-label {
  color: rgba(216, 216, 216, 0.7);
}
.dark-theme .real-time-sales-counter-page .rts-card-value {
  color: #f5f5f5;
}
.dark-theme .real-time-sales-counter-page .rts-card-value--time {
  color: rgba(216, 216, 216, 0.85);
}
.dark-theme .real-time-sales-counter-page .rts-card-hint {
  color: rgba(216, 216, 216, 0.55);
}
.dark-theme .real-time-sales-counter-page .rts-card-hint-muted {
  color: rgba(216, 216, 216, 0.55);
}
.dark-theme .real-time-sales-counter-page .rts-trend-flat {
  color: rgba(216, 216, 216, 0.55);
}

.dark-theme .real-time-sales-counter-page .rts-payment-bar {
  background: #202020;
  border-color: #2a2a2a;
}
.dark-theme .real-time-sales-counter-page .rts-payment-item {
  background: #292929;
  color: rgba(216, 216, 216, 0.85);
}
.dark-theme .real-time-sales-counter-page .rts-payment-item--due {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
}
.dark-theme .real-time-sales-counter-page .rts-payment-value {
  color: #f5f5f5;
}

.dark-theme .real-time-sales-counter-page .rts-panel {
  background: #202020;
  border-color: #2a2a2a;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}
.dark-theme .real-time-sales-counter-page .rts-panel-header {
  border-bottom-color: #2a2a2a;
}
.dark-theme .real-time-sales-counter-page .rts-panel-title {
  color: #f5f5f5;
}
.dark-theme .real-time-sales-counter-page .rts-panel-meta {
  background: #292929;
  color: rgba(216, 216, 216, 0.85);
}
.dark-theme .real-time-sales-counter-page .rts-empty {
  color: rgba(216, 216, 216, 0.55);
}

.dark-theme .real-time-sales-counter-page .rts-table thead th {
  background: #292929;
  color: rgba(216, 216, 216, 0.7);
  border-bottom-color: #2a2a2a;
}
.dark-theme .real-time-sales-counter-page .rts-table tbody td {
  border-bottom-color: #2a2a2a;
  color: #d8d8d8;
}
.dark-theme .real-time-sales-counter-page .rts-table tbody tr:hover {
  background: rgba(139, 92, 246, 0.08);
}
.dark-theme .real-time-sales-counter-page .rts-ref {
  color: #f5f5f5;
}
.dark-theme .real-time-sales-counter-page .rts-pos-pill {
  background: rgba(139, 92, 246, 0.22);
  color: #c4b5fd;
}
.dark-theme .real-time-sales-counter-page .rts-time-cell {
  color: rgba(216, 216, 216, 0.6);
}

.dark-theme .real-time-sales-counter-page .rts-status-pill--paid {
  background: rgba(16, 185, 129, 0.18);
  color: #6ee7b7;
}
.dark-theme .real-time-sales-counter-page .rts-status-pill--partial {
  background: rgba(245, 158, 11, 0.18);
  color: #fbbf24;
}
.dark-theme .real-time-sales-counter-page .rts-status-pill--unpaid {
  background: rgba(239, 68, 68, 0.18);
  color: #fca5a5;
}

.dark-theme .real-time-sales-counter-page .rts-row-new {
  background: rgba(74, 222, 128, 0.12);
}

.dark-theme .real-time-sales-counter-page .rts-top-rank {
  background: rgba(139, 92, 246, 0.22);
  color: #c4b5fd;
}
.dark-theme .real-time-sales-counter-page .rts-top-name {
  color: #f5f5f5;
}
.dark-theme .real-time-sales-counter-page .rts-top-meta {
  color: rgba(216, 216, 216, 0.65);
}
.dark-theme .real-time-sales-counter-page .rts-top-bar {
  background: #292929;
}

.dark-theme .real-time-sales-counter-page .rts-refresh-text {
  color: rgba(216, 216, 216, 0.55);
}

.dark-theme .rts-no-access-card {
  background: #202020;
  border-color: #2a2a2a;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}
.dark-theme .rts-no-access-icon {
  background: linear-gradient(135deg, #292929 0%, #1f1f1f 100%);
  color: rgba(216, 216, 216, 0.7);
}
.dark-theme .rts-no-access-title {
  color: #f5f5f5;
}
.dark-theme .rts-no-access-text {
  color: rgba(216, 216, 216, 0.7);
}

.dark-theme .real-time-sales-counter-page .rts-select {
  background: rgba(255, 255, 255, 0.95) !important;
  color: #1f2937 !important;
  border-color: rgba(255, 255, 255, 0.18) !important;
}

/* Sales by Location header band — soften the light-blue accent for
   dark-mode so it sits with the rest of the panels. */
.dark-theme .real-time-sales-counter-page .rts-panel-header--accent {
  background: linear-gradient(180deg, rgba(56, 189, 248, 0.12) 0%, rgba(56, 189, 248, 0.06) 100%);
  border-bottom-color: #2a2a2a;
}
.dark-theme .real-time-sales-counter-page .rts-panel-header--accent .rts-panel-title {
  color: #e0f2fe;
}
.dark-theme .real-time-sales-counter-page .rts-panel-header--accent .rts-panel-meta {
  background: rgba(56, 189, 248, 0.18);
  color: #e0f2fe;
}
.dark-theme .real-time-sales-counter-page .rts-loc-sn {
  color: rgba(216, 216, 216, 0.55);
}
.dark-theme .real-time-sales-counter-page .rts-loc-name {
  color: #f5f5f5;
}
</style>
