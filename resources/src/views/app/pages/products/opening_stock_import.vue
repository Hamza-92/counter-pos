<template>
  <div class="main-content import-products">
    <!-- Hero -->
    <div class="hero shadow-sm mb-4">
      <div class="hero-bg"></div>
      <div class="hero-body d-flex align-items-center justify-content-between flex-wrap">
        <div class="d-flex align-items-center">
          <div class="hero-icon mr-3"><lucide-icon name="upload" /></div>
          <div>
            <h3 class="mb-1">Opening Stock Import</h3>
            <div class="text-muted small">Add initial quantities from an Excel file.</div>
          </div>
        </div>
        <router-link :to="{ name: 'index_products' }" class="btn btn-outline-secondary btn-sm mt-3 mt-sm-0">
          <lucide-icon name="chevron-left" /> Back to list
        </router-link>
      </div>
    </div>

    <b-card class="shadow-sm">
      <!-- Type selector -->
      <div class="d-flex justify-content-center mb-3">
        <b-button-group size="sm" class="seg">
          <!-- Single is ALWAYS outline-primary (as requested) -->
          <b-button :variant="importType==='single' ? 'primary' : 'outline-primary'" @click="switchType('single')">
            <lucide-icon class="mr-1" name="store" />Single Products
          </b-button>
          <!-- Variant toggles to solid when active -->
          <b-button :variant="importType==='variant' ? 'primary' : 'outline-primary'" @click="switchType('variant')">
            <lucide-icon class="mr-1" name="library" />Variant Products
          </b-button>
        </b-button-group>
      </div>

      <b-row>
        <!-- Left column: Warehouse + Dropzone + Examples + Errors + Actions -->
        <b-col md="7" class="mb-4">
          <!-- Warehouse selector -->
          <b-card class="mb-3">
            <label class="font-weight-600 mb-1">Warehouse <span class="text-danger">*</span></label>
            <v-select
              :reduce="function (opt) { return opt.value }"
              :options="warehouseOptions"
              :placeholder="'Choose a warehouse...'"
              v-model="warehouse_id"
              :class="{'is-invalid': warehouseTouched && !warehouse_id}"
            />
            <small class="text-muted d-block mt-1">Stock will be added to this warehouse.</small>
            <div v-if="warehouseTouched && !warehouse_id" class="invalid-feedback d-block">Please choose a warehouse.</div>
          </b-card>

          <!-- Dropzone -->
          <div
            class="dropzone"
            :class="{ 'is-dragover': isDragOver, 'has-file': !!file }"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
            @drop.prevent="onDrop"
            @click="browse"
          >
            <input ref="file" type="file" class="d-none" @change="onFileSelected" :accept="accept" />
            <div class="dz-inner text-center">
              <div class="dz-icon mb-2"><lucide-icon name="download" /></div>
              <h5 class="mb-2">Click or drop your Excel file here</h5>
              <div class="text-muted small">
                Allowed formats: XLSX, XLS · Max size: 20MB
              </div>

              <!-- Selected file pill -->
              <div v-if="file" class="file-pill mt-3 d-inline-flex align-items-center">
                <div class="file-dot mr-2"></div>
                <div class="file-meta mr-3">
                  <div class="file-name">{{ fileName }}</div>
                  <div class="file-size text-muted small">{{ prettySize }}</div>
                </div>
                <b-button size="sm" variant="outline-danger" @click.stop="clearFile">Remove</b-button>
              </div>
            </div>
          </div>

          <!-- Example format -->
          <b-card class="mt-3">
            <div class="d-flex align-items-center mb-2">
              <lucide-icon class="mr-2 text-primary" name="info" />
              <h6 class="mb-0">Example format</h6>
            </div>

            <!-- Single example -->
            <div v-if="importType==='single'">
              <p class="small text-muted mb-2">
                One row per product. Columns in <span class="badge badge-success-soft">green</span> are required.
              </p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered example-table">
                  <thead class="thead-light">
                    <tr>
                      <th class="req">product_code</th>
                      <th class="req">qty</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>TSHIRT-BLUE</td>
                      <td>10</td>
                    </tr>
                    <tr>
                      <td>MUG-COF-01</td>
                      <td>25</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <ul class="mini-notes mt-2">
                <li><strong>product_code</strong> :must already exist.</li>
                <li><strong>qty</strong> : Quantity to added to current stock.</li>
              </ul>
            </div>

            <!-- Variants example -->
            <div v-else>
              <p class="small text-muted mb-2">
                One row per variant. Columns in <span class="badge badge-success-soft">green</span> are required.
              </p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered example-table">
                  <thead class="thead-light">
                    <tr>
                      <th class="req">product_code</th>
                      <th class="req">variant_code</th>
                      <th class="req">qty</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>TSHIRT-100</td>
                      <td>TSHIRT-100-S</td>
                      <td>5</td>
                    </tr>
                    <tr>
                      <td>TSHIRT-100</td>
                      <td>TSHIRT-100-M</td>
                      <td>8</td>
                    </tr>
                    <tr>
                      <td>TSHIRT-100</td>
                      <td>TSHIRT-100-L</td>
                      <td>7</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <ul class="mini-notes mt-2">
                <li><strong>product_code</strong> and <strong>variant_code</strong>  : must exist, and must match.</li>
                <li><strong>qty</strong>  : Quantity to added to the variant’s stock.</li>
              </ul>
            </div>

            <div class="mt-2">
              <a :href="exampleHref" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                <lucide-icon class="mr-1" name="file-spreadsheet" />Download example
              </a>
            </div>
          </b-card>

          <!-- Errors panel -->
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

          <!-- Warnings panel -->
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
              <span v-if="!uploading"><lucide-icon class="mr-1" name="upload" />Import now</span>
              <span v-else class="d-inline-flex align-items-center">
                <span class="spinner sm spinner-white mr-2"></span>Processing…
              </span>
            </b-button>

            <b-button
              variant="outline-secondary"
              size="sm"
              class="mb-2"
              :disabled="(!file && !warehouse_id) || uploading"
              @click="resetAll"
            >
              <lucide-icon class="mr-1" name="power" />Reset
            </b-button>
          </div>
        </b-col>

        <!-- Right column: Guide -->
        <b-col md="5" class="mb-4">
          <b-card class="mb-3">
            <h6 class="mb-2">Required & optional columns</h6>

            <!-- Singles -->
            <div v-if="importType==='single'">
              <div class="chip-grid">
                <span class="chip chip-req">product_code</span>
                <span class="chip chip-req">qty</span>
              </div>
              <ul class="mini-notes mt-3">
                <li><strong>product_code</strong> — Must exist in Products.</li>
                <li><strong>qty</strong> — Positive number.</li>
              </ul>
            </div>

            <!-- Variants -->
            <div v-else>
              <div class="chip-grid">
                <span class="chip chip-req">product_code</span>
                <span class="chip chip-req">variant_code</span>
                <span class="chip chip-req">qty</span>
              </div>
              <ul class="mini-notes mt-3">
                <li><strong>product_code</strong> — Must exist in Products.</li>
                <li><strong>variant_code</strong> — Must exist in Product Variants and belong to the product.</li>
                <li><strong>qty</strong> — Positive number.</li>
              </ul>
            </div>
          </b-card>

          <b-alert show variant="light" class="border">
            <div class="d-flex">
              <div class="tip-badge mr-2"><lucide-icon name="info" /></div>
              <div>
                <strong>Heads up</strong>
                <div class="small text-muted">Large files may take longer to process.</div>
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
  name: 'OpeningStockImportPage',
  data: function () {
    return {
      // endpoints
      metaEndpoint: 'opening-stock/import/meta',
      singleEndpoint: 'opening-stock/import/single',
      variantEndpoint: 'opening-stock/import/variants',

      // selection
      importType: 'single',     // single is default (and stays outline-primary)
      warehouse_id: '',
      warehouseTouched: false,
      warehouses: [],

      // file state
      file: null,
      fileName: '',
      fileSize: 0,

      // ui state
      uploading: false,
      progress: 0,

      // multi-error support
      errorMessages: [],
      warningMessages: [],

      // dnd
      isDragOver: false,

      // limits
      maxSize: 20 * 1024 * 1024, // 20MB
      accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,.xlsx,.xls'
    };
  },
  computed: {
    warehouseOptions: function () {
      var out = [];
      for (var i = 0; i < this.warehouses.length; i++) {
        var w = this.warehouses[i];
        out.push({ label: w.name, value: w.id });
      }
      return out;
    },
    canSubmit: function () {
      return !!this.file && !!this.warehouse_id && this.errorMessages.length === 0;
    },
    prettySize: function () {
      return this.formatBytes(this.fileSize);
    },
    exampleHref: function () {
      return this.importType === 'single'
        ? '/import/exemples/opening_stock_single.xlsx'
        : '/import/exemples/opening_stock_variants.xlsx';
    }
  },
  methods: {
    toast: function (msg, title, variant) {
      if (this.$root && this.$root.$bvToast) {
        this.$root.$bvToast.toast(msg, { title: title, variant: variant, solid: true });
      }
    },
    switchType: function (type) {
      this.importType = type;
      this.clearErrors();
    },
    // DnD + browse
    onDragOver: function () { this.isDragOver = true; },
    onDragLeave: function () { this.isDragOver = false; },
    onDrop: function (e) {
      this.isDragOver = false;
      var files = e && e.dataTransfer && e.dataTransfer.files;
      var f = files && files[0] ? files[0] : null;
      if (f) this.loadFile(f);
    },
    browse: function () {
      if (this.uploading) return;
      if (this.$refs && this.$refs.file) this.$refs.file.click();
    },
    onFileSelected: function (e) {
      var files = e && e.target && e.target.files;
      var f = files && files[0] ? files[0] : null;
      if (!f) return;
      this.loadFile(f);
    },

    // File load + checks
    loadFile: function (f) {
      this.clearErrors();
      var msgs = [];
      if (f.size > this.maxSize) msgs.push('File is too large. Please upload a file under the 20MB limit.');
      var name = f.name || '';
      var ext = name.split('.').pop().toLowerCase();
      if (['xlsx','xls'].indexOf(ext) === -1) msgs.push('Unsupported file type. Please upload an .xlsx or .xls file.');
      if (msgs.length) { this.errorMessages = msgs; this.clearFile(false); return; }
      this.file = f; this.fileName = f.name; this.fileSize = f.size;
    },
    clearFile: function (resetInput) {
      if (typeof resetInput === 'undefined') resetInput = true;
      this.file = null; this.fileName = ''; this.fileSize = 0;
      if (resetInput && this.$refs && this.$refs.file) this.$refs.file.value = '';
    },
    resetAll: function () {
      this.clearFile(true);
      this.warehouse_id = '';
      this.warehouseTouched = false;
      this.clearErrors();
    },
    clearErrors: function () {
      this.errorMessages = [];
      this.warningMessages = [];
    },
    formatBytes: function (bytes) {
      if (!bytes || bytes <= 0) return '0 B';
      var k = 1024; var sizes = ['B','KB','MB','GB','TB'];
      var i = Math.floor(Math.log(bytes) / Math.log(k));
      var v = (bytes / Math.pow(k, i)).toFixed(2);
      return v + ' ' + sizes[i];
    },

    // Error collectors (works with object OR array)
    flattenLaravelErrorsObject: function (errorsObj) {
      var out = [];
      if (!errorsObj || typeof errorsObj !== 'object') return out;
      for (var k in errorsObj) {
        if (!errorsObj.hasOwnProperty(k)) continue;
        var v = errorsObj[k];
        if (Array.isArray(v)) {
          for (var i = 0; i < v.length; i++) if (v[i]) out.push(String(v[i]));
        } else if (v) out.push(String(v));
      }
      return out;
    },
    collectErrorsFromResponse: function (data) {
      var out = [];
      if (!data || typeof data !== 'object') return out;

      // message
      if (data.message && typeof data.message === 'string') out.push(data.message);

      // errors as array of strings
      if (Array.isArray(data.errors)) {
        for (var i = 0; i < data.errors.length; i++) {
          if (data.errors[i]) out.push(String(data.errors[i]));
        }
      }
      // errors as object {field: [..]}
      else if (data.errors && typeof data.errors === 'object') {
        out = out.concat(this.flattenLaravelErrorsObject(data.errors));
      }

      // details (sometimes)
      if (Array.isArray(data.details)) {
        for (var j = 0; j < data.details.length; j++) {
          if (data.details[j]) out.push(String(data.details[j]));
        }
      } else if (data.details && typeof data.details === 'string') {
        out.push(data.details);
      }

      // error
      if (data.error && typeof data.error === 'string') out.push(data.error);

      // unique
      var seen = {};
      var unique = [];
      for (var u = 0; u < out.length; u++) {
        if (!seen[out[u]]) { seen[out[u]] = true; unique.push(out[u]); }
      }
      return unique;
    },
    collectErrorsFromAxios: function (err) {
      // Prefer 422
      if (err && err.response && err.response.status === 422) {
        var payload = err.response.data || {};
        var list = [];

        if (Array.isArray(payload.errors)) {
          list = list.concat(payload.errors);
        } else if (payload.errors && typeof payload.errors === 'object') {
          list = list.concat(this.flattenLaravelErrorsObject(payload.errors));
        }
        if (payload.message) list.push(String(payload.message));

        return list.length ? list : ['Validation failed. Please check your file and try again.'];
      }

      // Generic
      var payload2 = err && err.response ? err.response.data : null;
      var list2 = this.collectErrorsFromResponse(payload2);
      if (list2.length) return list2;

      if (err && err.message) return [String(err.message)];
      return ['Something went wrong while uploading. Please try again.'];
    },

    // Submit
    async submit () {
      this.warehouseTouched = true;
      if (!this.warehouse_id) {
        this.errorMessages = ['Please choose a warehouse.'];
        return;
      }
      if (!this.file) {
        this.errorMessages = ['Please choose a file to import.'];
        return;
      }

      this.clearErrors();
      this.uploading = true;
      this.progress = 0;
      NProgress.start(); NProgress.set(0.2);

      try {
        var fd = new FormData();
        fd.append('warehouse_id', this.warehouse_id);
        fd.append('products', this.file);

        var endpoint = this.importType === 'single' ? this.singleEndpoint : this.variantEndpoint;
        var self = this;

        var response = await axios.post(endpoint, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: function (pe) {
            if (pe && pe.total) self.progress = Math.round((pe.loaded * 100) / pe.total);
          }
        });

        var data = response && response.data ? response.data : null;
        var ok = data && (data.status === true || data.success === true);

        if (!ok) {
          var msgs = this.collectErrorsFromResponse(data);
          this.errorMessages = msgs.length ? msgs : ['Import failed. Please review your file and try again.'];
          this.toast('Check the error list and fix your file.', 'Import failed', 'danger');
          return;
        }

        this.toast('Opening stock imported successfully.', 'Success', 'success');
        this.$router.push({ name: 'index_products' });
      } catch (err) {
        this.errorMessages = this.collectErrorsFromAxios(err);
        this.toast('Check the error list and fix your file.', 'Import failed', 'danger');
      } finally {
        NProgress.done();
        this.uploading = false;
        this.progress = 0;
      }
    },

    // Load warehouses
    async loadWarehouses () {
      try {
        var { data } = await axios.get(this.metaEndpoint);
        this.warehouses = data && data.warehouses ? data.warehouses : [];
      } catch (e) {
        this.warehouses = [];
      }
    }
  },
  created: function () {
    this.loadWarehouses();
  }
};
</script>

