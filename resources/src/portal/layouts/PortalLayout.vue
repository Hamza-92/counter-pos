<template>
  <div class="portal-layout">
    <header class="pc-header">
      <div class="pc-header-inner">
        <router-link to="/dashboard" class="pc-brand">
          <span class="pc-brand-mark">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 7h18M3 12h18M3 17h12"/>
            </svg>
          </span>
          <span class="pc-brand-text">Client Portal</span>
        </router-link>

        <nav class="pc-nav pc-nav-desktop">
          <router-link v-for="item in navItems" :key="item.to" :to="item.to" class="pc-nav-link">
            <span class="pc-nav-icon" v-html="item.icon"></span>
            <span>{{ item.label }}</span>
          </router-link>
        </nav>

        <div class="pc-header-actions">
          <div class="pc-user" @click.stop="menuOpen = !menuOpen" :class="{ open: menuOpen }">
            <div class="pc-avatar" :title="clientName">{{ initials }}</div>
            <span class="pc-user-name">{{ clientName }}</span>
            <svg class="pc-caret" viewBox="0 0 20 20" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 7l5 5 5-5"/></svg>
            <div v-if="menuOpen" class="pc-menu" @click.stop>
              <div class="pc-menu-header">
                <div class="pc-avatar pc-avatar-lg">{{ initials }}</div>
                <div>
                  <div class="pc-menu-name">{{ clientName }}</div>
                  <div class="pc-menu-sub">Signed in</div>
                </div>
              </div>
              <router-link to="/profile" class="pc-menu-item" @click.native="menuOpen = false">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
                Profile
              </router-link>
              <button type="button" class="pc-menu-item pc-menu-danger" @click="logout">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                Logout
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="pc-main">
      <router-view />
    </main>

    <!-- Mobile bottom tab bar -->
    <nav class="pc-bottom-nav">
      <router-link v-for="item in navItems" :key="'bn-' + item.to" :to="item.to" class="pc-bottom-link">
        <span class="pc-bottom-icon" v-html="item.icon"></span>
        <span class="pc-bottom-label">{{ item.label }}</span>
      </router-link>
    </nav>
  </div>
</template>

<script>
const ICONS = {
  home: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>',
  invoice: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z"/><path d="M14 3v6h6"/><path d="M9 14h6M9 18h4"/></svg>',
  payment: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 11h20"/><path d="M6 15h4"/></svg>',
  statement: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/></svg>',
  quotation: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h9"/></svg>',
  appointment: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
  contract: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>',
  help: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9.1A3 3 0 0112 7a3 3 0 011 5.83V14"/><circle cx="12" cy="17.5" r="0.5" fill="currentColor"/></svg>',
  profile: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>',
};

export default {
  data() {
    return {
      clientName: '',
      menuOpen: false,
      navItems: [
        { to: '/dashboard', label: 'Home', icon: ICONS.home },
        { to: '/invoices', label: 'Invoices', icon: ICONS.invoice },
        { to: '/quotations', label: 'Quotations', icon: ICONS.quotation },
        { to: '/appointments', label: 'Appointments', icon: ICONS.appointment },
        { to: '/contracts', label: 'Contracts', icon: ICONS.contract },
        { to: '/payments', label: 'Payments', icon: ICONS.payment },
        { to: '/statement', label: 'Statement', icon: ICONS.statement },
        { to: '/help', label: 'Help', icon: ICONS.help },
        { to: '/profile', label: 'Profile', icon: ICONS.profile },
      ],
    };
  },
  computed: {
    initials() {
      const name = this.clientName || 'A';
      return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(w => w.charAt(0).toUpperCase())
        .join('') || 'A';
    },
  },
  async mounted() {
    document.addEventListener('click', this.closeMenu);
    try {
      const { data } = await axios.get('/portal/me');
      this.clientName = (data && data.portal_client && data.portal_client.client && data.portal_client.client.name) || 'Account';
    } catch (_) {
      this.clientName = 'Account';
    }
  },
  beforeDestroy() {
    document.removeEventListener('click', this.closeMenu);
  },
  methods: {
    closeMenu() {
      this.menuOpen = false;
    },
    async logout() {
      await axios.post('/portal/logout');
      window.location.href = '/portal/login';
    },
  },
};
</script>

