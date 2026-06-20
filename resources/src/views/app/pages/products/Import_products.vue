<template>
  <div class="main-content import-products">
    <!-- Hero -->
    <div class="hero shadow-sm mb-4">
      <div class="hero-bg"></div>
      <div class="hero-body d-flex align-items-center justify-content-between flex-wrap">
        <div class="d-flex align-items-center">
          <div class="hero-icon mr-3"><lucide-icon name="upload" /></div>
          <div>
            <h3 class="mb-1">Import Products</h3>
            <div class="text-muted small">Bulk add items from an Excel file.</div>
          </div>
        </div>
        <router-link :to="{ name: 'index_products' }" class="btn btn-outline-secondary btn-sm mt-3 mt-sm-0">
          <lucide-icon name="chevron-left" /> Back to list
        </router-link>
      </div>
    </div>

    <b-card class="shadow-sm">
      <!-- Type selector (single stays outline, variant becomes solid when active) -->
      <div class="d-flex justify-content-center mb-3">
        <b-button-group size="sm" class="seg">
          <!-- Always outline-primary for Single (as requested) -->
          <b-button :variant="importType==='single' ? 'primary' : 'outline-primary'" 
            @click="switchType('single')">
            <lucide-icon class="mr-1" name="store" />Single Products
          </b-button>
          <!-- Variant toggles to solid primary when active -->
          <b-button :variant="importType==='variant' ? 'primary' : 'outline-primary'"
                    @click="switchType('variant')">
            <lucide-icon class="mr-1" name="library" />Variant Products
          </b-button>
          <!-- Service products tab -->
          <b-button :variant="importType==='service' ? 'primary' : 'outline-primary'"
                    @click="switchType('service')">
            <lucide-icon class="mr-1" name="wrench" />Service Products
          </b-button>
        </b-button-group>
      </div>

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

            <!-- Single example -->
            <div v-if="importType==='single'">
              <p class="small text-muted mb-2">
                Create one row per product. Columns in <span class="badge badge-success-soft">green</span> are required.
              </p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered example-table">
                  <thead class="thead-light">
                    <tr>
                      <th class="req">name</th>
                      <th class="req">code</th>
                      
                      <th class="req">cost</th>
                      <th class="req">category</th>
                      <th>sub_category</th>
                      <th class="req">unit</th>
                      <th class="req">Retail price</th>
                      <th>Wholesale price</th>
                      <th>Min price</th>
                      <th>brand</th>
                      <th>Stock alert</th>
                      <th>note</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Blue T-Shirt</td>
                      <td>TSHIRT-BLUE</td>
                      <td>8.00</td>
                      <td>Apparel</td>
                      <td>T-Shirts</td>
                      <td>pc</td>
                      <td>19.90</td>
                      <td>17.00</td>
                      <td>15.00</td>
                      <td>Acme</td>
                      <td>5</td>
                      <td>Summer collection</td>
                    </tr>
                    <tr>
                      <td>Coffee Mug</td>
                      <td>MUG-COF-01</td>
                      <td>2.20</td>
                      <td>Home</td>
                      <td>Kitchen</td>
                      <td>pc</td>
                      <td>6.50</td>
                      <td>6.00</td>
                      <td>5.75</td>
                      <td></td>
                      <td>0</td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <ul class="mini-notes mt-2">
                <li><strong>code</strong> must be unique across products and variants.</li>
                <li><strong>unit</strong> must already exist (use its short name if available).</li>
                <li><strong>category</strong> will be created automatically if missing.</li>
                <li><strong>sub_category</strong> is optional. Will be created under its category if missing.</li>
              </ul>
            </div>

            <!-- Variant example -->
            <div v-else-if="importType==='variant'">
              <p class="small text-muted mb-2">
                Create one row per variant. Repeat the product columns for each variant of the same product_code.
                Columns in <span class="badge badge-success-soft">green</span> are required.
              </p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered example-table">
                  <thead class="thead-light">
                    <tr>
                      <th class="req">product name</th>
                      <th class="req">product code</th>
                      <th class="req">category</th>
                      <th>sub_category</th>
                      <th class="req">unit</th>
                      <th>brand</th>
                      <th class="req">variant name</th>
                      <th class="req">variant code</th>
                      <th class="req">variant cost</th>
                      <th class="req">variant price</th>
                      <th>variant wholesale</th>
                      <th>variant min price</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>T-Shirt</td>
                      <td>TSHIRT-100</td>
                      <td>Apparel</td>
                      <td>T-Shirts</td>
                      <td>pc</td>
                      <td>Acme</td>
                      <td>Small</td>
                      <td>TSHIRT-100-S</td>
                      <td>7.50</td>
                      <td>14.90</td>
                      <td>13.00</td>
                      <td>12.00</td>
                    </tr>
                    <tr>
                      <td>T-Shirt</td>
                      <td>TSHIRT-100</td>
                      <td>Apparel</td>
                      <td>T-Shirts</td>
                      <td>pc</td>
                      <td>Acme</td>
                      <td>Medium</td>
                      <td>TSHIRT-100-M</td>
                      <td>7.50</td>
                      <td>14.90</td>
                      <td>13.00</td>
                      <td>12.00</td>
                    </tr>
                    <tr>
                      <td>T-Shirt</td>
                      <td>TSHIRT-100</td>
                      <td>Apparel</td>
                      <td>T-Shirts</td>
                      <td>pc</td>
                      <td>Acme</td>
                      <td>Large</td>
                      <td>TSHIRT-100-L</td>
                      <td>7.50</td>
                      <td>14.90</td>
                      <td>13.00</td>
                      <td>12.00</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <ul class="mini-notes mt-2">
                <li><strong>product_code</strong>  Parent product code used to group variants.</li>
                <li><strong>variant_code</strong> must be unique globally (cannot match any product or variant code).</li>
                <li><strong>unit</strong> must already exist (use its short name if available).</li>
                <li><strong>category</strong> will be created automatically if missing.</li>
                <li><strong>sub_category</strong> is optional. Will be created under its category if missing.</li>
              </ul>
            </div>

            <!-- Service products example -->
            <div v-else-if="importType==='service'">
              <p class="small text-muted mb-2">
                Create one row per service. Columns in <span class="badge badge-success-soft">green</span> are required.
              </p>
              <div class="table-responsive">
                <table class="table table-sm table-bordered example-table">
                  <thead class="thead-light">
                    <tr>
                      <th class="req">name</th>
                      <th class="req">code</th>
                      <th class="req">Retail price</th>
                      <th class="req">category</th>
                      <th>sub_category</th>
                      <th class="req">unit</th>
                      <th>Wholesale price</th>
                      <th>Min price</th>
                      <th>brand</th>
                      <th>note</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Consulting Hour</td>
                      <td>SRV-CONS-01</td>
                      <td>120.00</td>
                      <td>Services</td>
                      <td>IT Consulting</td>
                      <td>hr</td>
                      <td>100.00</td>
                      <td>90.00</td>
                      <td></td>
                      <td>Professional consulting</td>
                    </tr>
                    <tr>
                      <td>Delivery Fee</td>
                      <td>SRV-DEL-01</td>
                      <td>15.00</td>
                      <td>Services</td>
                      <td></td>
                      <td>pc</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <ul class="mini-notes mt-2">
                <li><strong>code</strong> must be unique across products and variants.</li>
                <li><strong>unit</strong> must already exist (use its short name if available).</li>
                <li><strong>category</strong> will be created automatically if missing.</li>
                <li><strong>sub_category</strong> is optional. Will be created under its category if missing.</li>
              </ul>
            </div>
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
        <b-col md="12" class="mb-4">
          <b-card class="mb-3">
            <h6 class="mb-2">Required & optional columns</h6>

            <!-- Singles -->
            <div v-if="importType==='single'">
              <div class="chip-grid">
                <span v-for="c in singlesGuide" :key="c.key"
                      class="chip" :class="c.required ? 'chip-req' : 'chip-opt'">
                  {{ c.label }}
                </span>
              </div>
              <ul class="mini-notes mt-3">
                <li><strong>code</strong> — Must be unique across products and variants.</li>
                <li><strong>unit</strong> — Unit must already exist · Use the unit short name when possible.</li>
                <li><strong>category</strong> — Will be created if missing.</li>
                <li><strong>sub_category</strong> — Optional. Will be created under its category if it doesn't exist.</li>
                <li><strong>wholesale price</strong> and <strong>min price</strong> are optional.</li>
              </ul>
            </div>

            <!-- Service products -->
            <div v-else-if="importType==='service'">
              <div class="chip-grid">
                <span v-for="c in serviceGuide" :key="c.key"
                      class="chip" :class="c.required ? 'chip-req' : 'chip-opt'">
                  {{ c.label }}
                </span>
              </div>
              <ul class="mini-notes mt-3">
                <li><strong>code</strong> — Must be unique across products and variants.</li>
                <li><strong>unit</strong> — Unit must already exist · Use the unit short name when possible.</li>
                <li><strong>category</strong> — Will be created if missing.</li>
                <li><strong>sub_category</strong> — Optional. Will be created under its category if it doesn't exist.</li>
                <li><strong>wholesale price</strong> and <strong>min price</strong> are optional. Cost is always 0 for services.</li>
              </ul>
            </div>

            <!-- Variants -->
            <div v-else>
              <div class="chip-grid">
                <span v-for="c in variantsGuide" :key="c.key"
                      class="chip" :class="c.required ? 'chip-req' : 'chip-opt'">
                  {{ c.label }}
                </span>
              </div>
              <ul class="mini-notes mt-3">
                <li><strong>product_code</strong> — Parent product code used to group variants.</li>
                <li><strong>variant_code</strong> — Variant code must be globally unique.</li>
                <li><strong>unit</strong> — Unit must already exist · Use the unit short name when possible.</li>
                <li><strong>category</strong> — Will be created if missing.</li>
                <li><strong>sub_category</strong> — Optional. Will be created under its category if it doesn't exist.</li>
                <li><strong>variant wholesale</strong> and <strong>variant min price</strong> are optional.</li>
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
  metaInfo: {
    title: "Import Products"
  },
  data() {
    return {
      // endpoints
      singleEndpoint: 'products/import/single',
      variantEndpoint: 'products/import/variants',
      serviceEndpoint: 'products/import/service',

      // default selection: keep variant active (solid) and single always outline
      importType: 'single',

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
      accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,.xlsx,.xls',

      // guides (UI-only help)
      singlesGuide: [
        { key: 'name',        label: 'name',           required: true },
        { key: 'code',        label: 'code',           required: true },
        { key: 'Retail price',label: 'Retail price',   required: true },
        { key: 'cost',        label: 'cost',           required: true },
        { key: 'category',    label: 'category',       required: true },
        { key: 'sub_category',label: 'sub_category',   required: false },
        { key: 'unit',        label: 'unit',           required: true },
        { key: 'Wholesale price', label: 'Wholesale price', required: false },
        { key: 'Min price',       label: 'Min price',       required: false },
        { key: 'brand',       label: 'brand',          required: false },
        { key: 'Stock alert', label: 'Stock alert',    required: false },
        { key: 'note',        label: 'note',           required: false }
      ],
      variantsGuide: [
        { key: 'product_name',  label: 'product_name',                 required: true },
        { key: 'product_code',  label: 'product_code',                 required: true },
        { key: 'category',      label: 'category',                     required: true },
        { key: 'sub_category',  label: 'sub_category',                 required: false },
        { key: 'unit',          label: 'unit',                         required: true },
        { key: 'brand',         label: 'brand',                        required: false },
        { key: 'variant_name',  label: 'variant_name',                 required: true },
        { key: 'variant_code',  label: 'variant_code',                 required: true },
        { key: 'variant_cost',  label: 'variant_cost',                 required: true },
        { key: 'variant_price', label: 'variant_price',                required: true },
        { key: 'variant_wholesale', label: 'variant_wholesale',        required: false },
        { key: 'variant_min_price', label: 'variant_min_price',        required: false }
      ],
      serviceGuide: [
        { key: 'name',        label: 'name',           required: true },
        { key: 'code',        label: 'code',           required: true },
        { key: 'Retail price',label: 'Retail price',   required: true },
        { key: 'category',    label: 'category',       required: true },
        { key: 'sub_category',label: 'sub_category',   required: false },
        { key: 'unit',        label: 'unit',           required: true },
        { key: 'Wholesale price', label: 'Wholesale price', required: false },
        { key: 'Min price',       label: 'Min price',       required: false },
        { key: 'brand',       label: 'brand',          required: false },
        { key: 'note',        label: 'note',           required: false }
      ]
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
      if (this.importType === 'single') return '/import/exemples/single_products.xlsx';
      if (this.importType === 'service') return '/import/exemples/service_products.xlsx';
      return '/import/exemples/variant_products.xlsx';
    }
  },
  methods: {
    // ---------- UI helpers ----------
    toast(msg, title, variant) {
      if (this.$root && this.$root.$bvToast) {
        this.$root.$bvToast.toast(msg, { title: title, variant: variant, solid: true });
      }
    },
    switchType(type) {
      this.importType = type;
      // keep file, but clear previous error panel to avoid confusion
      this.clearErrors();
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
      const msgs = [];

      if (f.size > this.maxSize) {
        msgs.push('File is too large. Please upload a file under the 20MB limit.');
      }
      const name = f.name || '';
      const ext = name.split('.').pop().toLowerCase();
      if (['xlsx','xls'].indexOf(ext) === -1) {
        msgs.push('Unsupported file type. Please upload an .xlsx or .xls file.');
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
      this.uploading = true;
      this.progress = 0;
      NProgress.start(); NProgress.set(0.2);

      try {
        var fd = new FormData();
        fd.append('products', this.file);
        const typeMap = { single: 'is_single', variant: 'is_variant', service: 'is_service' };
        fd.append('type', typeMap[this.importType] || 'is_single');

        let endpoint = this.singleEndpoint;
        if (this.importType === 'variant') endpoint = this.variantEndpoint;
        else if (this.importType === 'service') endpoint = this.serviceEndpoint;

        const self = this;
        const response = await axios.post(endpoint, fd, {
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

        this.toast('Imported successfully.', 'Success', 'success');
        this.$router.push({ name: 'index_products' });

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
     [data-v-xxxx] attribute on every selector, which beats the global
     dark-theme rules in _dark.scss. Re-declare the page's surfaces
     here (without `scoped`) so .dark-theme on <body> can actually reach
     them. -->
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

/* Tip "Heads up" alert (b-alert variant="light" with .border). The
   default light variant fills white-on-light. */
.dark-theme .import-products .alert-light {
  background: #232323 !important;
  border-color: #2f2f2f !important;
  color: #d8d8d8 !important;
}
.dark-theme .import-products .tip-badge {
  background: rgba(38, 103, 255, 0.18);
  color: #93c5fd;
}

/* Bulleted notes / strong tags should pick up the heading color so the
   key terms still read against the dark surface. */
.dark-theme .import-products .mini-notes,
.dark-theme .import-products .mini-notes li,
.dark-theme .import-products .mini-notes strong {
  color: #d8d8d8;
}
</style>
