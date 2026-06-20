<template>
  <div class="main-content import-suppliers">
    <!-- Hero -->
    <div class="hero shadow-sm mb-4">
      <div class="hero-bg"></div>
      <div class="hero-body d-flex align-items-center justify-content-between flex-wrap">
        <div class="d-flex align-items-center">
          <div class="hero-icon mr-3"><lucide-icon name="upload" /></div>
          <div>
            <h3 class="mb-1">Import Suppliers</h3>
            <div class="text-muted small">Bulk add suppliers from an Excel file.</div>
          </div>
        </div>
        <router-link :to="{ name: 'Suppliers' }" class="btn btn-outline-secondary btn-sm mt-3 mt-sm-0">
          <lucide-icon name="chevron-left" /> Back to list
        </router-link>
      </div>
    </div>

    <b-card class="shadow-sm">
      <b-row>
        <!-- Upload -->
        <b-col md="7" class="mb-4">
          <div
            class="dropzone"
            :class="{ 'is-dragover': isDragOver, 'has-file': !!file }"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
            @drop.prevent="onDrop"
            @click="browse"
          >
            <input ref="file" type="file" class="d-none" @change="onFileSelected"
                   :accept="accept" />
            <div class="dz-inner text-center">
              <div class="dz-icon mb-2"><lucide-icon name="download" /></div>
              <h5 class="mb-2">Click or drop your Excel file here</h5>
              <div class="text-muted small">Allowed formats: XLSX, XLS · Max size: 20MB</div>

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

            <p class="small text-muted mb-2">
              One row per supplier. Columns in <span class="badge badge-success-soft">green</span> are required.
            </p>

            <div class="table-responsive">
              <table class="table table-sm table-bordered example-table">
                <thead class="thead-light">
                  <tr>
                    <th class="req">name</th>
                    <th class="req">code (integer)</th>
                    <th>email</th>
                    <th>phone</th>
                    <th>tax_number</th>
                    <th>country</th>
                    <th>city</th>
                    <th>Address</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>ACME Supplies</td>
                    <td>2001</td>
                    <td>contact@acmesupplies.com</td>
                    <td>+1 555 0100</td>
                    <td>VAT-4455</td>
                    <td>USA</td>
                    <td>New York</td>
                    <td>5th Ave</td>
                  </tr>
                  <tr>
                    <td>Global Vendor</td>
                    <td>2002</td>
                    <td></td>
                    <td>+44 20 7946 0000</td>
                    <td></td>
                    <td>UK</td>
                    <td>London</td>
                    <td>221B Baker Street</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <ul class="mini-notes mt-2">
              <li><strong>code</strong> must be an integer and unique (DB column is INT).</li>
              <li><strong>name</strong> is required.</li>
              <li><strong>Address</strong> is the address field expected by the backend.</li>
            </ul>
          </b-card>

          <!-- Error panel -->
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

          <!-- Optional warnings -->
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

        <!-- Guide -->
        <b-col md="5" class="mb-4">
          <b-card class="mb-3">
            <h6 class="mb-2">Required & optional columns</h6>
            <div class="chip-grid">
              <span v-for="c in columnsGuide" :key="c.key" class="chip" :class="c.required ? 'chip-req' : 'chip-opt'">
                {{ c.label }}
              </span>
            </div>
            <ul class="mini-notes mt-3">
              <li><strong>code</strong> — Integer only.</li>
              <li><strong>email</strong> — Must be valid if provided.</li>
              <li><strong>phone</strong> — Prefer including country code.</li>
            </ul>
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
// axios assumed global