<style scoped>
.portal-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--pc-bg);
}

/* Header */
.pc-header {
  position: sticky;
  top: 0;
  z-index: 100;
  background: var(--pc-header-bg);
  backdrop-filter: saturate(180%) blur(14px);
  -webkit-backdrop-filter: saturate(180%) blur(14px);
  border-bottom: 1px solid var(--pc-border);
}
.pc-header-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0.75rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  min-width: 0;
}
.pc-brand {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  text-decoration: none;
  color: var(--pc-text);
  font-weight: 700;
  font-size: 1.05rem;
  letter-spacing: -0.01em;
  flex-shrink: 0;
  min-width: 0;
}
.pc-brand-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pc-brand-mark {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--pc-primary) 0%, #6366f1 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px -2px rgba(79, 70, 229, 0.45);
}

.pc-nav {
  display: flex;
  gap: 0.1rem;
  flex: 1;
  justify-content: center;
  min-width: 0;
  flex-wrap: nowrap;
  overflow: hidden;
}
.pc-nav-link {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.55rem 0.9rem;
  border-radius: 10px;
  color: var(--pc-text-muted);
  font-weight: 500;
  font-size: 0.9rem;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
}
.pc-nav-link:hover { color: var(--pc-text); background: var(--pc-surface-alt); }
.pc-nav-link.router-link-active {
  color: var(--pc-primary);
  background: var(--pc-primary-50);
}
.pc-nav-icon { display: inline-flex; }
.pc-nav-icon >>> svg { width: 16px; height: 16px; }

