<template>
  <div class="portal-page portal-appointments">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">Appointments</h1>
        <p class="pc-page-sub">View and book appointments with our team</p>
      </div>
      <router-link to="/appointments/new" class="pc-btn pc-btn-primary">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        Book appointment
      </router-link>
    </header>

    <div class="pc-card">
      <div class="pc-toolbar">
        <div class="pc-search">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input v-model="search" type="text" placeholder="Search by ref, item or status..." @input="debounceFetch" />
        </div>
      </div>

      <div v-if="loading" class="pc-inline-loading">
        <div class="pc-spinner"></div><span>Loading...</span>
      </div>

      <template v-else>
        <div v-if="!appointments.length" class="pc-empty">
          <div class="pc-empty-icon">📅</div>
          <p>No appointments yet</p>
        </div>

        <div v-else class="pc-table-wrap pc-hide-mobile">
          <table class="pc-table">
            <thead>
              <tr><th>Ref</th><th>Service</th><th>When</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="a in appointments" :key="a.id">
                <td><router-link :to="`/appointments/${a.id}`" class="pc-link">{{ a.Ref }}</router-link></td>
                <td>{{ a.service_item }}</td>
                <td>{{ a.scheduled_date || '—' }}</td>
                <td><span :class="'pc-badge pc-badge-' + badgeClass(a.status)">{{ a.status }}</span></td>
                <td><router-link :to="`/appointments/${a.id}`" class="pc-chip">View</router-link></td>
              </tr>
            </tbody>
          </table>
        </div>

        <ul v-if="appointments.length" class="pc-mobile-list pc-show-mobile">
          <li v-for="a in appointments" :key="'m-' + a.id" class="pc-mobile-row">
            <router-link :to="`/appointments/${a.id}`" class="pc-mobile-row-main">
              <div class="pc-mobile-top">
                <strong class="pc-mobile-ref">{{ a.Ref }}</strong>
                <span :class="'pc-badge pc-badge-' + badgeClass(a.status)">{{ a.status }}</span>
              </div>
              <div class="pc-mobile-mid">
                <span class="pc-mobile-muted">{{ a.scheduled_date || '—' }}</span>
                <span>{{ a.service_item }}</span>
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
    return { appointments: [], search: '', page: 1, pageSize: 10, totalRows: 0, loading: false, debounce: null };
  },
  computed: { totalPages() { return Math.ceil(this.totalRows / this.pageSize) || 1; } },
  mounted() { this.fetch(); },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await axios.get('/portal/appointments', { params: { page: this.page, limit: this.pageSize, search: this.search || undefined } });
        this.appointments = data.appointments || [];
        this.totalRows = data.totalRows || 0;
      } catch (_) {}
      this.loading = false;
    },
    debounceFetch() {
      clearTimeout(this.debounce);
      this.debounce = setTimeout(() => { this.page = 1; this.fetch(); }, 300);
    },
    badgeClass(s) {
      const v = (s || '').toLowerCase();
      if (v === 'completed' || v === 'delivered' || v === 'ready') return 'paid';
      if (v === 'pending' || v === 'intake' || v === 'diagnostic' || v === 'quoted') return 'pending';
      if (v === 'in_progress' || v === 'approved') return 'progress';
      if (v === 'cancelled' || v === 'declined') return 'partial';
      return 'pending';
    },
  },
};
</script>

<style scoped>
.portal-appointments { padding-bottom: 1rem; }
.pc-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap; }
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }
.pc-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 0.95rem; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
.pc-btn-primary { background: var(--pc-primary); color: #fff; }
.pc-btn-primary:hover { background: var(--pc-primary-600); }
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
.pc-badge-progress { background: #dbeafe; color: #1d4ed8; }
.pc-badge-partial { background: #fee2e2; color: #b91c1c; }
.pc-mobile-list { list-style: none; margin: 0; padding: 0; }
.pc-mobile-row { border-bottom: 1px solid var(--pc-border); }
.pc-mobile-row-main { display: block; padding: 0.95rem 1.15rem; text-decoration: none; color: var(--pc-text); }
.pc-mobile-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; gap: 0.5rem; }
.pc-mobile-ref { font-weight: 600; font-size: 0.95rem; }
.pc-mobile-mid { display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; }
.pc-mobile-muted { color: var(--pc-text-muted); }
.pc-pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 0.85rem 1.15rem; border-top: 1px solid var(--pc-border); background: var(--pc-surface-alt); }
.pc-pg-btn { padding: 0.5rem 1rem; background: var(--pc-surface); border: 1px solid var(--pc-border-strong); border-radius: 8px; font-size: 0.88rem; font-weight: 500; color: var(--pc-text); cursor: pointer; }
.pc-pg-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.pc-pg-info { font-size: 0.85rem; color: var(--pc-text-muted); }
.pc-show-mobile { display: none; }
.pc-hide-mobile { display: block; }
@media (max-width: 768px) { .pc-page-title { font-size: 1.3rem; } .pc-show-mobile { display: block; } .pc-hide-mobile { display: none; } }
</style>
