<template>
  <div class="main-content">
    <div v-if="loading" class="loading_page spinner spinner-primary mr-3"></div>

    <div
      v-else-if="!loading && currentUserPermissions && currentUserPermissions.includes('dashboard')"
      class="dashboard-static dashboard-page-root"
      :class="{ 'dashboard-static--mobile-app': isMobileViewport }"
      :style="dashboardFontStyle"
    >
      <template v-for="sectionId in orderedDashboardSections">
        <!-- Header + mobile quick modules (modules directly under header) -->
        <template v-if="sectionId === 'header'">
          <div :key="'hdr-' + sectionId" class="dashboard-header mb-3">
            <div class="row align-items-center">
              <div class="col-md-6 dashboard-header-titles">
                <h2 class="mb-1 text-dark">{{ $t('dashboard') }}</h2>
                <p class="welcome-text mb-0">{{ $t('Welcome_back_message', { username: currentUser.username }) }}</p>
              </div>
              <div class="col-md-6 text-right dashboard-header-filters-col">
                <div class="dashboard-header-filters d-flex align-items-center justify-content-end gap-2 flex-wrap">
                  <div class="warehouse-filter">
                    <v-select
                      @input="Selected_Warehouse"
                      v-model="warehouse_id"
                      :reduce="label => label.value"
                      :placeholder="$t('Filter_by_warehouse')"
                      :options="warehouses.map(w => ({label:w.name, value:w.id}))"
                      :clearable="true"
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
                      :drops="'down'"
                      @update="Submit_filter_dateRange"
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
            </div>
          </div>
          <section
            :key="'mq-' + sectionId"
            class="dashboard-mobile-only mobile-quick-modules"
            aria-label="Quick modules"
          >
            <div class="mobile-module-grid">
              <router-link
                v-for="mod in mobileQuickModules"
                :key="mod.key"
                :to="mod.to"
                class="mobile-module-tile ripple-touch"
              >
                <div class="mobile-module-icon" :class="mod.toneClass">
                  <lucide-icon :name="mod.iconName" />
                </div>
                <span class="mobile-module-label">{{ mod.label }}</span>
              </router-link>
            </div>
          </section>
        </template>

        <!-- Stat cards row 1 -->
        <b-row v-else-if="sectionId === 'stat_cards_1'" :key="sectionId" class="mb-3 align-items-stretch dashboard-mobile-stat-grid">
          <b-col md="3" sm="6" class="mb-3 mb-md-0">
            <router-link to="/app/sales/list" class="stat-card sales-card h-100">
              <div class="stat-card-icon"><lucide-icon name="shopping-cart" /></div>
              <div class="stat-card-content">
                <p class="stat-card-label">{{ $t('Sales') }}</p>
                <h3 class="stat-card-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, report_today.today_sales || 0, 2) }}</h3>
              </div>
            </router-link>
          </b-col>
          <b-col md="3" sm="6" class="mb-3 mb-md-0">
            <router-link to="/app/purchases/list" class="stat-card purchases-card h-100">
              <div class="stat-card-icon"><lucide-icon name="shopping-cart" /></div>
              <div class="stat-card-content">
                <p class="stat-card-label">{{ $t('Purchases') }}</p>
                <h3 class="stat-card-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, report_today.today_purchases || 0, 2) }}</h3>
              </div>
            </router-link>
          </b-col>
          <b-col md="3" sm="6" class="mb-3 mb-md-0">
            <router-link to="/app/sale_return/list" class="stat-card returns-card h-100">
              <div class="stat-card-icon"><lucide-icon name="chevron-right" /></div>
              <div class="stat-card-content">
                <p class="stat-card-label">{{ $t('SalesReturn') }}</p>
                <h3 class="stat-card-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, report_today.return_sales || 0, 2) }}</h3>
              </div>
            </router-link>
          </b-col>
          <b-col md="3" sm="6" class="mb-3 mb-md-0">
            <router-link to="/app/purchase_return/list" class="stat-card revenue-card h-100">
              <div class="stat-card-icon"><lucide-icon name="chevron-left" /></div>
              <div class="stat-card-content">
                <p class="stat-card-label">{{ $t('PurchasesReturn') }}</p>
                <h3 class="stat-card-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, report_today.return_purchases || 0, 2) }}</h3>
              </div>
            </router-link>
          </b-col>
        </b-row>

        <!-- Stat cards row 2 -->
        <b-row v-else-if="sectionId === 'stat_cards_2'" :key="sectionId" class="mb-3 align-items-stretch dashboard-mobile-stat-grid">
          <b-col md="3" sm="6" class="mb-3 mb-md-0">
            <router-link to="/app/sales/list" class="stat-card sales-due-card h-100">
              <div class="stat-card-icon"><lucide-icon name="banknote" /></div>
              <div class="stat-card-content">
                <p class="stat-card-label">{{ $t('Sales_Due') }}</p>
                <h3 class="stat-card-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, report_today.sales_due || 0, 2) }}</h3>
              </div>
            </router-link>
          </b-col>
          <b-col md="3" sm="6" class="mb-3 mb-md-0">
            <router-link to="/app/purchases/list" class="stat-card purchase-due-card h-100">
              <div class="stat-card-icon"><lucide-icon name="wallet" /></div>
              <div class="stat-card-content">
                <p class="stat-card-label">{{ $t('Total_Purchase_Due') }}</p>
                <h3 class="stat-card-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, report_today.purchase_due || 0, 2) }}</h3>
              </div>
            </router-link>
          </b-col>
          <b-col md="3" sm="6" class="mb-3 mb-md-0">
            <router-link to="/app/sales/list" class="stat-card invoices-card h-100">
              <div class="stat-card-icon"><lucide-icon name="file-text" /></div>
              <div class="stat-card-content">
                <p class="stat-card-label">{{ $t('Invoice') }}</p>
                <h3 class="stat-card-value">{{ report_today.today_invoices ? report_today.today_invoices : 0 }}</h3>
              </div>
            </router-link>
          </b-col>
          <b-col md="3" sm="6" class="mb-3 mb-md-0">
            <router-link to="/app/reports/profit_and_loss" class="stat-card profit-card h-100">
              <div class="stat-card-icon"><lucide-icon name="banknote" /></div>
              <div class="stat-card-content">
                <p class="stat-card-label">{{ $t('Profit') }}</p>
                <h3 class="stat-card-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, report_today.today_profit || 0, 2) }}</h3>
              </div>
            </router-link>
          </b-col>
        </b-row>

          <!-- Chart: Sales & Purchases -->
        <b-row v-else-if="sectionId === 'chart_sales_purchases'" :key="sectionId" class="mb-3 align-items-stretch">
          <b-col cols="12">
            <div class="chart-card h-100">
              <div class="chart-card-header">
                <h4 class="chart-card-title">{{ $t('Sales') }} &amp; {{ $t('Purchases') }}</h4>
              </div>
              <div class="chart-card-body">
                <apexchart v-if="!loading" type="bar" :height="chartHeight" :options="chartSalesOptions" :series="chartSalesSeries"></apexchart>
                <div v-else class="text-center py-5"><div class="spinner spinner-primary"></div></div>
              </div>
            </div>
          </b-col>
        </b-row>

        <!-- Chart: Top Selling -->
        <b-row v-else-if="sectionId === 'chart_top_selling'" :key="sectionId" class="mb-3 align-items-stretch">
          <b-col cols="12">
            <div class="chart-card h-100">
              <div class="chart-card-header">
                <h4 class="chart-card-title">{{ $t('Top_Selling_Products') }} ({{ new Date().getFullYear() }})</h4>
              </div>
              <div class="chart-card-body">
                <apexchart v-if="!loading" type="donut" :height="chartHeight" :options="chartProductOptions" :series="chartProductSeries"></apexchart>
                <div v-else class="text-center py-5"><div class="spinner spinner-primary"></div></div>
              </div>
            </div>
          </b-col>
        </b-row>

        <!-- Sales by Payment + Stock Value -->
        <b-row v-else-if="sectionId === 'sales_by_payment_stock_value'" :key="sectionId" class="mb-3 align-items-stretch">
          <b-col md="6" class="mb-3 mb-md-0">
            <div class="info-card h-100">
              <div class="info-card-header">
                <h4 class="info-card-title">{{ $t('Sales_by_Payment') || 'Sales by Payment' }}</h4>
                <div class="info-card-menu"><lucide-icon name="more-vertical" /></div>
              </div>
              <div class="info-card-body">
                <div v-for="(payment, index) in sales_by_payment" :key="index" class="info-card-item">
                  <div class="info-card-item-header">
                    <div class="info-card-item-label">
                      <span class="info-card-dot" :class="'dot-' + payment.color"></span>
                      <span>{{ payment.name }}</span>
                    </div>
                    <div class="info-card-item-value">
                      {{ formatPriceWithSymbol(currentUser && currentUser.currency, payment.amount || 0, 2) }}
                      <span class="info-card-percentage">({{ payment.percentage }}%)</span>
                    </div>
                  </div>
                  <div class="info-card-progress">
                    <div class="info-card-progress-bar" :class="'progress-' + payment.color" :style="{ width: payment.percentage + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>
          </b-col>
          <b-col md="6" class="mb-3 mb-md-0">
            <div class="info-card h-100">
              <div class="info-card-header">
                <h4 class="info-card-title">{{ $t('Stock_Value') || 'Stock Value' }}</h4>
                <div class="info-card-menu"><lucide-icon name="more-vertical" /></div>
              </div>
              <div class="info-card-body">
                <div class="info-card-item stock-value-item">
                  <div class="info-card-item-header">
                    <div class="info-card-item-label">
                      <div class="info-card-icon stock-icon-cost"><lucide-icon name="dollar-sign" /></div>
                      <span>{{ $t('Stock_Value_by_Cost') || 'Stock Value by Cost' }}</span>
                    </div>
                    <div class="info-card-item-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, stock_value.by_cost || 0, 2) }}</div>
                  </div>
                </div>
                <div class="info-card-item stock-value-item">
                  <div class="info-card-item-header">
                    <div class="info-card-item-label">
                      <div class="info-card-icon stock-icon-retail"><lucide-icon name="tag" /></div>
                      <span>{{ $t('Stock_Value_by_Retail') || 'Stock Value by Retail' }}</span>
                    </div>
                    <div class="info-card-item-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, stock_value.by_retail || 0, 2) }}</div>
                  </div>
                </div>
                <div class="info-card-item stock-value-item">
                  <div class="info-card-item-header">
                    <div class="info-card-item-label">
                      <div class="info-card-icon stock-icon-wholesale"><lucide-icon name="package" /></div>
                      <span>{{ $t('Stock_Value_by_Wholesale') || 'Stock Value by Wholesale' }}</span>
                    </div>
                    <div class="info-card-item-value">{{ formatPriceWithSymbol(currentUser && currentUser.currency, stock_value.by_wholesale || 0, 2) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </b-col>
        </b-row>

        <!-- Chart: Payment Sent/Received -->
        <b-row v-else-if="sectionId === 'chart_payment_sent_received'" :key="sectionId" class="mb-3 align-items-stretch">
          <b-col cols="12">
            <div class="chart-card h-100">
              <div class="chart-card-header">
                <h4 class="chart-card-title">{{ $t('Payment_Sent_Received') }}</h4>
              </div>
              <div class="chart-card-body">
                <apexchart v-if="!loading" type="area" :height="chartHeight" :options="chartPaymentOptions" :series="chartPaymentSeries"></apexchart>
                <div v-else class="text-center py-5"><div class="spinner spinner-primary"></div></div>
              </div>
            </div>
          </b-col>
        </b-row>

        <!-- Chart: Top Customers -->
        <b-row v-else-if="sectionId === 'chart_top_customers'" :key="sectionId" class="mb-3 align-items-stretch">
          <b-col cols="12">
            <div class="chart-card h-100">
              <div class="chart-card-header">
                <h4 class="chart-card-title">{{ $t('TopCustomers') }} ({{ CurrentMonth }})</h4>
              </div>
              <div class="chart-card-body">
                <apexchart v-if="!loading" type="pie" :height="chartHeight" :options="chartCustomerOptions" :series="chartCustomerSeries"></apexchart>
                <div v-else class="text-center py-5"><div class="spinner spinner-primary"></div></div>
              </div>
            </div>
          </b-col>
        </b-row>

        <!-- Table: Stock Alert -->
        <b-row v-else-if="sectionId === 'table_stock_alert'" :key="sectionId" class="mb-3 align-items-stretch">
          <b-col cols="12">
            <div class="table-card h-100">
              <div class="table-card-header">
                <h4 class="table-card-title">{{ $t('StockAlert') }}</h4>
                <router-link to="/app/products/list" class="table-card-link">{{ $t('View') }} {{ $t('All') }} <lucide-icon name="arrow-right" /></router-link>
              </div>
              <div class="table-card-body">
                <vue-good-table
                  :columns="columns_stock"
                  row-style-class="text-left"
                  :rows="stock_alerts"
                  :pagination-options="{ enabled: false }"
                >
                  <template slot="table-row" slot-scope="props">
                    <div v-if="props.column.field == 'stock_alert'">
                      <span class="stock-alert-badge">{{ props.row.stock_alert }}</span>
                    </div>
                  </template>
                </vue-good-table>
              </div>
            </div>
          </b-col>
        </b-row>

        <!-- Table: Top Selling Products -->
        <b-row v-else-if="sectionId === 'table_top_selling_products'" :key="sectionId" class="mb-3 align-items-stretch">
          <b-col cols="12">
            <div class="table-card h-100">
              <div class="table-card-header">
                <h4 class="table-card-title">{{ $t('Top_Selling_Products') }} ({{ CurrentMonth }})</h4>
              </div>
              <div class="table-card-body">
                <vue-good-table
                  :columns="columns_products"
                  row-style-class="text-left"
                  :rows="products"
                  :pagination-options="{ enabled: false }"
                >
                  <template slot="table-row" slot-scope="props">
                    <div v-if="props.column.field == 'total'">
                      <span class="font-weight-bold text-success">{{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.total, 2) }}</span>
                    </div>
                  </template>
                </vue-good-table>
              </div>
            </div>
          </b-col>
        </b-row>

        <!-- Table: Recent Sales -->
        <b-row v-else-if="sectionId === 'table_recent_sales'" :key="sectionId" class="mb-3">
          <b-col cols="12">
            <div class="table-card">
              <div class="table-card-header">
                <h4 class="table-card-title">{{ $t('Recent_Sales') }}</h4>
                <router-link to="/app/sales/list" class="table-card-link">{{ $t('View') }} {{ $t('All') }} <lucide-icon name="arrow-right" /></router-link>
              </div>
              <div class="table-card-body">
                <vue-good-table
                  v-if="!loading"
                  :columns="columns_sales"
                  row-style-class="text-left"
                  :rows="sales"
                  :pagination-options="{ enabled: false }"
                >
                  <template slot="table-row" slot-scope="props">
                    <div v-if="props.column.field == 'statut'">
                      <span v-if="props.row.statut == 'completed'" class="badge badge-success">{{ $t('complete') }}</span>
                      <span v-else-if="props.row.statut == 'pending'" class="badge badge-info">{{ $t('Pending') }}</span>
                      <span v-else class="badge badge-warning">{{ $t('Ordered') }}</span>
                    </div>
                    <div v-else-if="props.column.field == 'payment_status'">
                      <span v-if="props.row.payment_status == 'paid'" class="badge badge-success">{{ $t('Paid') }}</span>
                      <span v-else-if="props.row.payment_status == 'partial'" class="badge badge-primary">{{ $t('partial') }}</span>
                      <span v-else class="badge badge-warning">{{ $t('Unpaid') }}</span>
                    </div>
                    <span v-else-if="props.column.field == 'GrandTotal'">{{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.GrandTotal, 2) }}</span>
                    <span v-else-if="props.column.field == 'paid_amount'">{{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.paid_amount, 2) }}</span>
                    <span v-else-if="props.column.field == 'due'">{{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.due, 2) }}</span>
                  </template>
                </vue-good-table>
              </div>
            </div>
          </b-col>
        </b-row>
      </template>

      <!-- Mobile: bottom tab bar (fixed; desktop hidden via CSS) -->
      <nav class="dashboard-mobile-only mobile-tabbar" role="navigation" :aria-label="$t('dashboard') || 'Dashboard'">
        <router-link
          v-for="tab in mobileTabBarItemsFiltered"
          :key="tab.key"
          :to="tab.to"
          :exact="tab.exact"
          class="mobile-tabbar__item ripple-touch"
          active-class="mobile-tabbar__item--active"
        >
          <lucide-icon :name="tab.iconName" />
          <span class="mobile-tabbar__label">{{ tab.label }}</span>
        </router-link>
      </nav>
    </div>

    <div v-else>
      <div class="welcome-card">
        <div class="welcome-icon">
          <lucide-icon name="home" />
        </div>
        <h3>{{ $t('Welcome_to_your_Dashboard') }}</h3>
        <p class="text-muted">{{ $t('No_dashboard_permission') }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import VueApexCharts from "vue-apexcharts";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../utils/priceFormat";

export default {
  components: {
    apexchart: VueApexCharts,
    "date-range-picker": DateRangePicker,
  },
  metaInfo: { title: "Dashboard" },
  data() {
    // Initial range (will be overwritten from setting in mounted)
    const end = moment().endOf("day");
    const start = end.clone().subtract(6, "days").startOf("day");
    return {
      dateRange: { startDate: start.toDate(), endDate: end.toDate() },
      startDate: start.format("YYYY-MM-DD"),
      endDate: end.format("YYYY-MM-DD"),
      defaultDateRange: "week",
      locale: {
        Label: this.$t("Apply") || "Apply",
        cancelLabel: this.$t("Cancel") || "Cancel",
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange") || "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },
      today_mode: true,

      sales: [],
      warehouses: [],
      warehouse_id: "",
      stock_alerts: [],
      report_today: {
        revenue: 0,
        today_purchases: 0,
        today_sales: 0,
        return_sales: 0,
        return_purchases: 0,
        // new summary metrics
        sales_due: 0,
        purchase_due: 0,
        today_invoices: 0,
        today_profit: 0
      },
      products: [],
      CurrentMonth: "",
      loading: true,
      // Optional price format key for frontend display (loaded from system settings/Vuex store)
      price_format_key: null,

      // Dashboard widget order (from System Settings); array of section ids
      dashboardSectionOrder: [],

      // Dashboard font (from System Settings)
      dashboardFontSize: "",
      dashboardFontFamily: "",

      // Sales by Payment data
      sales_by_payment: [
        { name: 'Cash', amount: 0, percentage: 0, color: 'orange' },
        { name: 'Card', amount: 0, percentage: 0, color: 'blue' },
        { name: 'Bank Transfer', amount: 0, percentage: 0, color: 'green' },
        { name: 'Cheque', amount: 0, percentage: 0, color: 'grey' }
      ],

      // Stock Value data
      stock_value: {
        by_cost: 0,
        by_retail: 0,
        by_wholesale: 0
      },

      // ApexCharts data
      chartSalesSeries: [],
      chartProductSeries: [],
      chartCustomerSeries: [],
      chartPaymentSeries: [],

      // ApexCharts options
      chartSalesOptions: {},
      chartProductOptions: {},
      chartCustomerOptions: {},
      chartPaymentOptions: {}
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),

    columns_sales() {
      return [
        { label: this.$t("Reference"), field: "Ref", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Customer"), field: "client_name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("warehouse"), field: "warehouse_name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Status"), field: "statut", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Total"), field: "GrandTotal", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Paid"), field: "paid_amount", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Due"), field: "due", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("PaymentStatus"), field: "payment_status", sortable: false, tdClass: "text-left", thClass: "text-left" }
      ];
    },
    columns_stock() {
      return [
        { label: this.$t("ProductCode"), field: "code", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("ProductName"), field: "name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("warehouse"), field: "warehouse", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Quantity"), field: "quantity", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("AlertQuantity"), field: "stock_alert", tdClass: "text-left", thClass: "text-left", sortable: false }
      ];
    },
    columns_products() {
      return [
        { label: this.$t("ProductName"), field: "name", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("TotalSales"), field: "total_sales", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("TotalAmount"), field: "total", tdClass: "text-left", thClass: "text-left", sortable: false }
      ];
    },

    // Default section ids (must match System Settings > Dashboard tab)
    defaultDashboardSectionIds() {
      return [
        "header",
        "stat_cards_1",
        "stat_cards_2",
        "chart_sales_purchases",
        "chart_top_selling",
        "sales_by_payment_stock_value",
        "chart_payment_sent_received",
        "chart_top_customers",
        "table_stock_alert",
        "table_top_selling_products",
        "table_recent_sales"
      ];
    },

    // Ordered list of section ids for display (saved order + any new sections appended)
    orderedDashboardSections() {
      const saved = this.dashboardSectionOrder || [];
      const defaultIds = this.defaultDashboardSectionIds || [];
      const byId = {};
      defaultIds.forEach(id => { byId[id] = true; });
      const ordered = [];
      saved.forEach(id => {
        if (byId[id]) {
          ordered.push(id);
          delete byId[id];
        }
      });
      defaultIds.forEach(id => {
        if (byId[id]) ordered.push(id);
      });
      return ordered.length ? ordered : defaultIds;
    },

    dashboardFontStyle() {
      const s = {};
      if (this.dashboardFontSize) s.fontSize = this.dashboardFontSize + "px";
      if (this.dashboardFontFamily) s.fontFamily = this.dashboardFontFamily;
      return s;
    }
  },
  methods: {
    fmt(d) {
      return moment(d).format("YYYY-MM-DD");
    },

    quick(key) {
      const end = moment().endOf("day");
      let start = end.clone();
      switch (key) {
        case "today": start = end.clone().startOf("day"); break;
        case "7d": start = end.clone().subtract(6, "days").startOf("day"); break;
        case "30d": start = end.clone().subtract(29, "days").startOf("day"); break;
        case "90d": start = end.clone().subtract(89, "days").startOf("day"); break;
        case "mtd": start = moment().startOf("month"); break;
        case "ytd": start = moment().startOf("year"); break;
      }
      this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      this.startDate = start.format("YYYY-MM-DD");
      this.endDate = end.format("YYYY-MM-DD");
      this.all_dashboard_data();
    },

    Submit_filter_dateRange() {
      const s = moment(this.dateRange.startDate);
      const e = moment(this.dateRange.endDate);
      this.startDate = s.format("YYYY-MM-DD");
      this.endDate = e.format("YYYY-MM-DD");
      this.all_dashboard_data();
    },

    get_data_loaded() {
      if (this.today_mode) {
        const end = moment().endOf("day");
        let start = end.clone();
        const range = this.defaultDateRange || "week";
        if (range === "today") {
          start = end.clone().startOf("day");
        } else if (range === "week") {
          start = end.clone().subtract(6, "days").startOf("day");
        } else if (range === "month") {
          start = moment().startOf("month");
        } else {
          start = end.clone().subtract(6, "days").startOf("day");
        }
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate = end.format("YYYY-MM-DD");
        this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      }
    },

    Selected_Warehouse(value) {
      if (value === null) this.warehouse_id = "";
      this.all_dashboard_data();
    },

    all_dashboard_data() {
      // Show full-page loading spinner while fetching dashboard data
      this.loading = true;
      this.get_data_loaded();
      axios
        .get(`/dashboard_data?warehouse_id=${this.warehouse_id}&to=${this.endDate}&from=${this.startDate}`)
        .then(response => {
          this.today_mode = false;
          const responseData = response.data;
          const seriesSalesName = this.$t('Sales');
          const seriesPurchasesName = this.$t('Purchases');
          const amountLabel = this.$t('Amount');
          const totalSalesLabel = this.$t('Total_Sales');
          const salesLabel = this.$t('Sales');
          const paymentSentName = this.$t('Payment_Sent');
          const paymentReceivedName = this.$t('Payment_Received');

          // Ensure numeric values are properly converted (backend now returns raw numbers)
          const reportData = response.data.report_dashboard.original.report;
          this.report_today = {
            ...reportData,
            today_sales: Number(reportData.today_sales) || 0,
            sales_due: Number(reportData.sales_due) || 0,
            return_sales: Number(reportData.return_sales) || 0,
            today_purchases: Number(reportData.today_purchases) || 0,
            purchase_due: Number(reportData.purchase_due) || 0,
            return_purchases: Number(reportData.return_purchases) || 0,
            today_profit: Number(reportData.today_profit) || 0,
            today_invoices: Number(reportData.today_invoices) || 0,
          };
          this.warehouses = response.data.warehouses;
          this.stock_alerts = response.data.report_dashboard.original.stock_alert;
          this.products = response.data.report_dashboard.original.products;
          this.sales = response.data.report_dashboard.original.last_sales;

          // Process Sales by Payment data (filtered by date and warehouse)
          if (response.data.sales_by_payment && Array.isArray(response.data.sales_by_payment)) {
            // Backend returns complete data with percentages, use it directly
            this.sales_by_payment = response.data.sales_by_payment.map(payment => ({
              name: payment.name,
              amount: Number(payment.amount) || 0,
              percentage: Number(payment.percentage) || 0,
              color: payment.color || 'grey'
            }));
          }

          // Process Stock Value data (filtered by warehouse only)
          if (response.data.stock_value) {
            this.stock_value = {
              by_cost: Number(response.data.stock_value.by_cost) || 0,
              by_retail: Number(response.data.stock_value.by_retail) || 0,
              by_wholesale: Number(response.data.stock_value.by_wholesale) || 0
            };
          }

          

          // Sales & Purchases Chart (Bar Chart)
          this.chartSalesSeries = [
            {
              name: seriesSalesName,
              data: responseData.sales.original.data
            },
            {
              name: seriesPurchasesName,
              data: responseData.purchases.original.data
            }
          ];
          this.chartSalesOptions = {
            chart: {
              type: "bar",
              toolbar: { show: true },
              fontFamily: "inherit"
            },
            colors: ["#8B5CF6", "#DDD6FE"],
            plotOptions: {
              bar: {
                horizontal: false,
                columnWidth: "55%",
                borderRadius: 8,
                dataLabels: {
                  position: "top"
                }
              }
            },
            dataLabels: {
              enabled: false
            },
            stroke: {
              show: true,
              width: 2,
              colors: ["transparent"]
            },
            xaxis: {
              categories: responseData.sales.original.days,
              labels: {
                rotate: -45,
                style: {
                  fontSize: "12px"
                }
              }
            },
            yaxis: {
              title: {
                text: amountLabel
              },
              labels: {
                formatter: (value) => {
                  try {
                    return this.formatPriceDisplay(value, 2);
                  } catch (e) {
                    var n = Number(value);
                    if (isNaN(n)) return value;
                    var s = n.toFixed(2);
                    return s.replace(/\.00$/, "");
                  }
                }
              }
            },
            fill: {
              opacity: 1
            },
            tooltip: {
              y: {
                formatter: (val) => {
                  try {
                    return this.formatPriceDisplay(val, 2);
                  } catch (e) {
                    var n = Number(val);
                    if (isNaN(n)) return val;
                    var s = n.toFixed(2);
                    return s.replace(/\.00$/, "");
                  }
                }
              }
            },
            legend: {
              position: "bottom",
              horizontalAlign: "center",
              fontSize: "14px",
              fontFamily: "inherit",
              fontWeight: 500,
              markers: {
                width: 12,
                height: 12,
                radius: 6
              },
              itemMargin: {
                horizontal: 15
              }
            },
            grid: {
              borderColor: "#e0e6ed",
              strokeDashArray: 5,
              xaxis: {
                lines: {
                  show: true
                }
              },
              yaxis: {
                lines: {
                  show: true
                }
              },
              padding: {
                top: 0,
                right: 0,
                bottom: 0,
                left: 0
              }
            }
          };

          // Top Selling Products Chart (Donut Chart)
          const productData = responseData.product_report.original;
          this.chartProductSeries = productData.map(item => item.value);
          this.chartProductOptions = {
            chart: {
              type: "donut",
              fontFamily: "inherit"
            },
            labels: productData.map(item => item.name),
            // Use a vibrant, highÔÇæcontrast palette so each top product is clearly distinct
            colors: ["#6366F1", "#10B981", "#F59E0B", "#EF4444", "#EC4899"],
            legend: {
              position: "bottom",
              fontSize: "12px"
            },
            dataLabels: {
              enabled: true,
              formatter: function(val) {
                return Math.floor(val) + "%";
              }
            },
            plotOptions: {
              pie: {
                donut: {
                  size: "65%",
                  labels: {
                    show: true,
                    total: {
                      show: true,
                      label: totalSalesLabel,
                      formatter: function() {
                        return Math.floor(productData.reduce((sum, item) => sum + item.value, 0));
                      }
                    }
                  }
                }
              }
            },
            tooltip: {
              y: {
                formatter: function(val) {
                  return Math.floor(val) + " " + salesLabel;
                }
              }
            }
          };

          // Top Customers Chart (Pie Chart)
          const customerData = responseData.customers.original;
          this.chartCustomerSeries = customerData.map(item => item.value);
          this.chartCustomerOptions = {
            chart: {
              type: "pie",
              fontFamily: "inherit"
            },
            labels: customerData.map(item => item.name),
            colors: ["#8B5CF6", "#A78BFA", "#C4B5FD", "#DDD6FE", "#EDE9FE"],
            legend: {
              position: "bottom",
              fontSize: "12px"
            },
            dataLabels: {
              enabled: true,
              formatter: function(val) {
                return Math.floor(val) + "%";
              }
            },
            tooltip: {
              y: {
                formatter: function(val) {
                  return Math.floor(val) + " " + salesLabel;
                }
              }
            }
          };

          // Payment Sent/Received Chart (Area Chart)
          this.chartPaymentSeries = [
            {
              name: paymentSentName,
              data: responseData.payments.original.payment_sent
            },
            {
              name: paymentReceivedName,
              data: responseData.payments.original.payment_received
            }
          ];
          this.chartPaymentOptions = {
            chart: {
              type: "area",
              toolbar: { show: true },
              fontFamily: "inherit"
            },
            colors: ["#EF4444", "#10B981"],
            dataLabels: {
              enabled: false
            },
            stroke: {
              curve: "smooth",
              width: 3
            },
            xaxis: {
              categories: responseData.payments.original.days,
              labels: {
                rotate: -45,
                style: {
                  fontSize: "12px"
                }
              }
            },
            yaxis: {
              title: {
                text: amountLabel
              },
              labels: {
                formatter: (value) => {
                  try {
                    return this.formatPriceDisplay(value, 2);
                  } catch (e) {
                    var n = Number(value);
                    if (isNaN(n)) return value;
                    var s = n.toFixed(2);
                    return s.replace(/\.00$/, "");
                  }
                }
              }
            },
            fill: {
              type: "gradient",
              gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.3,
                stops: [0, 90, 100]
              }
            },
            tooltip: {
              y: {
                formatter: (val) => {
                  try {
                    return this.formatPriceDisplay(val, 2);
                  } catch (e) {
                    var n = Number(val);
                    if (isNaN(n)) return val;
                    var s = n.toFixed(2);
                    return s.replace(/\.00$/, "");
                  }
                }
              }
            },
            legend: {
              position: "bottom",
              horizontalAlign: "center",
              fontSize: "14px",
              fontFamily: "inherit",
              fontWeight: 500,
              markers: {
                width: 12,
                height: 12,
                radius: 6
              },
              itemMargin: {
                horizontal: 15
              }
            },
            grid: {
              borderColor: "#e0e6ed",
              strokeDashArray: 5,
              xaxis: {
                lines: {
                  show: true
                }
              },
              yaxis: {
                lines: {
                  show: true
                }
              }
            }
          };

          // Hide loading only after all dashboard data and chart configs are ready
          this.loading = false;
        })
        .catch(() => {
          this.today_mode = false;
          // Hide loading even if the request fails
          this.loading = false;
        });
    },

    GetMonth() {
      const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
      this.CurrentMonth = months[new Date().getMonth()];
    },

    formatNumber(number, dec) {
      const value = (typeof number === "string" ? number : number.toString()).split(".");
      if (dec <= 0) return value[0];
      let f = value[1] || "";
      if (f.length > dec) return `${value[0]}.${f.substr(0, dec)}`;
      while (f.length < dec) f += "0";
      return `${value[0]}.${f}`;
    },

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing toLocaleString behavior to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 2;
        const n = Number(number || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, decimals, effectiveKey);
      } catch (e) {
        const n = Number(number || 0);
        return n.toLocaleString(undefined, { maximumFractionDigits: dec || 2 });
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      try {
        const safeSymbol = symbol || (this.currentUser && this.currentUser.currency) || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      } catch (e) {
        const safeSymbol = symbol || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      }
    },

    loadDefaultDateRangeSetting() {
      return axios
        .get("get_Settings_data")
        .then(response => {
          const settings = (response.data && response.data.settings) || {};
          const value = settings.default_dashboard_date_range;
          if (value === "today" || value === "week" || value === "month") {
            this.defaultDateRange = value;
          } else {
            this.defaultDateRange = "week";
          }
          // Apply dashboard widget order from System Settings
          const raw = settings.dashboard_section_order;
          let order = [];
          try {
            if (raw && typeof raw === "string") order = JSON.parse(raw);
            else if (Array.isArray(raw)) order = raw;
          } catch (e) {}
          this.dashboardSectionOrder = order;
          this.dashboardFontSize = settings.dashboard_font_size || "";
          this.dashboardFontFamily = settings.dashboard_font_family || "";
          return this.defaultDateRange;
        })
        .catch(() => {
          this.defaultDateRange = "week";
          return "week";
        });
    },
  },
  async mounted() {
    const range = await this.loadDefaultDateRangeSetting();
    this.defaultDateRange = range;
    await this.all_dashboard_data();
    this.GetMonth();
  }
};
</script>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, getCurrentInstance, watch } from "vue";

