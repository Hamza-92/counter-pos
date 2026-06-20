<template>
  <div class="portal-page portal-invoices">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">Invoices</h1>
        <p class="pc-page-sub">View and download your invoices</p>
      </div>
    </header>

    <div class="pc-card">
      <div class="pc-toolbar">
        <div class="pc-search">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input
            v-model="search"
            type="text"
            placeholder="Search by ref or date..."
            @input="debounceFetch"
          />
        </div>
      </div>

      <div v-if="loading" class="pc-inline-loading">
        <div class="pc-spinner"></div>
        <span>Loading invoices...</span>
      </div>

      <template v-else>
        <div v-if="!invoices.length" class="pc-empty">
          <div class="pc-empty-icon">📄</div>
          <p>No invoices found</p>
        </div>

        <!-- Desktop table -->
        <div v-else class="pc-table-wrap pc-hide-mobile">
          <table class="pc-table">
            <thead>
              <tr>
                <th>Ref</th>
                <th>Date</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Due</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="inv in invoices" :key="inv.id">
                <td><router-link :to="`/invoices/${inv.id}`" class="pc-link">{{ inv.Ref }}</router-link></td>
                <td>{{ inv.date }}</td>
                <td>{{ formatMoney(inv.GrandTotal) }}</td>
                <td>{{ formatMoney(inv.paid_amount) }}</td>
                <td><span :class="'pc-amount-due' + (Number(inv.due) > 0 ? ' has-due' : '')">{{ formatMoney(inv.due) }}</span></td>
                <td><span :class="'pc-badge pc-badge-' + badgeClass(inv.payment_status)">{{ inv.payment_status }}</span></td>
                <td><a :href="`/api/portal/invoices/${inv.id}/pdf`" target="_blank" rel="noopener" class="pc-chip">PDF</a></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile card list -->
        <ul v-if="invoices.length" class="pc-mobile-list pc-show-mobile">
          <li v-for="inv in invoices" :key="'m-' + inv.id" class="pc-mobile-row">
            <router-link :to="`/invoices/${inv.id}`" class="pc-mobile-row-main">
              <div class="pc-mobile-top">
                <strong class="pc-mobile-ref">{{ inv.Ref }}</strong>
                <span :class="'pc-badge pc-badge-' + badgeClass(inv.payment_status)">{{ inv.payment_status }}</span>
              </div>
              <div class="pc-mobile-mid">
                <span class="pc-mobile-muted">{{ inv.date }}</span>
                <span class="pc-mobile-amount">{{ formatMoney(inv.GrandTotal) }}</span>
              </div>
              <div class="pc-mobile-foot">
                <span class="pc-kv"><small>Paid</small>{{ formatMoney(inv.paid_amount) }}</span>
                <span class="pc-kv"><small>Due</small><strong :class="Number(inv.due) > 0 ? 'due' : ''">{{ formatMoney(inv.due) }}</strong></span>
                <a :href="`/api/portal/invoices/${inv.id}/pdf`" target="_blank" rel="noopener" class="pc-chip" @click.stop>PDF</a>
              </div>
            </router-link>
          </li>
        </ul>

        <div v-if="totalRows > pageSize" class="pc-pagination">
          <button type="button" class="pc-pg-btn" :disabled="page <= 1" @click="page--; fetch()">Previous</button>
          <span class="pc-pg-info">Page {{ page }} of {{ totalPages }}</span>
          <button type="button" class="pc-pg-btn" :disabled="page >= totalPages" @click="page++; fetch()">Next</button>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return { invoices: [], search: '', page: 1, pageSize: 10, totalRows: 0, loading: false, debounce: null };
  },
  computed: {
    totalPages() {
      return Math.ceil(this.totalRows / this.pageSize) || 1;
    },
  },
  mounted() {
    this.fetch();
  },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await axios.get('/portal/invoices', { params: { page: this.page, limit: this.pageSize, search: this.search || undefined } });
        this.invoices = data.invoices || [];
        this.totalRows = data.totalRows || 0;
      } catch (_) {}
      this.loading = false;
    },
    debounceFetch() {
      clearTimeout(this.debounce);
      this.debounce = setTimeout(() => { this.page = 1; this.fetch(); }, 300);
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
.portal-invoices { padding-bottom: 1rem; }

.pc-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; letter-spacing: -0.01em; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }

