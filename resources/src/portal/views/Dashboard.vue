<template>
  <div class="portal-dashboard portal-page">
    <div v-if="loading" class="pc-page-loading">
      <div class="pc-spinner"></div>
      <p>Loading your dashboard...</p>
    </div>

    <template v-else>
      <!-- Greeting -->
      <header class="pc-hero">
        <div>
          <p class="pc-eyebrow">{{ greeting }}</p>
          <h1 class="pc-hero-title">Welcome back<span v-if="clientName">, {{ firstName }}</span></h1>
          <p class="pc-hero-sub">Here’s a quick overview of your account.</p>
        </div>
        <router-link to="/invoices" class="pc-hero-cta">
          <span>View invoices</span>
          <svg viewBox="0 0 20 20" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 10h10M11 5l5 5-5 5"/></svg>
        </router-link>
      </header>

      <!-- Stats -->
      <section class="pc-stats">
        <div class="pc-stat pc-stat-blue">
          <div class="pc-stat-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v6h6"/></svg>
          </div>
          <div class="pc-stat-body">
            <span class="pc-stat-label">Total Invoices</span>
            <span class="pc-stat-value">{{ stats.total_invoices || 0 }}</span>
          </div>
        </div>
        <div class="pc-stat pc-stat-amber">
          <div class="pc-stat-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 10h5a1.5 1.5 0 010 3h-4a1.5 1.5 0 000 3h5"/></svg>
          </div>
          <div class="pc-stat-body">
            <span class="pc-stat-label">Total Amount</span>
            <span class="pc-stat-value">{{ formatMoney(stats.total_amount) }}</span>
          </div>
        </div>
        <div class="pc-stat pc-stat-green">
          <div class="pc-stat-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
          </div>
          <div class="pc-stat-body">
            <span class="pc-stat-label">Total Paid</span>
            <span class="pc-stat-value">{{ formatMoney(stats.total_paid) }}</span>
          </div>
        </div>
        <div class="pc-stat pc-stat-red pc-stat-highlight">
          <div class="pc-stat-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
          </div>
          <div class="pc-stat-body">
            <span class="pc-stat-label">Amount Due</span>
            <span class="pc-stat-value">{{ formatMoney(stats.total_due) }}</span>
            <small v-if="Number(stats.opening_balance) > 0" class="pc-stat-hint">
              Sales {{ formatMoney(stats.sales_due) }} + Opening {{ formatMoney(stats.opening_balance) }}
            </small>
          </div>
        </div>
      </section>

      <!-- Quick Actions -->
      <section class="pc-section">
        <h2 class="pc-section-title">Quick actions</h2>
        <div class="pc-quick-grid">
          <router-link to="/invoices" class="pc-quick">
            <span class="pc-quick-icon pc-quick-blue">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v6h6"/></svg>
            </span>
            <span class="pc-quick-text">
              <strong>Invoices</strong>
              <small>View all invoices</small>
            </span>
          </router-link>
          <router-link to="/payments" class="pc-quick">
            <span class="pc-quick-icon pc-quick-green">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 11h20"/></svg>
            </span>
            <span class="pc-quick-text">
              <strong>Payments</strong>
              <small>Payment history</small>
            </span>
          </router-link>
          <router-link to="/statement" class="pc-quick">
            <span class="pc-quick-icon pc-quick-violet">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/></svg>
            </span>
            <span class="pc-quick-text">
              <strong>Statement</strong>
              <small>Account activity</small>
            </span>
          </router-link>
        </div>
      </section>

      <!-- Recent Invoices + Payments -->
      <div class="pc-two-col">
        <section class="pc-card">
          <div class="pc-card-head">
            <h2 class="pc-card-title">Recent invoices</h2>
            <router-link to="/invoices" class="pc-link">View all →</router-link>
          </div>
          <div class="pc-card-body pc-list-body">
            <div v-if="!recentInvoices.length" class="pc-empty">
              <div class="pc-empty-icon">📄</div>
              <p>No invoices yet</p>
            </div>

            <!-- Desktop table -->
            <div v-else class="pc-table-wrap pc-hide-mobile">
              <table class="pc-table">
                <thead>
                  <tr>
                    <th>Ref</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="inv in recentInvoices" :key="inv.id">
                    <td><router-link :to="`/invoices/${inv.id}`" class="pc-link">{{ inv.Ref }}</router-link></td>
                    <td>{{ inv.date }}</td>
                    <td>{{ formatMoney(inv.GrandTotal) }}</td>
                    <td>{{ formatMoney(inv.due) }}</td>
                    <td><span :class="'pc-badge pc-badge-' + badgeClass(inv.payment_status)">{{ inv.payment_status }}</span></td>
                    <td><a :href="`/api/portal/invoices/${inv.id}/pdf`" target="_blank" rel="noopener" class="pc-chip">PDF</a></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Mobile list -->
            <ul class="pc-mobile-list pc-show-mobile">
              <li v-for="inv in recentInvoices" :key="'m-' + inv.id" class="pc-mobile-row">
                <router-link :to="`/invoices/${inv.id}`" class="pc-mobile-row-main">
                  <div class="pc-mobile-top">
                    <strong class="pc-mobile-ref">{{ inv.Ref }}</strong>
                    <span :class="'pc-badge pc-badge-' + badgeClass(inv.payment_status)">{{ inv.payment_status }}</span>
                  </div>
                  <div class="pc-mobile-mid">
                    <span class="pc-mobile-muted">{{ inv.date }}</span>
                    <span class="pc-mobile-amount">{{ formatMoney(inv.GrandTotal) }}</span>
                  </div>
                  <div v-if="Number(inv.due) > 0" class="pc-mobile-due">Due {{ formatMoney(inv.due) }}</div>
                </router-link>
              </li>
            </ul>
          </div>
        </section>

        <section class="pc-card">
          <div class="pc-card-head">
            <h2 class="pc-card-title">Recent payments</h2>
            <router-link to="/payments" class="pc-link">View all →</router-link>
          </div>
          <div class="pc-card-body pc-list-body">
            <div v-if="!recentPayments.length" class="pc-empty">
              <div class="pc-empty-icon">💳</div>
              <p>No payments yet</p>
            </div>

            <div v-else class="pc-table-wrap pc-hide-mobile">
              <table class="pc-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Ref</th>
                    <th>Invoice</th>
                    <th>Method</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="pay in recentPayments" :key="pay.id + '-' + (pay.payment_type || '')">
                    <td>{{ pay.date }}</td>
                    <td>{{ pay.Ref }}</td>
                    <td>{{ pay.Sale_Ref || '—' }}</td>
                    <td>{{ pay.payment_method || '—' }}</td>
                    <td class="pc-amount-pos">{{ formatMoney(pay.montant) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <ul class="pc-mobile-list pc-show-mobile">
              <li v-for="pay in recentPayments" :key="'pm-' + pay.id + '-' + (pay.payment_type || '')" class="pc-mobile-row">
                <div class="pc-mobile-row-main">
                  <div class="pc-mobile-top">
                    <strong class="pc-mobile-ref">{{ pay.Ref }}</strong>
                    <span class="pc-mobile-amount pc-amount-pos">{{ formatMoney(pay.montant) }}</span>
                  </div>
                  <div class="pc-mobile-mid">
                    <span class="pc-mobile-muted">{{ pay.date }} · {{ pay.payment_method || '—' }}</span>
                    <span class="pc-mobile-muted pc-mobile-right">{{ pay.Sale_Ref || '—' }}</span>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: true,
      stats: {},
      recentInvoices: [],
      recentPayments: [],
      clientName: '',
    };
  },
  computed: {
    greeting() {
      const h = new Date().getHours();
      if (h < 12) return 'Good morning';
      if (h < 18) return 'Good afternoon';
      return 'Good evening';
    },
    firstName() {
      if (!this.clientName) return '';
      return this.clientName.split(/\s+/)[0];
    },
  },
  mounted() {
    this.fetch();
  },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const [dashboardRes, paymentsRes, meRes] = await Promise.all([
          axios.get('/portal/dashboard'),
          axios.get('/portal/payments', { params: { limit: 5, page: 1 } }),
          axios.get('/portal/me').catch(() => ({ data: null })),
        ]);
        const data = dashboardRes.data;
        this.stats = data;
        this.recentInvoices = data.recent_invoices || [];
        this.recentPayments = (paymentsRes.data && paymentsRes.data.payments) || [];
        const me = meRes && meRes.data;
        this.clientName = (me && me.portal_client && me.portal_client.client && me.portal_client.client.name) || '';
      } catch (_) {
        this.stats = {};
        this.recentInvoices = [];
        this.recentPayments = [];
      }
      this.loading = false;
    },
    formatMoney(n) {
      if (n == null) return '0.00';
      return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    badgeClass(status) {
      if (!status) return 'pending';
      const s = (status + '').toLowerCase();
      if (s === 'paid' || s === 'completed') return 'paid';
      if (s === 'partial') return 'partial';
      return 'pending';
    },
  },
};
</script>

