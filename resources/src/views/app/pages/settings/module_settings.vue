<template>
  <div class="main-content">
    <breadcumb :page="$t('module_settings')" :folder="$t('Settings')"/>

    <!-- Loading -->
    <div v-if="isLoading" class="d-flex justify-content-center align-items-center" style="min-height: 300px;">
      <div class="spinner spinner-primary mr-3"></div>
    </div>

    <div v-if="!isLoading">

      <!-- Header Card -->
      <div class="module-header-card mb-4">
        <div class="module-header-content">
          <div class="module-header-icon">
            <lucide-icon name="puzzle" style="font-size: 28px;" />
          </div>
          <div>
            <h3 class="module-header-title">{{ $t('module_settings') || 'Module Settings' }}</h3>
            <p class="module-header-desc">Install, manage and configure modules to extend your Stocky application.</p>
          </div>
        </div>
        <div class="module-header-stats">
          <div class="stat-item">
            <span class="stat-number">{{ modules_info.length }}</span>
            <span class="stat-label">Installed</span>
          </div>
          <div class="stat-item">
            <span class="stat-number stat-active">{{ activeCount }}</span>
            <span class="stat-label">Active</span>
          </div>
          <div class="stat-item">
            <span class="stat-number stat-inactive">{{ inactiveCount }}</span>
            <span class="stat-label">Inactive</span>
          </div>
        </div>
      </div>

      <!-- Upload Section -->
      <div class="upload-card mb-4">
        <div class="upload-card-header">
          <lucide-icon name="upload" style="font-size: 18px;" />
          <span>Install New Module</span>
        </div>
        <div class="upload-card-body">
          <validation-observer ref="ref_Upload_Module">
            <b-form @submit.prevent="Submit_Upload_Module" enctype="multipart/form-data">
              <div class="upload-zone"
                :class="{ 'drag-over': isDragging, 'has-file': module_zip }"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="onFileDrop"
              >
                <validation-provider name="Upload Module" ref="Upload_Module">
                  <div slot-scope="{ validate, valid, errors }">
                    <input
                      ref="fileInput"
                      :state="errors[0] ? false : (valid ? true : null)"
                      :class="{'is-invalid': !!errors.length}"
                      @change="onFileSelected"
                      type="file"
                      accept=".zip"
                      class="d-none"
                    >
                    <div class="upload-zone-content" @click="$refs.fileInput.click()">
                      <div v-if="!module_zip" class="upload-placeholder">
                        <div class="upload-icon-circle">
                          <lucide-icon name="upload" style="font-size: 24px;" />
                        </div>
                        <p class="upload-text">Drag & drop your module <strong>.zip</strong> file here</p>
                        <p class="upload-subtext">or click to browse files</p>
                      </div>
                      <div v-else class="upload-selected">
                        <div class="file-icon-circle">
                          <lucide-icon name="file-archive" style="font-size: 22px;" />
                        </div>
                        <div class="file-details">
                          <span class="file-name">{{ module_zip.name }}</span>
                          <span class="file-size">{{ formatFileSize(module_zip.size) }}</span>
                        </div>
                        <button type="button" class="file-remove" @click.stop="removeFile">
                          <lucide-icon name="x" />
                        </button>
                      </div>
                    </div>
                    <b-form-invalid-feedback v-if="errors[0]" class="text-center mt-2" :state="false">
                      {{ errors[0] }}
                    </b-form-invalid-feedback>
                  </div>
                </validation-provider>
              </div>

              <div class="text-center mt-3">
                <b-button
                  variant="primary"
                  type="submit"
                  :disabled="SubmitProcessing || !module_zip"
                  class="upload-btn"
                >
                  <span v-if="!SubmitProcessing">
                    <lucide-icon class="mr-1" name="upload" /> Install Module
                  </span>
                  <span v-else class="d-flex align-items-center justify-content-center">
                    <div class="spinner spinner-white sm mr-2"></div> Installing...
                  </span>
                </b-button>
              </div>
            </b-form>
          </validation-observer>
        </div>
      </div>

      <!-- Modules Grid -->
      <div v-if="modules_info.length > 0">
        <div class="modules-section-header mb-3">
          <h4 class="modules-section-title">
            <lucide-icon class="mr-2" name="settings" style="font-size: 18px;" />
            Installed Modules
          </h4>
          <div class="modules-filter">
            <button
              class="filter-btn"
              :class="{ active: filter === 'all' }"
              @click="filter = 'all'"
            >All</button>
            <button
              class="filter-btn"
              :class="{ active: filter === 'active' }"
              @click="filter = 'active'"
            >Active</button>
            <button
              class="filter-btn"
              :class="{ active: filter === 'inactive' }"
              @click="filter = 'inactive'"
            >Inactive</button>
          </div>
        </div>

        <b-row>
          <b-col
            lg="4" md="6" sm="12"
            v-for="module_item in filteredModules"
            :key="module_item.module_name"
            class="mb-4"
          >
            <div class="module-card" :class="{ 'module-active': module_item.status, 'module-inactive': !module_item.status }">
              <!-- Status indicator bar -->
              <div class="module-status-bar" :class="module_item.status ? 'bar-active' : 'bar-inactive'"></div>

              <div class="module-card-body">
                <div class="module-card-top">
                  <div class="module-icon-wrapper" :class="module_item.status ? 'icon-active' : 'icon-inactive'">
                    <lucide-icon :name="getModuleIcon(module_item.module_name)" :size="22" />
                  </div>
                  <div class="module-badge-wrapper">
                    <span class="module-status-badge" :class="module_item.status ? 'badge-active' : 'badge-inactive'">
                      {{ module_item.status ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                </div>

                <div class="module-info">
                  <h5 class="module-name">{{ formatModuleName(module_item.module_name) }}</h5>
                  <p class="module-description">{{ getModuleDescription(module_item.module_name) }}</p>
                </div>

                <div class="module-meta">
                  <div class="meta-item">
                    <lucide-icon name="tag" style="font-size: 12px;" />
                    <span>v{{ module_item.current_version }}</span>
                  </div>
                </div>

                <div class="module-card-footer">
                  <div class="module-toggle-wrapper">
                    <label class="modern-toggle">
                      <input
                        type="checkbox"
                        v-model="module_item.status"
                        @change="update_status_module(module_item)"
                        :disabled="togglingModule === module_item.module_name"
                      >
                      <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label" :class="module_item.status ? 'text-success' : 'text-muted'">
                      {{ module_item.status ? 'Enabled' : 'Disabled' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </b-col>
        </b-row>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state">
        <div class="empty-state-icon">
          <lucide-icon name="puzzle" style="font-size: 48px;" />
        </div>
        <h5 class="empty-state-title">No Modules Installed</h5>
        <p class="empty-state-desc">Upload a module zip file above to get started. Modules add new features and functionality to your Stocky application.</p>
      </div>

    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Module Settings"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      modules_info: [],
      module_zip: '',
      data: new FormData(),
      isDragging: false,
      filter: 'all',
      togglingModule: null,
    };
  },

  computed: {
    activeCount() {
      return this.modules_info.filter(m => m.status).length;
    },
    inactiveCount() {
      return this.modules_info.filter(m => !m.status).length;
    },
    filteredModules() {
      if (this.filter === 'active') return this.modules_info.filter(m => m.status);
      if (this.filter === 'inactive') return this.modules_info.filter(m => !m.status);
      return this.modules_info;
    }
  },

  methods: {
    ...mapActions(["refreshUserPermissions"]),

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    formatFileSize(bytes) {
      if (!bytes) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    formatModuleName(name) {
      return name.replace(/([A-Z])/g, ' $1').replace(/^[\s]/, '').trim();
    },

    getModuleIcon(name) {
      const icons = {
        'ApiDocs': 'clipboard-list',
        'WooCommerceSync': 'shopping-cart',
        'Ecommerce': 'shopping-bag',
        'HRM': 'users',
        'Commission': 'banknote',
        'Contracts': 'file-pen',
        'Bookings': 'calendar',
        'Reports': 'bar-chart',
        'Recruit': 'users',
      };
      return icons[name] || 'puzzle';
    },

    getModuleDescription(name) {
      const descriptions = {
        'ApiDocs': 'Interactive API documentation with code examples and endpoint reference.',
        'WooCommerceSync': 'Sync products and stock with your WooCommerce store.',
        'Ecommerce': 'Full-featured online store with product catalog and checkout.',
        'HRM': 'Human resource management with employees, attendance, and payroll.',
        'Commission': 'Sales agent commission programs, rules, and tracking.',
        'Contracts': 'Contract management with templates, tasks, and attachments.',
        'Bookings': 'Appointment booking and service job management.',
        'Reports': 'Advanced reporting and business analytics.',
        'Recruit': 'Recruitment management with jobs, candidates, applications, interviews and reports.',
      };
      return descriptions[name] || 'Extends your Stocky application with additional functionality.';
    },

    async onFileSelected(e) {
      const { valid } = await this.$refs.Upload_Module.validate(e);
      if (valid) {
        this.module_zip = e.target.files[0];
      } else {
        this.module_zip = "";
      }
    },

    onFileDrop(e) {
      this.isDragging = false;
      const files = e.dataTransfer.files;
      if (files.length && files[0].name.endsWith('.zip')) {
        this.module_zip = files[0];
      } else {
        this.makeToast("danger", "Please upload a .zip file", this.$t("Failed"));
      }
    },

    removeFile() {
      this.module_zip = '';
      this.data = new FormData();
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = '';
      }
    },

    update_status_module(module_info) {
      this.togglingModule = module_info.module_name;
      axios
        .post("update_status_module", {
          status: module_info.status,
          name: module_info.module_name,
        })
        .then(response => {
          if (module_info.status) {
            this.makeToast(
              "success",
              this.$t("Module_enabled_success") || "Module enabled successfully",
              this.$t("Success")
            );
          } else {
            this.makeToast(
              "warning",
              this.$t("Module_Disabled_success") || "Module disabled successfully",
              this.$t("Warning")
            );
          }
          this.togglingModule = null;
          setTimeout(() => { window.location.reload(); }, 1000);
        })
        .catch(error => {
          module_info.status = !module_info.status;
          this.togglingModule = null;
          this.makeToast(
            "danger",
            this.$t("Delete_Therewassomethingwronge") || "Something went wrong",
            this.$t("Warning")
          );
        });
    },

    get_modules_info() {
      axios
        .get("get_modules_info")
        .then(response => {
          this.modules_info = response.data;
          this.isLoading = false;
        })
        .catch(error => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    Submit_Upload_Module() {
      this.$refs.ref_Upload_Module.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_Upload_the_Correct_Module") || "Please upload a valid module",
            this.$t("Failed")
          );
        } else {
          this.Upload_Module();
        }
      });
    },

    Upload_Module() {
      var self = this;
      self.SubmitProcessing = true;
      self.data.append("module_zip", self.module_zip);
      axios
        .post("upload_module", self.data)
        .then(response => {
          self.SubmitProcessing = false;
          self.module_zip = '';
          self.data = new FormData();
          self.makeToast(
            "success",
            self.$t("Uploaded_Success") || "Module installed successfully",
            self.$t("Success")
          );
          setTimeout(() => { window.location.reload(); }, 1000);
        })
        .catch(error => {
          self.SubmitProcessing = false;
          self.makeToast("danger", self.$t("InvalidData") || "Invalid module file", self.$t("Failed"));
        });
    },
  },

  created: function() {
    this.get_modules_info();
  }
};
</script>

