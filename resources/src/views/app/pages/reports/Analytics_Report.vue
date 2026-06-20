<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Analytics_Report')" :folder="$t('Reports')" />

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
              <b-button variant="light" class="btn-pill" :class="{ 'w-100': isMobile }">
                <lucide-icon class="mr-1" name="calendar-days" />
                <span class="d-none d-sm-inline">
                  {{ fmtDate(pickerSlot.startDate) }} — {{ fmtDate(pickerSlot.endDate) }}
                </span>
                <span class="d-inline d-sm-none">
                  {{ fmtShort(pickerSlot.startDate) }}–{{ fmtShort(pickerSlot.endDate) }}
                </span>
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
          <b-button variant="primary" class="btn-pill mr-2" @click="fetchReport">
            <lucide-icon class="mr-1" name="refresh-cw" /> {{$t('Refresh')}}
          </b-button>
          <b-button variant="outline-secondary" class="btn-pill mr-2" @click="printReport">
            <lucide-icon class="mr-1" name="printer" />{{$t('print')}}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="6" v-for="n in 2" :key="'skel-'+n" class="mb-3">
          <b-skeleton-img class="rounded-xl shadow-soft" height="400px" />
        </b-col>
      </b-row>
    </div>

    <!-- Content -->
    <div v-else class="analytics-report">
      <b-row>
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
      </b-row>
      <b-row>
        <!-- LEFT CARD: Opening/Purchase Side -->
        <b-col md="6" class="mb-3">
          <b-card class="analytics-card shadow-soft border-0">
            <h6 class="card-title mb-3">Opening/Purchase</h6>
            <div class="analytics-rows">
              <div class="analytics-row">
                <span class="label">Opening Stock (By purchase price)</span>
                <span class="value">{{ money(data.opening_stock_purchase_price) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Opening Stock (By sale price)</span>
                <span class="value">{{ money(data.opening_stock_sale_price) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total Purchase (Excl. tax, Discount)</span>
                <span class="value">{{ money(data.total_purchase_excl_tax) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total Stock Adjustment</span>
                <span class="value">{{ money(data.total_stock_adjustment) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total Expense</span>
                <span class="value">{{ money(data.total_expense) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total purchase shipping charge</span>
                <span class="value">{{ money(data.total_purchase_shipping_charge) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total transfer shipping charge</span>
                <span class="value">{{ money(data.total_transfer_shipping_charge) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total Sell discount</span>
                <span class="value">{{ money(data.total_sell_discount) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total customer reward</span>
                <span class="value">{{ money(data.total_customer_reward) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total Sell Return</span>
                <span class="value">{{ money(data.total_sell_return) }}</span>
              </div>
            </div>
          </b-card>
        </b-col>

        <!-- RIGHT CARD: Closing/Sales Side -->
        <b-col md="6" class="mb-3">
          <b-card class="analytics-card shadow-soft border-0">
            <h6 class="card-title mb-3">Closing/Sales</h6>
            <div class="analytics-rows">
              <div class="analytics-row">
                <span class="label">Closing stock (By purchase price)</span>
                <span class="value">{{ money(data.closing_stock_purchase_price) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Closing stock (By sale price)</span>
                <span class="value">{{ money(data.closing_stock_sale_price) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total Sales (Excl. tax, Discount)</span>
                <span class="value">{{ money(data.total_sales_excl_tax) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total sell shipping charge</span>
                <span class="value">{{ money(data.total_sell_shipping_charge) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total Purchase Return</span>
                <span class="value">{{ money(data.total_purchase_return) }}</span>
              </div>
              <div class="analytics-row">
                <span class="label">Total Purchase discount</span>
                <span class="value">{{ money(data.total_purchase_discount) }}</span>
              </div>
            </div>
          </b-card>
        </b-col>
      </b-row>

      <!-- Profit Calculations Section -->
      <b-row class="mt-3">
        <b-col md="12">
          <b-card class="profit-card shadow-soft border-0">
            <div class="profit-section">
              <div class="profit-row">
                <div class="profit-label">Gross Profit:</div>
                <div class="profit-value">{{ money(grossProfit) }}</div>
              </div>
              <div class="profit-formula">
                Formula: (Total sell price - Total purchase price)
              </div>
            </div>
            <div class="profit-section mt-3">
              <div class="profit-row">
                <div class="profit-label">Net Profit:</div>
                <div class="profit-value">{{ money(netProfit) }}</div>
              </div>
              <div class="profit-formula">
                Formula: Gross Profit - (Total Expense + Total transfer shipping charge)
              </div>
            </div>
          </b-card>
        </b-col>
      </b-row>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import moment from "moment";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: { title: "Analytics Report" },
  components: {
    "date-range-picker": DateRangePicker
  },
  data() {
    const end = moment().endOf('day').toDate();
    const start = moment().startOf('day').toDate();
    return {
      isLoading: true,
      warehouses: [],
      warehouse_id: null,
      dateRange: { startDate: start, endDate: end },
      picker: { opens: 'right', drops: 'auto' },
      isMobile: false,
      locale: {
        Label: this.$t("Apply") || "Apply",
        cancelLabel: this.$t("Cancel") || "Cancel",
        weekLabel: "W",
        customRangeLabel: this.$t("CustomRange") || "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1
      },
      data: {
        opening_stock_purchase_price: 0,
        opening_stock_sale_price: 0,
        total_purchase_excl_tax: 0,
        total_stock_adjustment: 0,
        total_expense: 0,
        total_purchase_shipping_charge: 0,
        total_transfer_shipping_charge: 0,
        total_sell_discount: 0,
        total_customer_reward: 0,
        total_sell_return: 0,
        closing_stock_purchase_price: 0,
        closing_stock_sale_price: 0,
        total_sales_excl_tax: 0,
        total_sell_shipping_charge: 0,
        total_purchase_return: 0,
        total_purchase_discount: 0
      },
      price_format_key: null
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    currency() {
      return (this.currentUser && this.currentUser.currency) || "KD";
    },
    warehouseLabel() {
      const w = this.warehouses.find(w => w.id === this.warehouse_id);
      return w ? w.name : null;
    },
    // Calculate total purchase price (opening stock + purchases)
    totalPurchasePrice() {
      return (
        Number(this.data.opening_stock_purchase_price || 0) +
        Number(this.data.total_purchase_excl_tax || 0)
      );
    },
    // Calculate total sell price (closing stock + sales)
    totalSellPrice() {
      return (
        Number(this.data.closing_stock_sale_price || 0) +
        Number(this.data.total_sales_excl_tax || 0)
      );
    },
    // Gross Profit = Total sell price - Total purchase price
    grossProfit() {
      return this.totalSellPrice - this.totalPurchasePrice;
    },
    // Net Profit = Gross Profit - operating costs that aren't already reflected
    // in the inventory/sales figures that make up Gross Profit.
    //
    // Gross Profit already accounts for:
    //   - sell/purchase shipping (included in GrandTotal of each sale/purchase)
    //   - sell/purchase discounts (already netted in total_sales_excl_tax / total_purchase_excl_tax)
    //   - customer reward points (subtracted in total_sales_excl_tax)
    //   - stock adjustments (the written-off/added units are reflected in closing stock)
    //
    // What remains to deduct are pure operating costs:
    //   - total_expense: general operating expenses
    //   - total_transfer_shipping_charge: inter-warehouse shipping (not part of sale/purchase GrandTotals)
    netProfit() {
      const deductions =
        Number(this.data.total_expense || 0) +
        Number(this.data.total_transfer_shipping_charge || 0);
      return this.grossProfit - deductions;
    }
  },
  methods: {
    // Responsiveness
    handleResize() {
      this.isMobile = window.innerWidth < 576;
    },
    updatePickerPlacement() {
      const isXs = window.matchMedia('(max-width: 576px)').matches;
      this.picker.opens = isXs ? 'center' : 'right';
      this.picker.drops = 'auto';
    },
    fmtDate(d) {
      return moment(d).format('YYYY-MM-DD');
    },
    fmtShort(d) {
      return moment(d).format('MMM D');
    },
    onDateChange() {
      this.fetchReport();
    },
    onWarehouseChange() {
      this.fetchReport();
    },
    applyQuick(kind) {
      const now = moment();
      let start, end;

      if (kind === 'today') {
        start = now.clone().startOf('day');
        end = now.clone().endOf('day');
      } else if (kind === 'yesterday') {
        start = now.clone().subtract(1, 'day').startOf('day');
        end = now.clone().subtract(1, 'day').endOf('day');
      } else if (kind === '7d') {
        start = now.clone().subtract(6, 'days').startOf('day');
        end = now.clone().endOf('day');
      } else if (kind === '30d') {
        start = now.clone().subtract(29, 'days').startOf('day');
        end = now.clone().endOf('day');
      } else if (kind === '90d') {
        start = now.clone().subtract(89, 'days').startOf('day');
        end = now.clone().endOf('day');
      } else if (kind === 'mtd') {
        start = now.clone().startOf('month');
        end = now.clone().endOf('day');
      } else if (kind === 'ytd') {
        start = now.clone().startOf('year');
        end = now.clone().endOf('day');
      }

      this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      this.fetchReport();
    },
    num(v) {
      const n = parseFloat(v || 0);
      return isNaN(n) ? 0 : n;
    },
    // Price formatting for display
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
          return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency: this.currency
          }).format(this.num(v));
        } catch (e2) {
          return `${this.currency} ${this.num(v).toLocaleString()}`;
        }
      }
    },
    fetchReport() {
      NProgress.start();
      NProgress.set(0.1);
      this.isLoading = true;

      const params = new URLSearchParams({
        from: this.fmtDate(this.dateRange.startDate),
        to: this.fmtDate(this.dateRange.endDate)
      });
      
      if (this.warehouse_id) {
        params.append('warehouse_id', this.warehouse_id);
      }

      axios
        .get(`report/analytics_summary?${params.toString()}`)
        .then(({ data }) => {
          this.data = {
            opening_stock_purchase_price: Number(data.opening_stock_purchase_price || 0),
            opening_stock_sale_price: Number(data.opening_stock_sale_price || 0),
            total_purchase_excl_tax: Number(data.total_purchase_excl_tax || 0),
            total_stock_adjustment: Number(data.total_stock_adjustment || 0),
            total_expense: Number(data.total_expense || 0),
            total_purchase_shipping_charge: Number(data.total_purchase_shipping_charge || 0),
            total_transfer_shipping_charge: Number(data.total_transfer_shipping_charge || 0),
            total_sell_discount: Number(data.total_sell_discount || 0),
            total_customer_reward: Number(data.total_customer_reward || 0),
            total_sell_return: Number(data.total_sell_return || 0),
            closing_stock_purchase_price: Number(data.closing_stock_purchase_price || 0),
            closing_stock_sale_price: Number(data.closing_stock_sale_price || 0),
            total_sales_excl_tax: Number(data.total_sales_excl_tax || 0),
            total_sell_shipping_charge: Number(data.total_sell_shipping_charge || 0),
            total_purchase_return: Number(data.total_purchase_return || 0),
            total_purchase_discount: Number(data.total_purchase_discount || 0)
          };
          this.warehouses = data.warehouses || [];
          this.isLoading = false;
          NProgress.done();
        })
        .catch((error) => {
          this.isLoading = false;
          NProgress.done();
          const msg = (error && error.response && error.response.data && error.response.data.message)
            || (this.$t ? this.$t('Error') : null)
            || 'Failed to load analytics report';
          if (this.$swal && typeof this.$swal.fire === 'function') {
            this.$swal.fire({ icon: 'error', title: msg, timer: 3000, showConfirmButton: false });
          } else if (window && typeof window.alert === 'function') {
            window.alert(msg);
          }
        });
    },
    printReport() {
      const title = `${this.$t("Reports")} / ${this.$t("Analytics_Report")}`;
      const dateRangeText = `${this.fmtDate(this.dateRange.startDate)} — ${this.fmtDate(this.dateRange.endDate)}`;
      const warehouseText = this.warehouseLabel ? ` (${this.warehouseLabel})` : '';
      const root = this.$el;
      if (!root) {
        window.print();
        return;
      }

      const reportContent = root.querySelector(".analytics-report");
      if (!reportContent) {
        window.print();
        return;
      }

      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map((l) => l.outerHTML)
        .join("\n");

      const inlineStyles = Array.from(document.querySelectorAll("style"))
        .filter((s) => !(s.textContent || "").includes("@media print"))
        .map((s) => s.outerHTML)
        .join("\n");

      // Build HTML for the report
      let reportHtml = `<div class="analytics-report">`;
      
      // Left Card
      reportHtml += `<div class="card mb-3" style="page-break-inside: avoid;">
        <div class="card-body">
          <h6 class="mb-3">Opening/Purchase</h6>
          <table style="width: 100%;">`;
      const leftFields = [
        { label: "Opening Stock (By purchase price)", value: this.money(this.data.opening_stock_purchase_price) },
        { label: "Opening Stock (By sale price)", value: this.money(this.data.opening_stock_sale_price) },
        { label: "Total Purchase (Excl. tax, Discount)", value: this.money(this.data.total_purchase_excl_tax) },
        { label: "Total Stock Adjustment", value: this.money(this.data.total_stock_adjustment) },
        { label: "Total Expense", value: this.money(this.data.total_expense) },
        { label: "Total purchase shipping charge", value: this.money(this.data.total_purchase_shipping_charge) },
        { label: "Total transfer shipping charge", value: this.money(this.data.total_transfer_shipping_charge) },
        { label: "Total Sell discount", value: this.money(this.data.total_sell_discount) },
        { label: "Total customer reward", value: this.money(this.data.total_customer_reward) },
        { label: "Total Sell Return", value: this.money(this.data.total_sell_return) }
      ];
      leftFields.forEach((field) => {
        reportHtml += `<tr style="border-bottom: 1px solid #e0e0e0;">
          <td style="padding: 8px; text-align: left;">${field.label}</td>
          <td style="padding: 8px; text-align: right;">${field.value}</td>
        </tr>`;
      });
      reportHtml += `</table></div></div>`;

      // Right Card
      reportHtml += `<div class="card mb-3" style="page-break-inside: avoid;">
        <div class="card-body">
          <h6 class="mb-3">Closing/Sales</h6>
          <table style="width: 100%;">`;
      const rightFields = [
        { label: "Closing stock (By purchase price)", value: this.money(this.data.closing_stock_purchase_price) },
        { label: "Closing stock (By sale price)", value: this.money(this.data.closing_stock_sale_price) },
        { label: "Total Sales (Excl. tax, Discount)", value: this.money(this.data.total_sales_excl_tax) },
        { label: "Total sell shipping charge", value: this.money(this.data.total_sell_shipping_charge) },
        { label: "Total Purchase Return", value: this.money(this.data.total_purchase_return) },
        { label: "Total Purchase discount", value: this.money(this.data.total_purchase_discount) }
      ];
      rightFields.forEach((field) => {
        reportHtml += `<tr style="border-bottom: 1px solid #e0e0e0;">
          <td style="padding: 8px; text-align: left;">${field.label}</td>
          <td style="padding: 8px; text-align: right;">${field.value}</td>
        </tr>`;
      });
      reportHtml += `</table></div></div>`;

      // Profit Section
      reportHtml += `<div class="card mt-3" style="page-break-inside: avoid;">
        <div class="card-body">
          <div style="margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
              <strong>Gross Profit:</strong>
              <strong>${this.money(this.grossProfit)}</strong>
            </div>
            <div style="font-size: 11px; color: #666;">Formula: (Total sell price - Total purchase price)</div>
          </div>
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
              <strong>Net Profit:</strong>
              <strong>${this.money(this.netProfit)}</strong>
            </div>
            <div style="font-size: 11px; color: #666;">Formula: Gross Profit - (Total Expense + Total transfer shipping charge)</div>
          </div>
        </div>
      </div>`;

      reportHtml += `</div>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

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
    ${inlineStyles}
    <style>
      @media print { 
        body, body * { visibility: visible !important; }
        @page { size: A4; margin: 1cm; }
      }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 8px; font-size: 14px; }
      .print-meta { font-size: 10px; margin-bottom: 15px; color: #666; }
      .card { border: 1px solid #ddd; border-radius: 8px; margin-bottom: 16px; }
      .card-body { padding: 16px; }
      table { width: 100%; border-collapse: collapse; }
      td { border-bottom: 1px solid #e0e0e0; }
      @media print {
        .analytics-report { display: block; }
      }
      @media screen {
        .analytics-report { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
      }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    <div class="print-meta">${dateRangeText}${warehouseText}</div>
    ${reportHtml}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    }
  },
  mounted() {
    this.handleResize();
    this.updatePickerPlacement();
    window.addEventListener('resize', this.handleResize);
    window.addEventListener('resize', this.updatePickerPlacement);
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.handleResize);
    window.removeEventListener('resize', this.updatePickerPlacement);
  },
  created() {
    this.fetchReport();
  }
};
</script>

<style scoped>
.rounded-xl {
  border-radius: 1rem;
}
.shadow-soft {
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06), 0 2px 6px rgba(0, 0, 0, 0.05);
}
.toolbar-card {
  background: #fff;
}
.btn-pill {
  border-radius: 999px;
}

.analytics-card {
  min-height: 450px;
}
.card-title {
  font-weight: 600;
  font-size: 1rem;
  color: #333;
  border-bottom: 1px solid #e0e0e0;
  padding-bottom: 12px;
}

.analytics-rows {
  display: flex;
  flex-direction: column;
}

.analytics-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #e0e0e0;
}

.analytics-row:last-child {
  border-bottom: none;
}

.analytics-row .label {
  flex: 1;
  text-align: left;
  color: #555;
  font-size: 0.9rem;
}

.analytics-row .value {
  flex: 0 0 auto;
  text-align: right;
  font-weight: 500;
  color: #333;
  font-size: 0.9rem;
  margin-left: 16px;
}

.profit-card {
  background: #fafbfc;
}

.profit-section {
  padding: 8px 0;
}

.profit-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.profit-label {
  font-weight: 600;
  font-size: 1rem;
  color: #333;
}

.profit-value {
  font-weight: 700;
  font-size: 1.1rem;
  color: #333;
}

.profit-formula {
  font-size: 0.75rem;
  color: #666;
  font-style: italic;
  margin-top: 4px;
}

/* Mobile responsive */
@media (max-width: 767.98px) {
  .analytics-card {
    min-height: auto;
  }

  .analytics-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .analytics-row .value {
    margin-left: 0;
    margin-top: 4px;
    text-align: left;
  }
}

.w-280 {
  width: 280px;
}

/* Date range picker responsiveness */
@media (max-width: 575.98px) {
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

/* Keep the picker above navbars/modals/offcanvas */
.daterangepicker {
  z-index: 2055 !important;
}
</style>
