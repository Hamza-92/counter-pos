<template>
  <div class="main-content import-products-update">
    <!-- Hero -->
    <div class="hero shadow-sm mb-4">
      <div class="hero-bg"></div>
      <div class="hero-body d-flex align-items-center justify-content-between flex-wrap">
        <div class="d-flex align-items-center">
          <div class="hero-icon mr-3"><lucide-icon name="pencil" /></div>
          <div>
            <h3 class="mb-1">Import Products (Update Only)</h3>
            <div class="text-muted small">Update cost and retail price for existing products via CSV import.</div>
          </div>
        </div>
        <router-link :to="{ name: 'index_products' }" class="btn btn-outline-secondary btn-sm mt-3 mt-sm-0">
          <lucide-icon name="chevron-left" /> Back to list
        </router-link>
      </div>
    </div>

    <b-card class="shadow-sm">
      <b-row>
        <!-- Upload column -->
        <b-col md="12" class="mb-4">
          <div
            class="dropzone"
            :class="{ 'is-dragover': isDragOver, 'has-file': file }"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
            @drop.prevent="onDrop"
            @click="browse"
          >
            <input ref="file" type="file" class="d-none" @change="onFileSelected" :accept="accept" />
            <div class="dz-inner text-center">
              <div class="dz-icon mb-2"><lucide-icon name="download" /></div>
              <h5 class="mb-2">Click or drop your CSV/Excel file here</h5>
              <div class="text-muted small">
                Allowed formats: CSV, XLSX, XLS · Max size: 20MB
              </div>

              <!-- Selected file pill -->
              <div v-if="file" class="file-pill mt-3 d-inline-flex align-items-center">
                <div class="file-dot mr-2"></div>
                <div class="file-meta mr-3">
                  <div class="file-name">{{ fileName }}</div>
                  <div class="file-size text-muted small">{{ prettySize }}</div>
                </div>
                <b-button size="sm" variant="outline-danger" @click.stop="clearFile">
                  Remove
                </b-button>
              </div>
            </div>
          </div>

          <!-- Example format -->
          <b-card class="mt-3">
            <div class="d-flex align-items-center mb-2">
              <lucide-icon class="mr-2 text-primary" name="info" />
              <h6 class="mb-0">File Format</h6>
            </div>

            <p class="small text-muted mb-2">
              Your file must have exactly 3 columns: <span class="badge badge-success-soft">code</span>, 
              <span class="badge badge-success-soft">cost</span>, and 
              <span class="badge badge-success-soft">retail_price</span>. Products are matched by code.
            </p>
            <div class="table-responsive">
              <table class="table table-sm table-bordered example-table">
                <thead class="thead-light">
                  <tr>
                    <th class="req">code</th>
                    <th class="req">cost</th>
                    <th class="req">retail_price</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>PROD-001</td>
                    <td>10.50</td>
                    <td>19.99</td>
                  </tr>
                  <tr>
                    <td>PROD-002</td>
                    <td>5.25</td>
                    <td>12.50</td>
                  </tr>
                  <tr>
                    <td>PROD-003</td>
                    <td>8.00</td>
                    <td>15.00</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <ul class="mini-notes mt-2">
              <li><strong>code</strong> — Must match an existing product code exactly.</li>
              <li><strong>cost</strong> — Product cost (numeric value).</li>
              <li><strong>retail_price</strong> — Product retail/selling price (numeric value).</li>
              <li>Only products with matching codes will be updated. Other fields are ignored.</li>
            </ul>
          </b-card>

          <!-- MULTI-ERROR PANEL -->
          <b-alert v-if="errorMessages.length" show variant="danger" class="mt-3">
            <div class="d-flex align-items-start">
              <lucide-icon class="mr-2 mt-1" name="x" />
              <div>
                <div class="font-weight-bold mb-1">Import failed. Fix the issues below:</div>
                <ul class="mb-0 pl-3">
                  <li v-for="(err, idx) in errorMessages" :key="'err-'+idx">{{ err }}</li>
                </ul>
              </div>
            </div>
          </b-alert>

          <!-- Success message -->
          <b-alert v-if="successMessage" show variant="success" class="mt-3">
            <div class="d-flex align-items-start">
              <lucide-icon class="mr-2 mt-1" name="check" />
              <div>
                <div class="font-weight-bold mb-1">{{ successMessage }}</div>
                <div v-if="importResults" class="small">
                  <div>Updated: {{ importResults.updated }} product(s)</div>
                  <div v-if="importResults.not_found > 0" class="text-warning">
                    Not found: {{ importResults.not_found }} code(s)
                  </div>
                  <div v-if="importResults.errors > 0" class="text-danger">
                    Errors: {{ importResults.errors }}
                  </div>
                </div>
              </div>
            </div>
          </b-alert>

          <!-- Optional warnings list -->
          <b-alert v-if="warningMessages.length" show variant="warning" class="mt-3">
            <div class="d-flex align-items-start">
              <lucide-icon class="mr-2 mt-1" name="info" />
              <div>
                <div class="font-weight-bold mb-1">Warnings</div>
                <ul class="mb-0 pl-3">
                  <li v-for="(w, idx) in warningMessages" :key="'warn-'+idx">{{ w }}</li>
                </ul>
              </div>
            </div>
          </b-alert>

          <!-- Progress -->
          <div v-if="uploading" class="mt-3">
            <div class="d-flex justify-content-between mb-1">
              <small class="text-muted">Uploading</small>
              <small>{{ progress }}%</small>
            </div>
            <b-progress :value="progress" height="8px"></b-progress>
          </div>

          <!-- Actions -->
          <div class="d-flex flex-wrap align-items-center mt-3">
            <b-button
              variant="primary"
              size="sm"
              class="mr-2 mb-2"
              :disabled="!canSubmit || uploading"
              @click="submit"
            >
              <span v-if="!uploading"><lucide-icon class="mr-1" name="upload" />Update Products</span>
              <span v-else class="d-inline-flex align-items-center">
                <span class="spinner sm spinner-white mr-2"></span>Processing…
              </span>
            </b-button>

            <a :href="exampleHref" class="btn btn-outline-info btn-sm mr-2 mb-2" target="_blank" rel="noopener">
              <lucide-icon class="mr-1" name="file-spreadsheet" />Download example
            </a>

            <b-button
              variant="outline-secondary"
              size="sm"
              class="mb-2"
              :disabled="!file || uploading"
              @click="clearFile"
            >
              <lucide-icon class="mr-1" name="power" />Reset
            </b-button>
          </div>
        </b-col>

        <!-- Info column -->
        <b-col md="12" class="mb-4">
          <b-card class="mb-3">
            <h6 class="mb-2">Important Notes</h6>
            <ul class="mini-notes">
              <li>This import will <strong>only update</strong> cost and retail_price fields.</li>
              <li>Products are matched by their <strong>code</strong> field.</li>
              <li>If a code doesn't exist, that row will be skipped.</li>
              <li>All other product fields remain unchanged.</li>
              <li>You can use CSV or Excel format (.csv, .xlsx, .xls).</li>
            </ul>
          </b-card>

          <b-alert show variant="light" class="border">
            <div class="d-flex">
              <div class="tip-badge mr-2"><lucide-icon name="info" /></div>
              <div>
                <strong>Heads up</strong>
                <div class="small text-muted">Large files may take longer to process. Make sure your product codes match exactly.</div>
              </div>
            </div>
          </b-alert>
        </b-col>
      </b-row>
    </b-card>
  </div>
