<template>
  <div class="portal-login">
    <div class="pc-login-bg"></div>
    <div class="pc-login-card">
      <div class="pc-login-brand">
        <div class="pc-login-mark">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
        </div>
        <h1>Welcome back</h1>
        <p class="pc-sub">Sign in to view your invoices and payments</p>
      </div>
      <form @submit.prevent="login" class="pc-form">
        <div class="pc-field">
          <label>Email</label>
          <div class="pc-input-wrap">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="M4 6l8 7 8-7"/></svg>
            <input v-model="email" type="email" required placeholder="your@email.com" autocomplete="email" />
          </div>
        </div>
        <div class="pc-field">
          <label>Password</label>
          <div class="pc-input-wrap">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            <input v-model="password" type="password" required placeholder="••••••••" autocomplete="current-password" />
          </div>
        </div>
        <p v-if="error" class="pc-alert pc-alert-error">{{ error }}</p>
        <button type="submit" class="pc-btn-primary" :disabled="loading">
          <span v-if="loading" class="pc-btn-spinner"></span>
          {{ loading ? 'Signing in...' : 'Sign in' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return { email: '', password: '', error: '', loading: false };
  },
  methods: {
    async login() {
      this.error = '';
      this.loading = true;
      try {
        await axios.post('/portal/login', { email: this.email, password: this.password });
        window.location.href = '/portal/dashboard';
      } catch (e) {
        const data = e && e.response && e.response.data ? e.response.data : e;
        this.error = (data && data.message) || (e && e.message) || 'Login failed';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.portal-login {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1.25rem;
  position: relative;
  overflow: hidden;
}
.pc-login-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    radial-gradient(1100px circle at 20% -10%, rgba(99, 102, 241, 0.35), transparent 55%),
    radial-gradient(1000px circle at 110% 110%, rgba(6, 182, 212, 0.28), transparent 55%),
    linear-gradient(180deg, #eef2ff 0%, #f6f8fb 100%);
}

.pc-login-card {
  position: relative;
  z-index: 1;
  background: var(--pc-surface);
  padding: 2.25rem 2.25rem 2rem;
  border-radius: 18px;
  box-shadow: 0 18px 50px -12px rgba(15, 23, 42, 0.18), 0 6px 18px -8px rgba(79, 70, 229, 0.2);
  border: 1px solid var(--pc-border);
  width: 100%;
  max-width: 420px;
  animation: pc-slide-up 0.35s ease-out both;
}
@keyframes pc-slide-up {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.pc-login-brand {
  text-align: center;
  margin-bottom: 1.75rem;
}
.pc-login-mark {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #7c3aed 100%);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.85rem;
  box-shadow: 0 10px 25px -8px rgba(79, 70, 229, 0.6);
}
.pc-login-card h1 {
  margin: 0 0 0.4rem;
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--pc-text);
  letter-spacing: -0.01em;
}
.pc-sub {
  color: var(--pc-text-muted);
  font-size: 0.92rem;
  margin: 0;
}

.pc-form { display: flex; flex-direction: column; gap: 1rem; }
.pc-field { display: flex; flex-direction: column; gap: 0.35rem; }
.pc-field label {
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--pc-text);
}
.pc-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.pc-input-wrap svg {
  position: absolute;
  left: 0.85rem;
  color: var(--pc-text-soft);
  pointer-events: none;
}
.pc-input-wrap input {
  width: 100%;
  padding: 0.7rem 1rem 0.7rem 2.35rem;
  border: 1px solid var(--pc-border-strong);
  border-radius: 10px;
  font-size: 0.95rem;
  background: var(--pc-surface-alt);
  color: var(--pc-text);
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
  box-sizing: border-box;
}
.pc-input-wrap input:focus {
  outline: none;
  background: var(--pc-surface);
  border-color: var(--pc-primary);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.pc-alert {
  margin: 0;
  padding: 0.65rem 0.85rem;
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 500;
}
.pc-alert-error { background: var(--pc-danger-bg); color: var(--pc-danger); border: 1px solid #fecaca; }

.pc-btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  width: 100%;
  padding: 0.8rem 1rem;
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.98rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
  box-shadow: 0 8px 20px -8px rgba(79, 70, 229, 0.6);
}
.pc-btn-primary:hover:not(:disabled) { transform: translateY(-1px); filter: brightness(1.05); }
.pc-btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
.pc-btn-spinner {
  display: inline-block;
  width: 1rem; height: 1rem;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: pc-spin 0.7s linear infinite;
}
@keyframes pc-spin { to { transform: rotate(360deg); } }

@media (max-width: 480px) {
  .portal-login { padding: 1rem; }
  .pc-login-card { padding: 1.75rem 1.25rem 1.5rem; border-radius: 16px; }
  .pc-login-card h1 { font-size: 1.25rem; }
}
</style>