<style scoped>
.portal-dashboard { padding-bottom: 1rem; }

/* Page loading */
.pc-page-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 40vh;
  color: var(--pc-text-muted);
  gap: 1rem;
}
.pc-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid rgba(79, 70, 229, 0.15);
  border-top-color: var(--pc-primary);
  border-radius: 50%;
  animation: pc-spin 0.8s linear infinite;
}
@keyframes pc-spin { to { transform: rotate(360deg); } }

/* Hero */
.pc-hero {
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 45%, #7c3aed 100%);
  color: #fff;
  border-radius: var(--pc-radius-lg);
  padding: 1.75rem;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  box-shadow: 0 18px 40px -18px rgba(79, 70, 229, 0.5);
  position: relative;
  overflow: hidden;
}
.pc-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(600px circle at 100% 0%, rgba(255,255,255,0.18), transparent 60%);
  pointer-events: none;
}
.pc-eyebrow {
  margin: 0 0 0.2rem;
  font-size: 0.82rem;
  font-weight: 500;
  opacity: 0.85;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.pc-hero-title {
  margin: 0 0 0.35rem;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.01em;
}
.pc-hero-sub { margin: 0; opacity: 0.9; font-size: 0.95rem; }
.pc-hero-cta {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.35);
  padding: 0.55rem 1rem;
  border-radius: 999px;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.9rem;
  backdrop-filter: blur(8px);
  transition: background 0.15s, transform 0.15s;
  flex-shrink: 0;
  position: relative;
}
.pc-hero-cta:hover { background: rgba(255, 255, 255, 0.28); transform: translateY(-1px); }

