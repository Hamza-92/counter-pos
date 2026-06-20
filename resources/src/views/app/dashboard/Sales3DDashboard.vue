<template>
  <div class="main-content sales-3d-dashboard">
    <div v-if="loading" class="s3d-loading">
      <div class="s3d-loader">
        <div class="s3d-loader-cube">
          <div class="s3d-loader-face"></div>
          <div class="s3d-loader-face"></div>
          <div class="s3d-loader-face"></div>
          <div class="s3d-loader-face"></div>
          <div class="s3d-loader-face"></div>
          <div class="s3d-loader-face"></div>
        </div>
        <p class="s3d-loader-text">{{ $t("sales_3d_dashboard") }}</p>
      </div>
    </div>

    <div
      v-else-if="!loading && currentUserPermissions && currentUserPermissions.includes('sales_3d_dashboard')"
      class="s3d-root"
    >
      <!-- Hero header with glassmorphism -->
      <header class="s3d-hero">
        <div class="s3d-hero-bg">
          <div class="s3d-orb s3d-orb--purple"></div>
          <div class="s3d-orb s3d-orb--cyan"></div>
          <div class="s3d-orb s3d-orb--pink"></div>
        </div>

        <div class="s3d-hero-inner">
          <div class="s3d-hero-titles">
            <span class="s3d-eyebrow">
              <span class="s3d-eyebrow-dot"></span>
              {{ $t("Live_Insights")}}
            </span>
            <h1 class="s3d-hero-title">{{ $t("sales_3d_dashboard") }}</h1>
            <p class="s3d-hero-sub">{{ $t("Sales_3D_Dashboard_Subtitle") }}</p>
          </div>

          <div class="dashboard-header-filters d-flex align-items-center gap-2 flex-wrap">
            <div class="warehouse-filter">
              <v-select
                v-model="warehouse_id"
                :reduce="label => label.value"
                :placeholder="$t('Filter_by_warehouse')"
                :options="warehouses.map(w => ({ label: w.name, value: w.id }))"
                :clearable="true"
                @input="fetchData"
              >
                <template v-slot:option="option">
                  <lucide-icon class="mr-2" name="home" />
                  {{ option.label }}
                </template>
                <template v-slot:selected-option="option">
                  <lucide-icon class="mr-2" name="home" />
                  {{ option ? option.label : $t('Filter_by_warehouse') }}
                </template>
              </v-select>
            </div>
            <div class="date-range-filter">
              <date-range-picker
                v-model="dateRange"
                :locale-data="locale"
                :autoApply="true"
                :showDropdowns="true"
                :opens="'left'"
                @update="fetchData"
              >
                <template v-slot:input="picker">
                  <button type="button" class="date-picker-header-btn">
                    <lucide-icon class="mr-2" name="calendar-days" />
                    <span>{{ fmt(picker.startDate) }} - {{ fmt(picker.endDate) }}</span>
                  </button>
                </template>
              </date-range-picker>
            </div>
          </div>
        </div>
      </header>

      <!-- KPI cards -->
      <section class="s3d-kpis">
        <div class="s3d-kpi s3d-kpi--violet">
          <div class="s3d-kpi-icon"><lucide-icon name="banknote" /></div>
          <div class="s3d-kpi-body">
            <span class="s3d-kpi-label">{{ $t("Revenue") }}</span>
            <span class="s3d-kpi-value">{{ formatCurrency(kpis.revenue) }}</span>
          </div>
          <div class="s3d-kpi-spark"></div>
        </div>
        <div class="s3d-kpi s3d-kpi--blue">
          <div class="s3d-kpi-icon"><lucide-icon name="receipt-text" /></div>
          <div class="s3d-kpi-body">
            <span class="s3d-kpi-label">{{ $t("Orders") }}</span>
            <span class="s3d-kpi-value">{{ kpis.orders }}</span>
          </div>
          <div class="s3d-kpi-spark"></div>
        </div>
        <div class="s3d-kpi s3d-kpi--cyan">
          <div class="s3d-kpi-icon"><lucide-icon name="calculator" /></div>
          <div class="s3d-kpi-body">
            <span class="s3d-kpi-label">{{ $t("Avg_Order") }}</span>
            <span class="s3d-kpi-value">{{ formatCurrency(kpis.avg_order) }}</span>
          </div>
          <div class="s3d-kpi-spark"></div>
        </div>
        <div class="s3d-kpi s3d-kpi--pink">
          <div class="s3d-kpi-icon"><lucide-icon name="users" /></div>
          <div class="s3d-kpi-body">
            <span class="s3d-kpi-label">{{ $t("Customers") }}</span>
            <span class="s3d-kpi-value">{{ kpis.customers }}</span>
          </div>
          <div class="s3d-kpi-spark"></div>
        </div>
      </section>

      <!-- Charts grid -->
      <section class="s3d-grid">
        <article class="s3d-card s3d-card--span-2">
          <header class="s3d-card-head">
            <div>
              <h3 class="s3d-card-title">{{ $t("Sales_by_Month_and_Warehouse") }}</h3>
              <span class="s3d-card-sub">3D bar visualization · drag to rotate</span>
            </div>
            <span class="s3d-chip s3d-chip--violet">3D</span>
          </header>
          <div class="s3d-card-body">
            <div ref="chartSalesMatrix" class="echart-3d echart-3d--lg"></div>
          </div>
        </article>

        <article class="s3d-card">
          <header class="s3d-card-head">
            <div>
              <h3 class="s3d-card-title">{{ $t("Top_Products_by_Month") }}</h3>
              <span class="s3d-card-sub">Top {{ topProductsCount }} products</span>
            </div>
            <span class="s3d-chip s3d-chip--blue">3D</span>
          </header>
          <div class="s3d-card-body">
            <div ref="chartTopProducts" class="echart-3d"></div>
          </div>
        </article>

        <article class="s3d-card">
          <header class="s3d-card-head">
            <div>
              <h3 class="s3d-card-title">{{ $t("Product_Quantity_vs_Price_vs_Revenue") }}</h3>
              <span class="s3d-card-sub">Auto-rotating scatter</span>
            </div>
            <span class="s3d-chip s3d-chip--cyan">3D</span>
          </header>
          <div class="s3d-card-body">
            <div ref="chartScatter" class="echart-3d"></div>
          </div>
        </article>

        <article class="s3d-card">
          <header class="s3d-card-head">
            <div>
              <h3 class="s3d-card-title">{{ $t("Payment_Methods") }}</h3>
              <span class="s3d-card-sub">Distribution by method</span>
            </div>
            <span class="s3d-chip s3d-chip--pink">Pie</span>
          </header>
          <div class="s3d-card-body">
            <div ref="chartPayments" class="echart-3d echart-3d--md"></div>
          </div>
        </article>

        <article class="s3d-card">
          <header class="s3d-card-head">
            <div>
              <h3 class="s3d-card-title">{{ $t("Sales_Heatmap_Hour_DayOfWeek") }}</h3>
              <span class="s3d-card-sub">Hour × Day of week</span>
            </div>
            <span class="s3d-chip s3d-chip--cyan">3D</span>
          </header>
          <div class="s3d-card-body">
            <div ref="chartHeatmap" class="echart-3d echart-3d--md"></div>
          </div>
        </article>

        <article class="s3d-card s3d-card--span-2">
          <header class="s3d-card-head">
            <div>
              <h3 class="s3d-card-title">{{ $t("Top_customers") }}</h3>
              <span class="s3d-card-sub">Top {{ topClientsCount }} by revenue</span>
            </div>
            <span class="s3d-chip s3d-chip--violet">Bar</span>
          </header>
          <div class="s3d-card-body">
            <div ref="chartClients" class="echart-3d echart-3d--md"></div>
          </div>
        </article>
      </section>
    </div>

    <div v-else class="s3d-noaccess">
      <lucide-icon name="lock" />
      <h4>{{ $t("you_dont_have_permission") }}</h4>
    </div>
  </div>
