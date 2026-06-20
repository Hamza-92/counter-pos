<template>
  <div class="main-content">
    <breadcumb :page="$t('Import_Sales') || 'Import Sales'" :folder="$t('ListSales')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="create_sale" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Sale">
        <!-- Hero Header -->
        <div :style="heroStyle">
          <div :style="heroInnerStyle">
            <div :style="heroLeftStyle">
              <div :style="heroIconCircleStyle">
                <lucide-icon name="file-up" :style="heroIconStyle" />
              </div>
              <div>
                <div :style="heroTitleStyle">{{ $t('Import_Sales') || 'Import Sales' }}</div>
                <div :style="heroSubtitleStyle">
                  {{ $t('Import_Sale_Sub') || 'Upload a CSV file with product codes and quantities to create a sale in bulk.' }}
                </div>
              </div>
            </div>
            <div :style="heroRightStyle">
              <a
                href="/import/exemples/import_sales.csv"
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
                {{ $t('SaleDetails') || 'Sale Details' }}
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
                          v-model="sale.date"
                        ></b-form-input>
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Customer -->
                  <b-col md="12" class="mb-3">
                    <validation-provider name="Customer" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('Customer') + ' *'">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="sale.client_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Customer')"
                          :options="clients.map(c => ({label: c.name, value: c.id}))"
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
                          v-model="sale.warehouse_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Warehouse')"
                          :options="warehouses.map(w => ({label: w.name, value: w.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Sales Agent -->
                  <b-col md="12" class="mb-3">
                    <validation-provider name="Sales Agent">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('Sales_Agent')">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="sale.sales_agent_id"
                          :reduce="label => label.value"
                          :placeholder="$t('PleaseSelect')"
                          :options="sales_agents.map(ag => ({label: ag.name, value: ag.id}))"
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
                          v-model="sale.statut"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Status')"
                          :options="[
                            { label: 'completed', value: 'completed' },
                            { label: 'Pending', value: 'pending' }
                          ]"
                        ></v-select>
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Order Tax -->
                  <b-col md="6" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')">
                    <validation-provider
                      name="Order Tax"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('OrderTax')">
                        <b-input-group append="%">
                          <b-form-input
                            :state="getValidationState(validationContext)"
                            v-model.number="sale.tax_rate"
                            @keyup="keyup_OrderTax()"
                          ></b-form-input>
                        </b-input-group>
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Discount -->
                  <b-col md="6" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')">
                    <validation-provider
                      name="Discount"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Discount')">
                        <b-input-group :append="currentUser.currency">
                          <b-form-input
                            :state="getValidationState(validationContext)"
                            v-model.number="sale.discount"
                            @keyup="keyup_Discount()"
                          ></b-form-input>
                        </b-input-group>
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Shipping -->
                  <b-col md="12" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')">
                    <validation-provider
                      name="Shipping"
                      :rules="{ regex: /^\d*\.?\d*$/}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Shipping')">
                        <b-input-group :append="currentUser.currency">
                          <b-form-input
                            :state="getValidationState(validationContext)"
                            v-model.number="sale.shipping"
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
                        v-model="sale.notes"
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

                <!-- Error list -->
                <div v-if="errorMessages.length" :style="previewStatusStyle('error')">
                  <div style="display: flex; align-items: flex-start; width: 100%;">
                    <lucide-icon name="x" style="margin-right: 8px; margin-top: 2px; font-weight: bold;" />
                    <div style="flex: 1;">
                      <div style="margin-bottom: 4px;">{{ $t('Import_Failed_Fix_Below') || 'Import failed. Fix the issues below:' }}</div>
                      <ul style="margin: 0; padding-left: 18px; font-weight: 500;">
                        <li v-for="(err, idx) in errorMessages" :key="'err-' + idx">{{ err }}</li>
                      </ul>
                    </div>
                  </div>
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
                          <th :style="previewThStyle('right')">{{ $t('Price') }}</th>
                          <th :style="previewThStyle('right')">{{ $t('Subtotal') || 'Subtotal' }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(row, idx) in previewRows"
                          :key="'prv-' + idx"
                          :style="{ background: idx % 2 === 1 ? '#f9fafb' : '#ffffff', borderTop: '1px solid #e5e7eb' }"
                        >
                          <td :style="previewTdStyle('left')">
                            <span :style="rowNumBadgeStyle">{{ idx + 1 }}</span>
                          </td>
                          <td :style="previewTdStyle('left')">
                            <code :style="codeStyle">{{ row.code }}</code>
                          </td>
                          <td :style="previewTdStyle('left', true)">
                            {{ row.name }}
                          </td>
                          <td :style="previewTdStyle('right')">
                            {{ formatNumber(row.qty, 2) }} <span style="color: #9ca3af; font-size: 11px;">{{ row.unit }}</span>
                          </td>
                          <td :style="previewTdStyle('right')">
                            {{ formatNumber(row.price, 2) }}
                          </td>
                          <td :style="previewTdStyle('right', false, true)">
                            {{ formatNumber(row.total, 2) }}
                          </td>
                        </tr>
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
                </div>

                <!-- Empty helper when no file yet -->
                <div
                  v-if="!import_products && !previewLoading && !errorMessages.length"
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
                @click="Submit_Sale"
                :disabled="SubmitProcessing || !previewRows.length"
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
import { mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Import Sales"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      warehouses: [],
      clients: [],
      sales_agents: [],
      import_products: null,
      previewRows: [],
      previewSubtotal: 0,
      previewLoading: false,
      errorMessages: [],
      dropzoneHover: false,
      sale: {
        date: new Date().toISOString().slice(0, 10),
        statut: "completed",
        notes: "",
        client_id: "",
        warehouse_id: "",
        sales_agent_id: null,
        tax_rate: 0,
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
        alignItems: "flex-start"
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
        this.errorMessages = [this.$t("field_must_be_in_csv_format") || "File must be in CSV format"];
        this.import_products = null;
        this.previewRows = [];
        this.previewSubtotal = 0;
        return;
      }
      this.import_products = file;
      this.errorMessages = [];
      this.fetchPreview();
    },

    clearFile() {
      this.import_products = null;
      this.previewRows = [];
      this.previewSubtotal = 0;
      this.errorMessages = [];
      const input = document.getElementById("csv-file-input");
      if (input) input.value = "";
    },

    formatBytes(bytes) {
      if (!bytes && bytes !== 0) return "";
      if (bytes < 1024) return bytes + " B";
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
      return (bytes / (1024 * 1024)).toFixed(2) + " MB";
    },

    //------------------------------ Preview CSV -------------------------\\
    fetchPreview() {
      if (!this.import_products) return;
      this.previewLoading = true;
      this.previewRows = [];
      this.previewSubtotal = 0;
      this.errorMessages = [];

      const formData = new FormData();
      formData.append("products", this.import_products);

      axios
        .post("preview_import_sales", formData)
        .then(response => {
          this.previewLoading = false;
          const d = response.data || {};
          if (d.status === false) {
            this.errorMessages = this.collectErrorsFromResponse(d);
            if (!this.errorMessages.length) {
              this.errorMessages = [this.$t("CSV_Parse_Failed") || "Failed to parse CSV"];
            }
            return;
          }
          const rows = Array.isArray(d.rows) ? d.rows : [];
          this.previewRows = rows;
          this.previewSubtotal = Number(d.grand_total) || 0;
          if (!this.previewRows.length) {
            this.errorMessages = [this.$t("CSV_No_Valid_Rows") || "No valid rows were found in the CSV file"];
          }
        })
        .catch(error => {
          this.previewLoading = false;
          this.errorMessages = this.collectErrorsFromAxios(error);
        });
    },

    flattenLaravelErrors(errorsObj) {
      const out = [];
      if (!errorsObj || typeof errorsObj !== "object") return out;
      Object.keys(errorsObj).forEach(k => {
        const v = errorsObj[k];
        if (Array.isArray(v)) {
          v.forEach(m => {
            if (m) out.push(String(m));
          });
        } else if (v) {
          out.push(String(v));
        }
      });
      return out;
    },
    collectErrorsFromResponse(data) {
      const out = [];
      if (!data || typeof data !== "object") return out;
      if (Array.isArray(data.messages)) {
        data.messages.forEach(m => {
          if (m) out.push(String(m));
        });
      }
      if (data.message) {
        out.push(String(data.message));
      }
      if (data.errors) {
        out.push(...this.flattenLaravelErrors(data.errors));
      }
      if (data.insufficient && Array.isArray(data.insufficient)) {
        data.insufficient.forEach(it => {
          out.push(
            `${it.product_code}: requested ${it.requested}, available ${it.available}`
          );
        });
      }
      if (data.msg && !(data.insufficient && data.insufficient.length)) {
        out.push(String(data.msg));
      }
      if (data.details) {
        if (Array.isArray(data.details)) {
          data.details.forEach(m => {
            if (m) out.push(String(m));
          });
        } else if (typeof data.details === "string") {
          out.push(data.details);
        }
      }
      if (data.error && typeof data.error === "string") {
        out.push(data.error);
      }
      const seen = {};
      return out.filter(m => (seen[m] ? false : (seen[m] = true)));
    },
    collectErrorsFromAxios(err) {
      let payload = null;
      if (err && err.response && err.response.data !== undefined) {
        payload = err.response.data;
      } else if (err && typeof err === "object" && (err.msg !== undefined || err.details !== undefined || err.errors !== undefined || err.message !== undefined)) {
        payload = err;
      }
      const list = this.collectErrorsFromResponse(payload);
      if (list.length) return list;
      if (err && typeof err === "object" && err.message) return [String(err.message)];
      return [this.$t("An_error_occurred_while_processing_the_CSV_file") || "An error occurred while processing the CSV file."];
    },

    //--- Submit Validate Create Sale
    Submit_Sale() {
      this.errorMessages = [];
      this.$refs.create_sale.validate().then(success => {
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
        this.Create_Sale();
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

    keyup_OrderTax() {
      if (isNaN(this.sale.tax_rate) || this.sale.tax_rate === "") {
        this.sale.tax_rate = 0;
      }
    },
    keyup_Discount() {
      if (isNaN(this.sale.discount) || this.sale.discount === "") {
        this.sale.discount = 0;
      }
    },
    keyup_Shipping() {
      if (isNaN(this.sale.shipping) || this.sale.shipping === "") {
        this.sale.shipping = 0;
      }
    },

    //--------------------------------- Create Sale -------------------------\\
    Create_Sale() {
      this.SubmitProcessing = true;
      NProgress.start();
      NProgress.set(0.1);

      const data = new FormData();
      data.append("date", this.sale.date);
      data.append("client_id", this.sale.client_id);
      data.append("warehouse_id", this.sale.warehouse_id);
      if (this.sale.sales_agent_id != null && this.sale.sales_agent_id !== "") {
        data.append("sales_agent_id", this.sale.sales_agent_id);
      }
      data.append("statut", this.sale.statut);
      data.append("notes", this.sale.notes);
      data.append("tax_rate", this.sale.tax_rate);
      data.append("discount", this.sale.discount);
      data.append("shipping", this.sale.shipping);
      data.append("products", this.import_products);

      axios
        .post("store_import_sales", data)
        .then(response => {
          NProgress.done();
          this.errorMessages = [];
          this.makeToast("success", this.$t("Successfully_Imported"), this.$t("Success"));
          this.SubmitProcessing = false;
          this.$router.push({ name: "index_sales" });
        })
        .catch(error => {
          NProgress.done();
          this.errorMessages = this.collectErrorsFromAxios(error);
          this.makeToast(
            "danger",
            this.$t("Check_the_error_list_and_fix_your_file") || "Check the error list below and fix your file.",
            this.$t("Failed")
          );
          this.SubmitProcessing = false;
        });
    },

    GetElements() {
      axios
        .get("get_import_sales")
        .then(response => {
          this.clients = response.data.clients;
          this.warehouses = response.data.warehouses;
          this.sales_agents = response.data.sales_agents || [];
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