.pc-card {
  background: var(--pc-surface);
  border: 1px solid var(--pc-border);
  border-radius: var(--pc-radius);
  box-shadow: var(--pc-shadow-sm);
  overflow: hidden;
}
.pc-toolbar {
  padding: 0.9rem 1.15rem;
  border-bottom: 1px solid var(--pc-border);
  background: var(--pc-surface);
}
.pc-search {
  position: relative;
  display: flex;
  align-items: center;
}
.pc-search svg {
  position: absolute;
  left: 0.85rem;
  color: var(--pc-text-soft);
  pointer-events: none;
}
.pc-search input {
  width: 100%;
  padding: 0.6rem 1rem 0.6rem 2.3rem;
  border: 1px solid var(--pc-border-strong);
  border-radius: 10px;
  font-size: 0.92rem;
  background: var(--pc-surface-alt);
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
  box-sizing: border-box;
}
.pc-search input:focus {
  outline: none;
  background: var(--pc-surface);
  border-color: var(--pc-primary);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

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

.pc-empty {
  padding: 3rem 1rem;
  text-align: center;
  color: var(--pc-text-soft);
}
.pc-empty-icon { font-size: 2.5rem; opacity: 0.7; margin-bottom: 0.5rem; }
.pc-empty p { margin: 0; font-size: 0.95rem; }

.pc-table-wrap { overflow-x: auto; }
.pc-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.pc-table th, .pc-table td { padding: 0.7rem 1.15rem; text-align: left; border-bottom: 1px solid var(--pc-border); }
.pc-table th {
  background: var(--pc-surface-alt);
  font-weight: 600;
  color: var(--pc-text-muted);
  font-size: 0.73rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.pc-table tbody tr:last-child td { border-bottom: none; }
.pc-table tbody tr:hover { background: var(--pc-surface-alt); }

.pc-link { color: var(--pc-primary); text-decoration: none; font-weight: 500; }
.pc-link:hover { color: var(--pc-primary-600); text-decoration: underline; }

.pc-amount-due.has-due { color: var(--pc-danger); font-weight: 600; }

.pc-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.6rem;
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

/* Mobile list */
.pc-mobile-list { list-style: none; margin: 0; padding: 0; }
.pc-mobile-row { border-bottom: 1px solid var(--pc-border); }
.pc-mobile-row:last-child { border-bottom: none; }
.pc-mobile-row-main {
  display: block;
  padding: 0.95rem 1.15rem;
  text-decoration: none;
  color: var(--pc-text);
}
.pc-mobile-top {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 0.35rem; gap: 0.5rem;
}
.pc-mobile-ref { font-weight: 600; font-size: 0.95rem; }
.pc-mobile-mid {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 0.88rem; margin-bottom: 0.55rem;
}
.pc-mobile-muted { color: var(--pc-text-muted); }
.pc-mobile-amount { font-weight: 600; font-size: 0.98rem; }
.pc-mobile-foot {
  display: flex; align-items: center; gap: 0.9rem; flex-wrap: wrap;
  padding-top: 0.5rem;
  border-top: 1px dashed var(--pc-border);
}
.pc-kv { display: inline-flex; flex-direction: column; gap: 0.1rem; font-size: 0.82rem; }
.pc-kv small {
  color: var(--pc-text-soft); font-size: 0.65rem;
  text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;
}
.pc-kv strong { font-weight: 600; color: var(--pc-text); }
.pc-kv strong.due { color: var(--pc-danger); }
.pc-mobile-foot .pc-chip { margin-left: auto; }

.pc-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 0.85rem 1.15rem;
  border-top: 1px solid var(--pc-border);
  background: var(--pc-surface-alt);
}
.pc-pg-btn {
  padding: 0.5rem 1rem;
  background: var(--pc-surface);
  border: 1px solid var(--pc-border-strong);
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 500;
  color: var(--pc-text);
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.pc-pg-btn:hover:not(:disabled) { background: var(--pc-surface-alt); border-color: var(--pc-primary); color: var(--pc-primary); }
.pc-pg-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.pc-pg-info { font-size: 0.85rem; color: var(--pc-text-muted); }

.pc-show-mobile { display: none; }
.pc-hide-mobile { display: block; }

@media (max-width: 768px) {
  .pc-page-title { font-size: 1.3rem; }
  .pc-show-mobile { display: block; }
  .pc-hide-mobile { display: none; }
  .pc-pagination { flex-wrap: wrap; gap: 0.5rem; padding: 0.75rem; }
  .pc-pg-info { width: 100%; text-align: center; order: -1; }
}
</style>