const MOBILE_MQ = "(max-width: 767px)";
const DASHBOARD_TABBAR_BODY_CLASS = "dashboard-mobile-tabbar";

const _inst = getCurrentInstance();
const vm = _inst && _inst.proxy;
const store = vm && vm.$store;

const isMobileViewport = ref(false);

const chartHeight = computed(() => (isMobileViewport.value ? 260 : 350));

const currentUserPermissions = computed(() => (store && store.getters.currentUserPermissions) || []);

function syncDashboardTabbarBodyClass() {
  if (typeof document === "undefined") return;
  const perms = currentUserPermissions.value;
  const show =
    isMobileViewport.value &&
    Array.isArray(perms) &&
    perms.includes("dashboard");
  document.body.classList.toggle(DASHBOARD_TABBAR_BODY_CLASS, show);
}

watch([isMobileViewport, currentUserPermissions], syncDashboardTabbarBodyClass, {
  immediate: true
});

// Matches Sidebar.vue > Reports parent menu visibility (any → show Reports / quick link)
const REPORTS_MENU_PERMISSIONS = [
  "Reports_payments_Sales",
  "Reports_payments_Purchases",
  "Reports_payments_Sale_Returns",
  "Reports_payments_purchase_Return",
  "Warehouse_report",
  "Reports_profit",
  "analytics_report",
  "Stock_Inventory_Valuation",
  "inventory_valuation",
  "expenses_report",
  "deposits_report",
  "Reports_purchase",
  "Reports_quantity_alerts",
  "Reports_sales",
  "product_sales_report",
  "product_purchases_report",
  "Reports_suppliers",
  "Top_Suppliers_Report",
  "Reports_customers",
  "Top_products",
  "inactive_customers_report",
  "Top_customers",
  "report_device_management",
  "users_report",
  "product_report",
  "zeroSalesProducts",
  "Dead_Stock_Report",
  "Stock_Aging_Report",
  "Stock_Transfer_Report",
  "discount_summary_report",
  "Stock_Adjustment_Report",
  "customer_loyalty_points_report",
  "tax_summary_report",
  "draft_invoices_report",
  "report_transactions",
  "cash_flow_report",
  "report_attendance_summary",
  "seller_report",
  "report_sales_by_category",
  "report_sales_by_brand",
  "report_error_logs",
  "cash_register_report",
  "report_warranty",
  "stock_report",
  "internal_location_report",
  "negative_stock_report",
  "return_ratio_report",
  "service_jobs",
  "service_jobs_report",
  "checklist_completion_report",
  "customer_maintenance_history_report"
];

