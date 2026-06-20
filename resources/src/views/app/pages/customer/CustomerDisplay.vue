<template>
  <div :class="[{ light: theme==='light' }]" class="customer-display-container">
    <!-- Modern Header with Logo -->
    <div class="cd-header">
      <div class="logo-wrapper">
        <img :src="logo" :alt="$t('Store_Logo')" class="store-logo" />
      </div>
      <div class="store-info">
        <h1 class="store-name">{{ $t('Welcome') }}</h1>
        <p class="tagline">{{ $t('Thank_you_for_shopping_with_us') }}</p>
      </div>
    <div class="payable-summary">
      <div class="payable-label">{{ $t('Total_Payable') }}</div>
      <div class="payable-value">{{ currency }} {{ format(total) }}</div>
    </div>
    </div>

    <!-- Main Content Area -->
    <div class="cd-content">
      <div class="cd-scroll">
        <!-- Empty State -->
        <div v-if="!hasItems" class="empty-state">
          <div class="empty-icon">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
          </div>
          <h2 class="empty-title">{{ $t('Waiting_for_Items') }}</h2>
          <p class="empty-subtitle">{{ $t('Items_will_appear_here_as_added_to_cart') }}</p>
        </div>

        <!-- Items List -->
        <div v-else class="cd-list">
          <div v-for="(row, idx) in items" :key="idx" class="cd-item" :style="{ animationDelay: `${idx * 0.05}s` }">
            <div class="item-left">
              <div class="item-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 7h12l-1 12H7L6 7z"></path>
                  <path d="M9 7a3 3 0 0 1 6 0" fill="none"></path>
                </svg>
              </div>
              <div class="item-info">
                <h3 class="item-name">{{ row.name }}</h3>
                <span class="item-qty">{{ $t('Quantity') }}: <strong>{{ row.quantity }}</strong></span>
              </div>
            </div>
            <div class="item-price">
              <span class="price-value">{{ currency }} {{ format(row.subtotal) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary Section -->
      <div v-if="hasItems" class="cd-summary">
        <div class="summary-card">
          <div class="summary-row">
            <span class="summary-label">
              <i class="icon-subtotal">🧮</i> {{ $t('Subtotal') }}
            </span>
            <span class="summary-value">{{ currency }} {{ format(subtotal) }}</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">
              <i class="icon-tax">🧾</i> {{ $t('Tax') }}
            </span>
            <span class="summary-value">{{ currency }} {{ format(tax) }}</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">
              <i class="icon-discount">🎁</i> {{ $t('Discount') }}
            </span>
            <span class="summary-value discount">-{{ currency }} {{ format(discount) }}</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">
              <i class="icon-shipping">🚚</i> {{ $t('Shipping') }}
            </span>
            <span class="summary-value">{{ currency }} {{ format(shipping) }}</span>
          </div>
          <div class="divider"></div>
          <div class="summary-row total-row">
            <span class="summary-label total-label">
              <i class="icon-total">💰</i> {{ $t('Total') }}
            </span>
            <span class="summary-value total-value">{{ currency }} {{ format(total) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Message -->
    <div class="cd-footer">
      <p class="footer-message">{{ footerMessage }}</p>
      <div v-if="hasItems" class="progress-indicator">
        <span class="progress-dot"></span>
      </div>
    </div>
  </div>
</template>

<script>

export default {
  name: 'CustomerDisplay',
  data() {
    const params = typeof window !== 'undefined' && window.location && window.location.search
      ? new URLSearchParams(window.location.search)
      : null;
    const screenId = (params && params.get('screen')) || '1';
    return {
      screenId,
      logo: window.__APP_LOGO__ || '/images/logo.png',
      currency: '',
      items: [],
      discount: 0,
      tax: 0,
      shipping: 0,
      total: 0,
      theme: (localStorage.getItem('cd_theme') || 'dark'),
      footerMessage: this.$t('Thank_you_for_your_purchase'),
      pollTimer: null,
    };
  },
  computed: {
    hasItems() { return Array.isArray(this.items) && this.items.length > 0; },
    subtotal() {
      try {
        return (this.items || []).reduce((sum, it) => sum + Number(it && it.subtotal || 0), 0);
      } catch (e) { return 0; }
    },
  },
  methods: {
    format(n) {
      const num = Number(n || 0);
      const locale = (this.$i18n && this.$i18n.locale) || (navigator.language || 'en-US');
      try {
        return new Intl.NumberFormat(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
      } catch (e) {
        return num.toFixed(2);
      }
    },
    applyCart(cart) {
      if (!cart) { this.items = []; this.discount = 0; this.tax = 0; this.total = 0; this.currency = cart && cart.currency || ''; return; }
      this.currency = cart.currency || '';
      this.items = (cart.details || cart.items || []).map(it => {
        const qty = Number(it.quantity || it.Qty || it.qte || 0);
        const unit = Number(it.unit_price || it.Net_price || it.price || 0);
        const line = Number(it.line_total || it.subtotal || it.total || 0);
        const subtotal = line > 0 ? line : (unit * qty);
        return {
          name: it.name || it.product_name || '',
          quantity: qty,
          subtotal: Number(isFinite(subtotal) ? subtotal : 0)
        };
      });
      this.discount = Number(cart.discount || cart.sale && cart.sale.discount || 0);
      this.tax = Number(cart.TaxNet || cart.sale && cart.sale.TaxNet || 0);
      this.shipping = Number(cart.shipping || cart.sale && cart.sale.shipping || 0);
      this.total = Number(cart.GrandTotal || cart.total || 0);
    },

    // Polling fallback (per-screen)
    async poll() {
      try {
        const { data } = await axios.get('pos/customer-display/last-cart', {
          params: { screen: this.screenId }
        });
        if (data && data.cart) this.applyCart(data.cart);
        if (data && data.completed) {
          this.items = [];
          this.footerMessage = this.$t('Sale_Completed_Thank_You');
        }
      } catch (e) { /* silent */ }
    },

    setupRealtime() {
      const channelName = 'pos-cart.' + this.screenId;
      if (window.Echo && window.Pusher) {
        try {
          window.Echo.channel(channelName)
            .listen('.CartUpdated', (e) => {
              if (e && e.cart) this.applyCart(e.cart);
              if (e && e.completed) {
                this.items = [];
                this.footerMessage = this.$t('Sale_Completed_Thank_You');
              }
            })
            .listenForWhisper('cart-updated', (cart) => {
              this.applyCart(cart);
            })
            .listenForWhisper('sale-completed', () => {
              this.items = [];
              this.footerMessage = this.$t('Sale_Completed_Thank_You');
            });
          return true;
        } catch (e) { /* fallback to polling */ }
      }
      return false;
    }
  },
  async mounted() {
    const ok = this.setupRealtime();
    if (!ok) {
      this.poll();
      this.pollTimer = setInterval(this.poll, 2500);
    }
  },
  beforeDestroy() {
    if (this.pollTimer) clearInterval(this.pollTimer);
  }
}
</script>

<style scoped>
/* ================== ROOT STYLES ================== */
.customer-display-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
  color: #ffffff;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  overflow: hidden;
}

.customer-display-container.light {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  color: #1e293b;
}

/* ================== HEADER ================== */
.cd-header {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0) 100%);
  padding: 30px 40px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  gap: 30px;
  backdrop-filter: blur(10px);
}

.light .cd-header {
  border-bottom-color: rgba(15, 23, 42, 0.1);
  background: linear-gradient(135deg, rgba(15, 23, 42, 0.02) 0%, rgba(15, 23, 42, 0) 100%);
}

.logo-wrapper {
  flex-shrink: 0;
}

.store-logo {
  height: 60px;
  width: auto;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
  animation: slideInLeft 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.light .store-logo {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.store-info {
  flex: 1;
}

.payable-summary {
  margin-left: auto;
  text-align: right;
}

.payable-label {
  font-size: 12px;
  letter-spacing: .5px;
  opacity: .8;
}

.payable-value {
  font-size: 28px;
  font-weight: 800;
  background: linear-gradient(135deg, #fbbf24, #f97316);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.light .payable-value {
  background: linear-gradient(135deg, #f59e0b, #f97316);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.store-name {
  font-size: 32px;
  font-weight: 700;
  margin: 0;
  letter-spacing: -1px;
  animation: slideInDown 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.tagline {
  font-size: 14px;
  margin: 8px 0 0 0;
  opacity: 0.7;
  animation: fadeIn 0.8s ease-out 0.2s both;
}

/* ================== CONTENT AREA ================== */
.cd-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 40px;
  overflow: hidden;
}

.cd-scroll {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

.cd-scroll::-webkit-scrollbar {
  width: 6px;
}

.cd-scroll::-webkit-scrollbar-track {
  background: transparent;
}

.cd-scroll::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 3px;
}

.cd-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.3);
}

/* ================== EMPTY STATE ================== */
.empty-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
}

.empty-icon {
  font-size: 80px;
  margin-bottom: 24px;
  opacity: 0.4;
  animation: float 3s ease-in-out infinite;
}

.empty-title {
  font-size: 28px;
  font-weight: 600;
  margin: 0 0 12px 0;
  opacity: 0.9;
}

.empty-subtitle {
  font-size: 16px;
  margin: 0;
  opacity: 0.6;
}

.light .empty-title,
.light .empty-subtitle {
  color: #475569;
}

/* ================== ITEMS LIST ================== */
.cd-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.cd-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 18px 24px;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
  border: 1px solid rgba(59, 130, 246, 0.2);
  border-radius: 14px;
  backdrop-filter: blur(10px);
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  animation: slideInRight 0.5s ease-out;
}

.cd-item:hover {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
  border-color: rgba(59, 130, 246, 0.3);
  transform: translateX(8px);
}

.light .cd-item {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.02) 100%);
  border-color: rgba(59, 130, 246, 0.15);
}

