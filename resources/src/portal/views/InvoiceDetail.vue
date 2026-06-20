<template>
  <div class="portal-page portal-invoice-detail">
    <router-link to="/invoices" class="pc-back">
      <svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10H5M10 5l-5 5 5 5"/></svg>
      Back to Invoices
    </router-link>

    <div v-if="loading" class="pc-inline-loading">
      <div class="pc-spinner"></div>
      <span>Loading invoice...</span>
    </div>

    <div v-else-if="invoice" class="pc-detail">
      <!-- Header -->
      <div class="pc-detail-head">
        <div class="pc-detail-title-wrap">
          <span class="pc-eyebrow">Invoice</span>
          <h1 class="pc-detail-title">{{ invoice.Ref }}</h1>
          <div class="pc-detail-meta">
            <span>{{ invoice.date }}<template v-if="invoice.time"> · {{ invoice.time }}</template></span>
            <span :class="'pc-badge pc-badge-' + badgeClass(invoice.payment_status)">{{ invoice.payment_status }}</span>
          </div>
        </div>
        <a :href="`/api/portal/invoices/${invoice.id}/pdf`" target="_blank" rel="noopener" class="pc-btn-primary">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
          Download PDF
        </a>
      </div>

      <!-- Meta cards -->
      <div class="pc-detail-stats">
        <div class="pc-detail-stat">
          <span class="pc-detail-stat-label">Total</span>
          <span class="pc-detail-stat-value">{{ formatMoney(invoice.GrandTotal) }}</span>
        </div>
        <div class="pc-detail-stat">
          <span class="pc-detail-stat-label">Paid</span>
          <span class="pc-detail-stat-value pc-amount-pos">{{ formatMoney(invoice.paid_amount) }}</span>
        </div>
        <div class="pc-detail-stat pc-detail-stat-due" :class="{ 'is-due': Number(invoice.due) > 0 }">
          <span class="pc-detail-stat-label">Due</span>
          <span class="pc-detail-stat-value">{{ formatMoney(invoice.due) }}</span>
        </div>
      </div>

      <!-- Line items -->
      <div class="pc-detail-items">
        <div class="pc-items-head">
          <h2 class="pc-items-title">Items</h2>
          <span class="pc-items-count">{{ (invoice.details || []).length }} item{{ (invoice.details || []).length === 1 ? '' : 's' }}</span>
        </div>

        <!-- Desktop table -->
        <div class="pc-table-wrap pc-hide-mobile">
          <table class="pc-table">
            <thead>
              <tr>
                <th>Product</th>
                <th class="pc-num">Qty</th>
                <th class="pc-num">Price</th>
                <th class="pc-num">Discount</th>
                <th class="pc-num">Tax</th>
                <th class="pc-num">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, i) in invoice.details" :key="i">
                <td>{{ line.product_name }}</td>
                <td class="pc-num">{{ line.quantity }}</td>
                <td class="pc-num">{{ formatMoney(line.price) }}</td>
                <td class="pc-num">{{ formatMoney(line.DiscountNet) }}</td>
                <td class="pc-num">{{ formatMoney(line.taxe) }}</td>
                <td class="pc-num pc-total-cell">{{ formatMoney(line.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile list -->
        <ul class="pc-item-list pc-show-mobile">
          <li v-for="(line, i) in invoice.details" :key="'m-' + i" class="pc-item-card">
            <div class="pc-item-top">
              <strong class="pc-item-name">{{ line.product_name }}</strong>
              <span class="pc-item-total">{{ formatMoney(line.total) }}</span>
            </div>
            <div class="pc-item-sub">
              <span>{{ line.quantity }} × {{ formatMoney(line.price) }}</span>
            </div>
            <div class="pc-item-sub pc-item-sub-extras" v-if="Number(line.DiscountNet) > 0 || Number(line.taxe) > 0">
              <span v-if="Number(line.DiscountNet) > 0" class="pc-item-discount">Discount {{ formatMoney(line.DiscountNet) }}</span>
              <span v-if="Number(line.taxe) > 0">Tax {{ formatMoney(line.taxe) }}</span>
            </div>
          </li>
        </ul>
      </div>

      <!-- Totals -->
      <div class="pc-totals">
        <div class="pc-totals-row">
          <span>Subtotal</span>
          <strong>{{ formatMoney(invoice.subtotal != null ? invoice.subtotal : invoice.GrandTotal) }}</strong>
        </div>
        <div class="pc-totals-row" v-if="Number(invoice.TaxNet) > 0">
          <span>Order Tax</span>
          <strong>{{ formatMoney(invoice.TaxNet) }}</strong>
        </div>
        <div class="pc-totals-row" v-if="Number(invoice.discount) > 0">
          <span>Discount</span>
          <strong class="pc-amount-neg">
            <template v-if="String(invoice.discount_Method || '2') === '1'">
              − {{ Number(invoice.discount).toFixed(2) }}%
            </template>
            <template v-else>
              − {{ formatMoney(invoice.discount) }}
            </template>
          </strong>
        </div>
        <div class="pc-totals-row" v-if="Number(invoice.discount_from_points) > 0">
          <span>Discount from Points</span>
          <strong class="pc-amount-neg">− {{ formatMoney(invoice.discount_from_points) }}</strong>
        </div>
        <div class="pc-totals-row" v-if="Number(invoice.shipping) > 0">
          <span>Shipping</span>
          <strong>{{ formatMoney(invoice.shipping) }}</strong>
        </div>
        <div class="pc-totals-row pc-totals-grand">
          <span>Total</span>
          <strong>{{ formatMoney(invoice.GrandTotal) }}</strong>
        </div>
        <div class="pc-totals-row">
          <span>Paid</span>
          <strong class="pc-amount-pos">{{ formatMoney(invoice.paid_amount) }}</strong>
        </div>
        <div class="pc-totals-row pc-totals-due">
          <span>Amount due</span>
          <strong>{{ formatMoney(invoice.due) }}</strong>
        </div>
      </div>
    </div>

    <div v-else class="pc-empty">
      <div class="pc-empty-icon">⚠️</div>
      <p>Invoice not found</p>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return { invoice: null, loading: true };
  },
  mounted() {
    this.fetch();
  },
  methods: {
    async fetch() {
      try {
        const { data } = await axios.get(`/portal/invoices/${this.$route.params.id}`);
        this.invoice = data;
      } catch (_) {
        this.invoice = null;
      }
      this.loading = false;
    },
    formatMoney(n) {
      if (n == null) return '0.00';
      return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    badgeClass(s) {
      if (!s) return 'pending';
      const v = (s + '').toLowerCase();
      if (v === 'paid' || v === 'completed') return 'paid';
      if (v === 'partial') return 'partial';
      return 'pending';
    },
  },
};
</script>

<style scoped>
.portal-invoice-detail { padding-bottom: 1rem; }

.pc-back {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 1.1rem;
  color: var(--pc-text-muted);
  text-decoration: none;
  font-size: 0.9rem;
  transition: color 0.15s;
}
.pc-back:hover { color: var(--pc-text); }

.pc-inline-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 3rem;
  color: var(--pc-text-muted);
}
.pc-spinner {
  width: 28px; height: 28px;
  border: 2px solid rgba(79, 70, 229, 0.15);
  border-top-color: var(--pc-primary);
  border-radius: 50%;
  animation: pc-spin 0.7s linear infinite;
}
@keyframes pc-spin { to { transform: rotate(360deg); } }

.pc-empty { padding: 3rem; text-align: center; color: var(--pc-text-soft); }
.pc-empty-icon { font-size: 2.5rem; opacity: 0.7; margin-bottom: 0.5rem; }
.pc-empty p { margin: 0; }

.pc-detail {
  background: var(--pc-surface);
  border: 1px solid var(--pc-border);
  border-radius: var(--pc-radius);
  box-shadow: var(--pc-shadow-sm);
  overflow: hidden;
}

.pc-detail-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1.4rem 1.5rem;
  background: linear-gradient(180deg, var(--pc-surface) 0%, var(--pc-surface-alt) 100%);
  border-bottom: 1px solid var(--pc-border);
}
.pc-eyebrow {
  font-size: 0.72rem;
  color: var(--pc-text-soft);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 600;
}
.pc-detail-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--pc-text);
  margin: 0.2rem 0 0.5rem;
  letter-spacing: -0.01em;
}
.pc-detail-meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.88rem;
  color: var(--pc-text-muted);
  flex-wrap: wrap;
}
.pc-btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.6rem 1rem;
  background: var(--pc-primary);
  color: #fff;
  border-radius: 10px;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
  box-shadow: 0 4px 10px -4px rgba(79, 70, 229, 0.5);
}
.pc-btn-primary:hover { background: var(--pc-primary-600); transform: translateY(-1px); }

