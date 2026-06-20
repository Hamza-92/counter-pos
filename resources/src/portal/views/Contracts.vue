<template>
  <div class="portal-page portal-contracts">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">Contracts</h1>
        <p class="pc-page-sub">View and download your contracts</p>
      </div>
    </header>

    <div class="pc-card">
      <div class="pc-toolbar">
        <div class="pc-search">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input v-model="search" type="text" placeholder="Search by number, subject or status..." @input="debounceFetch" />
        </div>
      </div>

      <div v-if="loading" class="pc-inline-loading">
        <div class="pc-spinner"></div><span>Loading contracts...</span>
      </div>

      <template v-else>
        <div v-if="!contracts.length" class="pc-empty">
          <div class="pc-empty-icon">📑</div>
          <p>No contracts available</p>
        </div>

        <div v-else class="pc-table-wrap pc-hide-mobile">
          <table class="pc-table">
            <thead>
              <tr><th>Number</th><th>Subject</th><th>Type</th><th>Period</th><th>Value</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="c in contracts" :key="c.id">
                <td><router-link :to="`/contracts/${c.id}`" class="pc-link">{{ c.contract_number }}</router-link></td>
                <td>{{ c.subject || '—' }}</td>
                <td>{{ c.type || '—' }}</td>
                <td><small>{{ c.start_date || '—' }} → {{ c.end_date || '—' }}</small></td>
                <td>{{ formatMoney(c.value) }}</td>
                <td><span :class="'pc-badge pc-badge-' + badgeClass(c.status)">{{ c.status || '—' }}</span></td>
                <td><router-link :to="`/contracts/${c.id}`" class="pc-chip">View</router-link></td>
              </tr>
            </tbody>
          </table>
        </div>

        <ul v-if="contracts.length" class="pc-mobile-list pc-show-mobile">
          <li v-for="c in contracts" :key="'m-' + c.id" class="pc-mobile-row">
            <router-link :to="`/contracts/${c.id}`" class="pc-mobile-row-main">
              <div class="pc-mobile-top">
                <strong class="pc-mobile-ref">{{ c.contract_number }}</strong>
                <span :class="'pc-badge pc-badge-' + badgeClass(c.status)">{{ c.status || '—' }}</span>
              </div>
              <div class="pc-mobile-mid">
                <span>{{ c.subject || '—' }}</span>
                <span class="pc-mobile-amount">{{ formatMoney(c.value) }}</span>
              </div>
              <div class="pc-mobile-foot">
                <small>{{ c.start_date || '—' }} → {{ c.end_date || '—' }}</small>
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
    return { contracts: [], search: '', page: 1, pageSize: 10, totalRows: 0, loading: false, debounce: null };
  },
  computed: { totalPages() { return Math.ceil(this.totalRows / this.pageSize) || 1; } },
  mounted() { this.fetch(); },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await axios.get('/portal/contracts', { params: { page: this.page, limit: this.pageSize, search: this.search || undefined } });
        this.contracts = data.contracts || [];
        this.totalRows = data.totalRows || 0;
      } catch (_) {}
      this.loading = false;
    },
    debounceFetch() {
      clearTimeout(this.debounce);
      this.debounce = setTimeout(() => { this.page = 1; this.fetch(); }, 300);
    },
    formatMoney(n) {
      if (n == null) return '—';
      return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    badgeClass(s) {
      const v = (s || '').toLowerCase();
      if (v === 'active' || v === 'signed') return 'paid';
      if (v === 'draft' || v === 'pending') return 'pending';
      if (v === 'expired' || v === 'cancelled' || v === 'terminated') return 'partial';
      return 'pending';
    },
  },
};
</script>