function hasPerm(keys) {
  const perms = currentUserPermissions.value;
  if (!keys || !keys.length) return false;
  return keys.some((k) => perms.includes(k));
}

const mobileQuickModules = computed(() => {
  if (!vm) return [];
  const $t = vm.$t.bind(vm);
  const rows = [];
  const push = (keys, to, iconName, toneClass, labelKey, fallback) => {
    if (!hasPerm(keys)) return;
    const label = $t(labelKey) || fallback;
    rows.push({
      key: `${to}::${labelKey}`,
      to,
      iconName,
      toneClass,
      label
    });
  };

  push(["Pos_view"], "/app/pos", "calculator", "tone-purple", "POS", "POS");
  push(["Sales_view"], "/app/sales/list", "shopping-cart", "tone-violet", "Sales", "Sales");
  push(["Sale_Returns_view"], "/app/sale_return/list", "chevron-right", "tone-amber", "SalesReturn", "Sales return");
  push(["Purchases_view"], "/app/purchases/list", "shopping-cart", "tone-green", "Purchases", "Purchases");
  push(["Purchase_Returns_view"], "/app/purchase_return/list", "chevron-left", "tone-orange", "PurchasesReturn", "Purchase returns");
  push(["products_view"], "/app/products/list", "package", "tone-teal", "productsList", "Products");
  push(["Quotations_view"], "/app/quotations/list", "shopping-basket", "tone-amber", "Quotations", "Quotations");
  push(["transfer_view"], "/app/transfers/list", "arrow-left", "tone-slate", "StockTransfers", "Transfers");
  push(["Customers_view"], "/app/People/Customers", "users", "tone-blue", "Customers", "Customers");
  push(["Suppliers_view"], "/app/People/Suppliers", "users", "tone-slate", "Suppliers", "Suppliers");
  push(["adjustment_view"], "/app/adjustments/list", "map-pin", "tone-cyan", "StockAdjustement", "Adjustments");
  push(["damage_view"], "/app/damages/list", "shopping-bag", "tone-rose", "Damages", "Damages");
  push(["expense_view"], "/app/expenses/list", "wallet", "tone-teal", "Expenses", "Expenses");
  push(["deposit_view"], "/app/deposits/list", "wallet", "tone-emerald", "List_Deposit", "Deposits");
  push(["AI_Reports"], "/app/reports/ai_reports", "lightbulb", "tone-violet", "AI_Reports", "AI Reports");
  push(REPORTS_MENU_PERMISSIONS, "/app/reports/profit_and_loss", "trending-up", "tone-rose", "Reports", "Reports");

  return rows;
});