<style scoped>
/* Hero */
.hero{position:relative;border-radius:12px;overflow:hidden}
.hero-bg{position:absolute;inset:0;background:linear-gradient(135deg,#e6f0ff 0%,#f7fbff 60%,#ffffff 100%);opacity:.9}
.hero-body{position:relative;padding:1.1rem 1.1rem}
.hero-icon{width:44px;height:44px;border-radius:12px;background:#2667ff10;color:#2667ff;display:inline-grid;place-items:center;font-size:20px}

/* Segmented control */
.seg .btn{min-width:160px}

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

/* Chips grid */
.chip-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-gap:8px}
@media (min-width:992px){.chip-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.chip{display:inline-block;padding:6px 10px;border-radius:999px;font-size:.85rem;font-weight:600;border:1px solid transparent;white-space:nowrap;text-overflow:ellipsis;overflow:hidden}
.chip-req{color:#0a7a2d;background:#eaf7ef;border-color:#cdebd7}
.chip-opt{color:#475569;background:#f5f7fb;border-color:#e6e9f2}

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
.dark-theme .import-products .hero-bg {
  background: linear-gradient(135deg, #1a1a1a 0%, #232323 60%, #292929 100%);
  opacity: 1;
}
.dark-theme .import-products .hero-icon {
  background: rgba(38, 103, 255, 0.18);
  color: #93c5fd;
}

/* Dropzone */
.dark-theme .import-products .dropzone {
  background: #1f1f1f;
  border-color: #2f2f2f;
  color: #d8d8d8;
}
.dark-theme .import-products .dropzone:hover {
  background: rgba(38, 103, 255, 0.08);
  border-color: #93c5fd;
  box-shadow: 0 1px 6px rgba(38, 103, 255, 0.18);
}
.dark-theme .import-products .dropzone.is-dragover {
  background: rgba(38, 103, 255, 0.14);
  border-color: #93c5fd;
}
.dark-theme .import-products .dropzone.has-file {
  border-color: #2f2f2f;
}
.dark-theme .import-products .dz-icon {
  color: #93c5fd;
}

/* File pill */
.dark-theme .import-products .file-pill {
  background: #292929;
  border-color: #2f2f2f;
  color: #d8d8d8;
}
.dark-theme .import-products .file-name {
  color: #d8d8d8;
}

/* Example table — keep the green "required" cells (saturated text on
   pastel reads on both modes), only patch the surrounding chrome. */
.dark-theme .import-products .example-table {
  color: #d8d8d8;
}
.dark-theme .import-products .example-table thead.thead-light th {
  background: #292929;
  color: #d8d8d8;
  border-color: #2a2a2a;
}
.dark-theme .import-products .example-table th,
.dark-theme .import-products .example-table td {
  border-color: #2a2a2a;
}
.dark-theme .import-products .example-table th.req {
  background: rgba(16, 185, 129, 0.18);
  color: #6ee7b7;
  border-color: rgba(16, 185, 129, 0.35);
}
.dark-theme .import-products .badge-success-soft {
  background: rgba(16, 185, 129, 0.18);
  color: #6ee7b7;
  border-color: rgba(16, 185, 129, 0.35);
}

/* Chip grid (column guide) — chip-opt was light-gray-on-light-gray
   which becomes invisible on dark; brighten both border and text. */
.dark-theme .import-products .chip-opt {
  background: #292929;
  color: rgba(216, 216, 216, 0.85);
  border-color: #2f2f2f;
}
.dark-theme .import-products .chip-req {
  background: rgba(16, 185, 129, 0.18);
  color: #6ee7b7;
  border-color: rgba(16, 185, 129, 0.35);
}

/* "Heads up" alert (b-alert variant="light" with .border) */
.dark-theme .import-products .alert-light {
  background: #232323 !important;
  border-color: #2f2f2f !important;
  color: #d8d8d8 !important;
}
.dark-theme .import-products .tip-badge {
  background: rgba(38, 103, 255, 0.18);
  color: #93c5fd;
}

/* Bulleted notes / strong tags so the keywords stay readable. */
.dark-theme .import-products .mini-notes,
.dark-theme .import-products .mini-notes li,
.dark-theme .import-products .mini-notes strong {
  color: #d8d8d8;
}

/* Required-field asterisk and warehouse picker label sit on dark cards
   here; the scoped .font-weight-600 label has no color, so it falls back
   to whatever the surrounding card grants. The card body itself comes
   from the global .dark-theme .card rule, which is fine — but the
   v-select invalid state needs the danger color to read on dark. */
.dark-theme .import-products .invalid-feedback {
  color: #f87171 !important;
}
</style>