</template>

<script>
import NProgress from 'nprogress';
// axios assumed globally available

export default {
  metaInfo: {
    title: "Import Products (Update Only)"
  },
  data() {
    return {
      // endpoint
      endpoint: 'products/import/update-only',

      // file state
      file: null,
      fileName: '',
      fileSize: 0,

      // ui state
      uploading: false,
      progress: 0,
      successMessage: '',
      importResults: null,

      // multi-error support
      errorMessages: [],
      warningMessages: [],

      // dnd
      isDragOver: false,

      // limits
      maxSize: 20 * 1024 * 1024, // 20MB
      accept: '.csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv',
    };
  },
  computed: {
    canSubmit() {
      return !!this.file && this.errorMessages.length === 0;
    },
    prettySize() {
      return this.formatBytes(this.fileSize);
    },
    exampleHref() {
      return '/import/exemples/update_products.csv';
    }
  },
  methods: {
    // ---------- UI helpers ----------
    toast(msg, title, variant) {
      if (this.$root && this.$root.$bvToast) {
        this.$root.$bvToast.toast(msg, { title: title, variant: variant, solid: true });
      }
    },

    // ---------- DnD + browse ----------
    onDragOver() { this.isDragOver = true; },
    onDragLeave() { this.isDragOver = false; },
    onDrop(e) {
      this.isDragOver = false;
      var f = e && e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0] ? e.dataTransfer.files[0] : null;
      if (f) this.loadFile(f);
    },
    browse() {
      if (this.uploading) return;
      if (this.$refs && this.$refs.file) this.$refs.file.click();
    },
    onFileSelected(e) {
      const f = e && e.target && e.target.files && e.target.files[0] ? e.target.files[0] : null;
      if (!f) return;
      this.loadFile(f);
    },

    // ---------- File load + checks ----------
    loadFile(f) {
      this.clearErrors();
      this.successMessage = '';
      this.importResults = null;
      const msgs = [];

      if (f.size > this.maxSize) {
        msgs.push('File is too large. Please upload a file under the 20MB limit.');
      }
      const name = f.name || '';
      const ext = name.split('.').pop().toLowerCase();
      if (['xlsx','xls','csv'].indexOf(ext) === -1) {
        msgs.push('Unsupported file type. Please upload a .csv, .xlsx or .xls file.');
      }

      if (msgs.length) {
        this.errorMessages = msgs;
        // do not keep invalid file
        this.clearFile(false);
        return;
      }

      this.file = f;
      this.fileName = f.name;
      this.fileSize = f.size;
    },
    clearFile(resetInput) {
      if (typeof resetInput === 'undefined') resetInput = true;
      this.file = null; this.fileName = ''; this.fileSize = 0;
      this.successMessage = '';
      this.importResults = null;
      if (resetInput && this.$refs && this.$refs.file) this.$refs.file.value = '';
    },
    clearErrors() {
      this.errorMessages = [];
      this.warningMessages = [];
    },
    formatBytes(bytes) {
      if (!bytes || bytes <= 0) return '0 B';
      var k = 1024; var sizes = ['B','KB','MB','GB','TB'];
      var i = Math.floor(Math.log(bytes) / Math.log(k));
      var v = (bytes / Math.pow(k, i)).toFixed(2);
      return v + ' ' + sizes[i];
    },

    // ---------- Error collectors ----------
    flattenLaravelErrors(errorsObj) {
      const out = [];
      if (!errorsObj || typeof errorsObj !== 'object') return out;
      Object.keys(errorsObj).forEach(k => {
        const v = errorsObj[k];
        if (Array.isArray(v)) {
          v.forEach(m => { if (m) out.push(String(m)); });
        } else if (v) {
          out.push(String(v));
        }
      });
      return out;
    },
    collectErrorsFromResponse(data) {
      const out = [];
      if (!data || typeof data !== 'object') return out;

      if (Array.isArray(data.messages)) {
        data.messages.forEach(m => { if (m) out.push(String(m)); });
      }
      if (data.message) {
        out.push(String(data.message));
      }
      if (data.errors) {
        out.push(...this.flattenLaravelErrors(data.errors));
      }
      if (data.details) {
        if (Array.isArray(data.details)) {
          data.details.forEach(m => { if (m) out.push(String(m)); });
        } else if (typeof data.details === 'string') {
          out.push(data.details);
        }
      }
      if (data.error && typeof data.error === 'string') {
        out.push(data.error);
      }

      // unique list
      const seen = {};
      return out.filter(m => (seen[m] ? false : (seen[m] = true)));
    },
    collectErrorsFromAxios(err) {
      if (err && err.response && err.response.status === 422) {
        const payload = err.response.data || {};
        const list = []
          .concat(this.flattenLaravelErrors(payload.errors))
          .concat(payload.message ? [String(payload.message)] : []);
        return list.length ? list : ['Validation failed. Please check your file and try again.'];
      }

      const payload = err && err.response ? err.response.data : null;
      const list = this.collectErrorsFromResponse(payload);
      if (list.length) return list;

      if (err && err.message) return [String(err.message)];
      return ['Something went wrong while uploading. Please try again.'];
    },

    // ---------- Submit ----------
    async submit() {
      if (!this.file) {
        this.errorMessages = ['Please choose a file to import.'];
        return;
      }

      this.clearErrors();
      this.successMessage = '';
      this.importResults = null;
      this.uploading = true;
      this.progress = 0;
      NProgress.start(); NProgress.set(0.2);

      try {
        var fd = new FormData();
        fd.append('products', this.file);

        const self = this;
        const response = await axios.post(this.endpoint, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: function (pe) {
            if (pe && pe.total) {
              self.progress = Math.round((pe.loaded * 100) / pe.total);
            }
          }
        });

        const data = response && response.data ? response.data : null;
        const ok = data && (data.status === true || data.success === true);

        if (!ok) {
          const msgs = this.collectErrorsFromResponse(data);
          this.errorMessages = msgs.length ? msgs : ['Import failed. Please review your file and try again.'];
          this.toast('Check the error list and fix your file.', 'Import failed', 'danger');
          return;
        }

        // Success - show results
        this.importResults = {
          updated: data.updated || 0,
          not_found: data.not_found || 0,
          errors: data.errors || 0
        };
        this.successMessage = data.message || 'Products updated successfully!';
        this.toast(this.successMessage, 'Success', 'success');
        
        // Redirect to products index after showing success message
        setTimeout(() => {
          this.$router.push({ name: 'index_products' });
        }, 2000);

      } catch (err) {
        this.errorMessages = this.collectErrorsFromAxios(err);
        this.toast('Check the error list and fix your file.', 'Import failed', 'danger');
      } 
      finally {
        NProgress.done();
        this.uploading = false;
        this.progress = 0;
      }
    }
  }
};
</script>