const mobileTabBarItems = computed(() => {
  if (!vm) return [];
  const $t = vm.$t.bind(vm);
  const perms = currentUserPermissions.value;
  const hasReports = REPORTS_MENU_PERMISSIONS.some((p) => perms.includes(p));
  const settingsTo = perms.includes("setting_system") ? "/app/settings/System_settings" : "/app/profile";
  const settingsLabel = perms.includes("setting_system")
    ? $t("Settings") || "Settings"
    : $t("profil") || "Account";

  return [
    {
      to: "/app/dashboard",
      label: $t("dashboard") || "Home",
      iconName: "bar-chart",
      exact: true,
      show: true
    },
    {
      to: "/app/sales/list",
      label: $t("Sales") || "Orders",
      iconName: "shopping-cart",
      exact: false,
      show: perms.includes("Sales_view")
    },
    {
      to: "/app/products/list",
      label: $t("Products") || "Stock",
      iconName: "package",
      exact: false,
      show: perms.includes("products_view")
    },
    {
      to: "/app/reports",
      label: $t("Reports") || "Reports",
      iconName: "trending-up",
      exact: false,
      show: hasReports
    },
    {
      to: settingsTo,
      label: settingsLabel,
      iconName: "settings",
      exact: false,
      show: true
    }
  ];
});