/* Stats */
.pc-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.9rem;
  margin-bottom: 1.75rem;
}
.pc-stat {
  background: var(--pc-surface);
  border: 1px solid var(--pc-border);
  border-radius: var(--pc-radius);
  padding: 1rem 1.1rem;
  display: flex;
  align-items: center;
  gap: 0.9rem;
  box-shadow: var(--pc-shadow-sm);
  transition: transform 0.15s, box-shadow 0.15s;
}
.pc-stat:hover { transform: translateY(-1px); box-shadow: var(--pc-shadow); }
.pc-stat-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pc-stat-blue .pc-stat-icon { background: #dbeafe; color: #1d4ed8; }
.pc-stat-amber .pc-stat-icon { background: #fef3c7; color: #b45309; }
.pc-stat-green .pc-stat-icon { background: #d1fae5; color: #047857; }
.pc-stat-red .pc-stat-icon { background: #fee2e2; color: #b91c1c; }
.pc-stat-highlight {
  background: linear-gradient(180deg, #fff 0%, #fff7f7 100%);
  border-color: #fecaca;
}
.pc-stat-body { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.pc-stat-label { font-size: 0.78rem; color: var(--pc-text-muted); font-weight: 500; }
.pc-stat-value {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--pc-text);
  letter-spacing: -0.01em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pc-stat-hint {
  font-size: 0.7rem;
  color: var(--pc-text-muted);
  font-weight: 500;
  margin-top: 0.1rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Quick actions */
.pc-section { margin-bottom: 1.75rem; }
.pc-section-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--pc-text);
  margin: 0 0 0.75rem;
  letter-spacing: -0.005em;
}
.pc-quick-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.85rem;
}
.pc-quick {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.95rem 1rem;
  background: var(--pc-surface);
  border: 1px solid var(--pc-border);
  border-radius: var(--pc-radius);
  text-decoration: none;
  color: var(--pc-text);
  transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
  box-shadow: var(--pc-shadow-sm);
}
.pc-quick:hover {
  transform: translateY(-1px);
  box-shadow: var(--pc-shadow);
  border-color: var(--pc-border-strong);
}
.pc-quick-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pc-quick-blue { background: #dbeafe; color: #1d4ed8; }
.pc-quick-green { background: #d1fae5; color: #047857; }
.pc-quick-violet { background: #ede9fe; color: #6d28d9; }
.pc-quick-text { display: flex; flex-direction: column; gap: 0.1rem; }
.pc-quick-text strong { font-weight: 600; font-size: 0.92rem; }
.pc-quick-text small { color: var(--pc-text-muted); font-size: 0.8rem; }

/* Two columns */
.pc-two-col {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}
.pc-card {
  background: var(--pc-surface);
  border: 1px solid var(--pc-border);
  border-radius: var(--pc-radius);
  box-shadow: var(--pc-shadow-sm);
  overflow: hidden;
}
.pc-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.95rem 1.15rem;
  border-bottom: 1px solid var(--pc-border);
}
.pc-card-title { font-size: 0.95rem; font-weight: 600; color: var(--pc-text); margin: 0; }
.pc-link {
  color: var(--pc-primary);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
}
.pc-link:hover { color: var(--pc-primary-600); }
.pc-card-body { padding: 0; }

/* Table */
.pc-table-wrap { overflow-x: auto; }
.pc-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.pc-table th, .pc-table td { padding: 0.7rem 1.15rem; text-align: left; border-bottom: 1px solid var(--pc-border); }
.pc-table th {
  background: var(--pc-surface-alt);
  font-weight: 600;
  color: var(--pc-text-muted);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.pc-table tbody tr:last-child td { border-bottom: none; }
.pc-table tbody tr:hover { background: var(--pc-surface-alt); }

.pc-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.55rem;
  border: 1px solid var(--pc-border-strong);
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--pc-text-muted);
  text-decoration: none;
  background: #fff;
  transition: border-color 0.15s, color 0.15s;
}
.pc-chip:hover { border-color: var(--pc-primary); color: var(--pc-primary); }

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

.pc-amount-pos { color: var(--pc-success); font-weight: 600; }

/* Empty */
.pc-empty {
  padding: 2rem 1rem;
  text-align: center;
  color: var(--pc-text-soft);
}
.pc-empty-icon {
  font-size: 2.25rem;
  opacity: 0.75;
  margin-bottom: 0.5rem;
}
.pc-empty p { margin: 0; font-size: 0.9rem; }

/* Mobile list */
.pc-mobile-list { list-style: none; margin: 0; padding: 0; }
.pc-mobile-row { border-bottom: 1px solid var(--pc-border); }
.pc-mobile-row:last-child { border-bottom: none; }
.pc-mobile-row-main {
  display: block;
  padding: 0.85rem 1.15rem;
  text-decoration: none;
  color: var(--pc-text);
  transition: background 0.15s;
}
.pc-mobile-row-main:hover, .pc-mobile-row-main:active { background: var(--pc-surface-alt); }
.pc-mobile-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.3rem;
}
.pc-mobile-ref { font-weight: 600; font-size: 0.92rem; }
.pc-mobile-mid {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}
.pc-mobile-muted { color: var(--pc-text-muted); }
.pc-mobile-right { text-align: right; }
.pc-mobile-amount { font-weight: 600; font-size: 0.95rem; }
.pc-mobile-due {
  margin-top: 0.3rem;
  display: inline-block;
  font-size: 0.78rem;
  color: var(--pc-danger);
  font-weight: 500;
}

/* Responsive visibility */
.pc-show-mobile { display: none; }
.pc-hide-mobile { display: block; }

/* Tablet */
@media (max-width: 1024px) {
  .pc-stats { grid-template-columns: repeat(2, 1fr); }
}

/* Mobile */
@media (max-width: 768px) {
  .pc-hero { padding: 1.35rem; flex-direction: column; align-items: flex-start; }
  .pc-hero-title { font-size: 1.4rem; }
  .pc-hero-sub { font-size: 0.9rem; }
  .pc-two-col { grid-template-columns: 1fr; gap: 1rem; }
  .pc-quick-grid { grid-template-columns: 1fr; }
  .pc-show-mobile { display: block; }
  .pc-hide-mobile { display: none; }
}

@media (max-width: 480px) {
  .pc-stats { grid-template-columns: 1fr; }
  .pc-stat-value { font-size: 1.15rem; }
  .pc-hero { padding: 1.15rem; }
}
</style>
