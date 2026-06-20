<template>
  <div class="main-content import-customers">
    <!-- Hero -->
    <div class="hero shadow-sm mb-4">
      <div class="hero-bg"></div>
      <div class="hero-body d-flex align-items-center justify-content-between flex-wrap">
        <div class="d-flex align-items-center">
          <div class="hero-icon mr-3"><lucide-icon name="upload" /></div>
          <div>
            <h3 class="mb-1">Import Customers</h3>
            <div class="text-muted small">Bulk add customers from an Excel file.</div>
          </div>
        </div>
        <router-link :to="{ name: 'Customers' }" class="btn btn-outline-secondary btn-sm mt-3 mt-sm-0">
          <lucide-icon name="chevron-left" /> Back to list
        </router-link>
      </div>
    </div>

    <b-card class="shadow-sm">
      <b-row>
        <!-- Upload column -->
        <b-col md="7" class="mb-4">
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
                <b-button size="sm" variant="outline-danger" @click.stop="clearFile">
                  Remove
                </b-button>
              </div>
            </div>
          </div>

          <!-- Example format (non-technical) -->
          <b-card class="mt-3">
            <div class="d-flex align-items-center mb-2">
              <lucide-icon class="mr-2 text-primary" name="info" />
              <h6 class="mb-0">Example format</h6>
            </div>

            <p class="small text-muted mb-2">
              Create one row per customer. Columns in <span class="badge badge-success-soft">green</span> are required.
            </p>

            <div class="table-responsive">
              <table class="table table-sm table-bordered example-table">
                <thead class="thead-light">
                  <tr>
                    <th class="req">Username</th>
                    <th>firstname</th>
                    <th>lastname</th>
                    <th class="req">code (integer)</th>
                    <th>email</th>
                    <th>phone</th>
                    <th>tax_number</th>
                    <th>country</th>
                    <th>city</th>
                    <th>Address</th>
                    <th>opening_balance</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Acme Trading</td>
                    <td>Acme</td>
                    <td>Trading</td>
                    <td>10001</td>
                    <td>info@acme.com</td>
                    <td>+1 555 0123</td>
                    <td>TAX-9988</td>
                    <td>USA</td>
                    <td>New York</td>
                    <td>5th Ave, Suite 2</td>
                    <td>150.50</td>
                  </tr>
                  <tr>
                    <td>Jane Smith</td>
                    <td>Jane</td>
                    <td>Smith</td>
                    <td>10002</td>
                    <td>jane@example.com</td>
                    <td>+44 20 7946 0958</td>
                    <td></td>
                    <td>UK</td>
                    <td>London</td>
                    <td>221B Baker Street</td>
                    <td>0</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <ul class="mini-notes mt-2">
              <li><strong>code</strong> must be an integer and unique (the database column is INT).</li>
              <li><strong>Username</strong> is required (column name in file: <strong>name</strong>).</li>
              <li><strong>firstname</strong> and <strong>lastname</strong> are optional.</li>
              <li><strong>Address</strong> is the address field name expected by the backend.</li>
              <li><strong>opening_balance</strong> is optional; if present it must be numeric and represents previous dues for this customer.</li>
              <li>You can leave optional columns empty if not applicable.</li>
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

        <!-- Guide column -->
        <b-col md="5" class="mb-4">
          <b-card class="mb-3">
            <h6 class="mb-2">Required & optional columns</h6>
            <div class="chip-grid">
              <span v-for="c in columnsGuide" :key="c.key"
                    class="chip" :class="c.required ? 'chip-req' : 'chip-opt'">
                {{ c.label }}
              </span>
            </div>
            <ul class="mini-notes mt-3">
              <li><strong>code</strong> — Integer only (no letters or punctuation).</li>
              <li><strong>firstname</strong> / <strong>lastname</strong> — Optional; accepted column names.</li>
              <li><strong>email</strong> — Should be a valid email address if provided.</li>
              <li><strong>phone</strong> — Include country code when possible.</li>
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
// axios assumed globally available

