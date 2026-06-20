<template>
  <div class="portal-page portal-quotation-detail">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">Quotation {{ quotation.Ref || '' }}</h1>
        <p class="pc-page-sub" v-if="quotation.date">Created {{ quotation.date }}</p>
      </div>
      <router-link to="/quotations" class="pc-link-back">&larr; Back to quotations</router-link>
    </header>

    <div v-if="loading" class="pc-inline-loading">
      <div class="pc-spinner"></div><span>Loading...</span>
    </div>

    <div v-else class="pc-card pc-detail">
      <div class="pc-detail-header">
        <span :class="'pc-badge pc-badge-' + badgeClass(quotation.statut)">{{ quotation.statut }}</span>
        <div class="pc-grand">{{ formatMoney(quotation.GrandTotal) }}</div>
      </div>

      <dl class="pc-meta-grid">
        <div><dt>Warehouse</dt><dd>{{ quotation.warehouse_name || '—' }}</dd></div>
        <div><dt>Discount</dt><dd>{{ formatMoney(quotation.discount) }}</dd></div>
        <div><dt>Shipping</dt><dd>{{ formatMoney(quotation.shipping) }}</dd></div>
        <div><dt>Tax</dt><dd>{{ formatMoney(quotation.TaxNet) }} ({{ quotation.tax_rate || 0 }}%)</dd></div>
      </dl>

      <div v-if="quotation.notes" class="pc-notes">
        <h3>Notes</h3>
        <pre>{{ quotation.notes }}</pre>
      </div>

      <div v-if="quotation.details && quotation.details.length" class="pc-table-wrap">
        <h3>Items</h3>
        <table class="pc-table">
          <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
          <tbody>
            <tr v-for="(d, i) in quotation.details" :key="i">
              <td>{{ d.product_name || '—' }}</td>
              <td>{{ d.quantity }}</td>
              <td>{{ formatMoney(d.price) }}</td>
              <td>{{ formatMoney(d.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return { quotation: {}, loading: false };
  },
  mounted() { this.fetch(); },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await axios.get(`/portal/quotations/${this.$route.params.id}`);
        this.quotation = data || {};
      } catch (_) {}
      this.loading = false;
    },
    formatMoney(n) {
      if (n == null) return '0.00';
      return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    badgeClass(s) {
      const v = (s || '').toLowerCase();
      if (v === 'approved' || v === 'accepted' || v === 'completed') return 'paid';
      if (v === 'pending' || v === 'requested') return 'pending';
      if (v === 'rejected' || v === 'cancelled') return 'partial';
      return 'pending';
    },
  },
};
</script>

<style scoped>
.portal-quotation-detail { padding-bottom: 1rem; }
.pc-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap; }
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }
.pc-link-back { color: var(--pc-text-muted); text-decoration: none; font-size: 0.88rem; }
.pc-link-back:hover { color: var(--pc-primary); }
.pc-card { background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius); box-shadow: var(--pc-shadow-sm); padding: 1.5rem; }
.pc-detail-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 1rem; border-bottom: 1px solid var(--pc-border); margin-bottom: 1.1rem; }
.pc-grand { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); }
.pc-badge { display: inline-block; padding: 0.3rem 0.7rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; text-transform: capitalize; }
.pc-badge-paid { background: var(--pc-success-bg); color: var(--pc-success); }
.pc-badge-pending { background: var(--pc-warning-bg); color: var(--pc-warning); }
.pc-badge-partial { background: #fee2e2; color: #b91c1c; }
.pc-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.85rem 1.5rem; margin: 0 0 1.2rem; }
.pc-meta-grid div { display: flex; flex-direction: column; }
.pc-meta-grid dt { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--pc-text-soft); font-weight: 600; }
.pc-meta-grid dd { margin: 0.15rem 0 0; font-size: 0.95rem; color: var(--pc-text); font-weight: 500; }
.pc-notes h3, .pc-table-wrap h3 { font-size: 0.95rem; margin: 0 0 0.55rem; color: var(--pc-text); }
.pc-notes pre { background: var(--pc-surface-alt); padding: 0.85rem 1rem; border-radius: 10px; font-family: inherit; white-space: pre-wrap; font-size: 0.9rem; color: var(--pc-text); margin: 0 0 1.2rem; }
.pc-table-wrap { overflow-x: auto; }
.pc-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.pc-table th, .pc-table td { padding: 0.6rem 0.75rem; text-align: left; border-bottom: 1px solid var(--pc-border); }
.pc-table th { background: var(--pc-surface-alt); font-weight: 600; color: var(--pc-text-muted); font-size: 0.73rem; text-transform: uppercase; }
.pc-inline-loading { display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 3rem; color: var(--pc-text-muted); }
.pc-spinner { width: 28px; height: 28px; border: 2px solid rgba(79, 70, 229, 0.15); border-top-color: var(--pc-primary); border-radius: 50%; animation: pc-spin 0.7s linear infinite; }
@keyframes pc-spin { to { transform: rotate(360deg); } }
</style>