.pc-badge {
  display: inline-block;
  padding: 0.22rem 0.55rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: capitalize;
}
.pc-badge-paid { background: var(--pc-success-bg); color: var(--pc-success); }
.pc-badge-pending, .pc-badge-unpaid { background: var(--pc-warning-bg); color: var(--pc-warning); }
.pc-badge-partial { background: #dbeafe; color: #1d4ed8; }

/* Stats */
.pc-detail-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;
  background: var(--pc-border);
}
.pc-detail-stat {
  padding: 1rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  background: var(--pc-surface);
}
.pc-detail-stat-label {
  font-size: 0.74rem;
  color: var(--pc-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
}
.pc-detail-stat-value {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--pc-text);
  letter-spacing: -0.01em;
}
.pc-detail-stat-due.is-due .pc-detail-stat-value { color: var(--pc-danger); }
.pc-amount-pos { color: var(--pc-success); }

/* Items */
.pc-detail-items { padding: 0.5rem 0 0.25rem; }
.pc-items-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem 0.75rem;
}
.pc-items-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--pc-text);
  margin: 0;
}
.pc-items-count {
  font-size: 0.8rem;
  color: var(--pc-text-muted);
  background: var(--pc-surface-alt);
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
}

.pc-table-wrap { overflow-x: auto; }
.pc-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.pc-table th, .pc-table td { padding: 0.7rem 1.5rem; text-align: left; border-top: 1px solid var(--pc-border); }
.pc-table th {
  background: var(--pc-surface-alt);
  font-weight: 600;
  color: var(--pc-text-muted);
  font-size: 0.73rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.pc-num { text-align: right; }
.pc-total-cell { font-weight: 600; color: var(--pc-text); }

/* Item cards (mobile) */
.pc-item-list { list-style: none; margin: 0; padding: 0; }
.pc-item-card {
  padding: 0.85rem 1.25rem;
  border-top: 1px solid var(--pc-border);
}
.pc-item-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}
.pc-item-name { font-weight: 500; font-size: 0.92rem; color: var(--pc-text); }
.pc-item-total { font-weight: 700; font-size: 0.95rem; color: var(--pc-text); }
.pc-item-sub { font-size: 0.82rem; color: var(--pc-text-muted); }