<style scoped>
/* ========== Header Card ========== */
.module-header-card {
  background: linear-gradient(135deg, #4361ee 0%, #7c3aed 100%);
  border-radius: 12px;
  padding: 28px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px;
  box-shadow: 0 4px 20px rgba(67, 97, 238, 0.25);
}
.module-header-content {
  display: flex;
  align-items: center;
  gap: 16px;
}
.module-header-icon {
  width: 52px;
  height: 52px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}
.module-header-title {
  color: #fff;
  font-size: 22px;
  font-weight: 700;
  margin: 0;
}
.module-header-desc {
  color: rgba(255, 255, 255, 0.8);
  font-size: 14px;
  margin: 4px 0 0;
}
.module-header-stats {
  display: flex;
  gap: 24px;
}
.stat-item {
  text-align: center;
}
.stat-number {
  display: block;
  font-size: 26px;
  font-weight: 800;
  color: #fff;
  line-height: 1.2;
}
.stat-active { color: #a7f3d0; }
.stat-inactive { color: #fecaca; }
.stat-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

/* ========== Upload Card ========== */
.upload-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}
.upload-card-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 24px;
  border-bottom: 1px solid #e2e8f0;
  font-weight: 600;
  font-size: 15px;
  color: #1e293b;
}
.upload-card-body {
  padding: 24px;
}

/* Upload Zone */
.upload-zone {
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  transition: all 0.25s ease;
  background: #f8fafc;
}
.upload-zone:hover {
  border-color: #4361ee;
  background: rgba(67, 97, 238, 0.03);
}
.upload-zone.drag-over {
  border-color: #4361ee;
  background: rgba(67, 97, 238, 0.06);
  transform: scale(1.01);
}
.upload-zone.has-file {
  border-color: #22c55e;
  border-style: solid;
  background: rgba(34, 197, 94, 0.04);
}
.upload-zone-content {
  cursor: pointer;
  padding: 32px 20px;
}

.upload-placeholder {
  text-align: center;
}
.upload-icon-circle {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: rgba(67, 97, 238, 0.1);
  color: #4361ee;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}
.upload-text {
  font-size: 15px;
  color: #475569;
  margin: 0;
}
.upload-subtext {
  font-size: 13px;
  color: #94a3b8;
  margin: 4px 0 0;
}

/* Selected file */
.upload-selected {
  display: flex;
  align-items: center;
  gap: 14px;
}
.file-icon-circle {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  background: rgba(34, 197, 94, 0.1);
  color: #22c55e;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.file-details {
  flex: 1;
  min-width: 0;
}
.file-name {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.file-size {
  display: block;
  font-size: 12px;
  color: #94a3b8;
  margin-top: 2px;
}
.file-remove {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: rgba(239, 68, 68, 0.08);
  color: #ef4444;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}
.file-remove:hover {
  background: rgba(239, 68, 68, 0.16);
}

/* Upload button */
.upload-btn {
  padding: 10px 32px;
  font-weight: 600;
  border-radius: 8px;
  font-size: 14px;
  min-width: 180px;
}

/* ========== Module Section Header ========== */
.modules-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.modules-section-title {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
}
.modules-filter {
  display: flex;
  gap: 4px;
  background: #f1f5f9;
  padding: 4px;
  border-radius: 8px;
}
.filter-btn {
  padding: 6px 16px;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
}
.filter-btn:hover {
  color: #1e293b;
}
.filter-btn.active {
  background: #fff;
  color: #4361ee;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* ========== Module Card ========== */
.module-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  transition: all 0.25s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  height: 100%;
}
.module-card:hover {
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

/* Status bar at top of card */
.module-status-bar {
  height: 4px;
  width: 100%;
}
.bar-active {
  background: linear-gradient(90deg, #22c55e, #16a34a);
}
.bar-inactive {
  background: #e2e8f0;
}

.module-card-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  height: calc(100% - 4px);
}

.module-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 16px;
}

/* Module icon */
.module-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.icon-active {
  background: rgba(34, 197, 94, 0.1);
  color: #22c55e;
}
.icon-inactive {
  background: #f1f5f9;
  color: #94a3b8;
}

/* Status badge */
.module-status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.badge-active {
  background: rgba(34, 197, 94, 0.1);
  color: #16a34a;
}
.badge-inactive {
  background: #f1f5f9;
  color: #94a3b8;
}

/* Module info */
.module-info {
  flex: 1;
  margin-bottom: 16px;
}
.module-name {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 6px;
}
.module-description {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Meta */
.module-meta {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #f1f5f9;
}
.meta-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: #94a3b8;
}

/* Card footer */
.module-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.module-toggle-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Modern Toggle Switch */
.modern-toggle {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  margin: 0;
  cursor: pointer;
}
.modern-toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}
.toggle-slider {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: #cbd5e1;
  border-radius: 24px;
  transition: all 0.3s ease;
}
.toggle-slider::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  left: 3px;
  bottom: 3px;
  background: #fff;
  border-radius: 50%;
  transition: all 0.3s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}
.modern-toggle input:checked + .toggle-slider {
  background: linear-gradient(135deg, #22c55e, #16a34a);
}
.modern-toggle input:checked + .toggle-slider::before {
  transform: translateX(20px);
}
.modern-toggle input:disabled + .toggle-slider {
  opacity: 0.5;
  cursor: not-allowed;
}
.toggle-label {
  font-size: 13px;
  font-weight: 500;
}

/* ========== Empty State ========== */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: #fff;
  border-radius: 12px;
  border: 2px dashed #e2e8f0;
}
.empty-state-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}
.empty-state-title {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 8px;
}
.empty-state-desc {
  font-size: 14px;
  color: #64748b;
  max-width: 400px;
  margin: 0 auto;
  line-height: 1.6;
}

/* ========== Dark Mode ========== */
.dark-mode .upload-card,
[data-theme="dark"] .upload-card {
  background: #1e293b;
  border-color: #334155;
}
.dark-mode .upload-card-header,
[data-theme="dark"] .upload-card-header {
  border-color: #334155;
  color: #e2e8f0;
}
.dark-mode .upload-zone,
[data-theme="dark"] .upload-zone {
  border-color: #475569;
  background: #0f172a;
}
.dark-mode .upload-zone:hover,
[data-theme="dark"] .upload-zone:hover {
  border-color: #4361ee;
  background: rgba(67, 97, 238, 0.08);
}
.dark-mode .upload-text,
[data-theme="dark"] .upload-text {
  color: #cbd5e1;
}
.dark-mode .file-name,
[data-theme="dark"] .file-name {
  color: #e2e8f0;
}

.dark-mode .module-card,
[data-theme="dark"] .module-card {
  background: #1e293b;
  border-color: #334155;
}
.dark-mode .module-name,
[data-theme="dark"] .module-name {
  color: #e2e8f0;
}
.dark-mode .module-description,
[data-theme="dark"] .module-description {
  color: #94a3b8;
}
.dark-mode .modules-section-title,
[data-theme="dark"] .modules-section-title {
  color: #e2e8f0;
}
.dark-mode .modules-filter,
[data-theme="dark"] .modules-filter {
  background: #0f172a;
}
.dark-mode .filter-btn,
[data-theme="dark"] .filter-btn {
  color: #94a3b8;
}
.dark-mode .filter-btn:hover,
[data-theme="dark"] .filter-btn:hover {
  color: #e2e8f0;
}
.dark-mode .filter-btn.active,
[data-theme="dark"] .filter-btn.active {
  background: #334155;
  color: #818cf8;
}
.dark-mode .module-meta,
[data-theme="dark"] .module-meta {
  border-color: #334155;
}
.dark-mode .icon-inactive,
[data-theme="dark"] .icon-inactive {
  background: #334155;
}
.dark-mode .badge-inactive,
[data-theme="dark"] .badge-inactive {
  background: #334155;
  color: #94a3b8;
}
.dark-mode .bar-inactive,
[data-theme="dark"] .bar-inactive {
  background: #334155;
}
.dark-mode .empty-state,
[data-theme="dark"] .empty-state {
  background: #1e293b;
  border-color: #334155;
}
.dark-mode .empty-state-title,
[data-theme="dark"] .empty-state-title {
  color: #e2e8f0;
}
.dark-mode .empty-state-icon,
[data-theme="dark"] .empty-state-icon {
  background: #334155;
}
.dark-mode .toggle-slider,
[data-theme="dark"] .toggle-slider {
  background: #475569;
}

/* ========== Responsive ========== */
@media (max-width: 768px) {
  .module-header-card {
    padding: 20px;
    flex-direction: column;
    align-items: flex-start;
  }
  .module-header-stats {
    width: 100%;
    justify-content: space-around;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
  }
  .upload-zone-content {
    padding: 24px 16px;
  }
  .upload-selected {
    flex-wrap: wrap;
  }
}
</style>
