<template>
  <div class="portal-page portal-contract-detail">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">{{ contract.contract_number || 'Contract' }}</h1>
        <p class="pc-page-sub" v-if="contract.subject">{{ contract.subject }}</p>
      </div>
      <router-link to="/contracts" class="pc-link-back">&larr; Back</router-link>
    </header>

    <div v-if="loading" class="pc-inline-loading">
      <div class="pc-spinner"></div><span>Loading...</span>
    </div>

    <div v-else class="pc-card pc-detail">
      <div class="pc-detail-header">
        <span :class="'pc-badge pc-badge-' + badgeClass(contract.status)">{{ contract.status || '—' }}</span>
        <div class="pc-amount" v-if="contract.value">{{ formatMoney(contract.value) }}</div>
      </div>

      <dl class="pc-meta-grid">
        <div><dt>Type</dt><dd>{{ contract.type || '—' }}</dd></div>
        <div><dt>Start date</dt><dd>{{ contract.start_date || '—' }}</dd></div>
        <div><dt>End date</dt><dd>{{ contract.end_date || '—' }}</dd></div>
        <div><dt>Signed</dt><dd>{{ contract.signed_at ? formatDate(contract.signed_at) : 'Not signed' }}</dd></div>
        <div v-if="contract.signer_name"><dt>Signer</dt><dd>{{ contract.signer_name }}</dd></div>
      </dl>

      <div v-if="contract.description" class="pc-block">
        <h3>Description</h3>
        <div class="pc-html" v-html="contract.description"></div>
      </div>

      <div v-if="contract.attachments && contract.attachments.length" class="pc-block">
        <h3>Attachments</h3>
        <ul class="pc-attachments">
          <li v-for="a in contract.attachments" :key="a.id" class="pc-attachment">
            <span class="pc-attachment-icon">📎</span>
            <span class="pc-attachment-name">{{ a.file_name }}</span>
            <a :href="downloadUrl(a.id)" target="_blank" rel="noopener" class="pc-chip">Download</a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() { return { contract: {}, loading: false }; },
  mounted() { this.fetch(); },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await axios.get(`/portal/contracts/${this.$route.params.id}`);
        this.contract = data || {};
      } catch (_) {}
      this.loading = false;
    },
    downloadUrl(attachmentId) {
      return `/api/portal/contracts/${this.$route.params.id}/attachments/${attachmentId}/download`;
    },
    formatMoney(n) {
      if (n == null) return '—';
      return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    formatDate(iso) {
      if (!iso) return '—';
      try { return new Date(iso).toLocaleString(); } catch (_) { return iso; }
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
.portal-contract-detail { padding-bottom: 1rem; }
.pc-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap; }
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }
.pc-link-back { color: var(--pc-text-muted); text-decoration: none; font-size: 0.88rem; }
.pc-link-back:hover { color: var(--pc-primary); }
.pc-card { background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius); box-shadow: var(--pc-shadow-sm); padding: 1.5rem; }
.pc-detail-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 1rem; border-bottom: 1px solid var(--pc-border); margin-bottom: 1.1rem; }
.pc-amount { font-size: 1.4rem; font-weight: 700; }
.pc-badge { display: inline-block; padding: 0.3rem 0.7rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; text-transform: capitalize; }
.pc-badge-paid { background: var(--pc-success-bg); color: var(--pc-success); }
.pc-badge-pending { background: var(--pc-warning-bg); color: var(--pc-warning); }
.pc-badge-partial { background: #fee2e2; color: #b91c1c; }
.pc-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem 1.5rem; margin: 0 0 1.2rem; }
.pc-meta-grid div { display: flex; flex-direction: column; }
.pc-meta-grid dt { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--pc-text-soft); font-weight: 600; }
.pc-meta-grid dd { margin: 0.15rem 0 0; font-size: 0.95rem; color: var(--pc-text); font-weight: 500; }
.pc-block { margin-top: 1rem; }
.pc-block h3 { font-size: 0.95rem; margin: 0 0 0.55rem; color: var(--pc-text); }
.pc-html { padding: 0.85rem 1rem; background: var(--pc-surface-alt); border-radius: 10px; font-size: 0.9rem; line-height: 1.55; color: var(--pc-text); }
.pc-html >>> p { margin: 0 0 0.6rem; }
.pc-attachments { list-style: none; margin: 0; padding: 0; }
.pc-attachment { display: flex; align-items: center; gap: 0.7rem; padding: 0.55rem 0.85rem; border: 1px solid var(--pc-border); border-radius: 10px; margin-bottom: 0.45rem; background: var(--pc-surface-alt); }
.pc-attachment-icon { font-size: 1.1rem; }
.pc-attachment-name { flex: 1; font-size: 0.9rem; color: var(--pc-text); word-break: break-all; }
.pc-chip { display: inline-flex; align-items: center; padding: 0.3rem 0.75rem; border: 1px solid var(--pc-border-strong); border-radius: 6px; font-size: 0.78rem; font-weight: 500; color: var(--pc-primary); text-decoration: none; background: #fff; }
.pc-chip:hover { background: var(--pc-primary); color: #fff; border-color: var(--pc-primary); }
.pc-inline-loading { display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 3rem; color: var(--pc-text-muted); }
.pc-spinner { width: 28px; height: 28px; border: 2px solid rgba(79, 70, 229, 0.15); border-top-color: var(--pc-primary); border-radius: 50%; animation: pc-spin 0.7s linear infinite; }
@keyframes pc-spin { to { transform: rotate(360deg); } }
</style>
