<template>
  <div class="main-content p-2 p-md-4 ai-reports-page">
    <breadcumb page="AI Reports" :folder="$t('Reports')" />

    <!-- Header Section -->
    <div class="ai-reports-header mb-4">
      <b-card class="header-card shadow-soft border-0">
        <div class="d-flex align-items-center">
          <div class="header-icon-wrapper mr-3">
            <lucide-icon class="header-icon" name="lightbulb" />
          </div>
          <div class="flex-grow-1">
            <h4 class="mb-1 font-weight-bold text-white">AI Reports</h4>
            <p class="mb-0 text-white" style="opacity: 0.9;">Get instant insights with AI-powered report questions</p>
          </div>
        </div>
      </b-card>
    </div>

    <!-- Quick Report Questions Card -->
    <b-card class="mb-4 shadow-soft border-0 question-card">
      <div class="card-header-custom mb-4">
        <h5 class="mb-0">
          <lucide-icon class="mr-2 text-primary" name="help-circle" />
          Quick Report Questions
        </h5>
      </div>
      
      <b-row class="gutter-sm">
        <!-- Question Select -->
        <b-col lg="4" md="6" sm="12" class="mb-3">
          <b-form-group label="Question" label-class="font-weight-semibold">
            <b-form-select
              v-model="selectedQuestionId"
              :options="questionOptions"
              class="custom-select"
            >
              <template #first>
                <b-form-select-option :value="null" disabled>Select a question...</b-form-select-option>
              </template>
            </b-form-select>
          </b-form-group>
        </b-col>

        <!-- Warehouse Select -->
        <b-col lg="4" md="6" sm="12" class="mb-3">
          <b-form-group label="Warehouse" label-class="font-weight-semibold">
            <b-form-select
              v-model="warehouse_id"
              :options="warehouseOptions"
              class="custom-select"
            >
              <template #first>
                <b-form-select-option :value="null">All Warehouses</b-form-select-option>
              </template>
            </b-form-select>
          </b-form-group>
        </b-col>

        <!-- Run Button -->
        <b-col lg="4" md="12" sm="12" class="mb-3">
          <label class="d-block font-weight-semibold" style="margin-bottom: 0.5rem;">&nbsp;</label>
          <b-button
            variant="primary"
            @click="runReport"
            :disabled="!selectedQuestionId || isLoading"
            block
            class="btn-run"
          >
            <lucide-icon class="mr-1" name="play" v-if="!isLoading" />
            <span v-if="isLoading" class="spinner-border spinner-border-sm mr-1"></span>
            <span v-if="!isLoading">Run</span>
            <span v-else>Running...</span>
          </b-button>
        </b-col>
      </b-row>
    </b-card>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container fade">
      <b-card class="loading-card shadow-soft border-0 text-center">
        <div class="loading-content">
          <div class="spinner-wrapper mb-4">
            <div class="pulse-ring"></div>
            <div class="spinner spinner-primary"></div>
          </div>
          <h4 class="loading-title mb-2">Generating Your Report</h4>
          <p class="loading-subtitle mb-4">Analyzing data and preparing insights...</p>
          <div class="loading-steps">
            <div class="step-item" :class="{ active: loadingStep >= 1 }">
              <lucide-icon name="database" />
              <span>Collecting Data</span>
            </div>
            <div class="step-item" :class="{ active: loadingStep >= 2 }">
              <lucide-icon name="activity" />
              <span>Analyzing</span>
            </div>
            <div class="step-item" :class="{ active: loadingStep >= 3 }">
              <lucide-icon name="lightbulb" />
              <span>Generating Insights</span>
            </div>
          </div>
        </div>
      </b-card>
    </div>

    <!-- Results Section -->
    <div v-else-if="reportResult && !isLoading" class="results-container slide-fade">
        <!-- Success Banner -->
        <div class="success-banner mb-4">
          <b-card class="shadow-soft border-0 success-card">
            <div class="d-flex align-items-center">
              <div class="success-icon-wrapper mr-3">
                <lucide-icon name="check" />
              </div>
              <div class="flex-grow-1">
                <h5 class="mb-1 font-weight-bold">Report Generated Successfully</h5>
                <p class="mb-0 text-muted">Your AI-powered insights are ready</p>
              </div>
              <div class="report-timestamp">
                <small class="text-muted">{{ getCurrentTime() }}</small>
              </div>
            </div>
          </b-card>
        </div>

        <!-- Filters Summary -->
        <b-card class="mb-4 shadow-soft border-0 filters-summary-card animated-card">
          <div class="filters-header mb-3">
            <h6 class="mb-0 font-weight-bold">
              <lucide-icon class="text-primary mr-2" name="filter" />
              Report Filters
            </h6>
          </div>
          <div class="filters-content">
            <div class="filter-badge mb-2 mr-2" v-for="(filter, index) in activeFilters" :key="index">
              <lucide-icon class="mr-2" :name="filter.icon" />
              <strong>{{ filter.label }}:</strong>
              <span class="ml-1">{{ filter.value }}</span>
            </div>
          </div>
        </b-card>

        <!-- Insights Alert -->
        <b-card
          v-if="reportResult.insights"
          class="mb-4 insights-card shadow-soft border-0 animated-card fade-up"
        >
            <div class="insights-header">
              <div class="d-flex align-items-center mb-3">
                <div class="insights-icon-wrapper mr-3">
                  <lucide-icon class="insights-icon" name="lightbulb" />
                </div>
                <div class="flex-grow-1">
                  <h5 class="mb-0 font-weight-bold">AI Insights</h5>
                  <small class="text-white-50">Automated analysis of your data</small>
                </div>
                <div class="insights-badge">
                  <lucide-icon name="sparkles" />
                </div>
              </div>
            </div>
            <div class="insights-content">
              <div class="insights-text-wrapper">
                <p class="mb-0 insights-text">{{ reportResult.insights }}</p>
              </div>
            </div>
          </b-card>

        <!-- Daily Sales Summary - Advanced Report -->
        <div v-if="reportResult.question.report_key === 'daily_sales_summary'" class="report-content fade-up">
          <!-- Report Actions -->
          <b-card class="mb-4 shadow-soft border-0 report-actions-card">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
              <div class="report-title-section">
                <h4 class="mb-1 font-weight-bold">
                  <lucide-icon class="text-primary mr-2" name="bar-chart-2" />
                  {{ reportResult.question.title }}
                </h4>
                <p class="text-muted mb-0 small">{{ questionUi.subtitle }}</p>
              </div>
              <div class="report-actions">
                <b-button variant="outline-primary" size="sm" class="mr-2" @click="exportToPDF('daily_sales')">
                  <lucide-icon class="mr-1" name="file-text" /> PDF
                </b-button>
                <b-button variant="outline-success" size="sm" class="mr-2" @click="exportToExcel('daily_sales')">
                  <lucide-icon class="mr-1" name="file-spreadsheet" /> Excel
                </b-button>
                <b-button variant="outline-secondary" size="sm" @click="printReport('daily_sales')">
                  <lucide-icon class="mr-1" name="printer" /> Print
                </b-button>
              </div>
            </div>
          </b-card>

          <!-- AI Explanation Card -->
          <b-card v-if="reportResult.insights" class="mb-4 shadow-soft border-0 ai-explanation-card">
            <div class="d-flex align-items-start">
              <div class="ai-icon-wrapper mr-3">
                <lucide-icon name="brain" />
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-2 font-weight-bold">AI Analysis & Insights</h6>
                <p class="mb-0 ai-explanation-text">{{ reportResult.insights }}</p>
                <div class="ai-suggestions mt-3" v-if="getAISuggestions('daily_sales').length > 0">
                  <strong class="d-block mb-2 small">Key Recommendations:</strong>
                  <ul class="mb-0 pl-3">
                    <li v-for="(suggestion, idx) in getAISuggestions('daily_sales')" :key="idx" class="small">
                      {{ suggestion }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </b-card>

          <!-- KPI Cards -->
          <b-card class="mb-4 shadow-soft border-0">
            <div class="card-header-custom mb-3">
              <h6 class="mb-0 font-weight-bold">
                <lucide-icon class="mr-2 text-primary" name="layout-dashboard" />
                Key Performance Indicators
              </h6>
            </div>
            <b-row class="gutter-sm">
              <b-col lg="4" md="4" sm="6" class="mb-3" v-for="(stat, index) in dailySalesStats" :key="index">
                <div class="scale-in" :style="{ transitionDelay: (index * 0.1) + 's' }">
                  <StatTile
                    :icon="stat.icon"
                    :label="stat.label"
                    :value="stat.value"
                    :theme="stat.theme"
                  />
                </div>
              </b-col>
            </b-row>
          </b-card>

          <!-- Chart Visualization -->
          <b-card class="mb-4 shadow-soft border-0">
            <div class="card-header-custom mb-3">
              <h6 class="mb-0 font-weight-bold">
                <lucide-icon class="mr-2 text-primary" name="trending-up" />
                {{ questionUi.chartTitle }}
              </h6>
            </div>
            <div class="chart-container">
              <apexchart
                :type="dailySalesChartType"
                :height="dailySalesChartHeight"
                :options="dailySalesChartOptions"
                :series="dailySalesChartSeries"
              ></apexchart>
            </div>
          </b-card>
        </div>

        <!-- Sales by Product - Advanced Report -->
        <div v-else-if="reportResult.question.report_key === 'sales_by_product'" class="report-content fade-up">
          <!-- Report Actions -->
          <b-card class="mb-4 shadow-soft border-0 report-actions-card">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
              <div class="report-title-section">
                <h4 class="mb-1 font-weight-bold">
                  <lucide-icon class="text-primary mr-2" name="bar-chart-2" />
                  {{ reportResult.question.title }}
                </h4>
                <p class="text-muted mb-0 small">{{ questionUi.subtitle }}</p>
              </div>
              <div class="report-actions">
                <b-button variant="outline-primary" size="sm" class="mr-2" @click="exportToPDF('products')">
                  <lucide-icon class="mr-1" name="file-text" /> PDF
                </b-button>
                <b-button variant="outline-success" size="sm" class="mr-2" @click="exportToExcel('products')">
                  <lucide-icon class="mr-1" name="file-spreadsheet" /> Excel
                </b-button>
                <b-button variant="outline-secondary" size="sm" @click="printReport('products')">
                  <lucide-icon class="mr-1" name="printer" /> Print
                </b-button>
              </div>
            </div>
          </b-card>

          <!-- AI Explanation Card -->
          <b-card v-if="reportResult.insights" class="mb-4 shadow-soft border-0 ai-explanation-card">
            <div class="d-flex align-items-start">
              <div class="ai-icon-wrapper mr-3">
                <lucide-icon name="brain" />
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-2 font-weight-bold">AI Analysis & Insights</h6>
                <p class="mb-0 ai-explanation-text">{{ reportResult.insights }}</p>
                <div class="ai-suggestions mt-3" v-if="getAISuggestions('products').length > 0">
                  <strong class="d-block mb-2 small">Key Recommendations:</strong>
                  <ul class="mb-0 pl-3">
                    <li v-for="(suggestion, idx) in getAISuggestions('products')" :key="idx" class="small">
                      {{ suggestion }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </b-card>

          <!-- Chart Visualization -->
          <b-card class="mb-4 shadow-soft border-0 animated-card">
            <div class="card-header-custom mb-3">
              <h6 class="mb-0 font-weight-bold">
                <lucide-icon class="mr-2 text-primary" name="bar-chart" />
                {{ questionUi.chartTitle }}
              </h6>
            </div>
            <div class="chart-container">
              <apexchart
                :key="productChartKey"
                :type="productChartType"
                :height="productChartHeight"
                :options="productChartOptions"
                :series="productChartSeries"
              ></apexchart>
            </div>
          </b-card>

          <!-- Data Table -->
          <b-card class="mb-4 shadow-soft border-0 animated-card">
            <div class="card-header-custom mb-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <h6 class="mb-0 font-weight-bold">
                    <lucide-icon class="mr-2 text-primary" name="table" />
                    {{ questionUi.tableTitle }}
                  </h6>
                  <p class="text-muted mb-0 mt-1 small">{{ questionUi.tableSubtitle }}</p>
                </div>
                <div class="table-badge">
                  <lucide-icon name="database" />
                  <span>{{ reportResult.data ? reportResult.data.length : 0 }} Products</span>
                </div>
              </div>
            </div>
            <vue-good-table
            :columns="productColumns"
            :rows="reportResult.data"
            :search-options="{
              enabled: true,
              placeholder: 'Search this table',
              externalQuery: searchTerm
            }"
            :pagination-options="{
              enabled: true,
              mode: 'records',
              perPage: productTablePerPage,
              perPageDropdown: productTablePerPageDropdown
            }"
            styleClass="vgt-table table-hover"
          >
            <template slot="table-row" slot-scope="props">
              <span v-if="props.column.field === 'name'">
                {{ productDisplayName(props.row) }}
              </span>
              <span v-else-if="props.column.field === 'revenue'">
                <span class="text-success font-weight-semibold">{{ money(props.row.revenue) }}</span>
              </span>
              <span v-else-if="props.column.field === 'cost'">
                <span class="text-danger">{{ money(props.row.cost) }}</span>
              </span>
              <span v-else-if="props.column.field === 'profit'">
                <span class="text-primary font-weight-bold">{{ money(props.row.profit) }}</span>
              </span>
              <span v-else-if="props.column.field === 'margin_percent'">
                <b-badge :variant="props.row.margin_percent >= 30 ? 'success' : props.row.margin_percent >= 15 ? 'warning' : 'danger'">
                  {{ props.row.margin_percent }}%
                </b-badge>
              </span>
              <span v-else>
                {{ props.formattedRow[props.column.field] }}
              </span>
            </template>
            </vue-good-table>
          </b-card>
        </div>

        <!-- Late Payments - Advanced Report -->
        <div v-else-if="reportResult.question.report_key === 'late_payments'" class="report-content fade-up">
          <!-- Report Actions -->
          <b-card class="mb-4 shadow-soft border-0 report-actions-card">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
              <div class="report-title-section">
                <h4 class="mb-1 font-weight-bold">
                  <lucide-icon class="text-warning mr-2" name="alert-triangle" />
                  {{ reportResult.question.title }}
                </h4>
                <p class="text-muted mb-0 small">{{ questionUi.subtitle }}</p>
              </div>
              <div class="report-actions">
                <b-button variant="outline-primary" size="sm" class="mr-2" @click="exportToPDF('late_payments')">
                  <lucide-icon class="mr-1" name="file-text" /> PDF
                </b-button>
                <b-button variant="outline-success" size="sm" class="mr-2" @click="exportToExcel('late_payments')">
                  <lucide-icon class="mr-1" name="file-spreadsheet" /> Excel
                </b-button>
                <b-button variant="outline-secondary" size="sm" @click="printReport('late_payments')">
                  <lucide-icon class="mr-1" name="printer" /> Print
                </b-button>
              </div>
            </div>
          </b-card>

          <!-- AI Explanation Card -->
          <b-card v-if="reportResult.insights" class="mb-4 shadow-soft border-0 ai-explanation-card">
            <div class="d-flex align-items-start">
              <div class="ai-icon-wrapper mr-3">
                <lucide-icon name="brain" />
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-2 font-weight-bold">AI Analysis & Insights</h6>
                <p class="mb-0 ai-explanation-text">{{ reportResult.insights }}</p>
                <div class="ai-suggestions mt-3" v-if="getAISuggestions('late_payments').length > 0">
                  <strong class="d-block mb-2 small">Key Recommendations:</strong>
                  <ul class="mb-0 pl-3">
                    <li v-for="(suggestion, idx) in getAISuggestions('late_payments')" :key="idx" class="small">
                      {{ suggestion }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </b-card>

          <!-- Summary Cards -->
          <b-row class="gutter-sm mb-4">
            <b-col md="4" sm="6" class="mb-3">
              <b-card class="shadow-soft border-0 summary-card">
                <div class="summary-content">
                  <div class="summary-icon text-danger">
                    <lucide-icon name="wallet" />
                  </div>
                  <div class="summary-details">
                    <div class="summary-label">Total Outstanding</div>
                    <div class="summary-value">{{ getTotalOutstanding() }}</div>
                  </div>
                </div>
              </b-card>
            </b-col>
            <b-col md="4" sm="6" class="mb-3">
              <b-card class="shadow-soft border-0 summary-card">
                <div class="summary-content">
                  <div class="summary-icon text-warning">
                    <lucide-icon name="users" />
                  </div>
                  <div class="summary-details">
                    <div class="summary-label">Affected Customers</div>
                    <div class="summary-value">{{ reportResult.data ? reportResult.data.length : 0 }}</div>
                  </div>
                </div>
              </b-card>
            </b-col>
            <b-col md="4" sm="6" class="mb-3">
              <b-card class="shadow-soft border-0 summary-card">
                <div class="summary-content">
                  <div class="summary-icon text-info">
                    <lucide-icon name="calendar" />
                  </div>
                  <div class="summary-details">
                    <div class="summary-label">Avg. Days Overdue</div>
                    <div class="summary-value">{{ getAvgDaysOverdue() }} days</div>
                  </div>
                </div>
              </b-card>
            </b-col>
          </b-row>

          <!-- Chart Visualization -->
          <b-card class="mb-4 shadow-soft border-0 animated-card">
            <div class="card-header-custom mb-3">
              <h6 class="mb-0 font-weight-bold">
                <lucide-icon class="mr-2 text-primary" name="pie-chart" />
                {{ questionUi.chartTitle }}
              </h6>
            </div>
            <div class="chart-container">
              <apexchart
                :type="latePaymentsChartType"
                :height="latePaymentsChartHeight"
                :options="latePaymentsChartOptions"
                :series="latePaymentsChartSeries"
              ></apexchart>
            </div>
          </b-card>

          <!-- Data Table -->
          <b-card class="mb-4 shadow-soft border-0 animated-card">
            <div class="card-header-custom mb-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <h6 class="mb-0 font-weight-bold">
                    <lucide-icon class="mr-2 text-primary" name="table" />
                    Customer Payment Details
                  </h6>
                  <p class="text-muted mb-0 mt-1 small">Complete list of customers with overdue payments</p>
                </div>
                <div class="table-badge warning">
                  <lucide-icon name="alert-circle" />
                  <span>{{ reportResult.data ? reportResult.data.length : 0 }} Customers</span>
                </div>
              </div>
            </div>
            <vue-good-table
            :columns="latePaymentsColumns"
            :rows="reportResult.data"
            :search-options="{
              enabled: true,
              placeholder: 'Search this table',
              externalQuery: searchTerm
            }"
            :pagination-options="{
              enabled: true,
              mode: 'records',
              perPage: 10,
              perPageDropdown: [10, 20, 50]
            }"
            styleClass="vgt-table table-hover"
          >
            <template slot="table-row" slot-scope="props">
              <span v-if="props.column.field === 'outstanding_amount'">
                <span class="text-danger font-weight-bold">{{ money(props.row.outstanding_amount) }}</span>
              </span>
              <span v-else-if="props.column.field === 'max_days_overdue'">
                <b-badge :variant="props.row.max_days_overdue > 60 ? 'danger' : props.row.max_days_overdue > 30 ? 'warning' : 'info'">
                  {{ props.row.max_days_overdue }} days
                </b-badge>
              </span>
              <span v-else>
                {{ props.formattedRow[props.column.field] }}
              </span>
            </template>
            </vue-good-table>
          </b-card>
        </div>
      </div>

    <!-- Empty State -->
    <b-card v-else class="empty-state-card shadow-soft border-0 text-center py-5">
      <div class="empty-state-icon mb-4">
        <lucide-icon name="help-circle" />
      </div>
      <h4 class="mb-3">No Report Selected</h4>
      <p class="text-muted mb-4">Select a question from above and click Run to generate your report</p>
      <div class="empty-state-features">
        <b-row class="justify-content-center">
          <b-col md="4" sm="6" class="mb-3">
            <div class="feature-item">
              <lucide-icon class="text-primary mb-2" name="zap" />
              <h6>Quick Answers</h6>
              <p class="text-muted small">Get instant insights from your data</p>
            </div>
          </b-col>
          <b-col md="4" sm="6" class="mb-3">
            <div class="feature-item">
              <lucide-icon class="text-success mb-2" name="bar-chart" />
              <h6>Visual Reports</h6>
              <p class="text-muted small">Beautiful charts and tables</p>
            </div>
          </b-col>
          <b-col md="4" sm="6" class="mb-3">
            <div class="feature-item">
              <lucide-icon class="text-info mb-2" name="brain" />
              <h6>AI Powered</h6>
              <p class="text-muted small">Smart analysis and insights</p>
            </div>
          </b-col>
        </b-row>
      </div>
    </b-card>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import moment from "moment";