/* Totals */
.pc-totals {
  margin-top: 0.5rem;
  padding: 1rem 1.5rem 1.25rem;
  background: var(--pc-surface-alt);
  border-top: 1px solid var(--pc-border);
}
.pc-totals-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.35rem 0;
  font-size: 0.92rem;
  max-width: 320px;
  margin-left: auto;
}
.pc-totals-row span { color: var(--pc-text-muted); }
.pc-totals-row strong { font-weight: 600; color: var(--pc-text); }
.pc-amount-neg { color: var(--pc-danger); }
.pc-totals-grand {
  margin-top: 0.35rem;
  padding-top: 0.6rem;
  border-top: 1px solid var(--pc-border);
  font-size: 1rem;
}
.pc-totals-grand span { color: var(--pc-text); font-weight: 600; }
.pc-totals-grand strong { font-size: 1.05rem; }
.pc-totals-due {
  margin-top: 0.35rem;
  padding-top: 0.75rem;
  border-top: 2px solid var(--pc-border);
  font-size: 1.05rem;
}
.pc-totals-due span { color: var(--pc-text); font-weight: 500; }
.pc-totals-due strong { color: var(--pc-danger); font-size: 1.15rem; }
.pc-item-sub-extras {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-top: 0.25rem;
}
.pc-item-sub-extras .pc-item-discount { color: var(--pc-danger); }

.pc-show-mobile { display: none; }
.pc-hide-mobile { display: block; }

@media (max-width: 768px) {
  .pc-detail-head { padding: 1.15rem 1.15rem; }
  .pc-detail-title { font-size: 1.25rem; }
  .pc-detail-stats { grid-template-columns: 1fr; }
  .pc-detail-stat { padding: 0.85rem 1.15rem; flex-direction: row; align-items: center; justify-content: space-between; gap: 0.5rem; }
  .pc-detail-stat-value { font-size: 1.05rem; }
  .pc-items-head { padding: 0.85rem 1.15rem 0.5rem; }
  .pc-totals { padding: 1rem 1.15rem; }
  .pc-totals-row { max-width: 100%; }
  .pc-show-mobile { display: block; }
  .pc-hide-mobile { display: none; }
  .pc-btn-primary { width: 100%; justify-content: center; }
}
</style>