export default {
  name: 'ImportSuppliersPage',
  data: function () {
    return {
      endpoint: 'suppliers/import',

      // file state
      file: null,
      fileName: '',
      fileSize: 0,

      // ui state
      uploading: false,
      progress: 0,

      // messages
      errorMessages: [],
      warningMessages: [],

      // dnd
      isDragOver: false,

      // limits
      maxSize: 20 * 1024 * 1024, // 20MB
      accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,.xlsx,.xls',

      // guide chips
      columnsGuide: [
        { key: 'name',       label: 'name',           required: true  },
        { key: 'code',       label: 'code (integer)', required: true  },
        { key: 'email',      label: 'email',          required: false },
        { key: 'phone',      label: 'phone',          required: false },
        { key: 'tax_number', label: 'tax_number',     required: false },
        { key: 'country',    label: 'country',        required: false },
        { key: 'city',       label: 'city',           required: false },
        { key: 'adresse',    label: 'adresse',        required: false }
      ]
    };
  },
  computed: {
    canSubmit: function () {
      return !!this.file && this.errorMessages.length === 0;
    },
    prettySize: function () {
      return this.formatBytes(this.fileSize);
    },
    exampleHref: function () {
      return '/import/exemples/suppliers.xlsx';
    }
  },
  methods: {
    // UI helpers
    toast: function (msg, title, variant) {
      if (this.$root && this.$root.$bvToast) {
        this.$root.$bvToast.toast(msg, { title: title, variant: variant, solid: true });
      }
    },

    // DnD + browse
    onDragOver: function () { this.isDragOver = true; },
    onDragLeave: function () { this.isDragOver = false; },
    onDrop: function (e) {
      this.isDragOver = false;
      var f = (e && e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) ? e.dataTransfer.files[0] : null;
      if (f) this.loadFile(f);
    },
    browse: function () {
      if (this.uploading) return;
      if (this.$refs && this.$refs.file) this.$refs.file.click();
    },
    onFileSelected: function (e) {
      var f = (e && e.target && e.target.files && e.target.files[0]) ? e.target.files[0] : null;
      if (f) this.loadFile(f);
    },

    // File load + checks
    loadFile: function (f) {
      this.clearErrors();
      var msgs = [];
      if (f.size > this.maxSize) msgs.push('File is too large. Please upload a file under the 20MB limit.');
      var name = f.name || '';
      var ext = name.split('.').pop().toLowerCase();
      if (['xlsx','xls'].indexOf(ext) === -1) msgs.push('Unsupported file type. Please upload an .xlsx or .xls file.');
      if (msgs.length) {
        this.errorMessages = msgs;
        this.clearFile(false);
        return;
      }
      this.file = f;
      this.fileName = f.name;
      this.fileSize = f.size;
    },
    clearFile: function (resetInput) {
      if (typeof resetInput === 'undefined') resetInput = true;
      this.file = null; this.fileName = ''; this.fileSize = 0;
      if (resetInput && this.$refs && this.$refs.file) this.$refs.file.value = '';
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

    // errors: return ONLY errors[] if present
    onlyErrorsArray: function (data) {
      if (!data || !data.errors) return [];
      var e = data.errors;
      var out = [];
      if (Array.isArray(e)) {
        for (var i = 0; i < e.length; i++) if (e[i]) out.push(String(e[i]));
      } else if (typeof e === 'object') {
        for (var k in e) {
          if (!Object.prototype.hasOwnProperty.call(e, k)) continue;
          var v = e[k];
          if (Array.isArray(v)) {
            for (var j = 0; j < v.length; j++) if (v[j]) out.push(String(v[j]));
          } else if (v) out.push(String(v));
        }
      } else if (typeof e === 'string') {
        out.push(e);
      }
      var seen = {};
      var filtered = [];
      for (var x = 0; x < out.length; x++) {
        var s = String(out[x]).trim();
        if (s && !seen[s]) { seen[s] = true; filtered.push(s); }
      }
      return filtered;
    },

    // Submit (async/await; accept 422 in then)
    async submit () {
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
        fd.append('suppliers', this.file);

        var resp = await axios.post(this.endpoint, fd, {
          headers: {
            'Content-Type': 'multipart/form-data',
            'Accept': 'application/json'
          },
          onUploadProgress: (pe) => {
            if (pe && pe.total) this.progress = Math.round((pe.loaded * 100) / pe.total);
          },
          validateStatus: function () { return true; } // handle 422 here
        });

        var data = resp && resp.data ? resp.data : {};
        var http = resp && resp.status ? resp.status : 0;

        if (http === 422 || data.status === false) {
          var errs = this.onlyErrorsArray(data);
          if (!errs.length && data && typeof data.message === 'string' &&
              data.message.trim().toLowerCase() !== 'validation failed') {
            errs = [data.message];
          }
          if (!errs.length) {
            errs = ['Please fix the highlighted errors in your file and try again.'];
          }
          this.errorMessages = errs;
          this.toast('Check the error list and fix your file.', 'Import failed', 'danger');
          return;
        }

        // success
        if (Array.isArray(data.warnings) && data.warnings.length) {
          this.warningMessages = data.warnings;
        }
        var count = data.imported || 0;
        this.toast(count + ' suppliers imported successfully.', 'Success', 'success');
        this.$router.push({ name: 'Suppliers' });
      } catch (e) {
        var msg = (e && e.message) ? String(e.message) : 'Network error. Please try again.';
        this.errorMessages = [msg];
        this.toast('Upload failed due to a network error.', 'Error', 'danger');
      } finally {
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