import VueApexCharts from "vue-apexcharts";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

const StatTile = {
  name: "StatTile",
  functional: true,
  props: { 
    icon: String, 
    label: String, 
    value: [String, Number], 
    theme: { type: String, default: 'blue' } 
  },
  render(h, { props }) {
    return h('div', { 
      class: ['stat-card', `theme-${props.theme}`, 'shadow-soft', 'rounded-xl', 'mb-2', 'h-100'] 
    }, [
      h('div', { class: 'stat-inner' }, [
        h('div', { class: 'stat-icon' }, [h('lucide-icon', { props: { name: props.icon } })]),
        h('div', { class: 'stat-content' }, [
          h('div', { class: 'stat-label' }, props.label),
          h('div', { class: 'stat-value' }, props.value)
        ])
      ])
    ]);
  }
};

export default {
  metaInfo: {
    title: "AI Reports"
  },
  components: {
    StatTile,
    apexchart: VueApexCharts
  },
  data() {
    return {
      isLoading: false,
      loadingStep: 1,
      loadingInterval: null,
      questions: [],
      selectedQuestionId: null,
      warehouse_id: null,
      warehouses: [],
      reportResult: null,
      price_format_key: null,
      searchTerm: '',
      isMobile: false
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    currency() {
      return (this.currentUser && this.currentUser.currency) || "USD";
    },
    questionOptions() {
      return this.questions.map(q => ({
        value: q.id,
        text: q.title
      }));
    },
    warehouseOptions() {
      return this.warehouses.map(w => ({
        value: w.id,
        text: w.name
      }));
    },
    activeFilters() {
      if (!this.reportResult || !this.reportResult.filters) return [];
      const filters = [];
      
      if (this.reportResult.question && this.reportResult.question.title) {
        filters.push({
          icon: 'help-circle',
          label: 'Question',
          value: this.reportResult.question.title
        });
      }
      
      if (this.reportResult.filters.warehouse_id) {
        filters.push({
          icon: 'home',
          label: 'Warehouse',
          value: this.getWarehouseName(this.reportResult.filters.warehouse_id)
        });
      }
      
      return filters;
    },
    dailySalesStats() {
      if (!this.reportResult || !this.reportResult.data) return [];
      const data = this.reportResult.data;
      return [
        {
          icon: 'receipt-text',
          label: 'Transactions',
          value: this.num(data.transactions),
          theme: 'blue'
        },
        {
          icon: 'banknote',
          label: 'Revenue',
          value: this.money(data.revenue),
          theme: 'green'
        },
        {
          icon: 'tag',
          label: 'Tax',
          value: this.money(data.tax),
          theme: 'orange'
        },
        {
          icon: 'tag',
          label: 'Discount',
          value: this.money(data.discount),
          theme: 'purple'
        },
        {
          icon: 'bar-chart',
          label: 'Profit',
          value: this.money(data.profit),
          theme: 'cyan'
        }
      ];
    },
    productColumns() {
      return [
        {
          label: 'Product',
          field: 'name',
          tdClass: 'text-left',
          thClass: 'text-left'
        },
        {
          label: 'Quantity',
          field: 'qty',
          type: 'number',
          tdClass: 'text-right',
          thClass: 'text-right'
        },
        {
          label: 'Revenue',
          field: 'revenue',
          type: 'number',
          tdClass: 'text-right',
          thClass: 'text-right'
        },
        {
          label: 'Cost',
          field: 'cost',
          type: 'number',
          tdClass: 'text-right',
          thClass: 'text-right'
        },
        {
          label: 'Profit',
          field: 'profit',
          type: 'number',
          tdClass: 'text-right',
          thClass: 'text-right'
        },
        {
          label: 'Margin %',
          field: 'margin_percent',
          type: 'number',
          tdClass: 'text-right',
          thClass: 'text-right'
        }
      ];
    },
    latePaymentsColumns() {
      return [
        {
          label: 'Customer',
          field: 'name',
          tdClass: 'text-left',
          thClass: 'text-left'
        },
        {
          label: 'Invoices Count',
          field: 'invoices_count',
          type: 'number',
          tdClass: 'text-center',
          thClass: 'text-center'
        },
        {
          label: 'Outstanding Amount',
          field: 'outstanding_amount',
          type: 'number',
          tdClass: 'text-right',
          thClass: 'text-right'
        },
        {
          label: 'Max Days Overdue',
          field: 'max_days_overdue',
          type: 'number',
          tdClass: 'text-center',
          thClass: 'text-center'
        }
      ];
    },
    /**
     * Per-question UI config (IDs 1..20 from our seeding migration).
     * This is what makes EACH question look different (chart type/metric, titles, paging, etc.).
     */
    questionUi() {
      const q = (this.reportResult && this.reportResult.question) ? this.reportResult.question : {};
      const id = Number(q.id || 0);
      const key = String(q.report_key || '');

      // Defaults (safe fallbacks)
      const base = {
        id,
        report_key: key,
        subtitle: 'Advanced report with AI insights, charts, export & print.',
        chartTitle: 'Visualization',
        tableTitle: 'Detailed Data',
        tableSubtitle: 'Full breakdown of results.',
        // chart behavior
        chart: {
          type: 'bar',
          height: 380,
          variant: 'default',
          metric: 'profit', // for product charts
          topN: 10,
          horizontal: false,
          stacked: false
        },
        table: {
          perPage: 10,
          perPageDropdown: [10, 20, 50]
        }
      };

      // DAILY SALES (IDs: 1..8, 20)
      if (key === 'daily_sales_summary') {
        const daily = {
          ...base,
          chart: { ...base.chart, type: 'donut', height: 350, variant: 'breakdown_donut' },
          table: { ...base.table }
        };
        switch (id) {
          case 1:
            return { ...daily, subtitle: 'Yesterday snapshot with revenue mix & KPIs.', chartTitle: 'Revenue Mix (Yesterday)' };
          case 2:
            return { ...daily, subtitle: 'Profit-drop investigation vs previous day (compare view).', chartTitle: 'Current vs Previous Day (Compare)', chart: { ...daily.chart, type: 'bar', height: 360, variant: 'compare_bar' } };
          case 3:
            return { ...daily, subtitle: 'Today snapshot with profitability gauges.', chartTitle: 'Performance Gauges (Today)', chart: { ...daily.chart, type: 'radialBar', height: 360, variant: 'margin_radial' } };
          case 4:
            return { ...daily, subtitle: 'Week-to-date KPI overview with metrics bar.', chartTitle: 'Week Metrics Overview', chart: { ...daily.chart, type: 'bar', height: 360, variant: 'metrics_bar' } };
          case 5:
            return { ...daily, subtitle: 'Last week snapshot with a different mix view.', chartTitle: 'Revenue Mix (Last Week)', chart: { ...daily.chart, type: 'donut', height: 350, variant: 'breakdown_donut_alt' } };
          case 6:
            return { ...daily, subtitle: 'This week vs last week comparison (AI insights ready).', chartTitle: 'This Week vs Last Week (Compare)', chart: { ...daily.chart, type: 'bar', height: 360, variant: 'compare_bar' } };
          case 7:
            return { ...daily, subtitle: 'Month-to-date performance summary.', chartTitle: 'Month Metrics Overview', chart: { ...daily.chart, type: 'bar', height: 360, variant: 'metrics_bar' } };
          case 8:
            return { ...daily, subtitle: 'Last month snapshot with profitability gauges.', chartTitle: 'Performance Gauges (Last Month)', chart: { ...daily.chart, type: 'radialBar', height: 360, variant: 'margin_radial' } };
          case 20:
            return { ...daily, subtitle: 'Profit change analysis: yesterday vs day before (compare view).', chartTitle: 'Profit Change (Compare)', chart: { ...daily.chart, type: 'bar', height: 360, variant: 'compare_bar' } };
          default:
            return daily;
        }
      }

      // PRODUCTS (IDs: 9..13, 19)
      if (key === 'sales_by_product') {
        const prod = {
          ...base,
          subtitle: 'Product performance with charts + detailed margin table.',
          chartTitle: 'Top Products (Chart)',
          tableTitle: 'Product Performance Table',
          tableSubtitle: 'Revenue, cost, profit, and margin per product.',
          chart: { ...base.chart, type: 'bar', height: 420, variant: 'product', metric: 'profit', topN: 10, horizontal: false, stacked: false },
          table: { ...base.table, perPage: 10, perPageDropdown: [10, 20, 50] }
        };
        switch (id) {
          case 9:
            return { ...prod, subtitle: 'Top profit winners (vertical ranking).', chartTitle: 'Top Profit Products', chart: { ...prod.chart, metric: 'profit', horizontal: false } };
          case 10:
            return { ...prod, subtitle: 'This month: profit vs revenue (stacked comparison).', chartTitle: 'Revenue vs Profit (Top Products)', chart: { ...prod.chart, metric: 'profit_vs_revenue', stacked: true, horizontal: false } };
          case 11:
            return { ...prod, subtitle: 'Revenue leaders (focus on top-line).', chartTitle: 'Top Revenue Products', chart: { ...prod.chart, metric: 'revenue', horizontal: false } };
          case 12:
            return { ...prod, subtitle: 'Best-sellers by quantity (volume focus).', chartTitle: 'Top Quantity Sold', chart: { ...prod.chart, metric: 'qty', horizontal: false } };
          case 13:
            return { ...prod, subtitle: 'Last month profit ranking with a compact view.', chartTitle: 'Last Month Profit Ranking', chart: { ...prod.chart, metric: 'profit', horizontal: false } };
          case 19:
            return { ...prod, subtitle: 'Extended list (top 25) + chart (top 15).', chartTitle: 'Top Profit Products (Top 15 Chart)', chart: { ...prod.chart, metric: 'profit', topN: 15, horizontal: false }, table: { perPage: 25, perPageDropdown: [10, 25, 50] } };
          default:
            return prod;
        }
      }

      // LATE PAYMENTS (IDs: 14..18)
      if (key === 'late_payments') {
        const late = {
          ...base,
          subtitle: 'Overdue customers with distribution + collection guidance.',
          chartTitle: 'Outstanding Distribution',
          tableTitle: 'Customer Payment Details',
          tableSubtitle: 'Who owes you money and how long it has been overdue.',
          chart: { ...base.chart, type: 'pie', height: 350, variant: 'amount_pie', topN: 10 },
          table: { ...base.table }
        };
        switch (id) {
          case 14:
            return { ...late, subtitle: '30+ days overdue: top outstanding customers (pie).', chartTitle: 'Outstanding Amount (Top 10)', chart: { ...late.chart, type: 'pie', variant: 'amount_pie', topN: 10 } };
          case 15:
            return { ...late, subtitle: '60+ days overdue: see who is most overdue (bar).', chartTitle: 'Days Overdue (Top 10)', chart: { ...late.chart, type: 'bar', height: 380, variant: 'days_bar', topN: 10, horizontal: true } };
          case 16:
            return { ...late, subtitle: '90+ days overdue: aging buckets overview (donut).', chartTitle: 'Aging Buckets (Counts)', chart: { ...late.chart, type: 'donut', variant: 'aging_bucket', topN: 0 } };
          case 17:
            return { ...late, subtitle: '7+ days overdue: quick action list (bar by amount).', chartTitle: 'Outstanding Amount (Bar)', chart: { ...late.chart, type: 'bar', height: 380, variant: 'amount_bar', topN: 12, horizontal: false } };
          case 18:
            return { ...late, subtitle: '14+ days overdue: focus on biggest balances (donut).', chartTitle: 'Outstanding Amount (Top 12)', chart: { ...late.chart, type: 'donut', variant: 'amount_pie', topN: 12 } };
          default:
            return late;
        }
      }

      return base;
    },
    dailySalesChartType() {
      if (!this.reportResult || !this.reportResult.question) return 'donut';
      return this.questionUi.chart.type || 'donut';
    },
    dailySalesChartHeight() {
      return this.questionUi.chart.height || 350;
    },
    productChartType() {
      return this.questionUi.chart.type || 'bar';
    },
    productChartHeight() {
      return this.questionUi.chart.height || 400;
    },
    latePaymentsChartType() {
      return this.questionUi.chart.type || 'pie';
    },
    latePaymentsChartHeight() {
      return this.questionUi.chart.height || 350;
    },
    productTablePerPage() {
      return (this.questionUi.table && this.questionUi.table.perPage) ? this.questionUi.table.perPage : 10;
    },
    productTablePerPageDropdown() {
      return (this.questionUi.table && this.questionUi.table.perPageDropdown) ? this.questionUi.table.perPageDropdown : [10, 20, 50];
    },
    /** Force chart re-mount when report data changes so categories/labels update. */
    productChartKey() {
      if (!this.reportResult || !this.reportResult.data) return 'no-data';
      const q = this.reportResult.question || {};
      const len = this.reportResult.data.length;
      const names = (this.reportResult.data || []).slice(0, 10).map(p => p.name || p.product_name || '').join('|');
      return `q${q.id}-n${len}-${names}`;
    },
    // ApexCharts - Daily Sales Chart
    dailySalesChartOptions() {
      if (!this.reportResult || !this.reportResult.data) return {};
      const data = this.reportResult.data;
      const variant = (this.questionUi.chart && this.questionUi.chart.variant) ? this.questionUi.chart.variant : 'breakdown_donut';

      // Compare chart (current vs previous period) when compare exists
      if (variant === 'compare_bar') {
        const cur = data || {};
        const prev = (this.reportResult && this.reportResult.compare) ? this.reportResult.compare : null;
        const categories = ['Revenue', 'Profit', 'Transactions'];
        const currentSeries = [
          this.num(cur.revenue || 0),
          this.num(cur.profit || 0),
          this.num(cur.transactions || 0)
        ];
        const previousSeries = prev ? [
          this.num(prev.revenue || 0),
          this.num(prev.profit || 0),
          this.num(prev.transactions || 0)
        ] : null;

        return {
          chart: { type: 'bar', toolbar: { show: true } },
          plotOptions: { bar: { horizontal: false, columnWidth: '55%', endingShape: 'rounded' } },
          dataLabels: { enabled: false },
          xaxis: { categories },
          tooltip: {
            y: {
              formatter: (val, opts) => {
                const idx = opts && typeof opts.dataPointIndex === 'number' ? opts.dataPointIndex : -1;
                if (idx === 2) return `${Math.round(this.num(val))}`;
                return this.money(val);
              }
            }
          },
          colors: ['#667eea', '#11998e'],
          legend: { position: 'top' }
        };
      }

      // Metrics overview bar (no compare required)
      if (variant === 'metrics_bar') {
        return {
          chart: { type: 'bar', toolbar: { show: true } },
          plotOptions: { bar: { horizontal: true, barHeight: '55%', distributed: true } },
          dataLabels: {
            enabled: true,
            formatter: (val, opts) => {
              const label = (opts && opts.w && opts.w.globals && opts.w.globals.labels) ? opts.w.globals.labels[opts.dataPointIndex] : '';
              if (label === 'Transactions') return `${Math.round(this.num(val))}`;
              return this.money(val);
            }
          },
          xaxis: { categories: ['Revenue', 'Profit', 'Tax', 'Discount', 'Transactions'] },
          colors: ['#28a745', '#17a2b8', '#ffc107', '#6f42c1', '#667eea'],
          tooltip: {
            y: {
              formatter: (val, opts) => {
                const label = (opts && opts.w && opts.w.globals && opts.w.globals.labels) ? opts.w.globals.labels[opts.dataPointIndex] : '';
                if (label === 'Transactions') return `${Math.round(this.num(val))}`;
                return this.money(val);
              }
            }
          }
        };
      }

      // Margin gauge (radial)
      if (variant === 'margin_radial') {
        const revenue = this.num(data.revenue || 0);
        const profit = this.num(data.profit || 0);
        const tax = this.num(data.tax || 0);
        const discount = this.num(data.discount || 0);
        const pct = (n) => revenue > 0 ? Math.max(0, Math.min(100, (n / revenue) * 100)) : 0;

        return {
          chart: { type: 'radialBar', toolbar: { show: true } },
          plotOptions: {
            radialBar: {
              hollow: { size: '40%' },
              dataLabels: {
                name: { fontSize: '14px' },
                value: { formatter: (v) => `${Number(v || 0).toFixed(1)}%` },
                total: {
                  show: true,
                  label: 'Profit Margin',
                  formatter: () => `${pct(profit).toFixed(1)}%`
                }
              }
            }
          },
          labels: ['Profit %', 'Tax %', 'Discount %'],
          colors: ['#17a2b8', '#ffc107', '#6f42c1']
        };
      }

      return {
        chart: {
          type: 'donut',
          toolbar: { show: true }
        },
        labels: ['Revenue', 'Tax', 'Discount', 'Profit'],
        colors: ['#28a745', '#ffc107', '#6f42c1', '#17a2b8'],
        legend: {
          position: 'bottom',
          fontSize: '14px'
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => `${val.toFixed(1)}%`
        },
        tooltip: {
          y: {
            formatter: (val) => this.money(val)
          }
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: { show: true, fontSize: '16px', fontWeight: 600 },
                value: { 
                  show: true, 
                  fontSize: '20px', 
                  fontWeight: 700,
                  formatter: (val) => this.money(val)
                },
                total: {
                  show: true,
                  label: 'Total',
                  formatter: () => this.money(data.revenue || 0)
                }
              }
            }
          }
        }
      };
    },
    dailySalesChartSeries() {
      if (!this.reportResult || !this.reportResult.data) return [];
      const data = this.reportResult.data;
      const variant = (this.questionUi.chart && this.questionUi.chart.variant) ? this.questionUi.chart.variant : 'breakdown_donut';

      if (variant === 'compare_bar') {
        const cur = data || {};
        const prev = (this.reportResult && this.reportResult.compare) ? this.reportResult.compare : null;
        const currentSeries = [
          this.num(cur.revenue || 0),
          this.num(cur.profit || 0),
          this.num(cur.transactions || 0)
        ];
        const previousSeries = prev ? [
          this.num(prev.revenue || 0),
          this.num(prev.profit || 0),
          this.num(prev.transactions || 0)
        ] : null;
        return previousSeries
          ? [{ name: 'Current', data: currentSeries }, { name: 'Previous', data: previousSeries }]
          : [{ name: 'Current', data: currentSeries }];
      }

      if (variant === 'metrics_bar') {
        return [{
          name: 'Metrics',
          data: [
            this.num(data.revenue || 0),
            this.num(data.profit || 0),
            this.num(data.tax || 0),
            this.num(data.discount || 0),
            this.num(data.transactions || 0)
          ]
        }];
      }

      if (variant === 'margin_radial') {
        const revenue = this.num(data.revenue || 0);
        const profit = this.num(data.profit || 0);
        const tax = this.num(data.tax || 0);
        const discount = this.num(data.discount || 0);
        const pct = (n) => revenue > 0 ? Math.max(0, Math.min(100, (n / revenue) * 100)) : 0;
        return [pct(profit), pct(tax), pct(discount)];
      }

      return [
        this.num(data.revenue || 0),
        this.num(data.tax || 0),
        this.num(data.discount || 0),
        this.num(data.profit || 0)
      ];
    },
    // ApexCharts - Product Chart
    productChartOptions() {
      if (!this.reportResult || !this.reportResult.data) return {};
      const cfg = this.questionUi.chart || {};
      const topN = Number(cfg.topN || 10);
      const topProducts = (this.reportResult.data || []).slice(0, topN);
      const metric = String(cfg.metric || 'profit');
      const horizontal = !!cfg.horizontal;
      const stacked = !!cfg.stacked;

      const isQty = metric === 'qty';
      const formatVal = (val) => isQty ? `${Math.round(this.num(val))}` : this.money(val);

      return {
        chart: {
          type: 'bar',
          toolbar: { show: true },
          height: 400
        },
        plotOptions: {
          bar: {
            horizontal,
            columnWidth: '55%',
            endingShape: 'rounded',
            stacked,
            dataLabels: {
              position: 'top'
            }
          }
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => formatVal(val),
          offsetY: horizontal ? 0 : -20,
          style: {
            fontSize: '12px',
            colors: ['#304758']
          }
        },
        grid: {
          padding: {
            left: horizontal ? 16 : 8,
            right: 8
          },
          show: true,
          xaxis: { lines: { show: true } },
          yaxis: { lines: { show: false } }
        },
        xaxis: {
          categories: topProducts.map((p, i) => this.productChartLabel(p) || `Product ${i + 1}`),
          labels: {
            rotate: horizontal ? 0 : -45,
            style: {
              fontSize: horizontal ? 13 : 12
            },
            maxWidth: horizontal ? 180 : undefined
          }
        },
        yaxis: {
          labels: {
            show: true,
            formatter: (val) => formatVal(val)
          }
        },
        tooltip: {
          y: {
            formatter: (val) => formatVal(val)
          }
        },
        colors: metric === 'profit' ? ['#667eea'] :
          metric === 'revenue' ? ['#28a745'] :
          metric === 'qty' ? ['#fd7e14'] :
          ['#667eea', '#28a745'],
        legend: {
          position: 'top'
        }
      };
    },
    productChartSeries() {
      if (!this.reportResult || !this.reportResult.data) return [];
      const cfg = this.questionUi.chart || {};
      const topN = Number(cfg.topN || 10);
      const topProducts = (this.reportResult.data || []).slice(0, topN);
      const metric = String(cfg.metric || 'profit');

      if (metric === 'profit_vs_revenue') {
        return [
          { name: 'Revenue', data: topProducts.map(p => this.num(p.revenue || 0)) },
          { name: 'Profit', data: topProducts.map(p => this.num(p.profit || 0)) }
        ];
      }

      if (metric === 'revenue') {
        return [{ name: 'Revenue', data: topProducts.map(p => this.num(p.revenue || 0)) }];
      }

      if (metric === 'qty') {
        return [{ name: 'Quantity', data: topProducts.map(p => this.num(p.qty || 0)) }];
      }

      // default: profit
      return [{ name: 'Profit', data: topProducts.map(p => this.num(p.profit || 0)) }];
    },
    // ApexCharts - Late Payments Chart
    latePaymentsChartOptions() {
      if (!this.reportResult || !this.reportResult.data) return {};
      const cfg = this.questionUi.chart || {};
      const variant = String(cfg.variant || 'amount_pie');
      const topN = Number(cfg.topN || 10);
      const topCustomers = topN > 0 ? (this.reportResult.data || []).slice(0, topN) : (this.reportResult.data || []);

      if (variant === 'days_bar') {
        return {
          chart: { type: 'bar', toolbar: { show: true } },
          plotOptions: { bar: { horizontal: true, barHeight: '55%', endingShape: 'rounded' } },
          dataLabels: { enabled: true, formatter: (v) => `${Math.round(this.num(v))} d` },
          xaxis: { categories: topCustomers.map(c => c.name || 'Unknown') },
          colors: ['#fd7e14'],
          tooltip: { y: { formatter: (v) => `${Math.round(this.num(v))} days` } }
        };
      }

      if (variant === 'amount_bar') {
        const horizontal = !!cfg.horizontal;
        return {
          chart: { type: 'bar', toolbar: { show: true } },
          plotOptions: { bar: { horizontal, barHeight: '55%', columnWidth: '55%', endingShape: 'rounded' } },
          dataLabels: { enabled: false },
          xaxis: {
            categories: topCustomers.map(c => c.name || 'Unknown'),
            labels: { rotate: horizontal ? 0 : -45 }
          },
          yaxis: { labels: { formatter: (v) => this.money(v) } },
          colors: ['#dc3545'],
          tooltip: { y: { formatter: (v) => this.money(v) } }
        };
      }

      if (variant === 'aging_bucket') {
        return {
          chart: { type: 'donut', toolbar: { show: true } },
          labels: ['< 30 days', '30–60 days', '60–90 days', '90+ days'],
          colors: ['#17a2b8', '#ffc107', '#fd7e14', '#dc3545'],
          legend: { position: 'bottom', fontSize: '12px' },
          dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` }
        };
      }

      return {
        chart: {
          type: this.questionUi.chart.type || 'pie',
          toolbar: { show: true }
        },
        labels: topCustomers.map(c => c.name || 'Unknown'),
        colors: ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#0dcaf0', '#6610f2', '#6f42c1', '#e83e8c', '#f8d7da', '#d1ecf1'],
        legend: {
          position: 'bottom',
          fontSize: '12px'
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => `${val.toFixed(1)}%`
        },
        tooltip: {
          y: {
            formatter: (val) => this.money(val)
          }
        }
      };
    },
    latePaymentsChartSeries() {
      if (!this.reportResult || !this.reportResult.data) return [];
      const cfg = this.questionUi.chart || {};
      const variant = String(cfg.variant || 'amount_pie');
      const topN = Number(cfg.topN || 10);
      const rows = (this.reportResult.data || []);
      const topCustomers = topN > 0 ? rows.slice(0, topN) : rows;

      if (variant === 'days_bar') {
        return [{ name: 'Days Overdue', data: topCustomers.map(c => this.num(c.max_days_overdue || 0)) }];
      }

      if (variant === 'amount_bar') {
        return [{ name: 'Outstanding', data: topCustomers.map(c => this.num(c.outstanding_amount || 0)) }];
      }

      if (variant === 'aging_bucket') {
        // counts by bucket based on max_days_overdue
        const buckets = [0, 0, 0, 0];
        rows.forEach((c) => {
          const d = this.num(c.max_days_overdue || 0);
          if (d < 30) buckets[0] += 1;
          else if (d < 60) buckets[1] += 1;
          else if (d < 90) buckets[2] += 1;
          else buckets[3] += 1;
        });
        return buckets;
      }

      // pie/donut amount distribution
      return topCustomers.map(c => this.num(c.outstanding_amount || 0));
    }
  },
  mounted() {
    this.loadQuestions();
    this.handleResize();
    window.addEventListener('resize', this.handleResize);
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.handleResize);
    if (this.loadingInterval) {
      clearInterval(this.loadingInterval);
    }
  },
  methods: {
    num(v) {
      const n = parseFloat(v || 0);
      return isNaN(n) ? 0 : n;
    },
    money(v) {
      try {
        const n = this.num(v);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        const formatted = formatPriceDisplayHelper(n, 2, effectiveKey);
        return `${this.currency} ${formatted}`;
      } catch (e) {
        try {
          return new Intl.NumberFormat(undefined, { style: 'currency', currency: this.currency }).format(this.num(v));
        } catch (e2) {
          return `${this.currency} ${this.num(v).toLocaleString()}`;
        }
      }
    },
    getWarehouseName(id) {
      const warehouse = this.warehouses.find(w => w.id === id);
      return warehouse ? warehouse.name : '';
    },
    /** Product display name for chart labels and table (handles name, product_name, or label from API). */
    productDisplayName(row) {
      if (!row) return '';
      return row.name || row.product_name || row.label || 'Unknown';
    },
    /** Same as productDisplayName for use inside computed (chart options). */
    productChartLabel(p) {
      return this.productDisplayName(p);
    },
    loadQuestions() {
      axios
        .get("report-questions")
        .then(response => {
          this.questions = response.data.questions;
          this.warehouses = response.data.warehouses;
        })
        .catch(error => {
          this.makeToast("danger", "Error", "Failed to load questions");
        });
    },

    handleResize() {
      this.isMobile = window.innerWidth < 576;
    },
    runReport() {
      if (!this.selectedQuestionId) {
        this.makeToast("warning", "Warning", "Please select a question");
        return;
      }

      this.isLoading = true;
      this.reportResult = null;
      this.loadingStep = 1;

      // Animate loading steps
      this.loadingInterval = setInterval(() => {
        if (this.loadingStep < 3) {
          this.loadingStep++;
        }
      }, 800);

      const payload = {
        question_id: this.selectedQuestionId,
        warehouse_id: this.warehouse_id || null
      };

      axios
        .post("report-questions/run", payload)
        .then(response => {
          // Clear loading interval
          if (this.loadingInterval) {
            clearInterval(this.loadingInterval);
            this.loadingInterval = null;
          }
          
          // Small delay for smooth transition
          setTimeout(() => {
            this.reportResult = response.data;
            this.isLoading = false;
            this.loadingStep = 1;
            
            // Scroll to results
            this.$nextTick(() => {
              const resultsElement = document.querySelector('.results-container');
              if (resultsElement) {
                resultsElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
              }
            });
          }, 300);
        })
        .catch(error => {
          if (this.loadingInterval) {
            clearInterval(this.loadingInterval);
            this.loadingInterval = null;
          }
          this.isLoading = false;
          this.loadingStep = 1;
          const message = error.response?.data?.message || "Failed to run report";
          this.makeToast("danger", "Error", message);
        });
    },
    getCurrentTime() {
      return moment().format('MMM D, YYYY [at] h:mm A');
    },
    getAISuggestions(reportType) {
      if (!this.reportResult) return [];
      const suggestions = {
        'daily_sales': [
          'Consider analyzing peak sales hours to optimize staffing',
          'Review discount patterns to maximize profitability',
          'Monitor tax collection efficiency',
          'Compare with previous periods for trend analysis'
        ],
        'products': [
          'Focus marketing efforts on high-profit margin products',
          'Review low-performing products for discontinuation',
          'Consider bulk purchasing for high-volume items',
          'Optimize pricing strategy based on margin analysis'
        ],
        'late_payments': [
          'Implement stricter payment terms for high-risk customers',
          'Send automated payment reminders',
          'Consider offering early payment discounts',
          'Review credit limits for customers with extended overdue periods'
        ]
      };
      return suggestions[reportType] || [];
    },
    getTotalOutstanding() {
      if (!this.reportResult || !this.reportResult.data) return this.money(0);
      const total = this.reportResult.data.reduce((sum, item) => {
        return sum + this.num(item.outstanding_amount || 0);
      }, 0);
      return this.money(total);
    },
    getAvgDaysOverdue() {
      if (!this.reportResult || !this.reportResult.data || this.reportResult.data.length === 0) return 0;
      const total = this.reportResult.data.reduce((sum, item) => {
        return sum + this.num(item.max_days_overdue || 0);
      }, 0);
      return Math.round(total / this.reportResult.data.length);
    },
    exportToPDF(reportType) {
      try {
        const pdf = new jsPDF('p', 'pt', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        let yPos = 40;

        // Header
        pdf.setFontSize(18);
        pdf.setFont('helvetica', 'bold');
        pdf.text(this.reportResult.question.title || 'AI Report', pageWidth / 2, yPos, { align: 'center' });
        yPos += 30;

        // Date and filters
        pdf.setFontSize(10);
        pdf.setFont('helvetica', 'normal');
        pdf.text(`Generated: ${this.getCurrentTime()}`, 40, yPos);
        yPos += 20;

        if (this.reportResult.filters.date_from && this.reportResult.filters.date_to) {
          pdf.text(`Date Range: ${this.reportResult.filters.date_from} - ${this.reportResult.filters.date_to}`, 40, yPos);
          yPos += 20;
        }

        if (this.reportResult.insights) {
          yPos += 10;
          pdf.setFont('helvetica', 'bold');
          pdf.setFontSize(12);
          pdf.text('AI Insights:', 40, yPos);
          yPos += 20;
          pdf.setFont('helvetica', 'normal');
          pdf.setFontSize(10);
          const insightsLines = pdf.splitTextToSize(this.reportResult.insights, pageWidth - 80);
          pdf.text(insightsLines, 40, yPos);
          yPos += insightsLines.length * 15 + 20;
        }

        // Data table
        if (this.reportResult.data && Array.isArray(this.reportResult.data)) {
          if (reportType === 'products') {
            const headers = [['Product', 'Quantity', 'Revenue', 'Cost', 'Profit', 'Margin %']];
            const rows = this.reportResult.data.map(item => [
              String(this.productDisplayName(item)).substring(0, 30),
              String(this.num(item.qty || 0)),
              this.money(item.revenue || 0),
              this.money(item.cost || 0),
              this.money(item.profit || 0),
              `${this.num(item.margin_percent || 0)}%`
            ]);
            autoTable(pdf, {
              head: headers,
              body: rows,
              startY: yPos,
              styles: { fontSize: 8 },
              headStyles: { fillColor: [102, 126, 234] }
            });
          } else if (reportType === 'late_payments') {
            const headers = [['Customer', 'Invoices', 'Outstanding', 'Days Overdue']];
            const rows = this.reportResult.data.map(item => [
              String(item.name || '').substring(0, 30),
              String(this.num(item.invoices_count || 0)),
              this.money(item.outstanding_amount || 0),
              `${this.num(item.max_days_overdue || 0)} days`
            ]);
            autoTable(pdf, {
              head: headers,
              body: rows,
              startY: yPos,
              styles: { fontSize: 8 },
              headStyles: { fillColor: [220, 53, 69] }
            });
          } else if (reportType === 'daily_sales') {
            const data = this.reportResult.data;
            const summary = [
              ['Metric', 'Value'],
              ['Transactions', String(this.num(data.transactions || 0))],
              ['Revenue', this.money(data.revenue || 0)],
              ['Tax', this.money(data.tax || 0)],
              ['Discount', this.money(data.discount || 0)],
              ['Profit', this.money(data.profit || 0)]
            ];
            autoTable(pdf, {
              body: summary,
              startY: yPos,
              styles: { fontSize: 10 },
              headStyles: { fillColor: [102, 126, 234] }
            });
          }
        }

        // Save PDF
        const filename = `AI_Report_${reportType}_${moment().format('YYYY-MM-DD')}.pdf`;
        pdf.save(filename);
        this.makeToast('success', 'Success', 'PDF exported successfully');
      } catch (error) {
        console.error('PDF Export Error:', error);
        this.makeToast('danger', 'Error', 'Failed to export PDF');
      }
    },
    exportToExcel(reportType) {
      try {
        let csvContent = '';
        const filename = `AI_Report_${reportType}_${moment().format('YYYY-MM-DD')}.csv`;

        // Header
        csvContent += `${this.reportResult.question.title || 'AI Report'}\n`;
        csvContent += `Generated: ${this.getCurrentTime()}\n`;
        if (this.reportResult.filters.date_from && this.reportResult.filters.date_to) {
          csvContent += `Date Range: ${this.reportResult.filters.date_from} - ${this.reportResult.filters.date_to}\n`;
        }
        csvContent += '\n';

        // Data
        if (this.reportResult.data && Array.isArray(this.reportResult.data)) {
          if (reportType === 'products') {
            csvContent += 'Product,Quantity,Revenue,Cost,Profit,Margin %\n';
            this.reportResult.data.forEach(item => {
              csvContent += `"${this.productDisplayName(item)}",${this.num(item.qty || 0)},${this.num(item.revenue || 0)},${this.num(item.cost || 0)},${this.num(item.profit || 0)},${this.num(item.margin_percent || 0)}\n`;
            });
          } else if (reportType === 'late_payments') {
            csvContent += 'Customer,Invoices Count,Outstanding Amount,Max Days Overdue\n';
            this.reportResult.data.forEach(item => {
              csvContent += `"${item.name || ''}",${this.num(item.invoices_count || 0)},${this.num(item.outstanding_amount || 0)},${this.num(item.max_days_overdue || 0)}\n`;
            });
          } else if (reportType === 'daily_sales') {
            const data = this.reportResult.data;
            csvContent += 'Metric,Value\n';
            csvContent += `Transactions,${this.num(data.transactions || 0)}\n`;
            csvContent += `Revenue,${this.num(data.revenue || 0)}\n`;
            csvContent += `Tax,${this.num(data.tax || 0)}\n`;
            csvContent += `Discount,${this.num(data.discount || 0)}\n`;
            csvContent += `Profit,${this.num(data.profit || 0)}\n`;
          }
        }

        // Download
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
        this.makeToast('success', 'Success', 'Excel file exported successfully');
      } catch (error) {
        console.error('Excel Export Error:', error);
        this.makeToast('danger', 'Error', 'Failed to export Excel file');
      }
    },
    printReport(reportType) {
      try {
        const printWindow = window.open('', '_blank');
        const reportTitle = this.reportResult.question.title || 'AI Report';
        
        printWindow.document.write(`
          <!DOCTYPE html>
          <html>
          <head>
            <title>${reportTitle}</title>
            <style>
              body { font-family: Arial, sans-serif; padding: 20px; }
              h1 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
              .header-info { margin: 20px 0; color: #666; }
              .insights { background: #f8f9fa; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
              table { width: 100%; border-collapse: collapse; margin: 20px 0; }
              th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
              th { background-color: #667eea; color: white; }
              tr:nth-child(even) { background-color: #f2f2f2; }
              @media print {
                body { padding: 0; }
                .no-print { display: none; }
              }
            </style>
          </head>
          <body>
            <h1>${reportTitle}</h1>
            <div class="header-info">
              <p><strong>Generated:</strong> ${this.getCurrentTime()}</p>
              ${this.reportResult.filters.date_from && this.reportResult.filters.date_to ? 
                `<p><strong>Date Range:</strong> ${this.reportResult.filters.date_from} - ${this.reportResult.filters.date_to}</p>` : ''}
            </div>
            ${this.reportResult.insights ? 
              `<div class="insights"><h3>AI Insights</h3><p>${this.reportResult.insights}</p></div>` : ''}
            ${this.generatePrintTable(reportType)}
          </body>
          </html>
        `);
        
        printWindow.document.close();
        setTimeout(() => {
          printWindow.print();
        }, 250);
      } catch (error) {
        console.error('Print Error:', error);
        this.makeToast('danger', 'Error', 'Failed to print report');
      }
    },
    generatePrintTable(reportType) {
      if (!this.reportResult.data) return '';
      
      if (reportType === 'products') {
        let table = '<table><thead><tr><th>Product</th><th>Quantity</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Margin %</th></tr></thead><tbody>';
        this.reportResult.data.forEach(item => {
          table += `<tr>
            <td>${this.productDisplayName(item)}</td>
            <td>${this.num(item.qty || 0)}</td>
            <td>${this.money(item.revenue || 0)}</td>
            <td>${this.money(item.cost || 0)}</td>
            <td>${this.money(item.profit || 0)}</td>
            <td>${this.num(item.margin_percent || 0)}%</td>
          </tr>`;
        });
        table += '</tbody></table>';
        return table;
      } else if (reportType === 'late_payments') {
        let table = '<table><thead><tr><th>Customer</th><th>Invoices Count</th><th>Outstanding Amount</th><th>Max Days Overdue</th></tr></thead><tbody>';
        this.reportResult.data.forEach(item => {
          table += `<tr>
            <td>${item.name || ''}</td>
            <td>${this.num(item.invoices_count || 0)}</td>
            <td>${this.money(item.outstanding_amount || 0)}</td>
            <td>${this.num(item.max_days_overdue || 0)} days</td>
          </tr>`;
        });
        table += '</tbody></table>';
        return table;
      } else if (reportType === 'daily_sales') {
        const data = this.reportResult.data;
        return `<table>
          <tr><th>Metric</th><th>Value</th></tr>
          <tr><td>Transactions</td><td>${this.num(data.transactions || 0)}</td></tr>
          <tr><td>Revenue</td><td>${this.money(data.revenue || 0)}</td></tr>
          <tr><td>Tax</td><td>${this.money(data.tax || 0)}</td></tr>
          <tr><td>Discount</td><td>${this.money(data.discount || 0)}</td></tr>
          <tr><td>Profit</td><td>${this.money(data.profit || 0)}</td></tr>
        </table>`;
      }
      return '';
    },
    makeToast(variant, title, message) {
      this.$bvToast.toast(message, {
        title: title,
        variant: variant,
        solid: true,
        autoHideDelay: 3000
      });
    }
  }
};
</script>

<style scoped>
/* Header Section */
.ai-reports-header {
  margin-bottom: 2rem;
}

.header-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white !important;
  border-radius: 1rem;
  padding: 1.5rem;
}

.header-card h4,
.header-card p,
.header-card * {
  color: white !important;
}

.header-icon-wrapper {
  width: 60px;
  height: 60px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.header-icon {
  font-size: 2rem;
  color: white;
}

/* Question Card */
.question-card {
  border-radius: 1rem;
  padding: 1.5rem;
}

.card-header-custom {
  border-bottom: 2px solid #f0f0f0;
  padding-bottom: 1rem;
}

.card-header-custom h5 {
  color: #2c3e50;
  font-weight: 600;
}

.custom-select {
  border-radius: 0.5rem;
}

.custom-input {
  border-radius: 0.5rem;
}

.font-weight-semibold {
  margin-bottom: 0.2rem !important;
}

.btn-run {
  border-radius: 0.5rem;
  font-weight: 600;
  padding: 0.5rem 0.75rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transition: all 0.3s;
  white-space: nowrap;
  min-height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-run:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.btn-run:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 991px) {
  .btn-run {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
  }
}

.question-option {
  display: flex;
  align-items: center;
  padding: 0.5rem 0;
}

/* Loading State */
.loading-container {
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 0;
}

.loading-container.fade {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.loading-card {
  border-radius: 1rem;
  max-width: 600px;
  width: 100%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
}

.loading-content {
  padding: 2rem;
}

.spinner-wrapper {
  position: relative;
  display: inline-block;
  width: 80px;
  height: 80px;
  margin: 0 auto;
}

.pulse-ring {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  animation: pulse-ring 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse-ring {
  0% {
    transform: scale(0.8);
    opacity: 1;
  }
  50% {
    transform: scale(1.2);
    opacity: 0.5;
  }
  100% {
    transform: scale(1.5);
    opacity: 0;
  }
}

.spinner-wrapper .spinner {
  position: relative;
  z-index: 1;
}

.loading-title {
  color: white;
  font-weight: 700;
  font-size: 1.5rem;
}

.loading-subtitle {
  color: rgba(255, 255, 255, 0.9);
  font-size: 1rem;
}

.loading-steps {
  display: flex;
  justify-content: space-around;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  opacity: 0.4;
  transition: all 0.3s ease;
  flex: 1;
}

.step-item.active {
  opacity: 1;
  transform: scale(1.1);
}

.step-item i {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
  color: white;
}

.step-item span {
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.9);
  text-align: center;
}

/* Results Container */
.results-container {
  animation: fadeInUp 0.5s ease-out;
}

.results-container.slide-fade {
  animation: slideFadeIn 0.5s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideFadeIn {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Success Banner */
.success-banner {
  animation: slideDown 0.4s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.success-card {
  background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
  color: white;
  border-radius: 1rem;
  border: none;
}

.success-icon-wrapper {
  width: 50px;
  height: 50px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.success-icon-wrapper i {
  font-size: 1.5rem;
  color: white;
}

.report-timestamp {
  text-align: right;
}

.report-timestamp small {
  color: rgba(255, 255, 255, 0.9) !important;
}

/* Filters Summary */
.filters-summary-card {
  background: linear-gradient(to right, #f8f9fa, #ffffff);
  border-left: 4px solid #667eea;
  border-radius: 0.75rem;
  transition: all 0.3s ease;
}

.filters-summary-card:hover {
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15) !important;
}

.filters-header {
  border-bottom: 1px solid #e9ecef;
  padding-bottom: 0.75rem;
}

.filters-content {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding-top: 0.5rem;
}

.filter-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  transition: all 0.2s ease;
}

.filter-badge:hover {
  background: #e9ecef;
  transform: translateY(-2px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.filter-badge i {
  color: #667eea;
}

.filter-badge strong {
  color: #495057;
  margin-right: 0.25rem;
}

.filter-badge span {
  color: #6c757d;
}

/* Insights Card */
.insights-card {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
  border-radius: 1rem;
  border: none;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.insights-card::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  animation: shimmer 3s infinite;
}

@keyframes shimmer {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.insights-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 35px rgba(240, 147, 251, 0.3) !important;
}

.insights-header {
  border-bottom: 1px solid rgba(255, 255, 255, 0.3);
  padding-bottom: 1rem;
  position: relative;
  z-index: 1;
}

.insights-icon-wrapper {
  width: 50px;
  height: 50px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.insights-card:hover .insights-icon-wrapper {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.1);
}

.insights-icon {
  font-size: 1.5rem;
  color: white;
}

.insights-badge {
  width: 40px;
  height: 40px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.insights-badge i {
  font-size: 1.25rem;
  color: white;
  animation: sparkle 2s infinite;
}

@keyframes sparkle {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.7;
    transform: scale(1.1);
  }
}

.insights-content {
  padding-top: 1rem;
  position: relative;
  z-index: 1;
}

.insights-text-wrapper {
  background: rgba(255, 255, 255, 0.1);
  padding: 1.25rem;
  border-radius: 0.5rem;
  backdrop-filter: blur(10px);
}

.insights-text {
  font-size: 1.1rem;
  line-height: 1.8;
  color: white;
  margin: 0;
}

/* Stat Cards */
.stat-card {
  padding: 1.5rem;
  background: white;
  border-radius: 0.75rem;
  transition: all 0.3s ease;
  height: 100%;
  border: 1px solid #e9ecef;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
}

.stat-inner {
  display: flex;
  align-items: center;
}

.stat-icon {
  font-size: 2.5rem;
  margin-right: 1rem;
  opacity: 0.9;
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  background: rgba(0, 0, 0, 0.05);
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 0.875rem;
  color: #6c757d;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: #2c3e50;
  line-height: 1.2;
}

.theme-blue .stat-icon { 
  color: #007bff; 
  background: rgba(0, 123, 255, 0.1);
}

.theme-green .stat-icon { 
  color: #28a745; 
  background: rgba(40, 167, 69, 0.1);
}

.theme-orange .stat-icon { 
  color: #ffc107; 
  background: rgba(255, 193, 7, 0.1);
}

.theme-purple .stat-icon { 
  color: #6f42c1; 
  background: rgba(111, 66, 193, 0.1);
}

.theme-cyan .stat-icon { 
  color: #17a2b8; 
  background: rgba(23, 162, 184, 0.1);
}

/* Empty State */
.empty-state-card {
  border-radius: 1rem;
  min-height: 400px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.empty-state-icon {
  font-size: 5rem;
  color: #dee2e6;
  margin-bottom: 1rem;
}

.empty-state-features {
  margin-top: 2rem;
}

.feature-item {
  padding: 1.5rem;
  text-align: center;
}

.feature-item i {
  font-size: 2.5rem;
  display: block;
}

.feature-item h6 {
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  color: #2c3e50;
}

/* Report Content */
.report-content {
  animation: fadeInUp 0.6s ease-out;
}

.report-content.fade-up {
  animation: fadeInUp 0.6s ease-out;
}

.report-header {
  padding: 1rem 0;
}

.report-header h5 {
  color: #2c3e50;
}

.table-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: #f8f9fa;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #495057;
}

.table-badge.warning {
  background: #fff3cd;
  color: #856404;
}

.table-badge i {
  font-size: 1rem;
}

/* Animated Cards */
.animated-card {
  animation: fadeInUp 0.5s ease-out;
  transition: all 0.3s ease;
}

.animated-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
}

/* Table Enhancements */
.vgt-table {
  border-radius: 0.5rem;
  overflow: hidden;
}

/* Responsive */
@media (max-width: 768px) {
  .header-card {
    padding: 1rem;
  }
  
  .header-icon-wrapper {
    width: 50px;
    height: 50px;
  }
  
  .header-icon {
    font-size: 1.5rem;
  }
  
  .stat-card {
    padding: 1rem;
  }
  
  .stat-icon {
    font-size: 2rem;
    width: 50px;
    height: 50px;
  }
  
  .stat-value {
    font-size: 1.5rem;
  }
}

/* Gutter spacing */
.gutter-sm > * {
  padding-left: 0.5rem;
  padding-right: 0.5rem;
}


/* Transitions */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter, .fade-leave-to {
  opacity: 0;
}

.slide-fade-enter-active {
  transition: all 0.4s ease-out;
}

.slide-fade-enter {
  opacity: 0;
  transform: translateY(30px);
}

.fade-up-enter-active {
  transition: all 0.5s ease-out;
}

.fade-up-enter {
  opacity: 0;
  transform: translateY(20px);
}

.scale-in-enter-active {
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.scale-in-enter {
  opacity: 0;
  transform: scale(0.8);
}

.scale-in {
  animation: scaleIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.8);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.fade-up {
  animation: fadeInUp 0.6s ease-out;
}

/* Responsive adjustments for loading */
@media (max-width: 768px) {
  .loading-steps {
    flex-direction: column;
    gap: 1rem;
  }
  
  .step-item {
    flex-direction: row;
    justify-content: flex-start;
  }
  
  .step-item i {
    margin-right: 0.75rem;
    margin-bottom: 0;
  }
  
  .success-card .d-flex {
    flex-direction: column;
    text-align: center;
  }
  
  .report-timestamp {
    text-align: center;
    margin-top: 0.5rem;
  }
  
  .filters-content {
    flex-direction: column;
  }
  
  .filter-badge {
    width: 100%;
    justify-content: flex-start;
  }
  
  .report-actions {
    width: 100%;
    margin-top: 1rem;
    justify-content: flex-start;
  }
  
  .report-actions .btn {
    flex: 1;
    min-width: 80px;
  }
  
  .ai-icon-wrapper {
    width: 40px;
    height: 40px;
  }
  
  .ai-icon-wrapper i {
    font-size: 1.25rem;
  }
  
  .summary-icon {
    width: 50px;
    height: 50px;
    font-size: 1.5rem;
  }
  
  .summary-value {
    font-size: 1.25rem;
  }
  
  .chart-container {
    padding: 0.5rem;
    min-height: 300px;
  }
}
</style>