.light .cd-item:hover {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(139, 92, 246, 0.05) 100%);
  border-color: rgba(59, 130, 246, 0.25);
}

.item-left {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  flex: 1;
}

.item-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(139, 92, 246, 0.1) 100%);
  border-radius: 10px;
  color: #60a5fa;
}

.light .item-icon {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
  color: #3b82f6;
}

.item-info {
  flex: 1;
  min-width: 0;
}

.item-name {
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 6px 0;
  word-break: break-word;
  color: #ffffff;
}

.light .item-name {
  color: #1e293b;
}

.item-qty {
  font-size: 13px;
  opacity: 0.7;
  color: #cbd5e1;
}

.light .item-qty {
  color: #64748b;
}

.item-qty strong {
  font-weight: 600;
  opacity: 1;
  font-size: 14px;
}

.item-price {
  text-align: right;
  flex-shrink: 0;
}

.price-value {
  font-size: 22px;
  font-weight: 700;
  background: linear-gradient(135deg, #60a5fa, #a78bfa);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.light .price-value {
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* ================== SUMMARY SECTION ================== */
.cd-summary {
  padding-top: 24px;
}

.summary-card {
  padding: 28px;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
  border: 2px solid rgba(59, 130, 246, 0.3);
  border-radius: 18px;
  backdrop-filter: blur(20px);
  box-shadow: 0 8px 32px rgba(59, 130, 246, 0.1);
}

.light .summary-card {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(139, 92, 246, 0.04) 100%);
  border-color: rgba(59, 130, 246, 0.2);
  box-shadow: 0 8px 32px rgba(59, 130, 246, 0.05);
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  font-size: 16px;
}

.summary-row:last-of-type {
  margin-bottom: 0;
}

.summary-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 500;
  opacity: 0.8;
  font-size: 15px;
}