<style scoped>
/* Hero */
.hero{position:relative;border-radius:12px;overflow:hidden}
.hero-bg{position:absolute;inset:0;background:linear-gradient(135deg,#e6f0ff 0%,#f7fbff 60%,#ffffff 100%);opacity:.9}
.hero-body{position:relative;padding:1.1rem 1.1rem}
.hero-icon{width:44px;height:44px;border-radius:12px;background:#2667ff10;color:#2667ff;display:inline-grid;place-items:center;font-size:20px}

/* Dropzone */
.dropzone{border:2px dashed #cfd8e3;border-radius:14px;padding:28px 18px;cursor:pointer;transition:all .15s ease;background:#fbfdff}
.dropzone:hover{border-color:#9cb4ff;background:#f7fbff;box-shadow:0 1px 6px rgba(38,103,255,.08)}
.dropzone.is-dragover{border-color:#2667ff;background:#f1f6ff}
.dropzone.has-file{border-color:#cfd8e3}
.dz-icon{font-size:28px;color:#2667ff}

/* File pill */
.file-pill{border:1px solid #e6ebf2;border-radius:999px;padding:8px 12px;background:#fff}
.file-dot{width:10px;height:10px;background:#2667ff;border-radius:999px}
.file-name{font-weight:600}

/* Example badges */
.badge-success-soft{background:#eaf7ef;color:#0a7a2d;border:1px solid #cdebd7;font-weight:600}

/* Example table */
.example-table th.req{background:#eaf7ef;border-color:#cdebd7}
.example-table thead th{font-weight:600}

/* Notes */
.mini-notes{padding-left:18px;margin:0}
.mini-notes li{margin-bottom:6px}

/* Tip badge */
.tip-badge{width:28px;height:28px;border-radius:8px;background:#f1f5ff;color:#2667ff;display:inline-grid;place-items:center;font-size:14px}
</style>

<!-- Non-scoped dark-mode overrides. The scoped block above gets a
     [data-v-xxxx] attribute on every selector and beats the global
     dark-theme rules in _dark.scss. Re-declare this page's surfaces
     (without `scoped`) so .dark-theme on <body> can reach them. -->
<style>
.dark-theme .import-products-update .hero-bg {
  background: linear-gradient(135deg, #1a1a1a 0%, #232323 60%, #292929 100%);
  opacity: 1;
}
.dark-theme .import-products-update .hero-icon {
  background: rgba(38, 103, 255, 0.18);
  color: #93c5fd;
}

/* Dropzone */
.dark-theme .import-products-update .dropzone {
  background: #1f1f1f;
  border-color: #2f2f2f;
  color: #d8d8d8;
}
.dark-theme .import-products-update .dropzone:hover {
  background: rgba(38, 103, 255, 0.08);
  border-color: #93c5fd;
  box-shadow: 0 1px 6px rgba(38, 103, 255, 0.18);
}
.dark-theme .import-products-update .dropzone.is-dragover {
  background: rgba(38, 103, 255, 0.14);
  border-color: #93c5fd;
}
.dark-theme .import-products-update .dropzone.has-file {
  border-color: #2f2f2f;
}
.dark-theme .import-products-update .dz-icon {
  color: #93c5fd;
}

/* File pill */
.dark-theme .import-products-update .file-pill {
  background: #292929;
  border-color: #2f2f2f;
  color: #d8d8d8;
}
.dark-theme .import-products-update .file-name {
  color: #d8d8d8;
}

/* Example table — keep the green "required" cells (saturated text on
   pastel reads on both modes), only patch the surrounding chrome. */
.dark-theme .import-products-update .example-table {
  color: #d8d8d8;
}
.dark-theme .import-products-update .example-table thead.thead-light th {
  background: #292929;
  color: #d8d8d8;
  border-color: #2a2a2a;
}
.dark-theme .import-products-update .example-table th,
.dark-theme .import-products-update .example-table td {
  border-color: #2a2a2a;
}
.dark-theme .import-products-update .example-table th.req {
  background: rgba(16, 185, 129, 0.18);
  color: #6ee7b7;
  border-color: rgba(16, 185, 129, 0.35);
}
.dark-theme .import-products-update .badge-success-soft {
  background: rgba(16, 185, 129, 0.18);
  color: #6ee7b7;
  border-color: rgba(16, 185, 129, 0.35);
}

/* "Heads up" alert (b-alert variant="light" with .border) — default
   light variant fills white-on-light, unreadable on dark. */
.dark-theme .import-products-update .alert-light {
  background: #232323 !important;
  border-color: #2f2f2f !important;
  color: #d8d8d8 !important;
}
.dark-theme .import-products-update .tip-badge {
  background: rgba(38, 103, 255, 0.18);
  color: #93c5fd;
}

/* Bulleted notes / strong tags so the "code", "cost", etc. terms stay
   readable on the dark surface. */
.dark-theme .import-products-update .mini-notes,
.dark-theme .import-products-update .mini-notes li,
.dark-theme .import-products-update .mini-notes strong {
  color: #d8d8d8;
}
</style>
