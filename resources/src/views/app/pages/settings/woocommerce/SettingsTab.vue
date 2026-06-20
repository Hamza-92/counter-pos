<template>
  <div>
    <validation-observer ref="form">
      <b-card class="settings-form-card shadow-sm mb-4">
        <template #header>
          <div class="d-flex align-items-center">
            <lucide-icon class="mr-2 text-primary" name="settings" />
            <h5 class="mb-0 font-weight-bold">Connection Settings</h5>
          </div>
        </template>
        <b-form @submit.prevent="onSubmit">
          <b-row>
            <b-col lg="6" md="6" sm="12" class="mb-3">
              <validation-provider :name="$t('Store_URL')" :rules="{ required: true, regex: urlPattern }" v-slot="v">
                <b-form-group :label="$t('Store_URL') + ' *'" class="form-group-modern">
                  <div class="input-icon-wrapper">
                    <lucide-icon class="input-icon" name="globe" />
                    <b-form-input 
                      v-model="form.store_url" 
                      :state="getState(v)" 
                      :placeholder="$t('Enter_Store_URL')"
                      class="form-control-modern"
                    />
                  </div>
                  <b-form-invalid-feedback>{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col lg="6" md="6" sm="12" class="mb-3">
              <validation-provider :name="$t('Consumer_Key')" :rules="{ required: true }" v-slot="v">
                <b-form-group :label="$t('Consumer_Key') + ' *'" class="form-group-modern">
                  <div class="input-icon-wrapper">
                    <lucide-icon class="input-icon" name="key" />
                    <b-form-input 
                      v-model="form.consumer_key" 
                      :state="getState(v)"
                      class="form-control-modern"
                    />
                  </div>
                  <b-form-invalid-feedback>{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col lg="6" md="6" sm="12" class="mb-3">
              <validation-provider :name="$t('Consumer_Secret')" :rules="{ required: true }" v-slot="v">
                <b-form-group :label="$t('Consumer_Secret') + ' *'" class="form-group-modern">
                  <div class="input-icon-wrapper">
                    <lucide-icon class="input-icon" name="lock" />
                    <b-form-input 
                      type="password" 
                      v-model="form.consumer_secret" 
                      :state="getState(v)"
                      class="form-control-modern"
                    />
                  </div>
                  <b-form-invalid-feedback>{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col lg="6" md="6" sm="12" class="mb-3">
              <b-form-group :label="$t('WP_Username_Optional')" class="form-group-modern">
                <div class="input-icon-wrapper">
                  <lucide-icon class="input-icon" name="user" />
                  <b-form-input 
                    v-model="form.wp_username" 
                    :placeholder="$t('Enter_WP_Username')"
                    class="form-control-modern"
                  />
                </div>
                <small class="text-muted form-help-text">
                  <lucide-icon class="mr-1" name="info" />
                  {{ $t('Used_for_media_upload_fallback') }}
                </small>
              </b-form-group>
            </b-col>

            <b-col lg="6" md="6" sm="12" class="mb-3">
              <b-form-group :label="$t('WP_Application_Password_Optional')" class="form-group-modern">
                <div class="input-icon-wrapper">
                  <lucide-icon class="input-icon" name="key-round" />
                  <b-form-input 
                    type="password" 
                    v-model="form.wp_app_password" 
                    :placeholder="$t('Enter_WP_Application_Password')"
                    class="form-control-modern"
                  />
                </div>
                <small class="text-muted form-help-text">
                  <lucide-icon class="mr-1" name="info" />
                  {{ $t('Create_from_WordPress_Profile') }}
                </small>
              </b-form-group>
            </b-col>

            <b-col lg="6" md="6" sm="12" class="mb-3">
              <b-form-group :label="$t('Connection_Status')" class="form-group-modern">
                <div class="connection-status-wrapper">
                  <b-badge :variant="connectionBadgeVariant" class="connection-status-badge px-3 py-2">
                    <lucide-icon :name="connectionIcon" class="mr-2" />
                    {{ connectionBadgeText }}
                  </b-badge>
                  <span class="mini-spinner ml-3" v-if="connecting"></span>
                </div>
              </b-form-group>
            </b-col>

            <b-col lg="12" md="12" sm="12" class="mt-2">
              <div class="d-flex flex-wrap align-items-center">
                <b-button variant="primary" type="submit" class="btn-modern-primary mr-3 mb-2">
                  <lucide-icon class="mr-2" name="check" /> {{ $t('Save') }}
                </b-button>

                <b-button 
                  variant="outline-success" 
                  class="btn-modern-outline mr-2 mb-2 d-inline-flex align-items-center" 
                  @click="testConnection" 
                  :disabled="connecting"
                >
                  <template v-if="!connecting">
                    <lucide-icon class="mr-2" name="cloud-check" /> {{ $t('Test_Connection') }}
                  </template>
                  <template v-else>
                    <span class="mini-spinner mr-2"></span>
                    {{ $t('Testing') }}
                  </template>
                </b-button>
              </div>
            </b-col>

            <b-col lg="12" md="12" sm="12" class="mt-3" v-if="last_sync_at">
              <b-alert show variant="light" class="sync-alert-modern">
                <lucide-icon class="mr-2" name="clock" />
                {{ $t('Last_Sync') }}: {{ lastSyncAtFromNow }}
              </b-alert>
            </b-col>
          </b-row>
        </b-form>
      </b-card>
    </validation-observer>

    <b-card class="guide-card shadow-sm">
      <template #header>
        <div class="d-flex align-items-center">
          <lucide-icon class="mr-2 text-info" name="book" />
          <h5 class="mb-0 font-weight-bold">WooCommerce Sync Guide</h5>
        </div>
      </template>
      <b-card-text>
        <div class="guide-section mb-4">
          <h6 class="guide-title">
            <lucide-icon class="mr-2 text-info" name="key" />
            Getting API keys
          </h6>
          <ul class="guide-list">
            <li><lucide-icon class="mr-2 text-primary" name="mouse-pointer" />In WooCommerce: WooCommerce → Settings → Advanced → REST API.</li>
            <li><lucide-icon class="mr-2 text-primary" name="plus" />Add key, choose Read/Write, then copy Consumer key and Consumer secret.</li>
            <li><lucide-icon class="mr-2 text-primary" name="globe" />Store URL: your site URL with no trailing slash (e.g. <code>https://yoursite.com</code>).</li>
          </ul>
        </div>

        <div class="guide-section mb-4">
          <h6 class="guide-title">
            <lucide-icon class="mr-2 text-info" name="user" />
            WP Username and Application Password (optional)
          </h6>
          <p class="guide-intro mb-2">These fields are used only for product images. The WooCommerce API (Store URL + Consumer key/secret) handles sync for products, stock, categories, brands, customers, and orders; the WordPress REST API handles the Media Library (search and upload images).</p>
          <ul class="guide-list mb-2">
            <li><lucide-icon class="mr-2 text-primary" name="image" />When syncing products or stock, Stocky can attach product images: it first searches the WordPress Media Library for an existing image by filename; if not found, it uploads the image via the WordPress API.</li>
            <li><lucide-icon class="mr-2 text-primary" name="key" />Use a WordPress user that can manage media (e.g. Administrator). Create an Application Password in WordPress: Users → Profile (or your user) → Application Passwords — add a new one and paste it here.</li>
            <li><lucide-icon class="mr-2 text-primary" name="info" />If you leave these blank, sync still works for all data (products, stock, categories, brands, customers, orders); only product image attachment (search/upload) is skipped.</li>
          </ul>
        </div>

        <div class="guide-section mb-4">
          <h6 class="guide-title">
            <lucide-icon class="mr-2 text-info" name="settings" />
            How to enable
          </h6>
          <ul class="guide-list">
            <li><lucide-icon class="mr-2 text-success" name="check" />Enter Store URL, Consumer key, and Consumer secret above, then click Save.</li>
            <li><lucide-icon class="mr-2 text-success" name="cloud-check" />Use Test Connection to verify credentials.</li>
            <li><lucide-icon class="mr-2 text-success" name="clock" />Use manual sync from any tab when you need to sync.</li>
          </ul>
        </div>

        <div class="guide-section mb-4">
          <h6 class="guide-title">
            <lucide-icon class="mr-2 text-primary" name="mouse-pointer-click" />
            Manual sync (on demand)
          </h6>
          <ul class="guide-list">
            <li><lucide-icon class="mr-2 text-primary" name="arrow-right-left" />Sync works in both directions: Stocky → WooCommerce and WooCommerce → Stocky.</li>
            <li><lucide-icon class="mr-2 text-primary" name="menu" />Manual sync is available in all WooCommerce tabs (Products, Stock, etc.); use the sync actions in each tab to run sync on demand.</li>
          </ul>
        </div>

        <div class="guide-section">
          <h6 class="guide-title">
            <lucide-icon class="mr-2 text-info" name="info" />
            Notes
          </h6>
          <ul class="guide-list mb-0">
            <li><lucide-icon class="mr-2 text-warning" name="alert-circle" />Changing Store URL or API keys resets mappings (products, categories, brands, customers); items will sync again to the (new) store.</li>
            <li><lucide-icon class="mr-2 text-warning" name="alert-circle" />Keep SKUs consistent between Stocky and WooCommerce to avoid duplicate products and to relink safely.</li>
          </ul>
        </div>
      </b-card-text>
    </b-card>
  </div>
</template>

<script>
import NProgress from 'nprogress';
import moment from 'moment';

export default {
  data() {
    return {
      connecting: false,
      connectionOk: null,
      last_sync_at: null,
      form: {
        store_url: '',
        consumer_key: '',
        consumer_secret: '',
        wp_username: '',
        wp_app_password: '',
      },
      
      urlPattern: /^https?:\/\//,
    };
  },
  computed: {
    lastSyncAtFromNow() {
      return this.last_sync_at ? moment(this.last_sync_at).fromNow() : null;
    },
    connectionBadgeVariant() {
      if (this.connectionOk === true) return 'success';
      if (this.connectionOk === false) return 'danger';
      return 'secondary';
    },
    connectionBadgeText() {
      if (this.connectionOk === true) return this.$t('Connected');
      if (this.connectionOk === false) return this.$t('Disconnected');
      return this.$t('Unknown');
    },
    connectionIcon() {
      if (this.connectionOk === true) return 'check-circle';
      if (this.connectionOk === false) return 'x-circle';
      return 'help-circle';
    },
  },
  methods: {
    getState(v) { return v.validated ? v.valid : null; },
    loadSettings() {
      return axios.get('woocommerce/settings').then(({ data }) => {
        if (data.settings) {
          this.form = Object.assign(this.form, data.settings);
          this.last_sync_at = data.settings.last_sync_at;
        }
      });
    },
    onSubmit() {
      this.$refs.form.validate().then(valid => {
        if (!valid) {
          this.toast('danger', this.$t('Please_fill_the_form_correctly'));
          return;
        }
        NProgress.start(); NProgress.set(0.1);
        axios.post('woocommerce/settings', this.form).then(() => {
          this.toast('success', this.$t('Successfully_Updated'));
          NProgress.done();
          this.$emit('updated');
          this.testConnection();
        }).catch(() => {
          this.toast('danger', this.$t('InvalidData'));
          NProgress.done();
        });
      });
    },
    testConnection() {
      this.connecting = true;
      axios.post('woocommerce/test-connection').then(({ data }) => {
        this.connectionOk = !!data.ok;
        this.$emit('connection', this.connectionOk);
        if (data.ok) this.toast('success', this.$t('Connection_successful'));
        else this.toast('danger', this.$t('Connection_failed'));
      }).catch(() => {
        this.connectionOk = false;
        this.$emit('connection', false);
        this.toast('danger', this.$t('Connection_failed'));
      }).finally(() => { this.connecting = false; });
    },
    toast(variant, msg) {
      this.$root.$bvToast.toast(msg, { title: this.$t('WooCommerce'), variant, solid: true });
    }
  },
  created() {
    this.loadSettings().then(() => this.testConnection()).finally(() => { this.$emit('ready'); });
  }
};
</script>

<style scoped>
.settings-form-card {
  border-radius: 12px;
  border: none;
}

.settings-form-card ::v-deep .card-header {
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  border-bottom: 2px solid #e9ecef;
  padding: 1.25rem 1.5rem;
  border-radius: 12px 12px 0 0;
}

.form-group-modern {
  margin-bottom: 0;
}

.form-group-modern ::v-deep label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 0.5rem;
  font-size: 14px;
}

.input-icon-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
  z-index: 1;
}

.form-control-modern {
  padding-left: 40px;
  border-radius: 8px;
  border: 1px solid #dee2e6;
  transition: all 0.3s ease;
  height: 44px;
}

.form-control-modern:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

.form-help-text {
  display: block;
  margin-top: 0.5rem;
  font-size: 12px;
}

.connection-status-wrapper {
  display: flex;
  align-items: center;
}

.connection-status-badge {
  font-size: 14px;
  font-weight: 600;
  border-radius: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.btn-modern-primary {
  border-radius: 8px;
  padding: 0.6rem 1.5rem;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
  transition: all 0.3s ease;
}

.btn-modern-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.btn-modern-outline {
  border-radius: 8px;
  padding: 0.6rem 1.5rem;
  font-weight: 600;
  border-width: 2px;
  transition: all 0.3s ease;
}

.btn-modern-outline:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
}

.sync-alert-modern {
  border-radius: 8px;
  border-left: 4px solid #667eea;
  background: #f8f9ff;
  padding: 1rem;
}

.guide-card {
  border-radius: 12px;
  border: none;
}

.guide-card ::v-deep .card-header {
  background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
  border-bottom: 2px solid #e9ecef;
  padding: 1.25rem 1.5rem;
  border-radius: 12px 12px 0 0;
}

.guide-section {
  padding-bottom: 1rem;
  border-bottom: 1px solid #f0f0f0;
}

.guide-section:last-child {
  border-bottom: none;
}

.guide-title {
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 0.75rem;
  font-size: 15px;
  display: flex;
  align-items: center;
}

.guide-intro {
  font-size: 13px;
  color: #4a5568;
  line-height: 1.6;
}

.guide-intro code,
.guide-list code {
  background: #e2e8f0;
  color: #1e293b;
  padding: 0.2em 0.4em;
  border-radius: 4px;
  font-size: 12px;
  font-family: 'Courier New', monospace;
}

.guide-list {
  list-style: none;
  padding-left: 0;
  margin-bottom: 0;
}

.guide-list li {
  padding: 0.5rem 0;
  display: flex;
  align-items: flex-start;
  color: #4a5568;
  line-height: 1.6;
}

.code-block-wrapper {
  margin-top: 0.75rem;
}

.code-label {
  font-weight: 600;
  color: #495057;
  font-size: 13px;
  margin-bottom: 0.5rem;
  display: block;
}

.code-block {
  background: #1e293b;
  color: #e2e8f0;
  border-radius: 8px;
  padding: 1rem;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 13px;
  line-height: 1.6;
  margin: 0;
  border: 1px solid #334155;
  font-family: 'Courier New', monospace;
  overflow-x: auto;
}

.mini-spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(102, 126, 234, 0.2);
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>