<style scoped>
.portal-contracts { padding-bottom: 1rem; }
.pc-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }
.pc-card { background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius); box-shadow: var(--pc-shadow-sm); overflow: hidden; }
.pc-toolbar { padding: 0.9rem 1.15rem; border-bottom: 1px solid var(--pc-border); }
.pc-search { position: relative; display: flex; align-items: center; }
.pc-search svg { position: absolute; left: 0.85rem; color: var(--pc-text-soft); pointer-events: none; }
.pc-search input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.3rem; border: 1px solid var(--pc-border-strong); border-radius: 10px; font-size: 0.92rem; background: var(--pc-surface-alt); box-sizing: border-box; }
.pc-search input:focus { outline: none; background: var(--pc-surface); border-color: var(--pc-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
.pc-inline-loading { display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 3rem; color: var(--pc-text-muted); }
.pc-spinner { width: 28px; height: 28px; border: 2px solid rgba(79, 70, 229, 0.15); border-top-color: var(--pc-primary); border-radius: 50%; animation: pc-spin 0.7s linear infinite; }
@keyframes pc-spin { to { transform: rotate(360deg); } }
.pc-empty { padding: 3rem 1rem; text-align: center; color: var(--pc-text-soft); }
.pc-empty-icon { font-size: 2.5rem; opacity: 0.7; margin-bottom: 0.5rem; }
.pc-table-wrap { overflow-x: auto; }
.pc-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.pc-table th, .pc-table td { padding: 0.7rem 1.15rem; text-align: left; border-bottom: 1px solid var(--pc-border); }
.pc-table th { background: var(--pc-surface-alt); font-weight: 600; color: var(--pc-text-muted); font-size: 0.73rem; text-transform: uppercase; letter-spacing: 0.04em; }
.pc-table tbody tr:last-child td { border-bottom: none; }
.pc-table tbody tr:hover { background: var(--pc-surface-alt); }
.pc-link { color: var(--pc-primary); text-decoration: none; font-weight: 500; }
.pc-link:hover { text-decoration: underline; }
.pc-chip { display: inline-flex; align-items: center; padding: 0.25rem 0.6rem; border: 1px solid var(--pc-border-strong); border-radius: 6px; font-size: 0.75rem; font-weight: 500; color: var(--pc-text-muted); text-decoration: none; background: #fff; }
.pc-chip:hover { border-color: var(--pc-primary); color: var(--pc-primary); }
.pc-badge { display: inline-block; padding: 0.22rem 0.55rem; border-radius: 999px; font-size: 0.72rem; font-weight: 600; text-transform: capitalize; }
.pc-badge-paid { background: var(--pc-success-bg); color: var(--pc-success); }
.pc-badge-pending { background: var(--pc-warning-bg); color: var(--pc-warning); }
.pc-badge-partial { background: #fee2e2; color: #b91c1c; }
.pc-mobile-list { list-style: none; margin: 0; padding: 0; }
.pc-mobile-row { border-bottom: 1px solid var(--pc-border); }
.pc-mobile-row-main { display: block; padding: 0.95rem 1.15rem; text-decoration: none; color: var(--pc-text); }
.pc-mobile-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; gap: 0.5rem; }
.pc-mobile-ref { font-weight: 600; font-size: 0.95rem; }
.pc-mobile-mid { display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; }
.pc-mobile-amount { font-weight: 600; }
.pc-mobile-foot { padding-top: 0.4rem; color: var(--pc-text-soft); font-size: 0.78rem; }
.pc-pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 0.85rem 1.15rem; border-top: 1px solid var(--pc-border); background: var(--pc-surface-alt); }
.pc-pg-btn { padding: 0.5rem 1rem; background: var(--pc-surface); border: 1px solid var(--pc-border-strong); border-radius: 8px; font-size: 0.88rem; font-weight: 500; color: var(--pc-text); cursor: pointer; }
.pc-pg-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.pc-pg-info { font-size: 0.85rem; color: var(--pc-text-muted); }
.pc-show-mobile { display: none; }
.pc-hide-mobile { display: block; }
@media (max-width: 768px) { .pc-page-title { font-size: 1.3rem; } .pc-show-mobile { display: block; } .pc-hide-mobile { display: none; } }
</style>