.pc-header-actions { margin-left: auto; flex-shrink: 0; }
.pc-user {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.3rem 0.55rem 0.3rem 0.3rem;
  border-radius: 999px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: border-color 0.15s, background 0.15s;
  max-width: 100%;
  min-width: 0;
}
.pc-user:hover { border-color: var(--pc-border); background: var(--pc-surface); }
.pc-user.open { border-color: var(--pc-border); background: var(--pc-surface); }
.pc-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.82rem;
  flex-shrink: 0;
}
.pc-avatar-lg { width: 42px; height: 42px; font-size: 0.95rem; }
.pc-user-name {
  font-size: 0.88rem;
  font-weight: 500;
  color: var(--pc-text);
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pc-caret { color: var(--pc-text-soft); }

.pc-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 220px;
  max-width: calc(100vw - 1.5rem);
  background: var(--pc-surface);
  border: 1px solid var(--pc-border);
  border-radius: var(--pc-radius);
  box-shadow: var(--pc-shadow-lg);
  padding: 0.35rem;
  z-index: 200;
  animation: pc-fade-down 0.15s ease-out;
}
@keyframes pc-fade-down {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.pc-menu-header {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.7rem 0.7rem 0.85rem;
  border-bottom: 1px solid var(--pc-border);
  margin-bottom: 0.35rem;
  min-width: 0;
}
.pc-menu-header > div:not(.pc-avatar) { min-width: 0; flex: 1; }
.pc-menu-name { font-weight: 600; color: var(--pc-text); font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pc-menu-sub { font-size: 0.78rem; color: var(--pc-text-muted); }
.pc-menu-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  padding: 0.55rem 0.7rem;
  border: none;
  background: transparent;
  color: var(--pc-text);
  font-size: 0.88rem;
  text-align: left;
  text-decoration: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s;
}
.pc-menu-item:hover { background: var(--pc-surface-alt); }
.pc-menu-danger { color: var(--pc-danger); }
.pc-menu-danger:hover { background: var(--pc-danger-bg); }

/* Main */
.pc-main {
  flex: 1;
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.75rem 1.5rem 2rem;
  box-sizing: border-box;
}

/* Bottom nav (mobile only) */
.pc-bottom-nav {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: saturate(180%) blur(14px);
  -webkit-backdrop-filter: saturate(180%) blur(14px);
  border-top: 1px solid var(--pc-border);
  padding: 0.35rem 0.25rem calc(0.35rem + env(safe-area-inset-bottom, 0px));
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
  -ms-overflow-style: none;
  -webkit-overflow-scrolling: touch;
  scroll-snap-type: x proximity;
  gap: 0.1rem;
}
.pc-bottom-nav::-webkit-scrollbar { display: none; }
.pc-bottom-link {
  flex: 1 1 auto;
  min-width: 56px;
  max-width: 96px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.18rem;
  padding: 0.4rem 0.2rem;
  text-decoration: none;
  color: var(--pc-text-soft);
  font-size: 0.68rem;
  font-weight: 500;
  border-radius: 10px;
  transition: color 0.15s, background 0.15s;
  scroll-snap-align: center;
}
.pc-bottom-link:hover { color: var(--pc-text-muted); }
.pc-bottom-link.router-link-active {
  color: var(--pc-primary);
  background: var(--pc-primary-50);
}
.pc-bottom-link.router-link-active .pc-bottom-icon {
  transform: translateY(-1px) scale(1.05);
}
.pc-bottom-icon {
  display: inline-flex;
  transition: transform 0.2s;
}
.pc-bottom-icon >>> svg { width: 22px; height: 22px; }
.pc-bottom-label {
  line-height: 1;
  letter-spacing: 0.01em;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Tablet: collapse header nav but keep header user block */
@media (max-width: 1024px) {
  .pc-nav-desktop .pc-nav-link span:not(.pc-nav-icon) { display: none; }
  .pc-nav-link { padding: 0.5rem; }
  .pc-nav-icon >>> svg { width: 18px; height: 18px; }
}

/* Narrow tablet: hide user name in header pill to free space for icon nav */
@media (max-width: 820px) {
  .pc-user-name { display: none; }
  .pc-caret { display: none; }
  .pc-user { padding: 0.25rem; }
}

/* Mobile: hide top nav, show bottom nav */
@media (max-width: 640px) {
  .pc-header-inner { padding: 0.55rem 0.85rem; gap: 0.5rem; }
  .pc-nav-desktop { display: none; }
  .pc-main { padding: 1rem 1rem 5.5rem; }
  .pc-bottom-nav { display: flex; }
  .pc-brand { font-size: 1rem; }
  .pc-brand-mark { width: 30px; height: 30px; border-radius: 9px; }
  .pc-bottom-link { min-width: 52px; font-size: 0.66rem; padding: 0.35rem 0.15rem; }
  .pc-bottom-icon >>> svg { width: 21px; height: 21px; }
}

@media (max-width: 480px) {
  .pc-header-inner { padding: 0.5rem 0.75rem; }
  .pc-bottom-nav { padding-left: 0.15rem; padding-right: 0.15rem; gap: 0.05rem; }
  .pc-bottom-link { min-width: 44px; font-size: 0.62rem; padding: 0.3rem 0.1rem; gap: 0.12rem; }
  .pc-bottom-icon >>> svg { width: 20px; height: 20px; }
  .pc-bottom-label { letter-spacing: 0; font-size: 0.6rem; }
}

@media (max-width: 380px) {
  .pc-brand-text { display: none; }
  .pc-main { padding: 0.85rem 0.85rem 5rem; }
  .pc-bottom-link { min-width: 38px; }
  .pc-bottom-label { display: none; }
  .pc-bottom-link { padding: 0.45rem 0.1rem; }
  .pc-bottom-icon >>> svg { width: 22px; height: 22px; }
}
</style>