</template>

<script>
import axios from "axios";
import moment from "moment";
import { mapGetters } from "vuex";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";

let echartsLib = null;

export default {
  name: "Sales3DDashboard",
  metaInfo: { title: "3D Sales Dashboard" },
  components: { "date-range-picker": DateRangePicker },

  data() {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - 29);
    return {
      loading: true,
      warehouses: [],
      warehouse_id: null,
      dateRange: { startDate: start, endDate: end },
      locale: {
        format: "yyyy-mm-dd",
        applyLabel: this.$t("Apply") || "Apply",
        cancelLabel: this.$t("Cancel") || "Cancel",
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange") || "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },
      kpis: { orders: 0, revenue: 0, avg_order: 0, customers: 0 },
      payload: null,
      charts: {},
      resizeHandler: null,
      fetchSeq: 0
    };
  },

  computed: {
    ...mapGetters(["currentUser", "currentUserPermissions"]),
    currency() {
      return (this.currentUser && this.currentUser.currency && this.currentUser.currency.symbol) || "$";
    },
    topProductsCount() {
      const d = this.payload && this.payload.top_products_by_month;
      return d && d.products ? d.products.length : 0;
    },
    topClientsCount() {
      const d = this.payload && this.payload.top_clients;
      return d ? d.length : 0;
    },
    palette() {
      return {
        violet: "#8b5cf6",
        blue: "#3b82f6",
        cyan: "#06b6d4",
        emerald: "#10b981",
        amber: "#f59e0b",
        pink: "#ec4899",
        rose: "#f43f5e",
        indigo: "#6366f1"
      };
    },
    pieColors() {
      return [
        "#8b5cf6", "#06b6d4", "#ec4899", "#f59e0b",
        "#10b981", "#3b82f6", "#f43f5e", "#6366f1",
        "#14b8a6", "#a855f7"
      ];
    },
    tooltipStyle() {
      return {
        backgroundColor: "rgba(15, 23, 42, 0.92)",
        borderColor: "rgba(139, 92, 246, 0.4)",
        borderWidth: 1,
        textStyle: { color: "#e2e8f0", fontSize: 12 },
        extraCssText: "backdrop-filter: blur(8px); border-radius: 10px; box-shadow: 0 8px 32px rgba(15,23,42,0.35);"
      };
    },
    axis3DName() {
      return {
        textStyle: { color: "#1e293b", fontSize: 13, fontWeight: 700 }
      };
    },
    axis3DStyle() {
      return {
        axisLine: { lineStyle: { color: "#94a3b8" } },
        axisLabel: { color: "#334155", fontSize: 12, fontWeight: 500 },
        splitLine: { lineStyle: { color: "rgba(100, 116, 139, 0.25)" } }
      };
    }
  },

  async mounted() {
    await this.loadEcharts();
    await this.fetchData();
    this.resizeHandler = this.handleResize.bind(this);
    window.addEventListener("resize", this.resizeHandler);
  },

  beforeDestroy() {
    if (this.resizeHandler) window.removeEventListener("resize", this.resizeHandler);
    Object.values(this.charts).forEach(c => c && c.dispose && c.dispose());
    this.charts = {};
  },

  methods: {
    fmt(d) {
      return moment(d).format("YYYY-MM-DD");
    },

    formatCurrency(v) {
      const n = Number(v || 0);
      return this.currency + " " + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    async loadEcharts() {
      if (echartsLib) return echartsLib;
      const echarts = await import(/* webpackChunkName: "echarts-gl" */ "echarts");
      await import(/* webpackChunkName: "echarts-gl" */ "echarts-gl");
      echartsLib = echarts;
      return echartsLib;
    },

    handleResize() {
      Object.values(this.charts).forEach(c => c && c.resize && c.resize());
    },

    buildParams() {
      return {
        warehouse_id: this.warehouse_id || 0,
        from: moment(this.dateRange.startDate).format("YYYY-MM-DD"),
        to: moment(this.dateRange.endDate).format("YYYY-MM-DD")
      };
    },

    async fetchData() {
      const seq = ++this.fetchSeq;
      this.loading = this.payload === null;
      try {
        const { data } = await axios.get("/api/sales_3d_dashboard_data", { params: this.buildParams() });
        if (seq !== this.fetchSeq) return;
        this.warehouses = data.warehouses || [];
        this.kpis = data.kpis || this.kpis;
        this.payload = data;
        this.loading = false;
        await this.$nextTick();
        this.renderAll();
      } catch (e) {
        if (seq !== this.fetchSeq) return;
        this.loading = false;
        console.error("Sales3DDashboard fetch failed", e);
      }
    },

    initChart(refName) {
      const el = this.$refs[refName];
      if (!el) return null;
      if (this.charts[refName]) {
        this.charts[refName].dispose();
      }
      this.charts[refName] = echartsLib.init(el, null, { renderer: "canvas" });
      return this.charts[refName];
    },

    renderAll() {
      if (!echartsLib || !this.payload) return;
      this.renderSalesMatrix();
      this.renderTopProducts();
      this.renderScatter();
      this.renderPayments();
      this.renderHeatmap();
      this.renderClients();
    },

    renderSalesMatrix() {
      const chart = this.initChart("chartSalesMatrix");
      if (!chart) return;
      const d = this.payload.sales_by_month_warehouse || { months: [], warehouses: [], data: [] };
      const max = Math.max(1, ...d.data.map(p => p[2]));
      chart.setOption({
        tooltip: {
          ...this.tooltipStyle,
          formatter: p =>
            `<b>${d.warehouses[p.value[1]]}</b> · ${d.months[p.value[0]]}<br/><span style="color:#a78bfa">${this.formatCurrency(p.value[2])}</span>`
        },
        visualMap: {
          max,
          show: true,
          right: 10,
          top: "middle",
          itemWidth: 10,
          itemHeight: 110,
          inRange: { color: ["#1e1b4b", "#4f46e5", "#8b5cf6", "#ec4899", "#f59e0b"] },
          textStyle: { color: "#334155", fontSize: 11, fontWeight: 500 }
        },
        xAxis3D: { type: "category", data: d.months, name: this.$t("Month"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        yAxis3D: { type: "category", data: d.warehouses, name: this.$t("Warehouses"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        zAxis3D: { type: "value", name: this.$t("Sales"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        grid3D: {
          boxWidth: 200,
          boxDepth: 80,
          environment: "rgba(248, 250, 252, 0)",
          viewControl: { autoRotate: false, projection: "perspective", distance: 200 },
          light: {
            main: { intensity: 1.4, shadow: true, shadowQuality: "high", alpha: 30, beta: 40 },
            ambient: { intensity: 0.4 }
          },
          postEffect: { enable: true, bloom: { enable: true, bloomIntensity: 0.1 }, SSAO: { enable: true, intensity: 1.2, radius: 5 } }
        },
        series: [
          {
            type: "bar3D",
            data: d.data.map(p => ({ value: p })),
            shading: "realistic",
            realisticMaterial: { roughness: 0.3, metalness: 0.2 },
            label: { show: false },
            itemStyle: { opacity: 0.95 },
            emphasis: { label: { show: true, formatter: p => this.formatCurrency(p.value[2]), textStyle: { color: "#fff", backgroundColor: "rgba(15,23,42,0.85)", padding: [4, 8], borderRadius: 4 } } }
          }
        ]
      });
    },

    renderTopProducts() {
      const chart = this.initChart("chartTopProducts");
      if (!chart) return;
      const d = this.payload.top_products_by_month || { months: [], products: [], data: [] };
      const max = Math.max(1, ...d.data.map(p => p[2]));
      chart.setOption({
        tooltip: {
          ...this.tooltipStyle,
          formatter: p =>
            `<b>${d.products[p.value[1]]}</b><br/>${d.months[p.value[0]]} · <span style="color:#22d3ee">${this.formatCurrency(p.value[2])}</span>`
        },
        visualMap: {
          max,
          show: true,
          right: 10,
          top: "middle",
          itemWidth: 10,
          itemHeight: 110,
          inRange: { color: ["#0c1638", "#1e40af", "#0ea5e9", "#06b6d4", "#a78bfa", "#ec4899"] },
          textStyle: { color: "#334155", fontSize: 11, fontWeight: 500 }
        },
        xAxis3D: { type: "category", data: d.months, name: this.$t("Month"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        yAxis3D: { type: "category", data: d.products, name: this.$t("Product"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        zAxis3D: { type: "value", name: this.$t("Revenue"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        grid3D: {
          boxWidth: 200,
          boxDepth: 120,
          viewControl: { autoRotate: false, projection: "perspective", distance: 220 },
          light: { main: { intensity: 1.3, shadow: true, alpha: 30, beta: 30 }, ambient: { intensity: 0.4 } },
          postEffect: { enable: true, SSAO: { enable: true, intensity: 1.0 } }
        },
        series: [
          {
            type: "bar3D",
            data: d.data.map(p => ({ value: p })),
            shading: "realistic",
            realisticMaterial: { roughness: 0.4, metalness: 0.15 },
            itemStyle: { opacity: 0.95 }
          }
        ]
      });
    },

    renderScatter() {
      const chart = this.initChart("chartScatter");
      if (!chart) return;
      const data = this.payload.product_scatter || [];
      const maxRev = Math.max(1, ...data.map(p => p[2]));
      chart.setOption({
        tooltip: {
          ...this.tooltipStyle,
          formatter: p =>
            `<b>${p.value[3]}</b><br/>${this.$t("Quantity")}: ${p.value[0]}<br/>${this.$t("Price")}: ${this.formatCurrency(p.value[1])}<br/><span style="color:#22d3ee">${this.$t("Revenue")}: ${this.formatCurrency(p.value[2])}</span>`
        },
        visualMap: {
          show: false,
          dimension: 2,
          max: maxRev,
          inRange: { color: ["#06b6d4", "#8b5cf6", "#ec4899"] }
        },
        xAxis3D: { type: "value", name: this.$t("Quantity"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        yAxis3D: { type: "value", name: this.$t("Price"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        zAxis3D: { type: "value", name: this.$t("Revenue"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        grid3D: {
          viewControl: { autoRotate: true, autoRotateSpeed: 6, projection: "perspective", distance: 220 },
          light: { main: { intensity: 1.2 }, ambient: { intensity: 0.5 } },
          postEffect: { enable: true, bloom: { enable: true, bloomIntensity: 0.4 } }
        },
        series: [
          {
            type: "scatter3D",
            data,
            symbolSize: 14,
            itemStyle: { opacity: 0.85 },
            emphasis: { itemStyle: { color: "#f43f5e", borderColor: "#fff", borderWidth: 2 } }
          }
        ]
      });
    },

    renderPayments() {
      const chart = this.initChart("chartPayments");
      if (!chart) return;
      const data = this.payload.payment_methods || [];
      chart.setOption({
        color: this.pieColors,
        tooltip: {
          ...this.tooltipStyle,
          trigger: "item",
          formatter: p => `<b>${p.name}</b><br/>${this.formatCurrency(p.value)} <span style="color:#a78bfa">(${p.percent}%)</span>`
        },
        legend: { bottom: 0, textStyle: { color: "#1e293b", fontSize: 12, fontWeight: 500 }, itemWidth: 10, itemHeight: 10, icon: "circle" },
        series: [
          {
            type: "pie",
            radius: ["42%", "72%"],
            center: ["50%", "44%"],
            roseType: "radius",
            avoidLabelOverlap: true,
            itemStyle: {
              borderRadius: 10,
              borderColor: "#fff",
              borderWidth: 3,
              shadowBlur: 12,
              shadowColor: "rgba(139, 92, 246, 0.18)"
            },
            label: { color: "#1e293b", fontSize: 12, fontWeight: 600, formatter: "{b}\n{d}%" },
            labelLine: { length: 8, length2: 6 },
            emphasis: {
              scale: true,
              scaleSize: 8,
              itemStyle: { shadowBlur: 20, shadowColor: "rgba(139,92,246,0.45)" }
            },
            data
          }
        ]
      });
    },

    renderHeatmap() {
      const chart = this.initChart("chartHeatmap");
      if (!chart) return;
      const data = this.payload.hour_dow_heatmap || [];
      const days = [
        this.$t("Sun") || "Sun",
        this.$t("Mon") || "Mon",
        this.$t("Tue") || "Tue",
        this.$t("Wed") || "Wed",
        this.$t("Thu") || "Thu",
        this.$t("Fri") || "Fri",
        this.$t("Sat") || "Sat"
      ];
      const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, "0") + "h");
      const max = Math.max(1, ...data.map(p => p[2]));
      chart.setOption({
        tooltip: {
          ...this.tooltipStyle,
          formatter: p => `<b>${days[p.value[1]]}</b> · ${hours[p.value[0]]}<br/><span style="color:#22d3ee">${this.formatCurrency(p.value[2])}</span>`
        },
        visualMap: {
          max,
          calculable: true,
          orient: "horizontal",
          left: "center",
          bottom: 0,
          itemWidth: 12,
          itemHeight: 110,
          inRange: { color: ["#0c4a6e", "#0891b2", "#22d3ee", "#a78bfa", "#f472b6"] },
          textStyle: { color: "#334155", fontSize: 11, fontWeight: 500 }
        },
        xAxis3D: { type: "category", data: hours, name: this.$t("Hour"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        yAxis3D: { type: "category", data: days, name: this.$t("Day"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        zAxis3D: { type: "value", name: this.$t("Sales"), nameTextStyle: this.axis3DName.textStyle, ...this.axis3DStyle },
        grid3D: {
          boxWidth: 220,
          boxDepth: 100,
          viewControl: { autoRotate: false, projection: "perspective", distance: 220 },
          light: { main: { intensity: 1.2, shadow: true, alpha: 30, beta: 30 }, ambient: { intensity: 0.45 } },
          postEffect: { enable: true, SSAO: { enable: true, intensity: 1.0 } }
        },
        series: [
          {
            type: "bar3D",
            data: data.map(p => ({ value: p })),
            shading: "realistic",
            realisticMaterial: { roughness: 0.4, metalness: 0.1 },
            itemStyle: { opacity: 0.92 }
          }
        ]
      });
    },

    renderClients() {
      const chart = this.initChart("chartClients");
      if (!chart) return;
      const data = this.payload.top_clients || [];
      chart.setOption({
        tooltip: {
          ...this.tooltipStyle,
          trigger: "axis",
          axisPointer: { type: "shadow", shadowStyle: { color: "rgba(139, 92, 246, 0.08)" } },
          formatter: p => {
            const it = p[0];
            const row = data[it.dataIndex] || {};
            return `<b>${it.name}</b><br/><span style="color:#a78bfa">${this.$t("Revenue")}: ${this.formatCurrency(it.value)}</span><br/>${this.$t("Orders")}: ${row.orders || 0}`;
          }
        },
        grid: { left: 140, right: 30, top: 10, bottom: 20, containLabel: false },
        xAxis: {
          type: "value",
          axisLine: { show: false },
          axisTick: { show: false },
          splitLine: { lineStyle: { color: "rgba(100, 116, 139, 0.25)", type: "dashed" } },
          axisLabel: { color: "#475569", fontSize: 12, fontWeight: 500, formatter: v => this.shortNumber(v) }
        },
        yAxis: {
          type: "category",
          inverse: true,
          data: data.map(d => d.name),
          axisLine: { show: false },
          axisTick: { show: false },
          axisLabel: { color: "#1e293b", fontSize: 13, fontWeight: 600 }
        },
        series: [
          {
            type: "bar",
            data: data.map(d => d.value),
            barWidth: 16,
            itemStyle: {
              borderRadius: [0, 8, 8, 0],
              color: {
                type: "linear", x: 0, y: 0, x2: 1, y2: 0,
                colorStops: [
                  { offset: 0, color: "#8b5cf6" },
                  { offset: 0.5, color: "#6366f1" },
                  { offset: 1, color: "#06b6d4" }
                ]
              },
              shadowBlur: 10,
              shadowColor: "rgba(139,92,246,0.25)"
            },
            emphasis: {
              itemStyle: {
                color: {
                  type: "linear", x: 0, y: 0, x2: 1, y2: 0,
                  colorStops: [
                    { offset: 0, color: "#ec4899" },
                    { offset: 1, color: "#f59e0b" }
                  ]
                }
              }
            },
            label: {
              show: true,
              position: "right",
              color: "#0f172a",
              fontSize: 12,
              fontWeight: 700,
              formatter: p => this.shortNumber(p.value)
            }
          }
        ]
      });
    },

    shortNumber(v) {
      const n = Number(v) || 0;
      if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(1) + "M";
      if (Math.abs(n) >= 1e3) return (n / 1e3).toFixed(1) + "K";
      return n.toString();
    }
  }
};
</script>

<style scoped>
/* ===== Layout root ===== */
.sales-3d-dashboard {
  --s3d-bg: #f6f7fb;
  --s3d-surface: #ffffff;
  --s3d-surface-2: rgba(255, 255, 255, 0.78);
  --s3d-text: #0f172a;
  --s3d-muted: #475569;
  --s3d-line: rgba(148, 163, 184, 0.18);
  --s3d-violet: #8b5cf6;
  --s3d-blue: #3b82f6;
  --s3d-cyan: #06b6d4;
  --s3d-pink: #ec4899;
  --s3d-amber: #f59e0b;
  --s3d-radius: 18px;
  --s3d-shadow: 0 10px 30px -12px rgba(15, 23, 42, 0.18);
  background:
    radial-gradient(1200px 600px at -10% -20%, rgba(139, 92, 246, 0.12), transparent 60%),
    radial-gradient(900px 500px at 110% 10%, rgba(6, 182, 212, 0.10), transparent 55%),
    var(--s3d-bg);
  min-height: 100%;
  padding: 18px;
}

.s3d-root {
  max-width: 1500px;
  margin: 0 auto;
  animation: s3dFadeIn 0.5s ease;
}
@keyframes s3dFadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ===== Hero header ===== */
.s3d-hero {
  position: relative;
  border-radius: 24px;
  padding: 28px 32px;
  margin-bottom: 22px;
  background:
    linear-gradient(135deg, rgba(139, 92, 246, 0.95) 0%, rgba(99, 102, 241, 0.92) 50%, rgba(6, 182, 212, 0.9) 100%);
  color: #fff;
  box-shadow: 0 20px 60px -20px rgba(99, 102, 241, 0.5);
}
.s3d-hero-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  overflow: hidden;
  border-radius: 24px;
}
.s3d-orb {
  position: absolute;
  width: 320px;
  height: 320px;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.55;
}
.s3d-orb--purple { background: #a855f7; top: -120px; left: -100px; animation: s3dFloat 14s ease-in-out infinite; }
.s3d-orb--cyan   { background: #22d3ee; bottom: -140px; right: 10%; animation: s3dFloat 18s ease-in-out infinite reverse; }
.s3d-orb--pink   { background: #ec4899; top: -80px; right: -120px; animation: s3dFloat 20s ease-in-out infinite; }
@keyframes s3dFloat {
  0%, 100% { transform: translate(0, 0) scale(1); }
  50%      { transform: translate(40px, 30px) scale(1.08); }
}

.s3d-hero-inner {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
}
.s3d-hero-titles { min-width: 0; }
.s3d-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  background: rgba(255, 255, 255, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.5px;
  backdrop-filter: blur(6px);
}
.s3d-eyebrow-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #4ade80;
  box-shadow: 0 0 10px #4ade80;
  animation: s3dPulse 1.6s ease-in-out infinite;
}
@keyframes s3dPulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%      { opacity: 0.55; transform: scale(0.85); }
}
.s3d-hero-title {
  margin: 12px 0 4px;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.5px;
}
.s3d-hero-sub {
  margin: 0;
  opacity: 0.85;
  font-size: 14px;
}

/* ===== Filters (matches main dashboard look) ===== */
.dashboard-header-filters {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: nowrap;
  flex-shrink: 0;
}

.warehouse-filter {
  display: inline-block;
  flex: 0 0 auto;
  min-width: 240px;
  max-width: 320px;
  position: relative;
}

.date-range-filter {
  display: inline-block;
  flex: 0 0 auto;
  min-width: 240px;
  max-width: 320px;
  position: relative;
}

.date-range-filter ::v-deep .vue-daterange-picker {
  width: 100% !important;
}

.date-picker-header-btn {
  background: rgba(255, 255, 255, 0.2) !important;
  border: none !important;
  border-radius: 8px !important;
  padding: 0.5rem 1rem !important;
  font-weight: 600 !important;
  color: white !important;
  transition: all 0.3s ease !important;
  width: 100%;
  text-align: left;
  display: flex;
  align-items: center;
  cursor: pointer;
  font-size: 0.95rem;
}
.date-picker-header-btn:hover {
  background: rgba(255, 255, 255, 0.3) !important;
  color: white !important;
}
.date-picker-header-btn:focus,
.date-picker-header-btn:active {
  background: rgba(255, 255, 255, 0.3) !important;
  color: white !important;
  outline: none;
  box-shadow: none !important;
}
.date-picker-header-btn i {
  color: white;
  margin-right: 0.5rem;
}
.date-picker-header-btn span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* vue-select on the gradient hero — white-on-glass */
.warehouse-filter ::v-deep .v-select {
  width: 100%;
  background: transparent !important;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-toggle {
  background: rgba(255, 255, 255, 0.2) !important;
  border: none !important;
  border-radius: 8px !important;
  padding: 0.5rem 1rem !important;
  min-height: auto !important;
  height: auto !important;
  cursor: pointer;
  transition: all 0.3s ease !important;
  display: flex !important;
  align-items: center !important;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-toggle * {
  background: transparent !important;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-toggle:hover {
  background: rgba(255, 255, 255, 0.3) !important;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-toggle:focus,
.warehouse-filter ::v-deep .v-select .vs__dropdown-toggle:active {
  background: rgba(255, 255, 255, 0.3) !important;
  outline: none;
  box-shadow: none !important;
}
.warehouse-filter ::v-deep .v-select .vs__selected-options {
  padding: 0 !important;
  margin: 0 !important;
  display: flex;
  align-items: center;
  flex: 1;
}
.warehouse-filter ::v-deep .v-select .vs__selected-options .vs__selected {
  color: white !important;
  font-weight: 600 !important;
  font-size: 0.95rem !important;
  margin: 0 !important;
  padding: 0 !important;
  background: transparent !important;
  border: none !important;
  display: flex;
  align-items: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.warehouse-filter ::v-deep .v-select .vs__selected-options .vs__selected i {
  color: white;
  margin-right: 0.5rem;
  flex-shrink: 0;
}
.warehouse-filter ::v-deep .v-select .vs__search,
.warehouse-filter ::v-deep .v-select .vs__search:focus {
  color: white !important;
  font-weight: 600 !important;
  font-size: 0.95rem !important;
  padding: 0 !important;
  margin: 0 !important;
  background: transparent !important;
  border: none !important;
  height: auto !important;
  line-height: 1.5 !important;
  box-shadow: none !important;
}
.warehouse-filter ::v-deep .v-select .vs__search::placeholder {
  color: rgba(255, 255, 255, 0.7) !important;
  font-weight: 600 !important;
}
.warehouse-filter ::v-deep .v-select .vs__actions {
  padding: 0 !important;
  margin-left: 0.5rem;
}
.warehouse-filter ::v-deep .v-select .vs__clear {
  fill: white !important;
  margin-right: 0.5rem;
}
.warehouse-filter ::v-deep .v-select .vs__open-indicator {
  fill: white !important;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-menu {
  z-index: 2056 !important;
  background: #ffffff !important;
  border-radius: 8px !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
  border: 1px solid #e0e6ed !important;
  margin-top: 0.5rem !important;
  padding: 4px 0 !important;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-menu .vs__dropdown-option,
.warehouse-filter ::v-deep .v-select .vs__dropdown-menu li {
  padding: 0.6rem 1rem !important;
  color: #1f2937 !important;
  background: #ffffff !important;
  font-size: 0.875rem !important;
  font-weight: 500 !important;
  display: flex !important;
  align-items: center !important;
  cursor: pointer;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-menu .vs__dropdown-option i {
  color: #1f2937 !important;
  margin-right: 0.5rem;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-menu .vs__dropdown-option--highlight,
.warehouse-filter ::v-deep .v-select .vs__dropdown-menu .vs__dropdown-option--highlight * {
  background: #8B5CF6 !important;
  color: #ffffff !important;
}
.warehouse-filter ::v-deep .v-select .vs__dropdown-menu .vs__no-options {
  color: #64748b !important;
  background: #ffffff !important;
  padding: 0.6rem 1rem !important;
}

.date-range-filter ::v-deep .form-control.reportrange-text {
  background: transparent !important;
  color: white !important;
  border: none !important;
  padding: 0 !important;
}
.date-range-filter ::v-deep .daterangepicker {
  z-index: 2055 !important;
  color: #111827 !important;
}

/* Calendar dropdown — responsive layout matches main dashboard */
@media (max-width: 767px) {
  .date-range-filter ::v-deep .daterangepicker {
    left: 12px !important;
    right: 12px !important;
    width: calc(100% - 24px) !important;
    max-width: calc(100vw - 24px) !important;
  }
  .date-range-filter ::v-deep .daterangepicker .calendars-container {
    display: flex !important;
    flex-direction: column !important;
  }
  .date-range-filter ::v-deep .daterangepicker .drp-calendar {
    float: none !important;
    width: 100% !important;
    padding: 10px !important;
  }
  .date-range-filter ::v-deep .daterangepicker .drp-calendar.right {
    display: none !important;
  }
  .date-range-filter ::v-deep .daterangepicker .ranges {
    float: none !important;
    width: 100% !important;
    margin: 10px 0 0 0 !important;
    border-top: 1px solid #e5e7eb !important;
    padding-top: 10px !important;
  }
  .date-range-filter ::v-deep .daterangepicker .ranges ul {
    width: 100% !important;
  }
  .date-range-filter ::v-deep .daterangepicker .ranges li {
    width: 100% !important;
    margin-bottom: 5px !important;
    text-align: center !important;
  }
  .date-range-filter ::v-deep .daterangepicker .calendar-table {
    width: 100% !important;
  }
  .date-range-filter ::v-deep .daterangepicker .calendar-table th,
  .date-range-filter ::v-deep .daterangepicker .calendar-table td {
    padding: 6px !important;
    font-size: 0.875rem !important;
  }
  .date-range-filter ::v-deep .daterangepicker .drp-buttons {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
    padding: 10px !important;
  }
  .date-range-filter ::v-deep .daterangepicker .drp-buttons .btn {
    width: 100% !important;
    margin: 0 !important;
  }
}
@media (max-width: 576px) {
  .date-range-filter ::v-deep .daterangepicker {
    left: 8px !important;
    right: 8px !important;
    width: calc(100% - 16px) !important;
    max-width: calc(100vw - 16px) !important;
  }
  .date-range-filter ::v-deep .daterangepicker .calendar-table th,
  .date-range-filter ::v-deep .daterangepicker .calendar-table td {
    padding: 4px !important;
    font-size: 0.75rem !important;
  }
  .date-range-filter ::v-deep .daterangepicker .drp-calendar {
    padding: 8px !important;
  }
}

/* ===== KPIs ===== */
.s3d-kpis {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}
.s3d-kpi {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  border-radius: var(--s3d-radius);
  background: var(--s3d-surface);
  box-shadow: var(--s3d-shadow);
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.s3d-kpi::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: inherit;
  padding: 1px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.4), rgba(148, 163, 184, 0.05));
  -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
          mask-composite: exclude;
  pointer-events: none;
}
.s3d-kpi:hover {
  transform: translateY(-3px);
  box-shadow: 0 18px 40px -16px rgba(15, 23, 42, 0.22);
}
.s3d-kpi-icon {
  flex: none;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  font-size: 22px;
  color: #fff;
  position: relative;
  z-index: 1;
}
.s3d-kpi-body {
  display: flex;
  flex-direction: column;
  position: relative;
  z-index: 1;
}
.s3d-kpi-label {
  font-size: 12px;
  color: var(--s3d-muted);
  font-weight: 600;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}
.s3d-kpi-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--s3d-text);
  letter-spacing: -0.4px;
  margin-top: 2px;
}
.s3d-kpi-spark {
  position: absolute;
  right: -20px;
  bottom: -20px;
  width: 130px;
  height: 130px;
  border-radius: 50%;
  opacity: 0.12;
  filter: blur(2px);
}
.s3d-kpi--violet .s3d-kpi-icon { background: linear-gradient(135deg, #a855f7, #6366f1); }
.s3d-kpi--violet .s3d-kpi-spark { background: radial-gradient(circle, #a855f7, transparent 70%); }
.s3d-kpi--blue   .s3d-kpi-icon { background: linear-gradient(135deg, #3b82f6, #06b6d4); }
.s3d-kpi--blue   .s3d-kpi-spark { background: radial-gradient(circle, #3b82f6, transparent 70%); }
.s3d-kpi--cyan   .s3d-kpi-icon { background: linear-gradient(135deg, #06b6d4, #14b8a6); }
.s3d-kpi--cyan   .s3d-kpi-spark { background: radial-gradient(circle, #06b6d4, transparent 70%); }
.s3d-kpi--pink   .s3d-kpi-icon { background: linear-gradient(135deg, #ec4899, #f59e0b); }
.s3d-kpi--pink   .s3d-kpi-spark { background: radial-gradient(circle, #ec4899, transparent 70%); }

/* ===== Charts grid ===== */
.s3d-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 18px;
}
.s3d-card {
  background: var(--s3d-surface);
  border-radius: var(--s3d-radius);
  box-shadow: var(--s3d-shadow);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.s3d-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 24px 50px -20px rgba(15, 23, 42, 0.24);
}
.s3d-card--span-2 { grid-column: span 2; }

.s3d-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 20px 8px;
}
.s3d-card-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--s3d-text);
  letter-spacing: -0.2px;
}
.s3d-card-sub {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  color: var(--s3d-muted);
  font-weight: 500;
}
.s3d-chip {
  flex: none;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: #fff;
}
.s3d-chip--violet { background: linear-gradient(135deg, #a855f7, #6366f1); }
.s3d-chip--blue   { background: linear-gradient(135deg, #3b82f6, #06b6d4); }
.s3d-chip--cyan   { background: linear-gradient(135deg, #06b6d4, #14b8a6); }
.s3d-chip--pink   { background: linear-gradient(135deg, #ec4899, #f59e0b); }

.s3d-card-body { padding: 8px 14px 18px; }

.echart-3d  { width: 100%; height: 380px; }
.echart-3d--md { height: 320px; }
.echart-3d--lg { height: 460px; }

/* ===== No-access state ===== */
.s3d-noaccess {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  color: var(--s3d-muted);
}
.s3d-noaccess i {
  font-size: 56px;
  margin-bottom: 12px;
  color: var(--s3d-violet);
}

/* ===== Loader (3D rotating cube) ===== */
.s3d-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
}
.s3d-loader { text-align: center; }
.s3d-loader-cube {
  width: 60px;
  height: 60px;
  margin: 0 auto 18px;
  position: relative;
  transform-style: preserve-3d;
  animation: s3dRotate 4s infinite linear;
}
.s3d-loader-face {
  position: absolute;
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #8b5cf6, #06b6d4);
  border: 1px solid rgba(255, 255, 255, 0.4);
  opacity: 0.85;
  border-radius: 8px;
  box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
}
.s3d-loader-face:nth-child(1) { transform: translateZ(30px); }
.s3d-loader-face:nth-child(2) { transform: rotateY(180deg) translateZ(30px); }
.s3d-loader-face:nth-child(3) { transform: rotateY( 90deg) translateZ(30px); }
.s3d-loader-face:nth-child(4) { transform: rotateY(-90deg) translateZ(30px); }
.s3d-loader-face:nth-child(5) { transform: rotateX( 90deg) translateZ(30px); }
.s3d-loader-face:nth-child(6) { transform: rotateX(-90deg) translateZ(30px); }
@keyframes s3dRotate {
  from { transform: rotateX(0) rotateY(0); }
  to   { transform: rotateX(360deg) rotateY(360deg); }
}
.s3d-loader-text {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.5px;
  color: var(--s3d-muted);
}

/* ===== Responsive ===== */
@media (max-width: 1100px) {
  .s3d-kpis { grid-template-columns: repeat(2, 1fr); }
  .s3d-grid { grid-template-columns: 1fr; }
  .s3d-card--span-2 { grid-column: span 1; }
}
@media (max-width: 900px) {
  .s3d-hero-inner { flex-direction: column; align-items: stretch; }
  .dashboard-header-filters { width: 100%; }
  .warehouse-filter,
  .date-range-filter { flex: 1 1 0; min-width: 0; max-width: 100%; }
}
@media (max-width: 600px) {
  .sales-3d-dashboard { padding: 12px; }
  .s3d-hero { padding: 22px 18px; border-radius: 18px; }
  .s3d-hero-title { font-size: 22px; }
  .s3d-kpis { grid-template-columns: 1fr; }
  .dashboard-header-filters { flex-direction: column; }
  .warehouse-filter,
  .date-range-filter { width: 100%; max-width: 100%; }
  .echart-3d { height: 320px; }
  .echart-3d--lg { height: 360px; }
}
</style>