/** Visible tabs only (no v-show placeholders) so flex width and keys stay stable */
const mobileTabBarItemsFiltered = computed(() =>
  mobileTabBarItems.value
    .filter((t) => t.show)
    .map((t, i) => ({
      key: `tab-${t.to}-${i}`,
      to: t.to,
      label: t.label,
      iconName: t.iconName,
      exact: t.exact
    }))
);

let mqCleanup = null;

onMounted(() => {
  if (typeof window === "undefined" || !window.matchMedia) return;
  const mq = window.matchMedia(MOBILE_MQ);
  const apply = () => {
    isMobileViewport.value = mq.matches;
  };
  apply();
  mq.addEventListener("change", apply);
  mqCleanup = () => mq.removeEventListener("change", apply);
});

onBeforeUnmount(() => {
  if (mqCleanup) mqCleanup();
  if (typeof document !== "undefined") {
    document.body.classList.remove(DASHBOARD_TABBAR_BODY_CLASS);
  }
});
</script>

<style scoped>
/* Dashboard static layout */
.dashboard-static {
  min-height: 0;
}

/* Dashboard Header */
.dashboard-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem;
  border-radius: 12px;
  color: white;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dashboard-header h2 {
  color: white !important;
  font-weight: 600;
}

.welcome-text {
  color: #FFFFFF !important;
  font-size: 1rem;
  font-weight: 500;
}

.dashboard-header-filters {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: nowrap;
}

.warehouse-filter {
  display: inline-block;
  flex: 0 0 auto;
  min-width: 279px;
  max-width: 380px;
  position: relative;
}

.date-range-filter {
  display: inline-block;
  flex: 0 0 auto;
  min-width: 280px;
  max-width: 380px;
  position: relative;
}

.date-range-filter >>> .vue-daterange-picker {
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

/* Warehouse Filter Styles - Match Date Range Filter */
.warehouse-filter >>> .v-select {
  width: 100%;
  background: transparent !important;
}

.warehouse-filter >>> .v-select .vs__dropdown-toggle {
  background: rgba(255, 255, 255, 0.2) !important;
  background-color: rgba(255, 255, 255, 0.2) !important;
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

.warehouse-filter >>> .v-select .vs__dropdown-toggle * {
  background: transparent !important;
  background-color: transparent !important;
}

.warehouse-filter >>> .v-select .vs__dropdown-toggle:hover {
  background: rgba(255, 255, 255, 0.3) !important;
}

.warehouse-filter >>> .v-select .vs__dropdown-toggle:focus,
.warehouse-filter >>> .v-select .vs__dropdown-toggle:active {
  background: rgba(255, 255, 255, 0.3) !important;
  outline: none;
  box-shadow: none !important;
}

.warehouse-filter >>> .v-select .vs__selected-options {
  padding: 0 !important;
  margin: 0 !important;
}

.warehouse-filter >>> .v-select .vs__selected-options {
  display: flex;
  align-items: center;
  flex: 1;
}

.warehouse-filter >>> .v-select .vs__selected-options .vs__selected {
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

.warehouse-filter >>> .v-select .vs__selected-options .vs__selected i {
  color: white;
  margin-right: 0.5rem;
  flex-shrink: 0;
}

.warehouse-filter >>> .v-select .vs__search,
.warehouse-filter >>> .v-select .vs__search:focus {
  color: white !important;
  font-weight: 600 !important;
  font-size: 0.95rem !important;
  padding: 0 !important;
  margin: 0 !important;
  background: transparent !important;
  border: none !important;
  height: auto !important;
  line-height: 1.5 !important;
}

.warehouse-filter >>> .v-select .vs__search::placeholder {
  color: rgba(255, 255, 255, 0.7) !important;
  font-weight: 600 !important;
}

.warehouse-filter >>> .v-select .vs__actions {
  padding: 0 !important;
  margin-left: 0.5rem;
}

.warehouse-filter >>> .v-select .vs__clear {
  fill: white !important;
  margin-right: 0.5rem;
}

.warehouse-filter >>> .v-select .vs__open-indicator {
  fill: white !important;
}

.warehouse-filter >>> .v-select .vs__dropdown-menu {
  z-index: 2056 !important;
  border-radius: 8px !important;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
  border: 1px solid #e0e6ed !important;
  margin-top: 0.5rem !important;
}

.warehouse-filter >>> .v-select .vs__dropdown-option {
  padding: 0.75rem 1rem !important;
  color: #1f2937 !important;
  font-size: 0.875rem !important;
  display: flex;
  align-items: center;
}

.warehouse-filter >>> .v-select .vs__dropdown-option--highlight {
  background: #8B5CF6 !important;
  color: white !important;
}

.warehouse-filter >>> .v-select .vs__dropdown-option--highlight i {
  color: white !important;
}

/* Filter Card */
.filter-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.form-label {
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
}

.date-picker-btn {
  border: 1px solid #e0e6ed;
  transition: all 0.3s ease;
}

.date-picker-btn:hover {
  border-color: #8B5CF6;
  color: #8B5CF6;
}

.quick-wrap .btn {
  min-width: 58px;
  border-radius: 6px;
  transition: all 0.3s ease;
}

.quick-wrap .btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(139, 92, 246, 0.2);
}

/* Stat Cards */
.dashboard-static .stat-card {
  min-height: 120px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  transition: all 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  height: 100%;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
  text-decoration: none;
  color: inherit;
}

.stat-card-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-right: 1rem;
  flex-shrink: 0;
}

.sales-card .stat-card-icon {
  background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%);
  color: white;
}

