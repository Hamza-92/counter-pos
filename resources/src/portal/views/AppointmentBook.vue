<template>
  <div class="portal-page portal-appointment-book">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">Book an appointment</h1>
        <p class="pc-page-sub">Tell us when and what you need — we'll confirm shortly.</p>
      </div>
      <router-link to="/appointments" class="pc-link-back">&larr; Back to appointments</router-link>
    </header>

    <form class="pc-card pc-form" @submit.prevent="submit">
      <div class="pc-form-row">
        <label>Service / Item <span class="pc-required">*</span></label>
        <input v-model="form.service_item" type="text" required maxlength="190" placeholder="e.g. iPhone 13 screen repair" />
      </div>

      <div class="pc-form-grid">
        <div class="pc-form-row">
          <label>Type</label>
          <select v-model="form.job_type">
            <option value="service">Service</option>
            <option value="repair">Repair</option>
            <option value="installation">Installation</option>
            <option value="consultation">Consultation</option>
          </select>
        </div>
        <div class="pc-form-row">
          <label>Preferred date & time <span class="pc-required">*</span></label>
          <input v-model="form.scheduled_date" type="datetime-local" required />
        </div>
      </div>

      <div class="pc-form-grid">
        <div class="pc-form-row">
          <label>Device brand <span class="pc-muted">(optional)</span></label>
          <input v-model="form.device_brand" type="text" maxlength="120" />
        </div>
        <div class="pc-form-row">
          <label>Device model <span class="pc-muted">(optional)</span></label>
          <input v-model="form.device_model" type="text" maxlength="120" />
        </div>
      </div>

      <div class="pc-form-row">
        <label>Serial / IMEI <span class="pc-muted">(optional)</span></label>
        <input v-model="form.device_serial" type="text" maxlength="120" />
      </div>

      <div class="pc-form-row">
        <label>What's the issue / what do you need?</label>
        <textarea v-model="form.reported_issue" rows="4" maxlength="5000" placeholder="Describe the problem or service needed"></textarea>
      </div>

      <div v-if="error" class="pc-alert pc-alert-error">{{ error }}</div>

      <div class="pc-form-actions">
        <router-link to="/appointments" class="pc-btn pc-btn-ghost">Cancel</router-link>
        <button type="submit" class="pc-btn pc-btn-primary" :disabled="submitting">
          <span v-if="submitting">Submitting...</span><span v-else>Book appointment</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  data() {
    return {
      form: {
        service_item: '', job_type: 'service', scheduled_date: '',
        device_brand: '', device_model: '', device_serial: '', reported_issue: '',
      },
      submitting: false,
      error: '',
    };
  },
  methods: {
    async submit() {
      if (!this.form.service_item || !this.form.scheduled_date) {
        this.error = 'Please fill in the required fields.';
        return;
      }
      this.error = '';
      this.submitting = true;
      try {
        await axios.post('/portal/appointments', {
          service_item: this.form.service_item,
          job_type: this.form.job_type || undefined,
          scheduled_date: this.form.scheduled_date,
          reported_issue: this.form.reported_issue || undefined,
          device_brand: this.form.device_brand || undefined,
          device_model: this.form.device_model || undefined,
          device_serial: this.form.device_serial || undefined,
        });
        this.$router.push('/appointments');
      } catch (e) {
        this.error = (e && e.response && e.response.data && e.response.data.message) || 'Could not book your appointment. Please try again.';
      }
      this.submitting = false;
    },
  },
};
</script>

<style scoped>
.portal-appointment-book { padding-bottom: 1rem; }
.pc-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap; }
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }
.pc-link-back { color: var(--pc-text-muted); text-decoration: none; font-size: 0.88rem; }
.pc-link-back:hover { color: var(--pc-primary); }
.pc-card { background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: var(--pc-radius); box-shadow: var(--pc-shadow-sm); }
.pc-form { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.15rem; }
.pc-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .pc-form-grid { grid-template-columns: 1fr; } }
.pc-form-row { display: flex; flex-direction: column; gap: 0.45rem; }
.pc-form-row label { font-size: 0.85rem; font-weight: 600; color: var(--pc-text); }
.pc-muted { color: var(--pc-text-soft); font-weight: 400; font-size: 0.78rem; }
.pc-required { color: var(--pc-danger); }
.pc-form-row input, .pc-form-row select, .pc-form-row textarea { width: 100%; padding: 0.6rem 0.85rem; border: 1px solid var(--pc-border-strong); border-radius: 10px; font-size: 0.92rem; background: var(--pc-surface-alt); box-sizing: border-box; font-family: inherit; }
.pc-form-row input:focus, .pc-form-row select:focus, .pc-form-row textarea:focus { outline: none; background: var(--pc-surface); border-color: var(--pc-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
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
