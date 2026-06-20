<template>
  <div class="customer-display-setup-container">
    <!-- Header Section -->
    <div class="setup-header">
      <div class="header-content">
        <div class="header-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
        </div>
        <div class="header-text">
          <h1 class="header-title">{{ $t('Customer_Display_Configuration') }}</h1>
          <p class="header-subtitle">{{ $t('Customer_Display_Configuration_Subtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="setup-content">
      <!-- Error Alert -->
      <div v-if="error" class="alert alert-error">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>{{ error }}</span>
      </div>

      <!-- Main Setup Card -->
      <div class="setup-card">
        <div class="card-header">
          <div class="header-top">
            <h2 class="card-title">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 1v22M1 12h22"></path>
              </svg>
              {{ $t('Generate_Access_Token') }}
            </h2>
            <button class="btn btn-generate" @click="generate" :disabled="loading">
              <svg v-if="!loading" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2-8.83"></path>
              </svg>
              <svg v-else width="18" height="18" class="spinner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
              </svg>
              <span v-if="!loading">{{ $t('Generate_New_Token') }}</span>
              <span v-else>{{ $t('Generating') }}…</span>
            </button>
          </div>
          <p class="card-subtitle">{{ $t('Create_secure_token_info') }}</p>
        </div>

        <div class="card-body">
          <!-- URL Section -->
          <div v-if="url" class="info-section url-section">
            <div class="section-header">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
              </svg>
              <label>{{ $t('Display_URL') }}</label>
            </div>
            <div class="url-input-wrapper">
              <input 
                class="url-input" 
                :value="url" 
                readonly 
                @focus="select($event)" 
                :placeholder="$t('Display_URL_Placeholder')"
              />
              <button class="btn btn-copy" @click="copy(url)" :title="$t('Copy_to_clipboard')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                  <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                </svg>
                {{ $t('Copy') }}
              </button>
            </div>
            <p class="url-hint">{{ $t('Click_input_to_select_then_use_copy_button') }}</p>
          </div>

          <!-- Multiple screens: one URL per display -->
          <div v-if="url" class="info-section multi-screen-section">
            <div class="section-header">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
              <label>{{ $t('Multiple_Displays') }}</label>
            </div>
            <p class="multi-screen-hint">{{ $t('Use_one_URL_per_screen_In_POS_select_matching_screen') }}</p>
            <div class="screen-url-list">
              <div v-for="n in 5" :key="n" class="screen-url-row">
                <span class="screen-label">{{ $t('Screen') }} {{ n }}</span>
                <input
                  :value="screenUrl(n)"
                  readonly
                  class="url-input url-input-sm"
                  @focus="select($event)"
                />
                <button type="button" class="btn btn-copy btn-copy-sm" @click="copy(screenUrl(n))" :title="$t('Copy_to_clipboard')">
                  {{ $t('Copy') }}
                </button>
                <button
                  type="button"
                  class="btn btn-qr-icon"
                  :title="$t('Show_QR_Code')"
                  @click="openScreenQrModal(n)"
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Modal: QR code for selected screen -->
          <div v-if="qrModalScreen !== null" class="qr-modal-overlay" @click.self="closeScreenQrModal">
            <div class="qr-modal-card">
              <div class="qr-modal-header">
                <h4>{{ $t('Screen') }} {{ qrModalScreen }} – {{ $t('QR_Code') }}</h4>
                <button type="button" class="btn btn-close-modal" @click="closeScreenQrModal" :title="$t('Close')">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
              </div>
              <div class="qr-modal-body">
                <div ref="qrModalContainer" class="qr-modal-container"></div>
                <p class="qr-modal-hint">{{ $t('Scan_QR_to_open_display') }}</p>
              </div>
            </div>
          </div>

          <!-- QR Code Section -->
          <div v-if="url" class="info-section qr-section">
            <div class="section-header">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
              </svg>
              <label>{{ $t('QR_Code') }}</label>
            </div>
            <p class="qr-description">{{ $t('Scan_QR_to_open_display') }}</p>
            <div class="qr-container">
              <div v-if="qr" class="qr-html" v-html="qr"></div>
              <div v-else ref="qrcanvas" class="qr-canvas-container"></div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="!url" class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M12 1v22M1 12h22"></path>
            </svg>
            <h3>{{ $t('No_Token_Generated_Yet') }}</h3>
            <p>{{ $t('Click_Generate_New_Token_to_create') }}</p>
          </div>
        </div>
      </div>

      <!-- Info Cards Grid -->
      <div class="info-cards-grid">
        <!-- Card 1: How to Use -->
        <div class="info-card">
          <div class="card-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
          </div>
          <h3>{{ $t('How_to_Use') }}</h3>
          <ul>
            <li>{{ $t('Open_URL_on_display_device') }}</li>
            <li>{{ $t('Items_appear_realtime') }}</li>
            <li>{{ $t('Display_updates_automatically') }}</li>
            <li>{{ $t('Perfect_for_showing_purchases') }}</li>
          </ul>
        </div>

        <!-- Card 2: Security -->
        <div class="info-card">
          <div class="card-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
          </div>
          <h3>{{ $t('Security') }}</h3>
          <ul>
            <li>{{ $t('Tokens_secure_time_limited') }}</li>
            <li>{{ $t('Token_expires_24h') }}</li>
            <li>{{ $t('Generate_new_tokens_anytime') }}</li>
            <li>{{ $t('Old_tokens_invalid_immediately') }}</li>
          </ul>
        </div>

        <!-- Card 3: Features -->
        <div class="info-card">
          <div class="card-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
            </svg>
          </div>
          <h3>{{ $t('Features') }}</h3>
          <ul>
            <li>{{ $t('Real_time_item_updates') }}</li>
            <li>{{ $t('Automatic_cart_sync') }}</li>
            <li>{{ $t('Theme_support_dark_light') }}</li>
            <li>{{ $t('Professional_responsive_design') }}</li>
          </ul>
        </div>
      </div>

      <!-- Troubleshooting Section -->
      <div class="troubleshooting-card">
        <h3>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18.36 6.64a9 9 0 1 1-12.73 0"></path>
            <line x1="12" y1="2" x2="12" y2="12"></line>
          </svg>
          {{ $t('Troubleshooting') }}
        </h3>
        <div class="troubleshooting-items">
          <div class="troubleshooting-item">
            <span class="question">{{ $t('Display_not_connecting_Q') }}</span>
            <span class="answer">{{ $t('Display_not_connecting_A') }}</span>
          </div>
          <div class="troubleshooting-item">
            <span class="question">{{ $t('Items_not_updating_Q') }}</span>
            <span class="answer">{{ $t('Items_not_updating_A') }}</span>
          </div>
          <div class="troubleshooting-item">
            <span class="question">{{ $t('Token_expired_Q') }}</span>
            <span class="answer">{{ $t('Token_expired_A') }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>

export default {
  name: 'CustomerDisplaySetup',
  data() {
    return {
      loading: false,
      token: '',
      url: '',
      qr: null,
      error: '',
      tokenExpiry: null,
      qrModalScreen: null
    };
  },
  mounted() {
    // Load existing token if it's still valid
    this.loadExistingToken();
  },
  methods: {
    loadExistingToken() {
      try {
        const stored = localStorage.getItem('cd_token_data');
        if (!stored) return;
        
        const data = JSON.parse(stored);
        const now = new Date().getTime();
        
        // Check if token is still valid (24 hours = 86400000 milliseconds)
        if (data.expiry && now < data.expiry) {
          this.token = data.token;
          this.url = data.url;
          this.qr = data.qr || null;
          this.tokenExpiry = data.expiry;
          
          // If no QR but URL exists, generate it
          this.$nextTick(() => {
            if (!this.qr && this.url) {
              this.generateQrCode(this.url);
            }
          });
        } else {
          // Token expired, clear it
          localStorage.removeItem('cd_token_data');
        }
      } catch (e) {
        console.error('Error loading existing token:', e);
      }
    },
    saveTokenData() {
      try {
        const expiry = new Date().getTime() + (24 * 60 * 60 * 1000); // 24 hours
        const data = {
          token: this.token,
          url: this.url,
          qr: this.qr,
          expiry: expiry
        };
        localStorage.setItem('cd_token_data', JSON.stringify(data));
        this.tokenExpiry = expiry;
      } catch (e) {
        console.error('Error saving token data:', e);
      }
    },
    async generate() {
      this.loading = true;
      this.error = '';
      try {
        const { data } = await axios.post('/customer-display/generate');
        this.token = data.token;
        this.url = data.url;
        this.qr = data.qr || null;
        this.tokenExpiry = new Date().getTime() + (24 * 60 * 60 * 1000); // Set expiry for new token
        this.$nextTick(() => {
          if (!this.qr && this.url) {
            this.generateQrCode(this.url);
          }
        });
        this.saveTokenData(); // Save new token data
      } catch (e) {
        this.error = (e && e.response && e.response.data && e.response.data.message) || this.$t('Failed_to_generate_token');
      } finally {
        this.loading = false;
      }
    },
    generateQrCode(text) {
      const canvas = this.$refs.qrcanvas;
      if (!canvas) return;
      
      // Load QR code library from CDN if not already loaded
      if (!window.QRCode) {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
        script.onload = () => {
          this.drawQrCanvas(text);
        };
        script.onerror = () => {
          this.drawQrCanvasFallback(text);
        };
        document.head.appendChild(script);
      } else {
        this.drawQrCanvas(text);
      }
    },
    drawQrCanvas(text) {
      const canvas = this.$refs.qrcanvas;
      if (!canvas) return;
      
      try {
        if (window.QRCode) {
          // Clear previous content
          canvas.innerHTML = '';
          
          // Generate QR code
          const qr = new window.QRCode(canvas, {
            text: text,
            width: 200,
            height: 200,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: window.QRCode.CorrectLevel.H
          });
          
          return;
        }
      } catch (e) {
        console.error('QR Code generation error:', e);
        this.drawQrCanvasFallback(text);
      }
    },
    drawQrCanvasFallback(text) {
      const canvas = this.$refs.qrcanvas;
      if (!canvas) return;
      
      try {
        // Create a canvas element if it doesn't exist
        let canvasElement = canvas.querySelector('canvas');
        if (!canvasElement) {
          canvasElement = document.createElement('canvas');
          canvas.appendChild(canvasElement);
        }
        
        const ctx = canvasElement.getContext('2d');
        canvasElement.width = 250;
        canvasElement.height = 250;
        
        // Draw white background
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvasElement.width, canvasElement.height);
        
        // Draw border
        ctx.strokeStyle = '#cccccc';
        ctx.lineWidth = 2;
        ctx.strokeRect(0, 0, canvasElement.width, canvasElement.height);
        
        // Draw text
        ctx.fillStyle = '#333333';
        ctx.font = 'bold 16px Arial';
        ctx.textAlign = 'center';
        
        // Wrap text
        const lines = [this.$t('QR_Code'), this.$t('Not_Available'), '', this.$t('Scan_URL_with_your_camera')];
        let y = 50;
        lines.forEach(line => {
          ctx.fillText(line, canvasElement.width / 2, y);
          y += 30;
        });
        
        // Draw the URL shortened
        ctx.font = '12px Arial';
        ctx.fillStyle = '#666666';
        const shortUrl = text.length > 40 ? text.substring(0, 40) + '...' : text;
        ctx.fillText(shortUrl, canvasElement.width / 2, y + 30);
      } catch (e) {
        console.error('Canvas fallback error:', e);
      }
    },
    copy(text) {
      if (!text) return;
      
      // Try modern Clipboard API first
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
          .then(() => {
            this.showCopySuccess();
          })
          .catch((err) => {
            console.error('Clipboard API failed:', err);
            this.copyFallback(text);
          });
      } else {
        // Fallback for older browsers
        this.copyFallback(text);
      }
    },
    
    copyFallback(text) {
      try {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);
        
        if (successful) {
          this.showCopySuccess();
        } else {
          alert(this.$t('Failed_to_copy_to_clipboard'));
        }
      } catch (err) {
        console.error('Fallback copy failed:', err);
        alert(this.$t('Failed_to_copy_to_clipboard'));
      }
    },
    
    showCopySuccess() {
      // Create a temporary success message
      const message = document.createElement('div');
      message.textContent = '✓ ' + this.$t('Copied_to_clipboard');
      message.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
      `;
      
      document.body.appendChild(message);
      
      // Remove message after 2 seconds
      setTimeout(() => {
        message.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => {
          document.body.removeChild(message);
        }, 300);
      }, 2000);
    },
    select(e) {
      e.target.select();
    },
    screenUrl(screenNumber) {
      if (!this.url) return '';
      const sep = this.url.indexOf('?') >= 0 ? '&' : '?';
      return this.url + sep + 'screen=' + screenNumber;
    },
    openScreenQrModal(n) {
      this.qrModalScreen = n;
      this.$nextTick(() => {
        this.drawQrIntoModal(this.screenUrl(n));
      });
    },
    closeScreenQrModal() {
      this.qrModalScreen = null;
      const el = this.$refs.qrModalContainer;
      if (el) el.innerHTML = '';
    },
    drawQrIntoModal(text) {
      const container = this.$refs.qrModalContainer;
      if (!container || !text) return;
      container.innerHTML = '';
      const draw = () => {
        try {
          if (window.QRCode) {
            new window.QRCode(container, {
              text: text,
              width: 200,
              height: 200,
              colorDark: '#000000',
              colorLight: '#ffffff',
              correctLevel: window.QRCode.CorrectLevel.H
            });
            return;
          }
        } catch (e) {
          console.error('QR generation error:', e);
        }
        this.drawQrCanvasFallbackInto(container, text);
      };
      if (!window.QRCode) {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
        script.onload = draw;
        script.onerror = () => this.drawQrCanvasFallbackInto(container, text);
        document.head.appendChild(script);
      } else {
        draw();
      }
    },
    drawQrCanvasFallbackInto(container, text) {
      if (!container) return;
      container.innerHTML = '';
      try {
        const canvas = document.createElement('canvas');
        container.appendChild(canvas);
        const ctx = canvas.getContext('2d');
        canvas.width = 200;
        canvas.height = 200;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, 200, 200);
        ctx.strokeStyle = '#ccc';
        ctx.lineWidth = 2;
        ctx.strokeRect(0, 0, 200, 200);
        ctx.fillStyle = '#333';
        ctx.font = '14px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(this.$t('QR_Code'), 100, 90);
        ctx.fillText(this.$t('Not_Available'), 100, 110);
        ctx.font = '11px Arial';
        ctx.fillStyle = '#666';
        const short = text.length > 35 ? text.substring(0, 35) + '...' : text;
        ctx.fillText(short, 100, 160);
      } catch (e) {
        console.error(e);
      }
    }
  }
};
</script>

<style scoped>
/* ================== ROOT STYLES ================== */
.customer-display-setup-container {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  min-height: 100vh;
  padding: 40px 20px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ================== HEADER ================== */
.setup-header {
  max-width: 1200px;
  margin: 0 auto 40px;
  padding: 40px;
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  border-radius: 20px;
  color: white;
  box-shadow: 0 20px 60px rgba(59, 130, 246, 0.2);
  animation: slideInDown 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.header-content {
  display: flex;
  align-items: center;
  gap: 24px;
}

.header-icon {
  flex-shrink: 0;
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
}

.header-text {
  flex: 1;
}

.header-title {
  font-size: 32px;
  font-weight: 700;
  margin: 0 0 8px 0;
}

.header-subtitle {
  font-size: 16px;
  margin: 0;
  opacity: 0.9;
}

/* ================== CONTENT ================== */
.setup-content {
  max-width: 1200px;
  margin: 0 auto;
}

/* Alert */
.alert {
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 15px;
  animation: slideInDown 0.4s ease-out;
}

.alert-error {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #dc2626;
}

/* Setup Card */
.setup-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  margin-bottom: 32px;
  overflow: hidden;
  animation: slideInUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.card-header {
  padding: 32px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-bottom: 1px solid #e2e8f0;
}

.header-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  gap: 16px;
}

.card-title {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 22px;
  font-weight: 700;
  margin: 0;
  color: #1e293b;
}

.card-title svg {
  color: #3b82f6;
}

.card-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}

.btn-generate {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;
}

.btn-generate:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.btn-generate:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner-icon {
  animation: spin 1s linear infinite;
}

.card-body {
  padding: 32px;
  display: flex;
  flex-direction: column;
  gap: 32px;
}

/* Sections */
.info-section {
  animation: fadeIn 0.5s ease-out;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.section-header svg {
  color: #3b82f6;
  flex-shrink: 0;
}

.section-header label {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

/* URL Section */

.url-input-wrapper {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}

.url-input {
  flex: 1;
  padding: 12px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  background: #f8fafc;
  color: #1e293b;
  transition: all 0.3s ease;
}

.url-input:focus {
  outline: none;
  border-color: #3b82f6;
  background: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-copy {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  color: #1e293b;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;
}

.btn-copy:hover {
  border-color: #3b82f6;
  color: #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.url-hint {
  font-size: 13px;
  color: #94a3b8;
  margin: 0;
}

/* Multiple screens */
.multi-screen-section {
  margin-top: 8px;
}
.multi-screen-hint {
  font-size: 13px;
  color: #64748b;
  margin: 0 0 16px 0;
  line-height: 1.5;
}
.screen-url-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.screen-url-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.screen-url-row .screen-label {
  font-weight: 600;
  color: #475569;
  min-width: 72px;
  font-size: 14px;
}
.screen-url-row .url-input-sm {
  flex: 1;
  min-width: 180px;
  padding: 8px 12px;
  font-size: 13px;
}
.screen-url-row .btn-copy-sm {
  padding: 8px 12px;
  font-size: 13px;
}
.btn-qr-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  color: #3b82f6;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
}
.btn-qr-icon:hover {
  border-color: #3b82f6;
  background: rgba(59, 130, 246, 0.08);
  color: #2563eb;
}

/* QR modal (per-screen) */
.qr-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
  animation: fadeIn 0.2s ease-out;
}
.qr-modal-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  max-width: 360px;
  width: 100%;
  overflow: hidden;
  animation: slideInUp 0.3s ease-out;
}
.qr-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
}
.qr-modal-header h4 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
}
.btn-close-modal {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: none;
  border-radius: 10px;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-close-modal:hover {
  background: #f1f5f9;
  color: #1e293b;
}
.qr-modal-body {
  padding: 24px;
  text-align: center;
}
.qr-modal-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  margin-bottom: 12px;
}
.qr-modal-container canvas,
.qr-modal-container img,
.qr-modal-container table {
  margin: 0 auto;
}
.qr-modal-hint {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  line-height: 1.4;
}

/* QR Section */

.qr-description {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 20px 0;
  line-height: 1.5;
}

.qr-container {
  display: flex;
  justify-content: center;
  padding: 24px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-radius: 12px;
  border: 2px dashed #cbd5e1;
}

.qr-html,
.qr-canvas {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
}

.qr-canvas {
  background: white;
  border: 1px solid #e2e8f0;
}

/* QR Canvas Container */
.qr-canvas-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 250px;
}

/* Style for QRCode library generated content */
.qr-canvas-container table {
  border-collapse: collapse;
  margin: auto;
  background: white;
  padding: 10px;
  border-radius: 8px;
}

.qr-canvas-container table tr,
.qr-canvas-container table td {
  padding: 0;
  margin: 0;
}

.qr-canvas-container img {
  border-radius: 8px;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: #94a3b8;
}

.empty-state svg {
  opacity: 0.3;
  margin-bottom: 16px;
}

.empty-state h3 {
  font-size: 18px;
  font-weight: 600;
  color: #475569;
  margin: 0 0 8px 0;
}

.empty-state p {
  font-size: 14px;
  margin: 0;
}

/* ================== INFO CARDS GRID ================== */
.info-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
  margin-bottom: 32px;
}

.info-card {
  background: white;
  border-radius: 16px;
  padding: 28px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
  transition: all 0.3s ease;
  animation: slideInUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.info-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
}

.card-icon {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(139, 92, 246, 0.05) 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #3b82f6;
  margin-bottom: 16px;
}

.info-card h3 {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 16px 0;
}

.info-card ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-card li {
  font-size: 14px;
  color: #64748b;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  line-height: 1.5;
}

.info-card li:before {
  content: '✓';
  color: #10b981;
  font-weight: 700;
  flex-shrink: 0;
  margin-top: 2px;
}

/* ================== TROUBLESHOOTING ================== */
.troubleshooting-card {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
  animation: slideInUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s both;
}

.troubleshooting-card h3 {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 24px 0;
}

.troubleshooting-card h3 svg {
  color: #3b82f6;
}

.troubleshooting-items {
  display: grid;
  gap: 16px;
}

.troubleshooting-item {
  padding: 16px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-radius: 12px;
  border-left: 4px solid #3b82f6;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.troubleshooting-item .question {
  font-weight: 600;
  color: #1e293b;
  font-size: 14px;
}

.troubleshooting-item .answer {
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}

/* ================== ANIMATIONS ================== */
@keyframes slideInDown {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
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

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(100px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeOut {
  from {
    opacity: 1;
  }
  to {
    opacity: 0;
  }
}

/* ================== RESPONSIVE ================== */
@media (max-width: 768px) {
  .customer-display-setup-container {
    padding: 20px 16px;
  }

  .setup-header {
    margin: 0 auto 24px;
    padding: 24px;
  }

  .header-content {
    flex-direction: column;
    text-align: center;
    gap: 16px;
  }

  .header-icon {
    width: 64px;
    height: 64px;
  }

  .header-title {
    font-size: 24px;
  }

  .header-subtitle {
    font-size: 14px;
  }

  .card-header {
    padding: 24px;
  }

  .header-top {
    flex-direction: column;
    align-items: flex-start;
  }

  .card-body {
    padding: 24px;
    gap: 24px;
  }

  .url-input-wrapper {
    flex-direction: column;
  }

  .btn-copy {
    width: 100%;
    justify-content: center;
  }

  .troubleshooting-card {
    padding: 24px;
  }

  .info-cards-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .customer-display-setup-container {
    padding: 16px 12px;
  }

  .setup-header {
    padding: 20px;
    border-radius: 16px;
  }

  .header-icon {
    width: 56px;
    height: 56px;
  }

  .header-title {
    font-size: 20px;
  }

  .card-title {
    font-size: 18px;
  }

  .card-header {
    padding: 20px;
  }

  .card-body {
    padding: 20px;
  }

  .btn-generate {
    padding: 8px 16px;
    font-size: 13px;
  }
}
</style>