.purchases-card .stat-card-icon {
  background: linear-gradient(135deg, #10B981 0%, #34D399 100%);
  color: white;
}

.sales-due-card .stat-card-icon {
  background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%);
  color: white;
}

.purchase-due-card .stat-card-icon {
  background: linear-gradient(135deg, #f97316 0%, #fdba74 100%);
  color: white;
}

.invoices-card .stat-card-icon {
  background: linear-gradient(135deg, #a855f7 0%, #d8b4fe 100%);
  color: white;
}

.profit-card .stat-card-icon {
  background: linear-gradient(135deg, #16a34a 0%, #4ade80 100%);
  color: white;
}

.returns-card .stat-card-icon {
  background: linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%);
  color: white;
}

.revenue-card .stat-card-icon {
  background: linear-gradient(135deg, #EF4444 0%, #F87171 100%);
  color: white;
}

.stat-card-content {
  flex: 1;
}

.stat-card-label {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0 0 0.25rem 0;
  font-weight: 500;
}

.stat-card-value {
  color: #1f2937;
  font-size: 0.9rem;
  font-weight: 700;
  margin: 0 0 0.5rem 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.stat-card-link {
  color: #8B5CF6;
  font-size: 0.875rem;
  text-decoration: none;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  transition: all 0.3s ease;
  white-space: nowrap;
}

.stat-card-link:hover {
  color: #6D28D9;
  text-decoration: none;
}

/* Info Cards (Sales by Payment & Stock Value) */
.info-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.info-card:hover {
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.info-card-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e0e6ed;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.info-card-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.info-card-menu {
  color: #6b7280;
  cursor: pointer;
  font-size: 1.25rem;
  transition: color 0.3s ease;
}

.info-card-menu:hover {
  color: #1f2937;
}

.info-card-body {
  padding: 1.5rem;
  flex: 1;
}

.info-card-item {
  margin-bottom: 1.5rem;
}

.info-card-item:last-child {
  margin-bottom: 0;
}

.info-card-item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.info-card-item-label {
  display: flex;
  align-items: center;
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.info-card-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 0.75rem;
  flex-shrink: 0;
}

.dot-orange {
  background: #F59E0B;
}

.dot-blue {
  background: #3B82F6;
}

.dot-green {
  background: #10B981;
}

.dot-grey {
  background: #6B7280;
}

.info-card-item-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1f2937;
  text-align: right;
}

.info-card-percentage {
  color: #6b7280;
  font-weight: 500;
  margin-left: 0.25rem;
}

.info-card-progress {
  width: 100%;
  height: 8px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}

.info-card-progress-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.progress-orange {
  background: #F59E0B;
}

.progress-blue {
  background: #3B82F6;
}

.progress-green {
  background: #10B981;
}

.progress-grey {
  background: #6B7280;
}

/* Stock Value Item Styles */
.stock-value-item {
  padding: 1rem 0;
  border-bottom: 1px solid #f3f4f6;
}

.stock-value-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.stock-value-item:first-child {
  padding-top: 0;
}

.info-card-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 0.75rem;
  flex-shrink: 0;
  font-size: 1.125rem;
  color: white;
}

.stock-icon-cost {
  background: linear-gradient(135deg, #3B82F6 0%, #60A5FA 100%);
}

.stock-icon-retail {
  background: linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%);
}

.stock-icon-wholesale {
  background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%);
}

.stock-value-item .info-card-item-label {
  font-size: 0.875rem;
  color: #6b7280;
}

.stock-value-item .info-card-item-value {
  font-size: 1rem;
  font-weight: 700;
  color: #1f2937;
}

/* Chart Cards */
.chart-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  overflow: visible; /* allow dropdowns (date range) to be fully visible */
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.chart-card:hover {
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.chart-card-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e0e6ed;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.chart-card-header-filter {
  margin-left: auto;
}

/* Style the embedded date-range input nicely inside the chart header */
.chart-card-header-filter >>> .form-control.reportrange-text {
  background: transparent !important;
  border: none !important;
  padding: 0 !important;
  height: auto !important;
  box-shadow: none !important;
}

.chart-card-header-filter >>> .daterangepicker {
  z-index: 2055 !important;
}

.chart-card-header .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.3s ease;
}

.chart-card-header .btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(139, 92, 246, 0.2);
}

.chart-card-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.chart-card-legend {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: center;
  font-size: 0.875rem;
  color: #6b7280;
}

.legend-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-right: 0.5rem;
}

.sales-dot {
  background: #8B5CF6;
}

.purchases-dot {
  background: #DDD6FE;
}

.sent-dot {
  background: #EF4444;
}

.received-dot {
  background: #10B981;
}

.chart-card-body {
  padding: 1.5rem;
  height: 400px;
  flex-shrink: 0;
  min-height: 400px;
}

/* Table Cards */
.table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  transition: all 0.3s ease;
}

.table-card:hover {
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.table-card-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e0e6ed;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-card-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.table-card-link {
  color: #8B5CF6;
  font-size: 0.875rem;
  text-decoration: none;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  transition: all 0.3s ease;
}

.table-card-link:hover {
  color: #6D28D9;
  text-decoration: none;
}

.table-card-link i {
  margin-left: 0.25rem;
  transition: transform 0.3s ease;
}

.table-card-link:hover i {
  transform: translateX(4px);
}

.table-card-body {
  padding: 1rem;
}

/* Removed dashboard-scoped table overrides; all vue-good-table styles are global */

/* Modern Table Styles */
.modern-table {
  font-size: 0.875rem;
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: #ffffff;
  border: 1px solid #dee2e6; /* align with Bootstrap table border */
  border-radius: 8px;
  overflow: hidden;
}

.modern-table thead th,
.modern-table.vgt-table thead th {
  background: #f8f9fa !important; /* Bootstrap-like header background */
  color: #212529; /* Bootstrap text color */
  font-weight: 600;
  text-transform: none;
  font-size: 0.8125rem;
  letter-spacing: 0.01em;
  padding: 0.75rem 1rem !important; /* align cell size with demo */
  border-bottom: 2px solid #dee2e6 !important; /* thicker header divider */
}

.modern-table tbody td,
.modern-table.vgt-table tbody td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e9ecef; /* Bootstrap-ish row divider */
  vertical-align: middle;
}

.modern-table tbody tr:nth-child(even) {
  background: #fcfcfd;
}

.modern-table tbody tr:hover {
  background: #f8f9fa; /* match demo hover feel */
}

/* Stock Alert Table Styles - Clean & Minimal */
.stock-alert-table {
  font-size: 0.875rem;
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: #ffffff;
  border: 1px solid #e5e7eb !important;
  border-radius: 8px;
  overflow: hidden;
}

.stock-alert-table thead {
  background: #f9fafb !important;
}

.stock-alert-table thead th {
  background: #f9fafb !important;
  color: #374151 !important;
  font-weight: 600 !important;
  text-transform: none;
  font-size: 0.8125rem !important;
  letter-spacing: 0.01em;
  padding: 0.75rem 1rem !important;
  border-bottom: 1px solid #e5e7eb !important;
}

.stock-alert-table tbody td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
  background: #ffffff;
  transition: background-color 0.2s ease;
}

.stock-alert-table tbody tr:nth-child(even) {
  background: #fcfcfd;
}

.stock-alert-table tbody tr {
  transition: background-color 0.2s ease;
}

.stock-alert-table tbody tr:hover {
  background: #f9fafb;
}

.stock-alert-table tbody tr:last-child td {
  border-bottom: none;
}

.stock-alert-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.375rem 0.75rem;
  background: #fee2e2;
  color: #991b1b;
  font-weight: 600;
  font-size: 0.875rem;
  border-radius: 6px;
  border: 1px solid #fecaca;
  transition: all 0.2s ease;
}

.stock-alert-badge:hover {
  background: #fecaca;
  border-color: #fca5a5;
}

/* Badges */
.badge {
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  font-weight: 500;
  font-size: 0.75rem;
}

.badge-success {
  background: #d1fae5;
  color: #065f46;
}

.badge-info {
  background: #dbeafe;
  color: #1e40af;
}

.badge-warning {
  background: #fef3c7;
  color: #92400e;
}

.badge-primary {
  background: #e0e7ff;
  color: #3730a3;
}

.badge-danger {
  background: #fee2e2;
  color: #991b1b;
}

/* Welcome Card */
.welcome-card {
  text-align: center;
  padding: 4rem 2rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.welcome-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #8B5CF6 0%, #A78BFA 100%);
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: white;
  margin-bottom: 1.5rem;
}

