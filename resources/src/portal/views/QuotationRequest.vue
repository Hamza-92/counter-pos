<template>
  <div class="portal-page portal-quotation-request">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">Request a quotation</h1>
        <p class="pc-page-sub">Tell us what you need and our team will respond shortly.</p>
      </div>
      <router-link to="/quotations" class="pc-link-back">&larr; Back to quotations</router-link>
    </header>

    <form class="pc-card pc-form" @submit.prevent="submit">
      <div class="pc-form-row">
        <label>Subject <span class="pc-muted">(optional)</span></label>
        <input v-model="form.subject" type="text" maxlength="190" placeholder="e.g. Office furniture for new branch" />
      </div>

      <div class="pc-form-row">
        <label>Details <span class="pc-required">*</span></label>
        <textarea v-model="form.notes" rows="5" required maxlength="5000" placeholder="Describe what you need, expected timing, delivery location, etc."></textarea>
      </div>

      <div class="pc-form-row">
        <label>Items <span class="pc-muted">(optional)</span></label>
        <div v-for="(item, i) in form.items" :key="i" class="pc-item-row">
          <input v-model="item.description" type="text" placeholder="Item description" />
          <input v-model.number="item.quantity" type="number" min="0" step="0.01" placeholder="Qty" class="pc-qty-input" />
          <button type="button" class="pc-icon-btn" @click="removeItem(i)" title="Remove">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
          </button>
        </div>
        <button type="button" class="pc-btn pc-btn-ghost" @click="addItem">+ Add item</button>
      </div>

      <div v-if="error" class="pc-alert pc-alert-error">{{ error }}</div>

      <div class="pc-form-actions">
        <router-link to="/quotations" class="pc-btn pc-btn-ghost">Cancel</router-link>
        <button type="submit" class="pc-btn pc-btn-primary" :disabled="submitting">
          <span v-if="submitting">Submitting...</span><span v-else>Submit request</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  data() {
    return {
      form: { subject: '', notes: '', items: [{ description: '', quantity: 1 }] },
      submitting: false,
      error: '',
    };
  },
  methods: {
    addItem() { this.form.items.push({ description: '', quantity: 1 }); },
    removeItem(i) { this.form.items.splice(i, 1); },
    async submit() {
      if (!this.form.notes || !this.form.notes.trim()) {
        this.error = 'Please describe your request.';
        return;
      }
      this.error = '';
      this.submitting = true;
      try {
        const items = this.form.items.filter(it => it.description && it.description.trim());
        await axios.post('/portal/quotations', {
          subject: this.form.subject || undefined,
          notes: this.form.notes,
          items: items.length ? items : undefined,
        });
        this.$router.push('/quotations');
      } catch (e) {
        this.error = (e && e.response && e.response.data && e.response.data.message) || 'Could not submit your request. Please try again.';
      }
      this.submitting = false;
    },
  },
};
</script>

<style scoped>
.portal-quotation-request { padding-bottom: 1rem; }
.pc-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap; }
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }
.pc-link-back { color: var(--pc-text-muted); text-decoration: none; font-size: 0.88rem; }
.pc-link-back:hover { color: var(--pc-primary); }
.pc-card { background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius); box-shadow: var(--pc-shadow-sm); }
.pc-form { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.15rem; }
.pc-form-row { display: flex; flex-direction: column; gap: 0.45rem; }
.pc-form-row label { font-size: 0.85rem; font-weight: 600; color: var(--pc-text); }
.pc-muted { color: var(--pc-text-soft); font-weight: 400; font-size: 0.78rem; }
.pc-required { color: var(--pc-danger); }
.pc-form-row input, .pc-form-row textarea { width: 100%; padding: 0.6rem 0.85rem; border: 1px solid var(--pc-border-strong); border-radius: 10px; font-size: 0.92rem; background: var(--pc-surface-alt); box-sizing: border-box; font-family: inherit; }
.pc-form-row input:focus, .pc-form-row textarea:focus { outline: none; background: var(--pc-surface); border-color: var(--pc-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
.pc-item-row { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center; }
.pc-item-row input { flex: 1; }
.pc-qty-input { max-width: 110px; }
.pc-icon-btn { background: transparent; border: 1px solid var(--pc-border-strong); width: 38px; height: 38px; border-radius: 10px; cursor: pointer; color: var(--pc-text-soft); display: flex; align-items: center; justify-content: center; }
.pc-icon-btn:hover { border-color: var(--pc-danger); color: var(--pc-danger); }
.pc-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1rem; border-radius: 10px; font-size: 0.9rem; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
.pc-btn-primary { background: var(--pc-primary); color: #fff; }
.pc-btn-primary:hover:not(:disabled) { background: var(--pc-primary-600); }
.pc-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.pc-btn-ghost { background: var(--pc-surface); border-color: var(--pc-border-strong); color: var(--pc-text); }
.pc-btn-ghost:hover { border-color: var(--pc-primary); color: var(--pc-primary); }
.pc-form-actions { display: flex; justify-content: flex-end; gap: 0.6rem; margin-top: 0.5rem; }
.pc-alert { padding: 0.7rem 0.95rem; border-radius: 10px; font-size: 0.88rem; }
.pc-alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
</style>
