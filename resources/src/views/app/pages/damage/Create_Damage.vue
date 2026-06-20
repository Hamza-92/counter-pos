<template>
  <div class="main-content">
    <breadcumb :page="$t('Create_Damage')" :folder="$t('Damages')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="Create_damage" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Damage">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>

                <b-modal hide-footer id="open_scan" size="md" :title="$t('Scan_Barcode')">
                  <qrcode-scanner :qrbox="250" :fps="10" style="width: 100%; height: calc(100vh - 56px);" @result="onScan" />
                </b-modal>

                <!-- warehouse -->
                <b-col md="6" class="mb-3">
                  <validation-provider name="warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('warehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        :disabled="details.length > 0"
                        @input="Selected_Warehouse"
                        v-model="damage.warehouse_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- date  -->
                <b-col lg="6" md="6" sm="12">
                  <validation-provider name="date" :rules="{ required: true}" v-slot="validationContext">
                    <b-form-group :label="$t('date') + ' ' + '*'"><b-form-input :state="getValidationState(validationContext)" aria-describedby="date-feedback" type="date" v-model="damage.date"/></b-form-group>
                    <b-form-invalid-feedback id="OrderTax-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                  </validation-provider>
                </b-col>

                <!-- Product -->
                <b-col md="12" class="mb-5">
                  <h6>{{$t('ProductName')}}</h6>
                  <div id="autocomplete" class="autocomplete">
                    <div class="input-with-icon">
                      <img src="/assets_setup/scan.png" :alt="$t('Scan')" class="scan-icon" @click="showModal">
                      <input :placeholder="$t('Scan_Search_Product_by_Code_Name')" @input='e => search_input = e.target.value' @keyup="search(search_input)" @focus="handleFocus" @blur="handleBlur" ref="product_autocomplete" class="autocomplete-input" />
                    </div>
                    <ul class="autocomplete-result-list" v-show="focused">
                      <li class="autocomplete-result" v-for="product_fil in product_filter" :key="product_fil.id + '_' + product_fil.product_variant_id" @mousedown="SearchProduct(product_fil)">{{getResultValue(product_fil)}}</li>
                    </ul>
                  </div>
                </b-col>

                <!-- Products -->
                <b-col md="12">
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="bg-gray-300">
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">{{$t('CodeProduct')}}</th>
                          <th scope="col">{{$t('ProductName')}}</th>
                          <th scope="col">{{$t('CurrentStock')}}</th>
                          <th scope="col">{{$t('Qty')}}</th>
                          <th scope="col" class="text-center"><i class="fa fa-trash"></i></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="details.length <=0"><td colspan="6">{{$t('NodataAvailable')}}</td></tr>
                        <template v-for="detail in details">
                        <tr :key="'r-' + detail.detail_id">
                          <td>{{detail.detail_id}}</td>
                          <td>{{detail.code}}</td>
                          <td>
                            ({{detail.name}})
                            <div v-if="detail.is_batch_tracked" class="mt-1">
                              <span class="badge" style="background:#eef2ff; color:#4f46e5; font-weight:600; letter-spacing:0.3px;">
                                <lucide-icon name="package" style="margin-right:3px;" />{{ $t('Batches') }}
                              </span>
                            </div>
                          </td>
                          <td>
                            <span class="badge badge-outline-warning">{{detail.current}} {{detail.unit}}</span>
                          </td>
                          <td>
                            <div class="quantity">
                              <b-input-group>
                                <b-input-group-prepend>
                                  <span class="btn btn-primary btn-sm" @click="decrement(detail ,detail.detail_id)">-</span>
                                </b-input-group-prepend>
                                <input class="form-control" @keyup="Verified_Qty(detail,detail.detail_id)" :min="0.00" :max="detail.current" v-model.number="detail.quantity">
                                <b-input-group-append>
                                  <span class="btn btn-primary btn-sm" @click="increment(detail ,detail.detail_id)">+</span>
                                </b-input-group-append>
                              </b-input-group>
                            </div>
                          </td>
                          <td>
                            <a @click="Remove_Product(detail.detail_id)" class="btn btn-icon btn-sm" :title="$t('Delete')"><lucide-icon class="text-25 text-danger" name="x" /></a>
                          </td>
                        </tr>

                        <!-- Batch picker row for batch-tracked products -->
                        <tr v-if="detail.is_batch_tracked" :key="'b-' + detail.detail_id" style="background: transparent;">
                          <td colspan="6" style="padding: 0; border-top: 0;">
                            <div style="margin: 6px 8px 14px 8px; border: 1px solid #e0e7ff; border-radius: 10px; overflow: visible; background: #f8faff;">
                              <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 14px; background: #4f46e5; color: #fff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; border-radius: 10px 10px 0 0;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                  <lucide-icon name="package" />
                                  <span>{{ $t('Batches') }}</span>
                                  <span style="background: rgba(255,255,255,0.22); padding: 1px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">
                                    {{ (detail.batches || []).length }} {{ $t('items') || 'items' }}
                                    <span v-if="(detail.batches || []).length" style="margin-left: 4px;">
                                      · {{ $t('Total') || 'Total' }}: {{ formatNumber(batch_total_qty(detail), 2) }} / {{ formatNumber(Number(detail.quantity) || 0, 2) }}
                                    </span>
                                  </span>
                                </div>
                                <button type="button" @click="add_batch_to_detail(detail)" style="padding: 4px 10px; font-size: 11px; font-weight: 600; background: #ffffff; color: #4f46e5; border: none; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center;">
                                  <lucide-icon name="plus" style="margin-right: 4px;" />{{ $t('Add') || 'Add' }}
                                </button>
                              </div>

                              <div v-if="detail.batches_loading" style="padding: 10px 14px; text-align: center; color: #6b7280; font-size: 12px;">
                                <div class="spinner sm spinner-primary" style="display: inline-block; margin-right: 8px;"></div>
                                {{ $t('Loading') || 'Loading...' }}
                              </div>

                              <div v-else-if="!(detail.available_batches && detail.available_batches.length)" style="padding: 12px 14px; text-align: center; color: #b91c1c; font-size: 12px; background: #fef2f2;">
                                <lucide-icon name="info" style="margin-right: 6px;" />
                                {{ $t('No_Batches_Available') || 'No available batches for this product in the selected warehouse' }}
                              </div>

                              <div v-else-if="!detail.batches || detail.batches.length === 0" style="padding: 12px 14px; text-align: center; color: #6b7280; font-size: 12px;">
                                <lucide-icon name="info" style="margin-right: 6px;" />
                                {{ $t('Click_Add_To_Pick_Batch') || 'Click "Add" to pick a batch' }}
                              </div>

                              <table v-else style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                <thead>
                                  <tr style="background: #eef2ff;">
                                    <th style="padding: 7px 10px; text-align: left; color: #3730a3; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.3px;">{{ $t('Batch_No') }} *</th>
                                    <th style="padding: 7px 10px; text-align: left; color: #3730a3; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.3px;">{{ $t('Expiry_Date') }}</th>
                                    <th style="padding: 7px 10px; text-align: right; color: #3730a3; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.3px;">{{ $t('Available') || 'Available' }}</th>
                                    <th style="padding: 7px 10px; text-align: right; color: #3730a3; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.3px;">{{ $t('Quantity') }} *</th>
                                    <th style="padding: 7px 10px; width: 40px;"></th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <tr v-for="(b, bIdx) in detail.batches" :key="'db-' + detail.detail_id + '-' + bIdx" :style="{ background: bIdx % 2 === 1 ? '#f8faff' : '#ffffff', borderTop: '1px solid #e0e7ff' }">
                                    <td style="padding: 6px 8px; vertical-align: middle; min-width: 220px;">
                                      <v-select
                                        :value="b.product_batch_id"
                                        :options="(detail.available_batches || []).map(ab => ({
                                          label: ab.batch_no + (ab.expiry_date ? ' · ' + ($t('Exp') || 'Exp') + ' ' + ab.expiry_date : '') + ' · ' + ab.qty_available,
                                          value: ab.id
                                        }))"
                                        :reduce="opt => opt.value"
                                        :placeholder="$t('Choose_Batch') || 'Choose batch'"
                                        :append-to-body="true"
                                        @input="val => on_batch_select(detail, bIdx, val)"
                                        style="font-size: 12px;"
                                      />
                                    </td>
                                    <td style="padding: 6px 8px; vertical-align: middle;">
                                      <span v-if="b.expiry_date" :style="expiry_pill_style(b.expiry_date)">
                                        {{ b.expiry_date }}
                                      </span>
                                      <span v-else style="color: #9ca3af;">—</span>
                                    </td>
                                    <td style="padding: 6px 8px; vertical-align: middle; text-align: right; font-weight: 600; color: #3730a3;">
                                      {{ formatNumber(Number(b.qty_available) || 0, 2) }}
                                    </td>
                                    <td style="padding: 6px 8px; vertical-align: middle;">
                                      <b-form-input
                                        size="sm"
                                        type="text"
                                        inputmode="decimal"
                                        lang="en"
                                        pattern="[0-9]*[.,]?[0-9]*"
                                        :value="b.qty"
                                        @input="val => on_batch_qty_input(b, val)"
                                        placeholder="0"
                                        :style="{
                                          textAlign: 'right',
                                          fontSize: '12px',
                                          padding: '5px 8px',
                                          height: '30px',
                                          borderRadius: '6px',
                                          borderColor: (Number(b.qty) > (Number(b.qty_available) || 0)) ? '#fca5a5' : undefined
                                        }"
                                      ></b-form-input>
                                    </td>
                                    <td style="padding: 6px 8px; vertical-align: middle; text-align: center;">
                                      <button type="button" @click="remove_batch_from_detail(detail, bIdx)" style="width: 26px; height: 26px; padding: 0; display: inline-flex; align-items: center; justify-content: center; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                        <lucide-icon name="x" />
                                      </button>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>

                              <div v-if="detail.batches && detail.batches.length && batch_qty_mismatch(detail)" style="padding: 8px 14px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600; border-top: 1px solid #fde68a; display: flex; align-items: center;">
                                <lucide-icon name="info" style="margin-right: 6px;" />
                                {{ $t('Total_batch_qty_mismatch') || 'Total batch quantity does not match the line quantity' }}
                                ({{ formatNumber(batch_total_qty(detail), 2) }} / {{ formatNumber(Number(detail.quantity) || 0, 2) }})
                              </div>
                            </div>
                          </td>
                        </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </b-col>
                <b-col md="12">
                  <b-form-group :label="$t('Note')" class="mt-4">
                    <textarea v-model="damage.notes" rows="4" class="form-control" :placeholder="$t('Afewwords')"></textarea>
                  </b-form-group>
                </b-col>
                <b-col md="12" v-if="hasBatchValidationErrors">
                  <div style="padding: 10px 14px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 10px; font-size: 13px; font-weight: 600; display: flex; align-items: center; margin-bottom: 14px;">
                    <lucide-icon name="info" style="margin-right: 8px; font-size: 16px;" />
                    {{ firstBatchErrorMessage }}
                  </div>
                </b-col>

                <b-col md="12">
                  <b-form-group>
                    <b-button variant="primary" :disabled="SubmitProcessing || hasBatchValidationErrors" @click="Submit_Damage"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                    <div v-once class="typo__p" v-if="SubmitProcessing"><div class="spinner sm spinner-primary mt-3"></div></div>
                  </b-form-group>
                </b-col>

              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: { title: "Create Damage" },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],
      isLoading: true,
      SubmitProcessing:false,
      warehouses: [],
      products: [],
      details: [],
      damage: { id: "", notes: "", warehouse_id: "", date: new Date().toISOString().slice(0, 10) },
      product: { id: "", code: "", current: "", quantity: 1, name: "", product_id: "", detail_id: "", product_variant_id: "", unit: "", is_batch_tracked: false, batches: [], available_batches: [], batches_loading: false },
      symbol: ""
    };
  },
  computed: {
    hasBatchValidationErrors() {
      if (!Array.isArray(this.details)) return false;
      for (const d of this.details) {
        if (!d || !d.is_batch_tracked) continue;
        const batches = Array.isArray(d.batches) ? d.batches : [];
        if (batches.length === 0) return true;
        const seen = new Set();
        for (const b of batches) {
          if (!b.product_batch_id) return true;
          const q = Number(b.qty);
          if (!(q > 0)) return true;
          if (q > (Number(b.qty_available) || 0) + 0.01) return true;
          if (seen.has(b.product_batch_id)) return true;
          seen.add(b.product_batch_id);
        }
        const total = Math.round(batches.reduce((s, b) => s + (Number(b.qty) || 0), 0) * 10000) / 10000;
        const target = Math.round((Number(d.quantity) || 0) * 10000) / 10000;
        if (Math.abs(total - target) > 0.01) return true;
      }
      return false;
    },
    firstBatchErrorMessage() {
      if (!Array.isArray(this.details)) return "";
      for (const d of this.details) {
        if (!d || !d.is_batch_tracked) continue;
        const batches = Array.isArray(d.batches) ? d.batches : [];
        const label = d.name || d.code || "";
        if (batches.length === 0) {
          return (this.$t("Select_Batch_Required_For") || "Select a batch for") + " " + label;
        }
        const seen = new Set();
        for (const b of batches) {
          if (!b.product_batch_id) return (this.$t("Select_Batch_Required_For") || "Select a batch for") + " " + label;
          const q = Number(b.qty);
          if (!(q > 0)) return (this.$t("Batch_Qty_Required_For") || "Batch quantity must be greater than 0 for") + " " + label;
          if (q > (Number(b.qty_available) || 0) + 0.01) return (this.$t("Batch_Qty_Exceeds_Available") || "Batch quantity exceeds available stock for") + " " + label;
          if (seen.has(b.product_batch_id)) return (this.$t("Duplicate_Batch_Selected") || "The same batch is selected twice for") + " " + label;
          seen.add(b.product_batch_id);
        }
        const total = Math.round(batches.reduce((s, b) => s + (Number(b.qty) || 0), 0) * 10000) / 10000;
        const target = Math.round((Number(d.quantity) || 0) * 10000) / 10000;
        if (Math.abs(total - target) > 0.01) {
          return (this.$t("Total_batch_qty_mismatch") || "Total batch quantity does not match the line quantity") + " (" + total + " / " + target + ") — " + label;
        }
      }
      return "";
    }
  },
  watch: {
    "details": {
      deep: true,
      handler(details) {
        if (!Array.isArray(details)) return;
        for (const d of details) {
          if (!d || !d.is_batch_tracked) continue;
          const batches = Array.isArray(d.batches) ? d.batches : [];
          if (batches.length !== 1) continue;
          const b = batches[0];
          const lineQty = Number(d.quantity);
          const batchQty = Number(b.qty);
          if (Number.isFinite(lineQty) && lineQty > 0 && batchQty !== lineQty) {
            this.$set(b, "qty", lineQty);
          }
        }
      }
    }
  },
  methods: {
    handleFocus() { this.focused = true },
    handleBlur() { this.focused = false },
    showModal() { this.$bvModal.show('open_scan'); },
    onScan(decodedText) { const code = decodedText; this.search_input = code; this.search(); this.$bvModal.hide('open_scan'); },
    search(){
      if (this.timer) { clearTimeout(this.timer); this.timer = null; }
      if (this.search_input.length < 2) { return this.product_filter= []; }
      if (this.damage.warehouse_id != "" &&  this.damage.warehouse_id != null) {
        this.timer = setTimeout(() => {
          const product_filter = this.products.filter(product => product.code === this.search_input || product.barcode.includes(this.search_input));
          if(product_filter.length === 1){ this.SearchProduct(product_filter[0]) }
          else {
            this.product_filter =  this.products.filter(product => {
              return (
                product.name.toLowerCase().includes(this.search_input.toLowerCase()) ||
                product.code.toLowerCase().includes(this.search_input.toLowerCase()) ||
                product.barcode.toLowerCase().includes(this.search_input.toLowerCase())
              );
            });
            if (this.product_filter.length <= 0) { this.makeToast("warning", this.$t("Product_Not_Found"), this.$t("Warning")); }
          }
        }, 800);
      } else {
        this.makeToast("warning", this.$t("SelectWarehouse"), this.$t("Warning"));
      }
    },
    SearchProduct(result) {
      this.product = {};
      if (this.details.length > 0 && this.details.some(detail => detail.code === result.code)) {
        this.makeToast("warning", this.$t("AlreadyAdd"), this.$t("Warning"));
      } else {
        this.product.code = result.code;
        this.product.current = result.qte;
        this.product.quantity = result.qte < 1 ? result.qte : 1;
        this.product.product_variant_id = result.product_variant_id;
        this.Get_Product_Details(result.id, result.product_variant_id);
      }
      this.search_input= '';
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },
    getResultValue(result) { return result.code + " " + "(" + result.name + ")"; },
    Submit_Damage() { this.$refs.Create_damage.validate().then(success => { if (!success) { this.makeToast("danger", this.$t("Please_fill_the_form_correctly"), this.$t("Failed")); } else { this.Create_Damage(); } }); },
    getValidationState({ dirty, validated, valid = null }) { return dirty || validated ? valid : null; },
    makeToast(variant, msg, title) { this.$root.$bvToast.toast(msg, { title: title, variant: variant, solid: true }); },
    Selected_Warehouse(value) {
      this.search_input= '';
      this.product_filter = [];
      this.Get_Products_By_Warehouse(value);
      if (Array.isArray(this.details)) {
        for (const d of this.details) {
          if (d && d.is_batch_tracked) {
            this.$set(d, "batches", []);
            this.fetch_batches_for_detail(d);
          }
        }
      }
    },
    Get_Products_By_Warehouse(id) {
      NProgress.start(); NProgress.set(0.1);
      axios.get("get_Products_by_warehouse/" + id + "?stock=" + 0 + "&product_service=" + 0 + "&product_combo=" + 1)
        .then(response => { this.products = response.data; NProgress.done(); })
        .catch(() => {});
    },
    add_product() {
      if (this.details.length > 0) { this.detail_order_id(); }
      else if (this.details.length === 0) { this.product.detail_id = 1; }
      this.details.push(this.product);
      const last = this.details[this.details.length - 1];
      if (last && last.is_batch_tracked) {
        this.fetch_batches_for_detail(last);
      }
    },

    //----------------------------------------- Batch handling -------------------------\\
    fetch_batches_for_detail(detail) {
      if (!detail) return;
      if (!("batches_loading" in detail)) this.$set(detail, "batches_loading", false);
      if (!("available_batches" in detail)) this.$set(detail, "available_batches", []);
      if (!Array.isArray(detail.batches)) this.$set(detail, "batches", []);
      if (!detail.is_batch_tracked) {
        this.$set(detail, "batches_loading", false);
        return;
      }
      const wid = this.damage && this.damage.warehouse_id;
      const productId = detail.product_id || detail.id;
      if (!wid || !productId) {
        this.$set(detail, "batches_loading", false);
        return;
      }
      const variantSeg = (detail.product_variant_id != null && detail.product_variant_id !== "")
        ? detail.product_variant_id
        : 0;
      // Existing batches were already debited on save → add their qty back to qty_available
      // so the user can re-edit allocations on the edit page without false over-allocation.
      const existingQtyById = {};
      for (const b of (Array.isArray(detail.batches) ? detail.batches : [])) {
        if (b && b.product_batch_id != null) {
          existingQtyById[b.product_batch_id] = (existingQtyById[b.product_batch_id] || 0) + (Number(b.qty) || 0);
        }
      }
      this.$set(detail, "batches_loading", true);
      axios
        .get(`batches_for_damage/${productId}/${wid}/${variantSeg}`, { timeout: 15000 })
        .then(response => {
          const list = (response && response.data && Array.isArray(response.data.batches))
            ? response.data.batches.map(ab => ({
                ...ab,
                qty_available: (Number(ab.qty_available) || 0) + (existingQtyById[ab.id] || 0),
              }))
            : [];
          this.$set(detail, "available_batches", list);
          if (Array.isArray(detail.batches)) {
            for (const b of detail.batches) {
              if (b && b.product_batch_id != null) {
                const ab = list.find(x => x.id === b.product_batch_id);
                this.$set(b, "qty_available", ab ? (Number(ab.qty_available) || 0) : (existingQtyById[b.product_batch_id] || 0));
              }
            }
          }
        })
        .catch(() => { this.$set(detail, "available_batches", []); })
        .then(() => { this.$set(detail, "batches_loading", false); });
    },

    add_batch_to_detail(detail) {
      if (!Array.isArray(detail.batches)) this.$set(detail, "batches", []);
      detail.batches.push({
        product_batch_id: null,
        batch_no: "",
        expiry_date: null,
        qty_available: 0,
        qty: detail.batches.length === 0 ? (Number(detail.quantity) || 0) : 0,
      });
    },

    remove_batch_from_detail(detail, idx) {
      if (!Array.isArray(detail.batches)) return;
      detail.batches.splice(idx, 1);
    },

    on_batch_select(detail, idx, batchId) {
      const list = Array.isArray(detail.available_batches) ? detail.available_batches : [];
      const row = detail.batches[idx];
      if (!row) return;
      const ab = list.find(x => x.id === batchId);
      this.$set(row, "product_batch_id", ab ? ab.id : null);
      this.$set(row, "batch_no", ab ? ab.batch_no : "");
      this.$set(row, "expiry_date", ab ? ab.expiry_date : null);
      this.$set(row, "qty_available", ab ? Number(ab.qty_available) || 0 : 0);
    },

    on_batch_qty_input(b, val) {
      const num = parseFloat(String(val).replace(",", "."));
      this.$set(b, "qty", Number.isFinite(num) ? num : 0);
    },

    batch_total_qty(detail) {
      if (!detail || !Array.isArray(detail.batches)) return 0;
      return detail.batches.reduce((sum, b) => sum + (Number(b.qty) || 0), 0);
    },

    batch_qty_mismatch(detail) {
      if (!detail || !detail.is_batch_tracked) return false;
      if (!Array.isArray(detail.batches) || detail.batches.length === 0) return false;
      const total = this.batch_total_qty(detail);
      const target = Number(detail.quantity) || 0;
      return Math.abs(total - target) > 0.01;
    },

    expiry_pill_style(dateStr) {
      const base = { display: "inline-block", padding: "2px 8px", fontSize: "11px", fontWeight: "600", borderRadius: "10px" };
      if (!dateStr) return Object.assign({}, base, { background: "#f3f4f6", color: "#6b7280" });
      const today = new Date(); today.setHours(0, 0, 0, 0);
      const exp = new Date(dateStr);
      if (isNaN(exp.getTime())) return Object.assign({}, base, { background: "#f3f4f6", color: "#6b7280" });
      exp.setHours(0, 0, 0, 0);
      const diffDays = Math.round((exp - today) / (1000 * 60 * 60 * 24));
      if (diffDays < 0) return Object.assign({}, base, { background: "#fee2e2", color: "#991b1b" });
      if (diffDays <= 30) return Object.assign({}, base, { background: "#fef3c7", color: "#92400e" });
      return Object.assign({}, base, { background: "#dcfce7", color: "#166534" });
    },

    buildSubmitDetails() {
      return (this.details || []).map(d => {
        const out = Object.assign({}, d);
        delete out.available_batches;
        delete out.batches_loading;
        if (d.is_batch_tracked && Array.isArray(d.batches)) {
          out.batches = d.batches
            .filter(b => b && b.product_batch_id && Number(b.qty) > 0)
            .map(b => ({ product_batch_id: Number(b.product_batch_id), qty: Number(b.qty) || 0 }));
        } else {
          delete out.batches;
        }
        return out;
      });
    },
    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (isNaN(detail.quantity)) { this.details[i].quantity = detail.current; }
          if (detail.type == "sub" && detail.quantity > detail.current) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            this.details[i].quantity = detail.current;
          } else { this.details[i].quantity = detail.quantity; }
        }
      }
      this.$forceUpdate();
    },
    increment(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.type == "sub") {
            if (detail.quantity + 1 > detail.current) { this.makeToast("warning", this.$t("LowStock"), this.$t("Warning")); }
            else { this.formatNumber(this.details[i].quantity++, 2); }
          } else { this.formatNumber(this.details[i].quantity++, 2); }
        }
      }
      this.$forceUpdate();
    },
    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.quantity - 1 > 0) {
            if (detail.type == "sub" && detail.quantity - 1 > detail.current) { this.makeToast("warning", this.$t("LowStock"), this.$t("Warning")); }
            else { this.formatNumber(this.details[i].quantity--, 2); }
          }
        }
      }
      this.$forceUpdate();
    },
    formatNumber(number, dec) { const value = (typeof number === "string" ? number : number.toString()).split("."); if (dec <= 0) return value[0]; let formated = value[1] || ""; if (formated.length > dec) return `${value[0]}.${formated.substr(0, dec)}`; while (formated.length < dec) formated += "0"; return `${value[0]}.${formated}`; },
    Remove_Product(id) { for (var i = 0; i < this.details.length; i++) { if (id === this.details[i].detail_id) { this.details.splice(i, 1); } } },
    verifiedForm() {
      if (this.details.length <= 0) { this.makeToast("warning", this.$t("AddProductToList"), this.$t("Warning")); return false; }
      var count = 0; for (var i = 0; i < this.details.length; i++) { if (this.details[i].quantity == "" || this.details[i].quantity === 0) { count += 1; } }
      if (count > 0) { this.makeToast("warning", this.$t("AddQuantity"), this.$t("Warning")); return false; }
      if (this.hasBatchValidationErrors) {
        this.makeToast("danger", this.firstBatchErrorMessage || (this.$t("Total_batch_qty_mismatch") || "Batch quantities are invalid"), this.$t("Failed") || "Failed");
        return false;
      }
      return true;
    },
    Create_Damage() {
      if (this.verifiedForm()) {
        this.SubmitProcessing = true; NProgress.start(); NProgress.set(0.1);
        axios.post("damages", { warehouse_id: this.damage.warehouse_id, date: this.damage.date, notes: this.damage.notes, details: this.buildSubmitDetails() })
          .then(() => { NProgress.done(); this.SubmitProcessing = false; this.$router.push({ name: "index_damage" }); this.makeToast("success", this.$t("Successfully_Created"), this.$t("Success")); })
          .catch(error => { NProgress.done(); if(error.errors && error.errors.details && error.errors.details.length){ this.makeToast("danger", error.errors.details[0], this.$t("Failed")); } else { this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed")); } this.SubmitProcessing = false; });
      }
    },
    detail_order_id() { this.product.detail_id = 0; var len = this.details.length; this.product.detail_id = this.details[len - 1].detail_id + 1; },
    Get_Product_Details(product_id, variant_id) {
      axios.get("/show_product_data/" + product_id +"/"+ variant_id).then(response => {
        this.product.product_id = response.data.id;
        this.product.name = response.data.name;
        this.product.type = "sub"; // always subtract for damage
        this.product.unit = response.data.unit;
        this.$set(this.product, "is_batch_tracked", !!response.data.is_batch_tracked);
        this.$set(this.product, "batches", []);
        this.$set(this.product, "available_batches", []);
        this.$set(this.product, "batches_loading", false);
        this.add_product();
      });
    },
    Get_Elements() {
      axios.get("damages/create").then(response => { this.warehouses = response.data.warehouses; this.isLoading = false; })
        .catch(() => { setTimeout(() => { this.isLoading = false; }, 500); });
    }
  },
  created() { this.Get_Elements(); }
};
</script>

<style>
  .input-with-icon { display: flex; align-items: center; }
  .scan-icon { width: 50px; height: 50px; margin-right: 8px; cursor: pointer; }
</style>