.summary-value {
  font-weight: 600;
  color: #60a5fa;
  font-size: 16px;
}

.light .summary-value {
  color: #3b82f6;
}

.summary-value.discount {
  color: #34d399;
}

.light .summary-value.discount {
  color: #10b981;
}

.divider {
  height: 1px;
  background: rgba(255, 255, 255, 0.1);
  margin: 16px 0;
}

.light .divider {
  background: rgba(15, 23, 42, 0.1);
}

.total-row {
  margin-bottom: 0;
  font-size: 18px;
}

.total-label {
  font-weight: 700;
  opacity: 1;
  font-size: 16px;
}

.total-value {
  font-size: 28px;
  font-weight: 800;
  background: linear-gradient(135deg, #fbbf24, #f97316);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.light .total-value {
  background: linear-gradient(135deg, #f59e0b, #f97316);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* ================== FOOTER ================== */
.cd-footer {
  padding: 24px 40px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.2) 0%, rgba(0, 0, 0, 0.05) 100%);
  text-align: center;
  backdrop-filter: blur(10px);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.light .cd-footer {
  border-top-color: rgba(15, 23, 42, 0.1);
  background: linear-gradient(135deg, rgba(15, 23, 42, 0.02) 0%, rgba(15, 23, 42, 0) 100%);
}

.footer-message {
  font-size: 16px;
  font-weight: 500;
  margin: 0;
  opacity: 0.9;
  animation: fadeIn 0.8s ease-out;
}

.light .footer-message {
  color: #334155;
}

.progress-indicator {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.progress-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.4);
  animation: pulse 2s ease-in-out infinite;
}

.light .progress-dot {
  background: rgba(15, 23, 42, 0.4);
}

/* ================== ANIMATIONS ================== */
@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideInDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-20px);
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 0.4;
    transform: scale(1);
  }
  50% {
    opacity: 1;
    transform: scale(1.2);
  }
}

/* ================== RESPONSIVE DESIGN ================== */
@media (max-width: 768px) {
  .cd-header {
    padding: 20px;
    gap: 16px;
    flex-direction: column;
    text-align: center;
  }

  .store-logo {
    height: 48px;
  }

  .store-name {
    font-size: 24px;
  }

  .tagline {
    font-size: 13px;
  }

  .payable-summary {
    margin-left: 0;
    text-align: center;
  }

  .payable-value {
    font-size: 24px;
  }

  .cd-content {
    padding: 20px;
  }

  .cd-item {
    padding: 14px 16px;
    flex-direction: column;
  }

  .item-price {
    text-align: left;
    margin-top: 12px;
  }

  .price-value {
    font-size: 18px;
  }

  .summary-card {
    padding: 20px;
  }

  .cd-footer {
    padding: 16px 20px;
  }

  .summary-label,
  .summary-value {
    font-size: 14px;
  }

  .total-value {
    font-size: 24px;
  }
}

@media (max-width: 480px) {
  .store-name {
    font-size: 20px;
  }

  .cd-content {
    padding: 16px;
  }

  .cd-item {
    padding: 12px;
  }

  .item-icon {
    width: 40px;
    height: 40px;
  }

  .item-name {
    font-size: 16px;
  }

  .summary-card {
    padding: 16px;
  }

  .cd-footer {
    padding: 12px 16px;
    font-size: 14px;
  }
}
</style>