.welcome-card h3 {
  color: #1f2937;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

/* Date Range Picker - Style form-control */
.date-range-filter >>> .form-control.reportrange-text {
  background: #764ba200 !important;
  color: white !important;
  border: none !important;
  padding: 0 !important;
}

/* Date Range Picker Dropdown (ensure it appears above header and text is readable) */
.date-range-filter >>> .daterangepicker {
  z-index: 2055 !important;
  color: #111827 !important; /* dark text for good contrast on white background */
}

/* Responsive - Tablet */
@media (max-width: 1024px) and (min-width: 769px) {
  .warehouse-filter,
  .date-range-filter {
    min-width: auto;
    max-width: 100%;
    width: 100%;
    flex: 1 1 auto;
  }
}

/* Responsive - Mobile (< md / 768px breakpoint) */
@media (max-width: 767px) {
  .dashboard-header {
    text-align: center;
  }

  .dashboard-header-filters {
    flex-direction: column;
    align-items: stretch !important;
    gap: 0.75rem !important;
    margin-top: 1rem;
  }

  .warehouse-filter,
  .date-range-filter {
    min-width: auto;
    max-width: 100%;
    width: 100%;
    margin-left: 0;
  }

  .stat-card {
    flex-direction: column;
    text-align: center;
  }

  .stat-card-icon {
    margin-right: 0;
    margin-bottom: 1rem;
  }

  .info-card-header {
    padding: 1.25rem;
  }

  .info-card-body {
    padding: 1.25rem;
  }

  .info-card-item {
    margin-bottom: 1.25rem;
  }

  .stock-value-item {
    padding: 0.875rem 0;
  }

  .chart-card-header,
  .table-card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .quick-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .quick-wrap .btn {
    flex: 1;
    min-width: auto;
  }
}

/* Date Range Picker - Responsive Styles */
@media (max-width: 767px) {
  .date-range-filter >>> .daterangepicker {
    left: 12px !important;
    right: 12px !important;
    width: calc(100% - 24px) !important;
    max-width: calc(100vw - 24px) !important;
  }

  .date-range-filter >>> .daterangepicker .calendars-container {
    display: flex !important;
    flex-direction: column !important;
  }

  .date-range-filter >>> .daterangepicker .drp-calendar {
    float: none !important;
    width: 100% !important;
    padding: 10px !important;
  }

  .date-range-filter >>> .daterangepicker .drp-calendar.right {
    display: none !important;
  }

  .date-range-filter >>> .daterangepicker .ranges {
    float: none !important;
    width: 100% !important;
    margin: 10px 0 0 0 !important;
    border-top: 1px solid #e5e7eb !important;
    padding-top: 10px !important;
  }

  .date-range-filter >>> .daterangepicker .ranges ul {
    width: 100% !important;
  }

  .date-range-filter >>> .daterangepicker .ranges li {
    width: 100% !important;
    margin-bottom: 5px !important;
    text-align: center !important;
  }

  .date-range-filter >>> .daterangepicker .calendar-table {
    width: 100% !important;
  }

  .date-range-filter >>> .daterangepicker .calendar-table th,
  .date-range-filter >>> .daterangepicker .calendar-table td {
    padding: 6px !important;
    font-size: 0.875rem !important;
  }

  .date-range-filter >>> .daterangepicker .drp-buttons {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
    padding: 10px !important;
  }

  .date-range-filter >>> .daterangepicker .drp-buttons .btn {
    width: 100% !important;
    margin: 0 !important;
  }
}

@media (max-width: 576px) {
  .date-range-filter >>> .daterangepicker {
    left: 8px !important;
    right: 8px !important;
    width: calc(100% - 16px) !important;
    max-width: calc(100vw - 16px) !important;
  }

  .date-range-filter >>> .daterangepicker .calendar-table th,
  .date-range-filter >>> .daterangepicker .calendar-table td {
    padding: 4px !important;
    font-size: 0.75rem !important;
  }

  .date-range-filter >>> .daterangepicker .drp-calendar {
    padding: 8px !important;
  }
}

/* ------------------------------------------------------------------ */
/* Mobile app-style shell (only when viewport ≤767px, this page root) */
/* ------------------------------------------------------------------ */
.dashboard-page-root .dashboard-mobile-only {
  display: none;
}

@media (max-width: 767px) {
  .dashboard-page-root {
    padding-bottom: calc(5.35rem + env(safe-area-inset-bottom, 0px));
  }

  @keyframes dashboard-mobile-fade-in {
    from {
      opacity: 0;
      transform: translateY(6px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .dashboard-page-root .dashboard-mobile-only.mobile-quick-modules {
    display: block;
    margin-bottom: 1.75rem;
    padding-bottom: 0.35rem;
    animation: dashboard-mobile-fade-in 0.38s ease-out both;
  }

  /* Extra separation between quick grid and the stats rows below */
  .dashboard-page-root .mobile-quick-modules + .row {
    margin-top: 0.5rem;
  }

  .dashboard-page-root .mobile-module-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.6rem;
    width: 100%;
  }

  /* Single tile on last row (e.g. Reports): avoid a tiny cell / clipping; center a full-width chip */
  .dashboard-page-root
    .mobile-module-grid
    .mobile-module-tile:last-child:nth-child(3n + 1) {
    grid-column: 1 / -1;
    justify-self: center;
    width: 100%;
    max-width: 13rem;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    min-height: 3.5rem;
    padding: 0.65rem 1rem;
  }

  .dashboard-page-root
    .mobile-module-grid
    .mobile-module-tile:last-child:nth-child(3n + 1)
    .mobile-module-label {
    display: block;
    -webkit-line-clamp: unset;
    -webkit-box-orient: unset;
    overflow: visible;
    text-overflow: unset;
    white-space: nowrap;
  }

  .dashboard-page-root .mobile-module-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 0.45rem;
    padding: 0.8rem 0.3rem;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 2px 14px rgba(15, 23, 42, 0.07);
    color: #1e293b;
    text-decoration: none !important;
    border: 1px solid rgba(15, 23, 42, 0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    min-height: 5.25rem;
    -webkit-tap-highlight-color: transparent;
  }

  .dashboard-page-root .mobile-module-tile:active {
    transform: scale(0.97);
    box-shadow: 0 1px 8px rgba(15, 23, 42, 0.08);
  }

  .dashboard-page-root .mobile-module-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
  }

  .dashboard-page-root .mobile-module-icon > .lucide-icon {
    width: 1.5rem;
    height: 1.5rem;
    color: #fff;
  }

  .dashboard-page-root .mobile-module-label {
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.2;
    max-width: 100%;
    padding: 0 0.15rem;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  .dashboard-page-root .tone-purple {
    background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);
  }
  .dashboard-page-root .tone-violet {
    background: linear-gradient(135deg, #6366f1 0%, #818cf8 100%);
  }
  .dashboard-page-root .tone-teal {
    background: linear-gradient(135deg, #0d9488 0%, #2dd4bf 100%);
  }
  .dashboard-page-root .tone-green {
    background: linear-gradient(135deg, #059669 0%, #34d399 100%);
  }
  .dashboard-page-root .tone-blue {
    background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
  }
  .dashboard-page-root .tone-amber {
    background: linear-gradient(135deg, #d97706 0%, #fbbf24 100%);
  }
  .dashboard-page-root .tone-orange {
    background: linear-gradient(135deg, #ea580c 0%, #fb923c 50%, #fdba74 100%);
  }
  .dashboard-page-root .tone-slate {
    background: linear-gradient(135deg, #475569 0%, #94a3b8 100%);
  }
  .dashboard-page-root .tone-rose {
    background: linear-gradient(135deg, #e11d48 0%, #fb7185 100%);
  }
  .dashboard-page-root .tone-cyan {
    background: linear-gradient(135deg, #0891b2 0%, #22d3ee 100%);
  }
  .dashboard-page-root .tone-emerald {
    background: linear-gradient(135deg, #047857 0%, #34d399 100%);
  }

  .dashboard-page-root .mobile-tabbar {
    display: flex;
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    /* Below vertical sidebar mobile layer (z-index 90) so drawer stacks above tab bar */
    z-index: 85;
    justify-content: space-around;
    align-items: stretch;
    padding: 0.3rem 0.2rem calc(0.35rem + env(safe-area-inset-bottom, 0px));
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 -6px 28px rgba(15, 23, 42, 0.1);
    animation: dashboard-mobile-fade-in 0.4s ease-out 0.05s both;
  }

  .dashboard-page-root .mobile-tabbar__item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.1rem;
    font-size: 0.625rem;
    font-weight: 600;
    color: #64748b;
    text-decoration: none !important;
    padding: 0.2rem;
    min-width: 0;
    max-width: 100%;
    transition: color 0.2s ease, transform 0.15s ease;
    -webkit-tap-highlight-color: transparent;
  }

  .dashboard-page-root .mobile-tabbar__item i {
    font-size: 1.28rem;
    line-height: 1;
    flex-shrink: 0;
  }

  .dashboard-page-root .mobile-tabbar__item > .lucide-icon {
    width: 1.4rem;
    height: 1.4rem;
  }

  .dashboard-page-root .mobile-tabbar__label {
    display: block;
    width: 100%;
    max-width: 100%;
    text-align: center;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .dashboard-page-root .mobile-tabbar__item--active {
    color: #6d28d9;
  }

  .dashboard-page-root .mobile-tabbar__item--active i {
    color: #6d28d9;
  }

  .dashboard-page-root .mobile-tabbar__item:active {
    transform: scale(0.94);
  }

  .dashboard-page-root .dashboard-header {
    border-radius: 0 0 22px 22px;
    padding: 1.2rem 1rem 1rem;
    margin-bottom: 0.75rem !important;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.35);
  }

  .dashboard-page-root .dashboard-header-titles h2 {
    font-size: 1.35rem;
  }

  .dashboard-page-root .dashboard-header-filters-col {
    text-align: left !important;
  }

  /* Stat cards — mobile KPI tiles (dashboard only; desktop layout unchanged ≥768px) */
  .dashboard-page-root.dashboard-static .stat-card {
    min-height: 0;
  }

  /* Two columns on mobile; desktop stays Bootstrap row (≥768px) */
  .dashboard-page-root .dashboard-mobile-stat-grid.row {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 0.65rem;
    row-gap: 0.7rem;
    margin-left: 0 !important;
    margin-right: 0 !important;
  }

  .dashboard-page-root .dashboard-mobile-stat-grid.row > [class*="col"] {
    flex: none !important;
    width: auto !important;
    max-width: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-bottom: 0 !important;
  }

  .dashboard-page-root .stat-card {
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
    text-align: left;
    padding: 1rem 0.85rem 0.9rem;
    border-radius: 20px;
    border: 1px solid rgba(15, 23, 42, 0.07);
    background: linear-gradient(165deg, #ffffff 0%, #f8fafc 45%, #ffffff 100%);
    box-shadow:
      0 2px 8px rgba(15, 23, 42, 0.04),
      0 12px 32px rgba(15, 23, 42, 0.06),
      inset 0 1px 0 rgba(255, 255, 255, 0.85);
    min-height: 0;
    height: 100%;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    -webkit-tap-highlight-color: transparent;
  }

  /* Distinct soft tints per metric (mobile) */
  .dashboard-page-root .stat-card.sales-card {
    background: linear-gradient(155deg, rgba(139, 92, 246, 0.14) 0%, rgba(255, 255, 255, 0.92) 42%, #faf5ff 100%);
    border-color: rgba(139, 92, 246, 0.18);
    box-shadow:
      0 2px 10px rgba(139, 92, 246, 0.08),
      0 14px 36px rgba(15, 23, 42, 0.05),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card.purchases-card {
    background: linear-gradient(155deg, rgba(16, 185, 129, 0.13) 0%, rgba(255, 255, 255, 0.94) 40%, #ecfdf5 100%);
    border-color: rgba(16, 185, 129, 0.17);
    box-shadow:
      0 2px 10px rgba(16, 185, 129, 0.07),
      0 14px 36px rgba(15, 23, 42, 0.05),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card.returns-card {
    background: linear-gradient(155deg, rgba(245, 158, 11, 0.14) 0%, rgba(255, 255, 255, 0.93) 40%, #fffbeb 100%);
    border-color: rgba(245, 158, 11, 0.2);
    box-shadow:
      0 2px 10px rgba(245, 158, 11, 0.08),
      0 14px 36px rgba(15, 23, 42, 0.05),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card.revenue-card {
    background: linear-gradient(155deg, rgba(239, 68, 68, 0.11) 0%, rgba(255, 255, 255, 0.94) 40%, #fef2f2 100%);
    border-color: rgba(239, 68, 68, 0.16);
    box-shadow:
      0 2px 10px rgba(239, 68, 68, 0.06),
      0 14px 36px rgba(15, 23, 42, 0.05),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card.sales-due-card {
    background: linear-gradient(155deg, rgba(14, 165, 233, 0.13) 0%, rgba(255, 255, 255, 0.94) 40%, #f0f9ff 100%);
    border-color: rgba(14, 165, 233, 0.18);
    box-shadow:
      0 2px 10px rgba(14, 165, 233, 0.07),
      0 14px 36px rgba(15, 23, 42, 0.05),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card.purchase-due-card {
    background: linear-gradient(155deg, rgba(249, 115, 22, 0.13) 0%, rgba(255, 255, 255, 0.93) 40%, #fff7ed 100%);
    border-color: rgba(249, 115, 22, 0.18);
    box-shadow:
      0 2px 10px rgba(249, 115, 22, 0.07),
      0 14px 36px rgba(15, 23, 42, 0.05),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card.invoices-card {
    background: linear-gradient(155deg, rgba(168, 85, 247, 0.12) 0%, rgba(255, 255, 255, 0.94) 40%, #faf5ff 100%);
    border-color: rgba(168, 85, 247, 0.16);
    box-shadow:
      0 2px 10px rgba(168, 85, 247, 0.07),
      0 14px 36px rgba(15, 23, 42, 0.05),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card.profit-card {
    background: linear-gradient(155deg, rgba(22, 163, 74, 0.13) 0%, rgba(255, 255, 255, 0.94) 40%, #f0fdf4 100%);
    border-color: rgba(22, 163, 74, 0.17);
    box-shadow:
      0 2px 10px rgba(22, 163, 74, 0.07),
      0 14px 36px rgba(15, 23, 42, 0.05),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card:hover {
    transform: none;
    box-shadow:
      0 4px 12px rgba(15, 23, 42, 0.06),
      0 16px 40px rgba(15, 23, 42, 0.07),
      inset 0 1px 0 rgba(255, 255, 255, 0.9);
  }

  .dashboard-page-root .stat-card:active {
    transform: scale(0.985);
  }

  .dashboard-page-root .stat-card-icon {
    width: 44px;
    height: 44px;
    margin: 0 0 0.75rem 0 !important;
    font-size: 1.2rem;
    border-radius: 13px;
    flex-shrink: 0;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.15);
  }

  .dashboard-page-root .stat-card-content {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    min-width: 0;
    flex: 1;
  }

  .dashboard-page-root .stat-card-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    margin: 0 0 0.35rem 0 !important;
    font-weight: 600;
    line-height: 1.25;
  }

  .dashboard-page-root .stat-card-value {
    font-size: clamp(0.8125rem, 3.2vw, 1.0625rem);
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 0.85rem 0 !important;
    line-height: 1.2;
    letter-spacing: -0.03em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 100%;
  }

  .dashboard-page-root .stat-card-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: stretch;
    width: 100%;
    margin: 0 !important;
    padding: 0.4rem 0.5rem;
    font-size: 0.625rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    text-decoration: none !important;
    white-space: nowrap;
    border-radius: 11px;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
  }

  .dashboard-page-root .sales-card .stat-card-link {
    color: #5b21b6 !important;
    background: rgba(139, 92, 246, 0.12);
    border: 1px solid rgba(139, 92, 246, 0.22);
  }
  .dashboard-page-root .purchases-card .stat-card-link {
    color: #047857 !important;
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.22);
  }
  .dashboard-page-root .returns-card .stat-card-link {
    color: #b45309 !important;
    background: rgba(245, 158, 11, 0.14);
    border: 1px solid rgba(245, 158, 11, 0.25);
  }
  .dashboard-page-root .revenue-card .stat-card-link {
    color: #b91c1c !important;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
  }
  .dashboard-page-root .sales-due-card .stat-card-link {
    color: #0369a1 !important;
    background: rgba(14, 165, 233, 0.12);
    border: 1px solid rgba(14, 165, 233, 0.22);
  }
  .dashboard-page-root .purchase-due-card .stat-card-link {
    color: #c2410c !important;
    background: rgba(249, 115, 22, 0.12);
    border: 1px solid rgba(249, 115, 22, 0.22);
  }
  .dashboard-page-root .invoices-card .stat-card-link {
    color: #6d28d9 !important;
    background: rgba(168, 85, 247, 0.12);
    border: 1px solid rgba(168, 85, 247, 0.22);
  }
  .dashboard-page-root .profit-card .stat-card-link {
    color: #15803d !important;
    background: rgba(22, 163, 74, 0.12);
    border: 1px solid rgba(22, 163, 74, 0.22);
  }

  .dashboard-page-root .stat-card-link:hover {
    filter: brightness(0.97);
  }

  .dashboard-page-root .chart-card,
  .dashboard-page-root .info-card,
  .dashboard-page-root .table-card {
    border-radius: 16px;
    box-shadow: 0 2px 14px rgba(15, 23, 42, 0.06);
  }

  .dashboard-page-root .chart-card-body {
    height: auto !important;
    min-height: 220px !important;
    padding: 0.85rem !important;
  }

  .dashboard-page-root .table-card-body {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding: 0.65rem !important;
  }

  .dashboard-page-root .info-card-header,
  .dashboard-page-root .chart-card-header,
  .dashboard-page-root .table-card-header {
    padding: 1rem 1rem !important;
  }
}

.ripple-touch {
  cursor: pointer;
}
</style>