export default {
  name: 'ImportCustomersPage',
  data: function () {
    return {
      endpoint: 'customers/import',

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

      // guide
      columnsGuide: [
        { key: 'name',            label: 'Username',         required: true  },
        { key: 'firstname',       label: 'firstname',        required: false },
        { key: 'lastname',        label: 'lastname',         required: false },
        { key: 'code',            label: 'code (integer)',   required: true  },
        { key: 'email',           label: 'email',            required: false },
        { key: 'phone',           label: 'phone',            required: false },
        { key: 'tax_number',      label: 'tax_number',       required: false },
        { key: 'country',         label: 'country',          required: false },
        { key: 'city',            label: 'city',             required: false },
        { key: 'adresse',         label: 'adresse',          required: false },
        { key: 'opening_balance', label: 'opening_balance',  required: false }
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
      return '/import/exemples/customers.xlsx';
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
      if (['xlsx', 'xls'].indexOf(ext) === -1) msgs.push('Unsupported file type. Please upload an .xlsx or .xls file.');

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

    // Always return ONLY errors[] when present.
// Ignore "message" completely if we have any errors.
flattenLaravelErrors: function (errors) {
  var out = [];
  if (!errors) return out;

  // array of strings
  if (Array.isArray(errors)) {
    for (var i = 0; i < errors.length; i++) {
      var v = errors[i];
      if (v != null && v !== '') out.push(String(v));
    }
    return out;
  }

  // object map: { field: [msg1, msg2], ... }
  if (typeof errors === 'object') {
    for (var k in errors) {
      if (!Object.prototype.hasOwnProperty.call(errors, k)) continue;
      var val = errors[k];
      if (Array.isArray(val)) {
        for (var j = 0; j < val.length; j++) {
          var vv = val[j];
          if (vv != null && vv !== '') out.push(String(vv));
        }
      } else if (val != null && val !== '') {
        out.push(String(val));
      }
    }
    return out;
  }

  // single string
  if (typeof errors === 'string') return [errors];

  return out;
},

// Otherwise, fall back to other shapes (messages/details/error or message alone).
collectErrorsFromResponse: function (data) {
  var out = [];
  if (!data) return out;

  // String fallback
  if (typeof data === 'string') {
    var txt = data.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    if (txt && txt.toLowerCase() !== 'validation failed') out.push(txt);
    return out;
  }

  // ✅ 1) Use errors[] only
  if (data.errors) {
    var errs = this.flattenLaravelErrors(data.errors);
    if (errs.length) return errs; // early return only errors[]
  }

  // ✅ 2) Only if no errors[] exist, use other keys
  if (Array.isArray(data.messages)) out.push(...data.messages);
  if (data.details) {
    if (Array.isArray(data.details)) out.push(...data.details);
    else if (typeof data.details === 'string') out.push(data.details);
  }
  if (typeof data.error === 'string') out.push(data.error);

  // ❌ 3) Never push "Validation failed"
  if (!out.length && typeof data.message === 'string') {
    var msg = data.message.trim();
    if (msg.toLowerCase() !== 'validation failed') out.push(msg);
  }

  // Remove duplicates & trim
  var seen = {}, filtered = [];
  for (var i = 0; i < out.length; i++) {
    var s = String(out[i]).trim();
    if (s && !seen[s]) { seen[s] = true; filtered.push(s); }
  }
  return filtered;
},




// On 422, only show payload.errors (ignore payload.message).
collectErrorsFromAxios: function (err) {
  if (err && err.response && err.response.status === 422) {
    var payload = err.response.data || {};
    var errsOnly = this.flattenLaravelErrors(payload.errors);
    if (errsOnly.length) return errsOnly; // <-- ONLY errors[]
    // fallback if server forgot errors[]
    var fallback = this.collectErrorsFromResponse(payload);
    return fallback.length ? fallback : ['Validation failed. Please check your file and try again.'];
  }

  // Non-422: try to parse whatever came back
  var payloadAny = err && err.response ? err.response.data : null;
  var parsed = this.collectErrorsFromResponse(payloadAny);
  if (parsed.length) return parsed;

  if (err && err.message) return [String(err.message)];
  return ['Something went wrong while uploading. Please try again.'];
},

onlyErrorsArray(data) {
  if (!data || !data.errors) return [];
  const e = data.errors;
  const out = [];
  if (Array.isArray(e)) {
    for (let i = 0; i < e.length; i++) if (e[i]) out.push(String(e[i]));
  } else if (typeof e === 'object') {
    Object.keys(e).forEach(k => {
      const v = e[k];
      if (Array.isArray(v)) v.forEach(m => { if (m) out.push(String(m)); });
      else if (v) out.push(String(v));
    });
  } else if (typeof e === 'string') {
    out.push(e);
  }
  const seen = {};
  return out
    .map(s => String(s).trim())
    .filter(s => s && !seen[s] && (seen[s] = 1));
},



    // Submit
    async submit() {
      if (!this.file) {
        this.errorMessages = ['Please choose a file to import.'];
        return;
      }

      this.clearErrors();
      this.uploading = true;
      this.progress = 0;
      NProgress.start(); NProgress.set(0.2);

      try {
        const fd = new FormData();
        fd.append('customers', this.file);

        const resp = await axios.post(this.endpoint, fd, {
          headers: {
            'Content-Type': 'multipart/form-data',
            'Accept': 'application/json'
          },
          onUploadProgress: (pe) => {
            if (pe && pe.total) this.progress = Math.round((pe.loaded * 100) / pe.total);
          },
          // accept 422 so we decide success/fail ourselves
          validateStatus: () => true
        });

        const data = resp && resp.data ? resp.data : {};
        const http = resp && resp.status ? resp.status : 0;

        // Fail paths: HTTP 422 or explicit status:false
        if (http === 422 || data.status === false) {
          const errs = this.onlyErrorsArray(data);
          this.errorMessages =
            errs.length
              ? errs
              : (data.message && data.message.trim().toLowerCase() !== 'validation failed'
                  ? [data.message]
                  : ['Please fix the highlighted errors in your file and try again.']);

          this.toast('Check the error list and fix your file.', 'Import failed', 'danger');
          return;
        }

        // Success
        if (Array.isArray(data.warnings) && data.warnings.length) {
          this.warningMessages = data.warnings;
        }
        const count = data.imported || 0;
        this.toast(count + ' customers imported successfully.', 'Success', 'success');
        this.$router.push({ name: 'Customers' });
      } catch (e) {
        const msg = (e && e.message) ? String(e.message) : 'Network error. Please try again.';
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
