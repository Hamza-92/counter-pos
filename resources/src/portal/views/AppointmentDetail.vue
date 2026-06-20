<template>
  <div class="portal-page portal-appointment-detail">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">{{ appointment.Ref || 'Appointment' }}</h1>
        <p class="pc-page-sub" v-if="appointment.scheduled_date">Scheduled {{ appointment.scheduled_date }}</p>
      </div>
      <router-link to="/appointments" class="pc-link-back">&larr; Back</router-link>
    </header>

    <div v-if="loading" class="pc-inline-loading">
      <div class="pc-spinner"></div><span>Loading...</span>
    </div>

    <div v-else-if="error" class="pc-card pc-error">
      <div class="pc-error-icon">⚠️</div>
      <h3 class="pc-error-title">{{ error.title }}</h3>
      <p class="pc-error-text">{{ error.message }}</p>
      <button type="button" class="pc-btn pc-btn-primary" @click="fetch">Try again</button>
    </div>

    <div v-else-if="!appointment.id" class="pc-card pc-error">
      <div class="pc-error-icon">📭</div>
      <h3 class="pc-error-title">Appointment not found</h3>
      <p class="pc-error-text">We couldn't find this appointment. It may have been removed or it belongs to a different account.</p>
    </div>

    <div v-else class="pc-card pc-detail">
      <div class="pc-detail-header">
        <span :class="'pc-badge pc-badge-' + badgeClass(appointment.status)">{{ appointment.status || 'pending' }}</span>
        <div class="pc-amount" v-if="appointment.total_amount">{{ formatMoney(appointment.total_amount) }}</div>
      </div>

      <dl class="pc-meta-grid">
        <div><dt>Reference</dt><dd>{{ appointment.Ref || '—' }}</dd></div>
        <div><dt>Service</dt><dd>{{ appointment.service_item || '—' }}</dd></div>
        <div><dt>Type</dt><dd>{{ appointment.job_type || '—' }}</dd></div>
        <div><dt>Scheduled</dt><dd>{{ appointment.scheduled_date || '—' }}</dd></div>
        <div><dt>Booked</dt><dd>{{ appointment.created_at || '—' }}</dd></div>
        <div><dt>Started</dt><dd>{{ appointment.started_at || '—' }}</dd></div>
        <div><dt>Completed</dt><dd>{{ appointment.completed_at || '—' }}</dd></div>
        <div><dt>Technician</dt><dd>{{ appointment.technician_name || '—' }}</dd></div>
        <div><dt>Device</dt><dd>{{ deviceLabel || '—' }}</dd></div>
        <div><dt>Quote</dt><dd>{{ appointment.quote_amount ? formatMoney(appointment.quote_amount) : '—' }}</dd></div>
        <div><dt>Paid</dt><dd>{{ appointment.paid_amount != null ? formatMoney(appointment.paid_amount) : '—' }}</dd></div>
        <div><dt>Payment status</dt><dd>{{ appointment.payment_status || '—' }}</dd></div>
      </dl>

      <div v-if="appointment.reported_issue" class="pc-block">
        <h3>Reported issue</h3>
        <p>{{ appointment.reported_issue }}</p>
      </div>

      <div v-if="appointment.diagnosis" class="pc-block">
        <h3>Diagnosis</h3>
        <p>{{ appointment.diagnosis }}</p>
      </div>

      <div v-if="appointment.notes" class="pc-block">
        <h3>Notes</h3>
        <p>{{ appointment.notes }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() { return { appointment: {}, loading: true, error: null }; },
  computed: {
    deviceLabel() {
      const a = this.appointment;
      return [a.device_brand, a.device_model, a.device_serial].filter(Boolean).join(' / ');
    },
  },
  mounted() { this.fetch(); },
  methods: {
    async fetch() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get(`/portal/appointments/${this.$route.params.id}`);
        this.appointment = data || {};
      } catch (e) {
        this.appointment = {};
        const status = e && e.response && e.response.status;
        const serverMessage = e && e.response && e.response.data && e.response.data.message;
        if (status === 404) {
          this.error = { title: 'Appointment not found', message: serverMessage || "We couldn't find this appointment under your account." };
        } else if (status === 403) {
          this.error = { title: 'Access denied', message: serverMessage || 'Your portal session is not allowed to view this appointment.' };
        } else if (status === 401) {
          this.error = { title: 'Session expired', message: 'Please sign in again to view this appointment.' };
        } else {
          this.error = { title: 'Could not load appointment', message: serverMessage || 'Something went wrong while loading this appointment. Please try again.' };
        }
      }
      this.loading = false;
    },
    formatMoney(n) {
      if (n == null) return '0.00';
      return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
.portal-appointment-detail { padding-bottom: 1rem; }
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
.pc-badge-progress { background: #dbeafe; color: #1d4ed8; }
.pc-badge-partial { background: #fee2e2; color: #b91c1c; }
.pc-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem 1.5rem; margin: 0 0 1.2rem; }
.pc-meta-grid div { display: flex; flex-direction: column; }
.pc-meta-grid dt { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--pc-text-soft); font-weight: 600; }
.pc-meta-grid dd { margin: 0.15rem 0 0; font-size: 0.95rem; color: var(--pc-text); font-weight: 500; }
.pc-block { margin-top: 1rem; }
.pc-block h3 { font-size: 0.95rem; margin: 0 0 0.4rem; color: var(--pc-text); }
.pc-block p { margin: 0; padding: 0.85rem 1rem; background: var(--pc-surface-alt); border-radius: 10px; font-size: 0.9rem; white-space: pre-wrap; }
.pc-error { text-align: center; padding: 2rem 1.5rem; }
.pc-error-icon { font-size: 2.5rem; opacity: 0.85; margin-bottom: 0.5rem; }
.pc-error-title { font-size: 1.05rem; margin: 0 0 0.35rem; color: var(--pc-text); }
.pc-error-text { margin: 0 0 1.1rem; color: var(--pc-text-muted); font-size: 0.92rem; }
.pc-error .pc-btn-primary { display: inline-flex; }
.pc-inline-loading { display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 3rem; color: var(--pc-text-muted); }
.pc-spinner { width: 28px; height: 28px; border: 2px solid rgba(79, 70, 229, 0.15); border-top-color: var(--pc-primary); border-radius: 50%; animation: pc-spin 0.7s linear infinite; }
@keyframes pc-spin { to { transform: rotate(360deg); } }
</style>
