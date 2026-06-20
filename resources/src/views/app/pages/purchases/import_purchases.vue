<template>
  <div class="main-content">
    <breadcumb :page="$t('Import_Purchases')" :folder="$t('ListPurchases')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="create_purchase" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Purchase">
        <!-- Hero Header -->
        <div :style="heroStyle">
          <div :style="heroInnerStyle">
            <div :style="heroLeftStyle">
              <div :style="heroIconCircleStyle">
                <lucide-icon name="file-up" :style="heroIconStyle" />
              </div>
              <div>
                <div :style="heroTitleStyle">{{ $t('Import_Purchases') }}</div>
                <div :style="heroSubtitleStyle">
                  {{ $t('Import_Purchase_Sub') || 'Upload a CSV file with product codes and quantities to create a purchase in bulk.' }}
                </div>
              </div>
            </div>
            <div :style="heroRightStyle">
              <a
                href="/import/exemples/import_purchases.csv"
                :style="downloadBtnStyle"
                download
              >
                <lucide-icon name="download" />
                <span style="margin-left: 6px;">{{ $t('Download_exemple') }}</span>
              </a>
            </div>
          </div>
        </div>

        <b-row>
          <!-- Left: Form -->
          <b-col lg="5" md="12" sm="12">
            <div :style="cardStyle">
              <div :style="cardHeaderStyle">
                <lucide-icon name="receipt" style="font-size: 16px; margin-right: 8px;" />
                {{ $t('PurchaseDetails') || 'Purchase Details' }}
              </div>
              <div style="padding: 20px;">
                <b-row>
                  <!-- date -->
                  <b-col md="12" class="mb-3">
                    <validation-provider
                      name="date"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('date') + ' *'">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          type="date"
                          v-model="purchase.date"
                        ></b-form-input>
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Supplier -->
                  <b-col md="12" class="mb-3">
                    <validation-provider name="Supplier" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('Supplier') + ' *'">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="purchase.supplier_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Supplier')"
                          :options="suppliers.map(s => ({label: s.name, value: s.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- warehouse -->
                  <b-col md="12" class="mb-3">
                    <validation-provider name="warehouse" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('warehouse') + ' *'">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="purchase.warehouse_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Warehouse')"
                          :options="warehouses.map(w => ({label: w.name, value: w.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Status -->
                  <b-col md="12" class="mb-3">
                    <validation-provider name="Status" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('Status') + ' *'">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="purchase.statut"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Status')"
                          :options="[
                            {label: 'received', value: 'received'},
                            {label: 'pending', value: 'pending'},
                            {label: 'ordered', value: 'ordered'}
                          ]"
                        ></v-select>
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Order Tax -->
                  <b-col md="6" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_purchase')">
                    <validation-provider
                      name="Order Tax"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('OrderTax')">
                        <b-input-group append="%">
                          <b-form-input
                            :state="getValidationState(validationContext)"
                            v-model.number="purchase.tax_rate"
                            @keyup="keyup_OrderTax()"
                          ></b-form-input>
                        </b-input-group>
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Discount -->
                  <b-col md="6" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_purchase')">
                    <validation-provider
                      name="Discount"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Discount')">
                        <b-input-group :append="currentUser.currency">
                          <b-form-input
                            :state="getValidationState(validationContext)"
                            v-model.number="purchase.discount"
                            @keyup="keyup_Discount()"
                          ></b-form-input>
                        </b-input-group>
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Shipping -->
                  <b-col md="12" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_purchase')">
                    <validation-provider
                      name="Shipping"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Shipping')">
                        <b-input-group :append="currentUser.currency">
                          <b-form-input
                            :state="getValidationState(validationContext)"
                            v-model.number="purchase.shipping"
                            @keyup="keyup_Shipping()"
                          ></b-form-input>
                        </b-input-group>
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Notes -->
                  <b-col md="12">
                    <b-form-group :label="$t('Note')">
                      <textarea
                        v-model="purchase.notes"
                        rows="3"
                        class="form-control"
                        :placeholder="$t('Afewwords')"
                      ></textarea>
                    </b-form-group>
                  </b-col>
                </b-row>
              </div>
            </div>
          </b-col>

          <!-- Right: CSV Upload + Preview -->
          <b-col lg="7" md="12" sm="12">
            <div :style="cardStyle">
              <div :style="cardHeaderStyle">
                <lucide-icon name="clipboard-list" style="font-size: 16px; margin-right: 8px;" />
                {{ $t('CSV_Import') || 'CSV Import' }}
              </div>
              <div style="padding: 20px;">
                <!-- Dropzone -->
                <label
                  for="csv-file-input"
                  :style="dropzoneStyle"
                  @dragover.prevent="onDragOver"
                  @dragleave.prevent="onDragLeave"
                  @drop.prevent="onDrop"
                >
                  <input
                    id="csv-file-input"
                    type="file"
                    accept=".csv,text/csv"
                    @change="onFileSelected"
                    style="display: none;"
                  />
                  <div style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <div :style="dropzoneIconStyle">
                      <lucide-icon name="cloud-sun" v-if="!import_products" />
                      <lucide-icon name="file-text" v-else />
                    </div>
                    <div v-if="!import_products">
                      <div style="font-size: 15px; font-weight: 600; color: #1f2937;">
                        {{ $t('Click_Or_Drop_CSV') || 'Click to browse or drop your CSV file here' }}
                      </div>
                      <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                        {{ $t('Accepted_Format_CSV') || 'Only .csv files are supported · semicolon (;) separator' }}
                      </div>
                    </div>
                    <div v-else style="text-align: center;">
                      <div style="font-size: 14px; font-weight: 600; color: #1f2937;">
                        {{ import_products.name }}
                      </div>
                      <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                        {{ formatBytes(import_products.size) }}
                      </div>
                      <button
                        type="button"
                        @click.stop.prevent="clearFile"
                        :style="clearFileBtnStyle"
                      >
                        <lucide-icon name="x" style="margin-right: 4px;" />
                        {{ $t('Remove') || 'Remove' }}
                      </button>
                    </div>
                  </div>
                </label>

                <!-- Loading -->
                <div v-if="previewLoading" :style="previewStatusStyle('loading')">
                  <div class="spinner sm spinner-primary" style="display: inline-block; margin-right: 10px;"></div>
                  {{ $t('Parsing_CSV') || 'Parsing and validating CSV...' }}
                </div>

                <!-- Error -->
                <div v-if="previewError" :style="previewStatusStyle('error')">
                  <lucide-icon name="x" style="margin-right: 8px; font-weight: bold;" />
                  {{ previewError }}
                </div>

                <!-- Preview Table -->
                <div v-if="previewRows.length" style="margin-top: 20px;">
                  <div :style="previewHeaderStyle">
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <lucide-icon name="check" style="font-size: 18px;" />
                      <span>{{ $t('Preview') || 'Preview' }}</span>
                      <span :style="previewBadgeStyle">
                        {{ previewRows.length }} {{ $t('items') || 'items' }}
                      </span>
                    </div>
                    <div style="font-size: 12px; opacity: 0.92;">
                      {{ $t('Review_Before_Submit') || 'Review the items below before submitting' }}
                    </div>
                  </div>

                  <div :style="previewTableWrapStyle">
                    <table :style="previewTableStyle">
                      <thead>
                        <tr>
                          <th :style="previewThStyle('left', 40)">#</th>
                          <th :style="previewThStyle('left')">{{ $t('Code') || 'Code' }}</th>
                          <th :style="previewThStyle('left')">{{ $t('product_name') || $t('Product_Name') || 'Product' }}</th>
                          <th :style="previewThStyle('right')">{{ $t('Quantity') }}</th>
                          <th :style="previewThStyle('right')">{{ $t('Cost') }}</th>
                          <th :style="previewThStyle('right')">{{ $t('Subtotal') || 'Subtotal' }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <template v-for="(row, idx) in previewRows">
                          <tr
                            :key="'prv-' + idx"
                            :style="{ background: idx % 2 === 1 ? '#f9fafb' : '#ffffff', borderTop: '1px solid #e5e7eb' }"
                          >
                            <td :style="previewTdStyle('left')">
                              <span :style="rowNumBadgeStyle">{{ idx + 1 }}</span>
                            </td>
                            <td :style="previewTdStyle('left')">
                              <code :style="codeStyle">{{ row.code }}</code>
                              <div v-if="row.is_batch_tracked" :style="batchPillStyle">
                                <lucide-icon name="package" style="margin-right: 3px;" />
                                {{ $t('Batches') || 'Batches' }}
                              </div>
                            </td>
                            <td :style="previewTdStyle('left', true)">
                              {{ row.name }}
                            </td>
                            <td :style="previewTdStyle('right')">
                              {{ formatNumber(row.qty, 2) }} <span style="color: #9ca3af; font-size: 11px;">{{ row.unit }}</span>
                            </td>
                            <td :style="previewTdStyle('right')">
                              {{ formatNumber(row.cost, 2) }}
                            </td>
                            <td :style="previewTdStyle('right', false, true)">
                              {{ formatNumber(row.total, 2) }}
                            </td>
                          </tr>
                          <tr v-if="row.is_batch_tracked" :key="'batch-' + idx">
                            <td colspan="6" style="padding: 0; background: #ffffff;">
                              <div :style="batchPanelStyle">
                                <div :style="batchPanelHeaderStyle">
                                  <div style="display: flex; align-items: center; gap: 8px;">
                                    <lucide-icon name="package" style="font-size: 14px;" />
                                    <span>{{ $t('Batches') || 'Batches' }}</span>
                                    <span :style="batchCountBadgeStyle">
                                      {{ (row.batches || []).length }} {{ $t('items') || 'items' }}
                                    </span>
                                  </div>
                                  <button
                                    type="button"
                                    @click="add_batch(row)"
                                    :style="addBatchBtnStyle"
                                  >
                                    <lucide-icon name="plus" style="margin-right: 4px;" />
                                    {{ $t('Add') || 'Add' }}
                                  </button>
                                </div>

                                <div v-if="!row.batches || row.batches.length === 0" :style="batchEmptyStyle">
                                  <lucide-icon name="info" style="margin-right: 6px;" />
                                  {{ $t('Click_Add_To_Start') || 'Click "Add" to create a batch' }}
                                </div>

                                <table v-else style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                  <thead>
                                    <tr style="background: #eef2ff;">
                                      <th :style="batchThStyle">{{ $t('Batch_No') }} *</th>
                                      <th :style="batchThStyle">{{ $t('Mfg_Date') }}</th>
                                      <th :style="batchThStyle">{{ $t('Expiry_Date') }}</th>
                                      <th :style="batchThRightStyle">{{ $t('Quantity') }} *</th>
                                      <th :style="batchThRightStyle">{{ $t('Cost') }}</th>
                                      <th :style="batchThStyle" style="width: 40px;"></th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr
                                      v-for="(b, bIdx) in row.batches"
                                      :key="'b-' + idx + '-' + bIdx"
                                      :style="{ background: bIdx % 2 === 1 ? '#f8faff' : '#ffffff', borderTop: '1px solid #e0e7ff' }"
                                    >
                                      <td :style="batchTdStyle">
                                        <b-form-input
                                          size="sm"
                                          type="text"
                                          v-model="b.batch_no"
                                          :placeholder="$t('Batch_No')"
                                          :style="batchInputStyle"
                                        ></b-form-input>
                                      </td>
                                      <td :style="batchTdStyle">
                                        <b-form-input
                                          size="sm"
                                          type="date"
                                          v-model="b.mfg_date"
                                          :style="batchInputStyle"
                                        ></b-form-input>
                                      </td>
                                      <td :style="batchTdStyle">
                                        <b-form-input
                                          size="sm"
                                          type="date"
                                          v-model="b.expiry_date"
                                          :style="batchInputStyle"
                                        ></b-form-input>
                                      </td>
                                      <td :style="batchTdStyle">
                                        <b-form-input
                                          size="sm"
                                          type="text"
                                          inputmode="decimal"
                                          lang="en"
                                          pattern="[0-9]*[.,]?[0-9]*"
                                          :value="b.qty"
                                          @input="val => onBatchNumberInput(b, 'qty', val)"
                                          placeholder="0"
                                          :style="[batchInputStyle, { textAlign: 'right' }]"
                                        ></b-form-input>
                                      </td>
                                      <td :style="batchTdStyle">
                                        <b-form-input
                                          size="sm"
                                          type="text"
                                          inputmode="decimal"
                                          lang="en"
                                          pattern="[0-9]*[.,]?[0-9]*"
                                          :value="b.unit_cost"
                                          @input="val => onBatchNumberInput(b, 'unit_cost', val)"
                                          :placeholder="String(row.cost)"
                                          :style="[batchInputStyle, { textAlign: 'right' }]"
                                        ></b-form-input>
                                      </td>
                                      <td :style="batchTdStyle" style="text-align: center;">
                                        <button
                                          type="button"
                                          @click="remove_batch(row, bIdx)"
                                          :style="removeBatchBtnStyle"
                                          :title="$t('Del') || 'Remove'"
                                        >
                                          <lucide-icon name="x" />
                                        </button>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>

                                <div v-if="batchQtyMismatch(row)" :style="batchWarnStyle">
                                  <lucide-icon name="info" style="margin-right: 6px;" />
                                  {{ $t('Total_batch_qty_mismatch') || 'Total batch quantity does not match the line quantity' }}
                                  ({{ formatNumber(batchTotalQty(row), 2) }} / {{ formatNumber(row.qty, 2) }})
                                </div>
                              </div>
                            </td>
                          </tr>
                        </template>
                      </tbody>
                      <tfoot>
                        <tr :style="tfootRowStyle">
                          <td :colspan="5" :style="tfootLabelStyle">
                            {{ $t('Subtotal') || 'Subtotal' }}
                          </td>
                          <td :style="tfootValueStyle">
                            {{ formatNumber(previewSubtotal, 2) }}
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>

                  <div v-if="hasBatchValidationErrors" :style="globalBatchWarnStyle">
                    <lucide-icon name="info" style="margin-right: 6px; font-size: 16px;" />
                    {{ firstBatchErrorDetail }}
                  </div>
                </div>

                <!-- Empty helper when no file yet -->
                <div
                  v-if="!import_products && !previewLoading && !previewError"
                  :style="emptyHintStyle"
                >
                  <lucide-icon name="info" style="font-size: 22px; color: #4f46e5;" />
                  <div>
                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 2px;">
                      {{ $t('CSV_Format_Hint_Title') || 'Expected CSV format' }}
                    </div>
                    <div style="font-size: 12px; color: #6b7280;">
                      {{ $t('CSV_Format_Hint_Body') || 'Columns: productcode;qty — use the example file as a reference.' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Submit bar -->
            <div :style="submitBarStyle">
              <div style="flex: 1;">
                <div v-if="previewRows.length" style="font-size: 13px; color: #6b7280;">
                  <strong style="color: #1f2937;">{{ previewRows.length }}</strong>
                  {{ $t('items_ready') || 'items ready to import' }} ·
                  <strong style="color: #4f46e5;">{{ formatNumber(previewSubtotal, 2) }}</strong>
                </div>
                <div v-else style="font-size: 13px; color: #6b7280;">
                  {{ $t('Upload_CSV_To_Preview') || 'Upload a CSV file to preview items before submitting' }}
                </div>
              </div>
              <b-button
                variant="primary"
                @click="Submit_Purchase"
                :disabled="SubmitProcessing || !previewRows.length || hasBatchValidationErrors"
                :style="submitBtnStyle"
              >
                <lucide-icon name="check" style="margin-right: 6px;" />
                {{ $t('submit') }}
              </b-button>
              <div v-if="SubmitProcessing" class="spinner sm spinner-primary" style="margin-left: 10px;"></div>
            </div>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>
  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Import Purchase"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      warehouses: [],
      suppliers: [],
      import_products: null,
      previewRows: [],
      previewSubtotal: 0,
      previewLoading: false,
      previewError: "",
      dropzoneHover: false,
      purchase: {
        id: "",
        date: new Date().toISOString().slice(0, 10),
        statut: "received",
        notes: "",
        supplier_id: "",
        warehouse_id: "",
        tax_rate: 0,
        TaxNet: 0,
        shipping: 0,
        discount: 0
      }
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),

    // Hero
    heroStyle() {
      return {
        background: "linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%)",
        borderRadius: "14px",
        padding: "22px 26px",
        color: "#fff",
        marginBottom: "20px",
        boxShadow: "0 10px 25px rgba(79, 70, 229, 0.25)"
      };
    },
    heroInnerStyle() {
      return {
        display: "flex",
        flexWrap: "wrap",
        alignItems: "center",
        justifyContent: "space-between",
        gap: "16px"
      };
    },
    heroLeftStyle() {
      return { display: "flex", alignItems: "center", gap: "16px", flex: "1 1 auto", minWidth: "260px" };
    },
    heroRightStyle() {
      return { display: "flex", alignItems: "center", gap: "10px" };
    },
    heroIconCircleStyle() {
      return {
        width: "56px",
        height: "56px",
        borderRadius: "14px",
        background: "rgba(255,255,255,0.18)",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        flexShrink: 0
      };
    },
    heroIconStyle() {
      return { fontSize: "26px", color: "#fff" };
    },
    heroTitleStyle() {
      return { fontSize: "22px", fontWeight: "700", lineHeight: "1.2" };
    },
    heroSubtitleStyle() {
      return { fontSize: "13px", opacity: "0.9", marginTop: "4px", maxWidth: "560px" };
    },
    downloadBtnStyle() {
      return {
        display: "inline-flex",
        alignItems: "center",
        padding: "9px 16px",
        borderRadius: "10px",
        background: "rgba(255,255,255,0.2)",
        color: "#fff",
        fontWeight: "600",
        fontSize: "13px",
        textDecoration: "none",
        border: "1px solid rgba(255,255,255,0.3)",
        transition: "background 0.2s"
      };
    },

    // Card
    cardStyle() {
      return {
        background: "#ffffff",
        border: "1px solid #e5e7eb",
        borderRadius: "12px",
        overflow: "hidden",
        marginBottom: "16px",
        boxShadow: "0 1px 3px rgba(0,0,0,0.04)"
      };
    },
    cardHeaderStyle() {
      return {
        display: "flex",
        alignItems: "center",
        padding: "12px 18px",
        background: "linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%)",
        borderBottom: "1px solid #e5e7eb",
        fontSize: "13px",
        fontWeight: "700",
        color: "#374151",
        textTransform: "uppercase",
        letterSpacing: "0.4px"
      };
    },

    // Dropzone
    dropzoneStyle() {
      const hover = this.dropzoneHover;
      return {
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        padding: "28px 20px",
        border: `2px dashed ${hover ? "#4f46e5" : "#cbd5e1"}`,
        borderRadius: "12px",
        background: hover
          ? "linear-gradient(135deg, #eef2ff 0%, #faf5ff 100%)"
          : "linear-gradient(135deg, #f8faff 0%, #ffffff 100%)",
        cursor: "pointer",
        transition: "all 0.2s ease",
        minHeight: "150px",
        margin: "0"
      };
    },
    dropzoneIconStyle() {
      return {
        width: "58px",
        height: "58px",
        borderRadius: "14px",
        background: "linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)",
        color: "#fff",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        fontSize: "24px",
        boxShadow: "0 6px 14px rgba(79, 70, 229, 0.35)"
      };
    },
    clearFileBtnStyle() {
      return {
        marginTop: "10px",
        padding: "4px 12px",
        fontSize: "12px",
        background: "#fef2f2",
        color: "#b91c1c",
        border: "1px solid #fecaca",
        borderRadius: "8px",
        cursor: "pointer",
        fontWeight: "600"
      };
    },

    // Preview header
    previewHeaderStyle() {
      return {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        padding: "10px 16px",
        background: "linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)",
        color: "#fff",
        fontSize: "13px",
        fontWeight: "700",
        borderRadius: "10px 10px 0 0"
      };
    },
    previewBadgeStyle() {
      return {
        fontSize: "11px",
        fontWeight: "600",
        background: "rgba(255,255,255,0.22)",
        padding: "2px 10px",
        borderRadius: "10px",
        marginLeft: "4px"
      };
    },
    previewTableWrapStyle() {
      return {
        border: "1px solid #e0e7ff",
        borderTop: "none",
        borderRadius: "0 0 10px 10px",
        overflow: "hidden",
        background: "#ffffff"
      };
    },
    previewTableStyle() {
      return {
        width: "100%",
        borderCollapse: "collapse",
        fontSize: "13px"
      };
    },
    codeStyle() {
      return {
        fontFamily: "monospace",
        fontSize: "12px",
        background: "#f3f4f6",
        color: "#3730a3",
        padding: "2px 8px",
        borderRadius: "5px",
        fontWeight: "600"
      };
    },
    rowNumBadgeStyle() {
      return {
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        width: "24px",
        height: "24px",
        borderRadius: "6px",
        background: "#eef2ff",
        color: "#4f46e5",
        fontSize: "11px",
        fontWeight: "700"
      };
    },
    tfootRowStyle() {
      return {
        background: "linear-gradient(135deg, #eef2ff 0%, #faf5ff 100%)",
        borderTop: "2px solid #c7d2fe"
      };
    },
    tfootLabelStyle() {
      return {
        padding: "12px 14px",
        textAlign: "right",
        fontWeight: "700",
        fontSize: "13px",
        color: "#374151",
        textTransform: "uppercase",
        letterSpacing: "0.3px"
      };
    },
    tfootValueStyle() {
      return {
        padding: "12px 14px",
        textAlign: "right",
        fontWeight: "700",
        fontSize: "15px",
        color: "#4f46e5"
      };
    },

    // Empty hint
    emptyHintStyle() {
      return {
        marginTop: "16px",
        padding: "14px 18px",
        background: "linear-gradient(135deg, #f8faff 0%, #ffffff 100%)",
        border: "1px dashed #c7d2fe",
        borderRadius: "10px",
        display: "flex",
        alignItems: "center",
        gap: "12px"
      };
    },

    // Submit bar
    submitBarStyle() {
      return {
        display: "flex",
        alignItems: "center",
        gap: "14px",
        padding: "14px 18px",
        background: "#ffffff",
        border: "1px solid #e5e7eb",
        borderRadius: "12px",
        boxShadow: "0 1px 3px rgba(0,0,0,0.04)",
        marginTop: "4px"
      };
    },
    submitBtnStyle() {
      return {
        padding: "9px 22px",
        fontWeight: "600",
        background: "linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)",
        border: "none",
        borderRadius: "10px",
        color: "#fff",
        boxShadow: "0 4px 10px rgba(79, 70, 229, 0.3)"
      };
    },

    // ---- Batch UI styles ----
    batchPillStyle() {
      return {
        display: "inline-flex",
        alignItems: "center",
        marginTop: "4px",
        padding: "1px 7px",
        background: "#eef2ff",
        color: "#4f46e5",
        fontSize: "10px",
        fontWeight: "700",
        borderRadius: "10px",
        textTransform: "uppercase",
        letterSpacing: "0.3px"
      };
    },
    batchPanelStyle() {
      return {
        margin: "6px 10px 12px 10px",
        border: "1px solid #e0e7ff",
        borderRadius: "8px",
        overflow: "hidden",
        background: "linear-gradient(180deg, #f8faff 0%, #ffffff 100%)"
      };
    },
    batchPanelHeaderStyle() {
      return {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        padding: "8px 12px",
        background: "linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)",
        color: "#fff",
        fontSize: "12px",
        fontWeight: "700",
        textTransform: "uppercase",
        letterSpacing: "0.3px"
      };
    },
    batchCountBadgeStyle() {
      return {
        fontSize: "10px",
        fontWeight: "600",
        background: "rgba(255,255,255,0.22)",
        padding: "1px 8px",
        borderRadius: "10px"
      };
    },
    addBatchBtnStyle() {
      return {
        padding: "4px 10px",
        fontSize: "11px",
        fontWeight: "600",
        background: "#ffffff",
        color: "#4f46e5",
        border: "none",
        borderRadius: "6px",
        cursor: "pointer",
        display: "inline-flex",
        alignItems: "center"
      };
    },
    batchEmptyStyle() {
      return {
        padding: "12px 14px",
        textAlign: "center",
        fontSize: "12px",
        color: "#6b7280",
        background: "#ffffff"
      };
    },
    batchThStyle() {
      return {
        padding: "7px 10px",
        textAlign: "left",
        color: "#3730a3",
        fontWeight: "700",
        textTransform: "uppercase",
        fontSize: "10px",
        letterSpacing: "0.3px"
      };
    },
    batchThRightStyle() {
      return {
        padding: "7px 10px",
        textAlign: "right",
        color: "#3730a3",
        fontWeight: "700",
        textTransform: "uppercase",
        fontSize: "10px",
        letterSpacing: "0.3px"
      };
    },
    batchTdStyle() {
      return {
        padding: "6px 8px",
        verticalAlign: "middle"
      };
    },
    batchInputStyle() {
      return {
        fontSize: "12px",
        padding: "5px 8px",
        height: "30px",
        borderRadius: "6px"
      };
    },
    removeBatchBtnStyle() {
      return {
        width: "26px",
        height: "26px",
        padding: "0",
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        background: "#fef2f2",
        color: "#b91c1c",
        border: "1px solid #fecaca",
        borderRadius: "6px",
        cursor: "pointer",
        fontSize: "12px"
      };
    },
    batchWarnStyle() {
      return {
        padding: "8px 12px",
        background: "#fef3c7",
        color: "#92400e",
        fontSize: "12px",
        fontWeight: "600",
        borderTop: "1px solid #fde68a",
        display: "flex",
        alignItems: "center"
      };
    },
    globalBatchWarnStyle() {
      return {
        marginTop: "12px",
        padding: "10px 14px",
        background: "#fef3c7",
        color: "#92400e",
        border: "1px solid #fde68a",
        borderRadius: "10px",
        fontSize: "13px",
        fontWeight: "600",
        display: "flex",
        alignItems: "center"
      };
    },

    // ---- Batch validation ----
    hasBatchValidationErrors() {
      if (!Array.isArray(this.previewRows)) return false;
      for (const row of this.previewRows) {
        if (!row.is_batch_tracked) continue;
        const batches = Array.isArray(row.batches) ? row.batches : [];
        if (batches.length === 0) return true;
        if (this.batchQtyMismatch(row)) return true;
        for (const b of batches) {
          if (!b.batch_no || String(b.batch_no).trim() === "") return true;
          const q = Number(b.qty);
          if (!(q > 0)) return true;
        }
      }
      return false;
    },
    firstBatchErrorDetail() {
      if (!Array.isArray(this.previewRows)) return "";
      for (const row of this.previewRows) {
        if (!row.is_batch_tracked) continue;
        const batches = Array.isArray(row.batches) ? row.batches : [];
        if (batches.length === 0) {
          return (this.$t("Batch_Required_For_Item") || "Add at least one batch for") + " " + row.name;
        }
        for (const b of batches) {
          if (!b.batch_no || String(b.batch_no).trim() === "") {
            return (this.$t("Batch_No_Required_For") || "Batch No is required for") + " " + row.name;
          }
          const q = Number(b.qty);
          if (!(q > 0)) {
            return (this.$t("Batch_Qty_Required_For") || "Batch quantity must be greater than 0 for") + " " + row.name;
          }
        }
        if (this.batchQtyMismatch(row)) {
          return (this.$t("Total_batch_qty_mismatch") || "Total batch quantity does not match the line quantity") + " — " + row.name;
        }
      }
      return "";
    }
  },

  methods: {
    previewThStyle(align, width) {
      return {
        padding: "11px 14px",
        textAlign: align,
        fontSize: "11px",
        fontWeight: "700",
        textTransform: "uppercase",
        letterSpacing: "0.4px",
        color: "#374151",
        background: "#f9fafb",
        borderBottom: "1px solid #e5e7eb",
        width: width ? width + "px" : undefined
      };
    },
    previewTdStyle(align, strong, accent) {
      const base = {
        padding: "10px 14px",
        textAlign: align,
        fontSize: "13px",
        color: accent ? "#4f46e5" : "#1f2937",
        verticalAlign: "middle"
      };
      if (strong || accent) base.fontWeight = "600";
      return base;
    },

    previewStatusStyle(kind) {
      if (kind === "loading") {
        return {
          marginTop: "16px",
          padding: "12px 16px",
          background: "#eef2ff",
          border: "1px solid #c7d2fe",
          borderRadius: "10px",
          color: "#3730a3",
          fontWeight: "600",
          fontSize: "13px",
          display: "flex",
          alignItems: "center"
        };
      }
      return {
        marginTop: "16px",
        padding: "12px 16px",
        background: "#fef2f2",
        border: "1px solid #fecaca",
        borderRadius: "10px",
        color: "#991b1b",
        fontWeight: "600",
        fontSize: "13px",
        display: "flex",
        alignItems: "center"
      };
    },

    //------------------------------ File handlers -------------------------\\
    onFileSelected(e) {
      const file = e.target.files[0];
      if (!file) return;
      this.handleFile(file);
    },

    onDragOver() {
      this.dropzoneHover = true;
    },

    onDragLeave() {
      this.dropzoneHover = false;
    },

    onDrop(e) {
      this.dropzoneHover = false;
      const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (!file) return;
      this.handleFile(file);
    },

    handleFile(file) {
      const name = file.name || "";
      const ext = name.split(".").pop().toLowerCase();
      if (ext !== "csv") {
        this.previewError = this.$t("field_must_be_in_csv_format") || "File must be in CSV format";
        this.import_products = null;
        this.previewRows = [];
        this.previewSubtotal = 0;
        return;
      }
      this.import_products = file;
      this.previewError = "";
      this.fetchPreview();
    },

    clearFile() {
      this.import_products = null;
      this.previewRows = [];
      this.previewSubtotal = 0;
      this.previewError = "";
      const input = document.getElementById("csv-file-input");
      if (input) input.value = "";
    },

    formatBytes(bytes) {
      if (!bytes && bytes !== 0) return "";
      if (bytes < 1024) return bytes + " B";
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
      return (bytes / (1024 * 1024)).toFixed(2) + " MB";
    },

    //------------------------------ Batch handling -------------------------\\
    add_batch(row) {
      if (!Array.isArray(row.batches)) {
        this.$set(row, "batches", []);
      }
      row.batches.push({
        batch_no: "",
        expiry_date: null,
        mfg_date: null,
        qty: "",
        unit_cost: ""
      });
    },

    remove_batch(row, idx) {
      if (Array.isArray(row.batches)) {
        row.batches.splice(idx, 1);
      }
    },

    batchTotalQty(row) {
      if (!Array.isArray(row.batches)) return 0;
      return row.batches.reduce((sum, b) => {
        const n = Number(b.qty);
        return sum + (Number.isFinite(n) ? n : 0);
      }, 0);
    },

    batchQtyMismatch(row) {
      const rowQty = Number(row.qty) || 0;
      const total = this.batchTotalQty(row);
      return Math.abs(total - rowQty) > 0.0001;
    },

    onBatchNumberInput(batchRow, field, raw) {
      let s = raw == null ? "" : String(raw);
      s = s.replace(",", ".");
      s = s.replace(/[^0-9.]/g, "");
      const firstDot = s.indexOf(".");
      if (firstDot !== -1) {
        s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, "");
      }
      this.$set(batchRow, field, s);
    },

    //------------------------------ Preview CSV -------------------------\\
    fetchPreview() {
      if (!this.import_products) return;
      this.previewLoading = true;
      this.previewRows = [];
      this.previewSubtotal = 0;
      this.previewError = "";

      const formData = new FormData();
      formData.append("products", this.import_products);

      axios
        .post("preview_import_purchases", formData)
        .then(response => {
          this.previewLoading = false;
          const d = response.data || {};
          if (d.status === false) {
            this.previewError = d.msg || this.$t("CSV_Parse_Failed") || "Failed to parse CSV";
            return;
          }
          const rows = Array.isArray(d.rows) ? d.rows : [];
          this.previewRows = rows.map(r => {
            return Object.assign({}, r, { batches: [] });
          });
          this.previewSubtotal = Number(d.subtotal) || 0;
          if (!this.previewRows.length) {
            this.previewError = this.$t("CSV_No_Valid_Rows") || "No valid rows were found in the CSV file";
          }
        })
        .catch(error => {
          this.previewLoading = false;
          const msg =
            (error && error.response && error.response.data && error.response.data.msg) ||
            this.$t("CSV_Parse_Failed") ||
            "Failed to parse CSV";
          this.previewError = msg;
        });
    },

    //--- Submit Validate Create Purchase
    Submit_Purchase() {
      this.$refs.create_purchase.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
          return;
        }
        if (!this.import_products) {
          this.makeToast(
            "danger",
            this.$t("field_must_be_in_csv_format"),
            this.$t("Failed")
          );
          return;
        }
        if (!this.previewRows.length) {
          this.makeToast(
            "danger",
            this.$t("CSV_No_Valid_Rows") || "No valid rows were found in the CSV file",
            this.$t("Failed")
          );
          return;
        }
        this.Create_Purchase();
      });
    },

    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : (number == null ? "0" : number.toString())
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    //---------- keyup OrderTax
    keyup_OrderTax() {
      if (isNaN(this.purchase.tax_rate) || this.purchase.tax_rate == "") {
        this.purchase.tax_rate = 0;
      }
    },

    //---------- keyup Discount
    keyup_Discount() {
      if (isNaN(this.purchase.discount) || this.purchase.discount == "") {
        this.purchase.discount = 0;
      }
    },

    //---------- keyup Shipping
    keyup_Shipping() {
      if (isNaN(this.purchase.shipping) || this.purchase.shipping == "") {
        this.purchase.shipping = 0;
      }
    },

    //--------------------------------- Create Purchase -------------------------\\
    Create_Purchase() {
      this.SubmitProcessing = true;
      NProgress.start();
      NProgress.set(0.1);

      const data = new FormData();
      data.append("date", this.purchase.date);
      data.append("supplier_id", this.purchase.supplier_id);
      data.append("warehouse_id", this.purchase.warehouse_id);
      data.append("statut", this.purchase.statut);
      data.append("notes", this.purchase.notes);
      data.append("tax_rate", this.purchase.tax_rate);
      data.append("discount", this.purchase.discount);
      data.append("shipping", this.purchase.shipping);
      data.append("products", this.import_products);

      // Include batches keyed by productcode for batch-tracked products.
      const batchesByCode = {};
      for (const row of this.previewRows) {
        if (!row.is_batch_tracked) continue;
        const cleaned = (row.batches || [])
          .filter(b => b && b.batch_no && String(b.batch_no).trim() !== "" && Number(b.qty) > 0)
          .map(b => ({
            batch_no: String(b.batch_no).trim(),
            expiry_date: b.expiry_date || null,
            mfg_date: b.mfg_date || null,
            qty: Number(b.qty),
            unit_cost: b.unit_cost === "" || b.unit_cost == null ? null : Number(b.unit_cost)
          }));
        if (cleaned.length) {
          batchesByCode[row.code] = cleaned;
        }
      }
      data.append("batches_by_code", JSON.stringify(batchesByCode));

      axios
        .post("store_import_purchases", data)
        .then(response => {
          NProgress.done();
          this.makeToast(
            "success",
            this.$t("Successfully_Imported"),
            this.$t("Success")
          );
          this.SubmitProcessing = false;
          this.$router.push({ name: "index_purchases" });
        })
        .catch(error => {
          NProgress.done();
          this.makeToast(
            "danger",
            "An error occurred while processing the CSV file.",
            this.$t("Failed")
          );
          this.SubmitProcessing = false;
        });
    },

    //---------------------------------------Get Elements Purchase ------------------------------\\
    GetElements() {
      axios
        .get("get_import_purchases")
        .then(response => {
          this.suppliers = response.data.suppliers;
          this.warehouses = response.data.warehouses;
          this.isLoading = false;
        })
        .catch(() => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  created() {
    this.GetElements();
  }
};
</script>

<style scoped>
.main-content {
  width: 100%;
}

/* Make v-select and form controls feel consistent */
/deep/ .vs__dropdown-toggle {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  padding: 3px 4px;
  min-height: 38px;
}

/deep/ .form-control {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

/deep/ .form-control:focus {
  border-color: #7c3aed;
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}

/deep/ .input-group-text {
  border-radius: 0 8px 8px 0;
  background: #f9fafb;
  border-color: #e5e7eb;
}

/deep/ .form-group label {
  font-weight: 600;
  font-size: 13px;
  color: #374151;
  margin-bottom: 6px;
}
</style>
