<template>
  <div class="main-content">
    <breadcumb
      :page="isEdit ? $t('Edit_Service_Job') : $t('Create_Service_Job')"
      :folder="$t('Service_Maintenance')"
    />

    <div class="mb-3">
      <router-link to="/app/service/jobs" class="btn btn-light back-btn">
        <lucide-icon name="arrow-left" />
        <span>{{ $t('Back_to_Service_Jobs') || 'Back to Service Jobs' }}</span>
      </router-link>
    </div>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="card">
      <div class="card-body">
        <!-- Job header summary (edit mode) -->
        <div v-if="isEdit && jobMeta" class="repair-summary mb-3">
          <div class="rs-block">
            <div class="rs-label">{{ $t('Reference') }}</div>
            <div class="rs-value">{{ jobMeta.Ref }}</div>
          </div>
          <div class="rs-block">
            <div class="rs-label">{{ $t('Status') }}</div>
            <div class="rs-value">
              <span class="badge" :class="statusBadgeClass(jobMeta.status)">
                {{ $t(statusLabel(jobMeta.status)) || jobMeta.status }}
              </span>
            </div>
          </div>
          <div class="rs-block">
            <div class="rs-label">{{ $t('Total') }}</div>
            <div class="rs-value">{{ currencySymbol }}{{ formatNumber(totals.total_amount) }}</div>
          </div>
          <div class="rs-block">
            <div class="rs-label">{{ $t('Paid') }}</div>
            <div class="rs-value text-success">{{ currencySymbol }}{{ formatNumber(totals.paid_amount) }}</div>
          </div>
          <div class="rs-block">
            <div class="rs-label">{{ $t('Balance_Due') || 'Balance Due' }}</div>
            <div class="rs-value" :class="totals.balance_due > 0 ? 'text-danger' : 'text-success'">
              {{ currencySymbol }}{{ formatNumber(totals.balance_due) }}
            </div>
          </div>
          <div class="rs-block">
            <div class="rs-label">{{ $t('Payment_Status') || 'Payment' }}</div>
            <div class="rs-value">
              <span class="badge" :class="paymentBadgeClass(jobMeta.payment_status)">
                {{ $t(jobMeta.payment_status || 'unpaid') }}
              </span>
            </div>
          </div>
        </div>

        <validation-observer ref="Create_Service_Job">
          <b-form @submit.prevent="submit">
            <b-tabs v-model="activeTab" content-class="mt-3" pills>

              <!-- ============== TAB 1: Intake ============== -->
              <b-tab :title="$t('Intake') || 'Intake'" active>
                <b-row>
                  <b-col md="4">
                    <validation-provider
                      name="Customer"
                      :rules="{ required: true }"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Customer') + ' *'">
                        <v-select
                          :reduce="c => c.id"
                          v-model="form.client_id"
                          :options="clients"
                          label="name"
                          :placeholder="$t('Choose_Customer')"
                          :class="{ 'is-invalid': validationContext.errors.length > 0 }"
                        />
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <b-col md="4">
                    <b-form-group :label="$t('Technician')">
                      <v-select
                        :reduce="t => t.id"
                        v-model="form.technician_id"
                        :options="technicians"
                        label="full_name"
                        :placeholder="$t('Choose_Technician')"
                      />
                    </b-form-group>
                  </b-col>

                  <b-col md="4">
                    <validation-provider
                      name="Service Item"
                      :rules="{ required: true }"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Service_Item') + ' *'">
                        <b-form-input
                          v-model="form.service_item"
                          :state="getValidationState(validationContext)"
                          :placeholder="$t('Service_Item')"
                        />
                        <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <b-col md="4">
                    <b-form-group :label="$t('Job_Type')">
                      <b-form-input v-model="form.job_type" :placeholder="$t('Job_Type')" />
                    </b-form-group>
                  </b-col>

                  <b-col md="4">
                    <b-form-group :label="$t('Status')">
                      <b-form-select v-model="form.status" :options="statusOptions" />
                    </b-form-group>
                  </b-col>

                  <b-col md="4">
                    <b-form-group :label="$t('Scheduled_Date')">
                      <b-form-input v-model="form.scheduled_date" type="datetime-local" />
                    </b-form-group>
                  </b-col>
                </b-row>

                <h5 class="mt-4 mb-2">{{ $t('Device_Information') || 'Device Information' }}</h5>
                <b-row>
                  <b-col md="3">
                    <b-form-group :label="$t('Brand') || 'Brand'">
                      <b-form-input v-model="form.device_brand" placeholder="Apple, Samsung..." />
                    </b-form-group>
                  </b-col>
                  <b-col md="3">
                    <b-form-group :label="$t('Model') || 'Model'">
                      <b-form-input v-model="form.device_model" placeholder="iPhone 13, Galaxy S22..." />
                    </b-form-group>
                  </b-col>
                  <b-col md="3">
                    <b-form-group :label="$t('Color') || 'Color'">
                      <b-form-input v-model="form.device_color" />
                    </b-form-group>
                  </b-col>
                  <b-col md="3">
                    <b-form-group :label="$t('Serial_Number') || 'Serial Number'">
                      <b-form-input v-model="form.device_serial" />
                    </b-form-group>
                  </b-col>
                  <b-col md="3">
                    <b-form-group :label="$t('IMEI') || 'IMEI'">
                      <b-form-input v-model="form.device_imei" />
                    </b-form-group>
                  </b-col>
                  <b-col md="3">
                    <b-form-group :label="$t('Unlock_Code') || 'Unlock Code'">
                      <b-form-input v-model="form.device_password" :placeholder="$t('PIN_pattern_password')" />
                    </b-form-group>
                  </b-col>
                  <b-col md="6">
                    <b-form-group :label="$t('Accessories_Received') || 'Accessories Received'">
                      <div>
                        <b-form-checkbox-group
                          v-model="form.accessories"
                          :options="accessoryOptions"
                          stacked
                          switches
                        />
                      </div>
                    </b-form-group>
                  </b-col>
                </b-row>

                <b-row>
                  <b-col md="6">
                    <b-form-group :label="$t('Condition_On_Arrival') || 'Condition on Arrival'">
                      <b-form-textarea
                        v-model="form.condition_on_arrival"
                        rows="3"
                        :placeholder="$t('Pre_existing_damage_scratches_etc')"
                      />
                    </b-form-group>
                  </b-col>
                  <b-col md="6">
                    <b-form-group :label="$t('Reported_Issue') || 'Reported Issue (customer words)'">
                      <b-form-textarea
                        v-model="form.reported_issue"
                        rows="3"
                        :placeholder="$t('What_the_customer_said')"
                      />
                    </b-form-group>
                  </b-col>
                  <b-col md="12">
                    <b-form-group :label="$t('Notes')">
                      <b-form-textarea v-model="form.notes" rows="2" />
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-tab>

              <!-- ============== TAB 2: Diagnostic ============== -->
              <b-tab :title="$t('Diagnostic') || 'Diagnostic'">
                <b-row>
                  <b-col md="9">
                    <b-form-group :label="$t('Diagnosis') || 'Diagnosis (technician findings)'">
                      <b-form-textarea
                        v-model="form.diagnosis"
                        rows="4"
                        :placeholder="$t('What_the_technician_found')"
                      />
                    </b-form-group>
                  </b-col>
                  <b-col md="3">
                    <b-form-group :label="$t('Diagnostic_Fee') || 'Diagnostic Fee'">
                      <b-form-input
                        v-model.number="form.diagnostic_fee"
                        type="number"
                        step="0.01"
                        min="0"
                      />
                    </b-form-group>
                  </b-col>
                </b-row>

                <h5 class="mt-3 mb-2">{{ $t('Checklist') }}</h5>
                <b-row>
                  <b-col md="12" v-if="checklistItems.length === 0">
                    <p class="text-muted">{{ $t('No_checklist_items_defined') }}</p>
                  </b-col>
                  <b-col
                    v-for="item in checklistItems"
                    :key="item.id"
                    md="4"
                    class="mt-2 mb-2"
                  >
                    <label class="switch switch-primary mr-3">
                      {{ item.name }}
                      <input type="checkbox" v-model="checklistState[item.id]">
                      <span class="slider"></span>
                    </label>
                  </b-col>
                </b-row>
              </b-tab>

              <!-- ============== TAB 3: Quote ============== -->
              <b-tab :title="$t('Quote') || 'Quote'">
                <b-row>
                  <b-col md="3">
                    <b-form-group :label="$t('Quote_Amount') || 'Quote Amount (manual)'">
                      <b-form-input
                        v-model.number="form.quote_amount"
                        type="number"
                        step="0.01"
                        min="0"
                      />
                      <small class="text-muted">{{ $t('Leave_blank_to_use_line_items') || 'Leave blank to use line items total' }}</small>
                    </b-form-group>
                  </b-col>
                  <b-col md="3">
                    <b-form-group :label="$t('Valid_Until') || 'Valid Until'">
                      <b-form-input v-model="form.quote_valid_until" type="date" />
                    </b-form-group>
                  </b-col>
                  <b-col md="3">
                    <b-form-group :label="$t('Warranty_Days') || 'Warranty (days)'">
                      <b-form-input
                        v-model.number="form.warranty_days"
                        type="number"
                        min="0"
                        max="3650"
                      />
                    </b-form-group>
                  </b-col>
                  <b-col md="3" v-if="isEdit && jobMeta && jobMeta.parent_job_id">
                    <b-form-group :label="$t('Warranty_Claim_For') || 'Warranty claim for'">
                      <div class="form-control-plaintext">
                        <lucide-icon class="text-success mr-1" name="shield-check" />
                        Job #{{ jobMeta.parent_job_id }}
                      </div>
                    </b-form-group>
                  </b-col>
                </b-row>

                <div v-if="isEdit" class="mt-3 mb-2">
                  <b-button size="sm" variant="outline-warning" class="mr-2" @click="downloadQuotePdf">
                    <lucide-icon class="mr-1" name="file-down" /> {{ $t('Download_Quote_PDF') || 'Download Quote PDF' }}
                  </b-button>
                  <template v-if="jobMeta && jobMeta.quotation_id">
                    <router-link :to="`/app/quotations/detail/${jobMeta.quotation_id}`" class="btn btn-sm btn-outline-success">
                      <lucide-icon class="mr-1" name="check" /> Linked to {{ jobMeta.quotation_ref || ('Quotation #' + jobMeta.quotation_id) }}
                    </router-link>
                  </template>
                  <b-button v-else size="sm" variant="outline-primary" :disabled="creatingQuotation" @click="sendToQuotations">
                    <lucide-icon class="mr-1" name="send" /> {{ creatingQuotation ? 'Sending...' : 'Send to Quotations' }}
                  </b-button>
                </div>

                <div v-if="isEdit && jobMeta" class="quote-status-box mt-3">
                  <template v-if="jobMeta.quote_approved_at">
                    <lucide-icon class="text-success mr-2" name="check" />
                    <strong>{{ $t('Quote_Approved') || 'Quote approved' }}</strong>
                    {{ $t('on') || 'on' }} {{ formatDate(jobMeta.quote_approved_at) }}
                    <span v-if="jobMeta.quote_approved_by"> {{ $t('by') || 'by' }} {{ jobMeta.quote_approved_by }}</span>
                  </template>
                  <template v-else-if="jobMeta.status === 'declined'">
                    <lucide-icon class="text-danger mr-2" name="x" />
                    <strong class="text-danger">{{ $t('Quote_Declined') || 'Quote declined' }}</strong>
                  </template>
                  <template v-else>
                    <div>
                      <lucide-icon class="text-warning mr-2" name="info" />
                      {{ $t('Quote_Awaiting_Approval') || 'Awaiting customer approval' }}
                    </div>
                    <div class="mt-2">
                      <b-form-input
                        v-model="approveBy"
                        :placeholder="$t('Customer_signature_name') || 'Customer signature/name'"
                        size="sm"
                        class="d-inline-block w-auto mr-2"
                      />
                      <b-button size="sm" variant="success" @click="approveQuote">
                        <lucide-icon name="check" /> {{ $t('Approve_Quote') || 'Approve' }}
                      </b-button>
                      <b-button size="sm" variant="outline-danger" @click="declineQuote">
                        <lucide-icon name="x" /> {{ $t('Decline') || 'Decline' }}
                      </b-button>
                    </div>
                  </template>
                </div>
              </b-tab>

              <!-- ============== TAB 4: Parts & Labor ============== -->
              <b-tab :title="$t('Parts_Labor') || 'Parts & Labor'">
                <b-row class="mb-3">
                  <b-col md="6">
                    <b-form-group :label="$t('Warehouse') + ' *'">
                      <v-select
                        :reduce="w => w.id"
                        v-model="selectedWarehouseId"
                        :options="warehouses"
                        label="name"
                        :placeholder="$t('Choose_Warehouse')"
                        @input="onWarehouseChange"
                      />
                    </b-form-group>
                  </b-col>
                  <b-col md="6">
                    <b-form-group :label="$t('Add_Part_From_Stock') || 'Add part from stock'">
                      <v-select
                        :reduce="p => p"
                        v-model="productPick"
                        :options="warehouseProducts"
                        label="name"
                        :placeholder="$t('Search_product')"
                        :disabled="!selectedWarehouseId"
                        @input="onPickProduct"
                      >
                        <template #option="{ name, code, qte_sale }">
                          <span>{{ name }} <small class="text-muted">({{ code }} · stock: {{ qte_sale }})</small></span>
                        </template>
                      </v-select>
                    </b-form-group>
                  </b-col>
                </b-row>

                <div class="d-flex justify-content-between mb-2">
                  <h5 class="mb-0">{{ $t('Line_Items') || 'Line Items' }}</h5>
                  <div>
                    <b-button size="sm" variant="outline-primary" @click="addLaborLine">
                      <lucide-icon class="mr-1" name="plus" /> {{ $t('Add_Labor_Line') || 'Add Labor' }}
                    </b-button>
                    <b-button size="sm" variant="outline-secondary" @click="addOtherLine" class="ml-2">
                      <lucide-icon class="mr-1" name="plus" /> {{ $t('Add_Other_Line') || 'Add Other' }}
                    </b-button>
                  </div>
                </div>

                <table class="table table-sm table-bordered repair-items-table">
                  <thead>
                    <tr>
                      <th style="width: 80px;">{{ $t('Type') || 'Type' }}</th>
                      <th>{{ $t('Description') || 'Description' }}</th>
                      <th style="width: 110px;">{{ $t('Qty') || 'Qty' }}</th>
                      <th style="width: 130px;">{{ $t('Unit_Price') || 'Unit Price' }}</th>
                      <th style="width: 120px;">{{ $t('Discount') || 'Discount' }}</th>
                      <th style="width: 110px;">{{ $t('Tax') || 'Tax %' }}</th>
                      <th style="width: 130px;">{{ $t('Total') || 'Total' }}</th>
                      <th style="width: 50px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="form.items.length === 0">
                      <td colspan="8" class="text-center text-muted py-3">
                        {{ $t('No_items_added_yet') || 'No items yet — pick a part above or add a labor line' }}
                      </td>
                    </tr>
                    <tr v-for="(row, idx) in form.items" :key="idx">
                      <td>
                        <b-badge v-if="row.type === 'part'" variant="info">{{ $t('Part') || 'Part' }}</b-badge>
                        <b-badge v-else-if="row.type === 'labor'" variant="primary">{{ $t('Labor') || 'Labor' }}</b-badge>
                        <b-badge v-else variant="secondary">{{ $t('Other') || 'Other' }}</b-badge>
                      </td>
                      <td>
                        <b-form-input v-model="row.description" size="sm" />
                      </td>
                      <td>
                        <b-form-input v-model.number="row.quantity" type="number" min="0" step="0.01" size="sm" @input="recomputeRow(row)" />
                      </td>
                      <td>
                        <b-form-input v-model.number="row.unit_price" type="number" min="0" step="0.01" size="sm" @input="recomputeRow(row)" />
                      </td>
                      <td>
                        <b-input-group size="sm">
                          <b-form-input v-model.number="row.discount" type="number" min="0" step="0.01" @input="recomputeRow(row)" />
                          <b-input-group-append>
                            <b-form-select v-model="row.discount_method" :options="[{value:'1',text:'$'},{value:'2',text:'%'}]" @change="recomputeRow(row)" />
                          </b-input-group-append>
                        </b-input-group>
                      </td>
                      <td>
                        <b-form-input v-model.number="row.tax_rate" type="number" min="0" step="0.01" size="sm" @input="recomputeRow(row)" />
                      </td>
                      <td class="text-right">
                        <strong>{{ currencySymbol }}{{ formatNumber(row.total) }}</strong>
                      </td>
                      <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeItem(idx)">
                          <lucide-icon name="x" />
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="6" class="text-right"><strong>{{ $t('Items_Subtotal') || 'Items Subtotal' }}</strong></td>
                      <td class="text-right"><strong>{{ currencySymbol }}{{ formatNumber(itemsSubtotal) }}</strong></td>
                      <td></td>
                    </tr>
                    <tr v-if="form.diagnostic_fee > 0">
                      <td colspan="6" class="text-right">{{ $t('Diagnostic_Fee') || 'Diagnostic Fee' }}</td>
                      <td class="text-right">{{ currencySymbol }}{{ formatNumber(form.diagnostic_fee) }}</td>
                      <td></td>
                    </tr>
                    <tr>
                      <td colspan="6" class="text-right"><strong>{{ $t('Grand_Total') || 'Grand Total' }}</strong></td>
                      <td class="text-right"><strong class="text-primary">{{ currencySymbol }}{{ formatNumber(grandTotal) }}</strong></td>
                      <td></td>
                    </tr>
                  </tfoot>
                </table>
              </b-tab>

              <!-- ============== TAB 5: Photos (edit only) ============== -->
              <b-tab :title="$t('Photos') || 'Photos'" :disabled="!isEdit">
                <div v-if="!isEdit" class="text-muted text-center py-4">
                  {{ $t('Save_job_first_to_upload_photos') || 'Save the job first to upload photos.' }}
                </div>
                <div v-else>
                  <b-row class="mb-3">
                    <b-col md="3">
                      <b-form-group :label="$t('Stage') || 'Stage'">
                        <b-form-select v-model="photoStage" :options="photoStageOptions" />
                      </b-form-group>
                    </b-col>
                    <b-col md="6">
                      <b-form-group :label="$t('Caption') || 'Caption'">
                        <b-form-input v-model="photoCaption" />
                      </b-form-group>
                    </b-col>
                    <b-col md="3" class="d-flex align-items-end">
                      <b-form-file
                        v-model="photoFiles"
                        multiple
                        accept="image/*"
                        :placeholder="$t('Choose_files') || 'Choose photos...'"
                        class="mb-3"
                      />
                    </b-col>
                    <b-col md="12">
                      <b-button variant="primary" :disabled="!photoFiles || photoFiles.length === 0 || photoUploading" @click="uploadPhotos">
                        <lucide-icon class="mr-1" name="upload" /> {{ photoUploading ? ($t('Uploading') || 'Uploading...') : ($t('Upload') || 'Upload') }}
                      </b-button>
                    </b-col>
                  </b-row>

                  <div v-if="photos.length === 0" class="text-muted text-center py-3">
                    {{ $t('No_photos_yet') || 'No photos yet' }}
                  </div>
                  <div v-else class="photo-grid">
                    <div v-for="ph in photos" :key="ph.id" class="photo-tile">
                      <img :src="ph.url" :alt="ph.original_name" @click="previewPhoto = ph" />
                      <div class="photo-meta">
                        <span class="badge badge-outline-info">{{ $t(ph.stage) || ph.stage }}</span>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="deletePhoto(ph)">
                          <lucide-icon name="x" />
                        </button>
                      </div>
                      <small v-if="ph.caption" class="text-muted d-block">{{ ph.caption }}</small>
                    </div>
                  </div>

                  <b-modal v-if="previewPhoto" :visible="!!previewPhoto" hide-footer @hidden="previewPhoto = null" size="lg">
                    <img :src="previewPhoto.url" class="img-fluid" />
                  </b-modal>
                </div>
              </b-tab>

              <!-- ============== TAB 6: Payments (edit only) ============== -->
              <b-tab :title="$t('Payments') || 'Payments'" :disabled="!isEdit">
                <div v-if="!isEdit" class="text-muted text-center py-4">
                  {{ $t('Save_job_first_to_record_payments') || 'Save the job first to record payments.' }}
                </div>
                <div v-else>
                  <!-- Summary stat cards -->
                  <b-row class="pay-stats mb-4">
                    <b-col md="4" class="mb-2">
                      <div class="pay-stat pay-stat--total">
                        <div class="pay-stat__icon">
                          <lucide-icon name="receipt" />
                        </div>
                        <div class="pay-stat__body">
                          <div class="pay-stat__label">{{ $t('Total') }}</div>
                          <div class="pay-stat__value">{{ currencySymbol }}{{ formatNumber(totals.total_amount) }}</div>
                        </div>
                      </div>
                    </b-col>
                    <b-col md="4" class="mb-2">
                      <div class="pay-stat pay-stat--paid">
                        <div class="pay-stat__icon">
                          <lucide-icon name="check-circle" />
                        </div>
                        <div class="pay-stat__body">
                          <div class="pay-stat__label">{{ $t('Paid') }}</div>
                          <div class="pay-stat__value">{{ currencySymbol }}{{ formatNumber(totals.paid_amount) }}</div>
                          <div class="pay-stat__sub">
                            <div class="pay-progress">
                              <div class="pay-progress__bar" :style="{ width: paidPercent + '%' }"></div>
                            </div>
                            <span class="pay-progress__label">{{ paidPercent }}%</span>
                          </div>
                        </div>
                      </div>
                    </b-col>
                    <b-col md="4" class="mb-2">
                      <div class="pay-stat" :class="totals.balance_due > 0 ? 'pay-stat--due' : 'pay-stat--settled'">
                        <div class="pay-stat__icon">
                          <lucide-icon :name="totals.balance_due > 0 ? 'alert-circle' : 'check'" />
                        </div>
                        <div class="pay-stat__body">
                          <div class="pay-stat__label">{{ $t('Balance_Due') || 'Balance Due' }}</div>
                          <div class="pay-stat__value">{{ currencySymbol }}{{ formatNumber(totals.balance_due) }}</div>
                        </div>
                      </div>
                    </b-col>
                  </b-row>

                  <!-- Payments card -->
                  <div class="pay-card">
                    <div class="pay-card__head">
                      <div class="pay-card__title">
                        <lucide-icon name="credit-card" />
                        <span>{{ $t('Payments') || 'Payments' }}</span>
                        <b-badge variant="light" class="pay-card__count">{{ payments.length }}</b-badge>
                      </div>
                      <b-button size="sm" variant="primary" class="pay-add-btn" @click="openPaymentModal()">
                        <lucide-icon class="mr-1" name="plus" /> {{ $t('Add_Payment') || 'Add Payment' }}
                      </b-button>
                    </div>

                    <div class="pay-table-wrap">
                      <table class="table pay-table mb-0">
                        <thead>
                          <tr>
                            <th>{{ $t('Reference') }}</th>
                            <th>{{ $t('Date') }}</th>
                            <th>{{ $t('Kind') || 'Kind' }}</th>
                            <th>{{ $t('Method') || 'Method' }}</th>
                            <th class="text-right">{{ $t('Amount') || 'Amount' }}</th>
                            <th>{{ $t('Notes') }}</th>
                            <th style="width: 100px;" class="text-center">{{ $t('Actions') || '' }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-if="payments.length === 0">
                            <td colspan="7" class="pay-empty">
                              <lucide-icon name="inbox" />
                              <div>{{ $t('No_payments_yet') || 'No payments yet' }}</div>
                              <small class="text-muted">{{ $t('Click_Add_Payment_to_record_one') || 'Click “Add Payment” to record one.' }}</small>
                            </td>
                          </tr>
                          <tr v-for="p in payments" :key="p.id" class="pay-row">
                            <td>
                              <span class="pay-ref">{{ p.Ref }}</span>
                            </td>
                            <td>
                              <div class="pay-date">
                                <lucide-icon name="calendar" />
                                <span>{{ p.date }}</span>
                              </div>
                            </td>
                            <td>
                              <span class="pay-kind" :class="'pay-kind--' + (p.payment_kind || 'payment')">
                                <lucide-icon :name="kindIcon(p.payment_kind)" />
                                {{ $t(p.payment_kind) || p.payment_kind }}
                              </span>
                            </td>
                            <td>
                              <span v-if="p.payment_method" class="pay-method">{{ p.payment_method }}</span>
                              <span v-else class="text-muted">—</span>
                            </td>
                            <td class="text-right">
                              <span class="pay-amount" :class="p.payment_kind === 'refund' ? 'text-danger' : 'text-dark'">
                                {{ p.payment_kind === 'refund' ? '-' : '' }}{{ currencySymbol }}{{ formatNumber(p.montant) }}
                              </span>
                            </td>
                            <td>
                              <span v-if="p.notes" class="pay-notes" :title="p.notes">{{ p.notes }}</span>
                              <span v-else class="text-muted">—</span>
                            </td>
                            <td class="text-center">
                              <b-button size="sm" variant="light" class="pay-action" :title="$t('Edit')" @click="openPaymentModal(p)">
                                <lucide-icon name="pencil" />
                              </b-button>
                              <b-button size="sm" variant="light" class="pay-action pay-action--danger ml-1" :title="$t('Delete')" @click="deletePayment(p)">
                                <lucide-icon name="trash-2" />
                              </b-button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <hr v-if="canMarkDelivered" />
                  <div v-if="canMarkDelivered" class="text-right">
                    <b-button variant="success" @click="markDelivered">
                      <lucide-icon class="mr-1" name="check" /> {{ $t('Mark_Delivered_Decrement_Stock') || 'Mark Delivered (decrements stock + starts warranty)' }}
                    </b-button>
                  </div>
                  <div v-else-if="jobMeta && jobMeta.status === 'delivered'" class="text-right text-success">
                    <lucide-icon name="check" />
                    {{ $t('Delivered_On') || 'Delivered on' }} {{ formatDate(jobMeta.delivered_at) }}
                    <span v-if="jobMeta.warranty_expires_at"> · {{ $t('Warranty_until') || 'Warranty until' }} {{ formatDate(jobMeta.warranty_expires_at) }}</span>
                  </div>
                </div>

                <!-- Payment modal -->
                <b-modal v-model="paymentModalShow" :title="paymentModalTitle" hide-footer>
                  <b-form @submit.prevent="submitPayment">
                    <b-row>
                      <b-col md="6">
                        <b-form-group :label="$t('Date') + ' *'">
                          <b-form-input v-model="paymentForm.date" type="date" required />
                        </b-form-group>
                      </b-col>
                      <b-col md="6">
                        <b-form-group :label="$t('Amount') + ' *'">
                          <b-form-input v-model.number="paymentForm.montant" type="number" step="0.01" min="0.01" required />
                        </b-form-group>
                      </b-col>
                      <b-col md="6">
                        <b-form-group :label="$t('Payment_Kind') || 'Kind'">
                          <b-form-select v-model="paymentForm.payment_kind" :options="paymentKindOptions" />
                        </b-form-group>
                      </b-col>
                      <b-col md="6">
                        <b-form-group :label="$t('Payment_Method') || 'Method'">
                          <v-select
                            :reduce="m => m.id"
                            v-model="paymentForm.payment_method_id"
                            :options="paymentMethods"
                            label="name"
                            :placeholder="$t('Choose_Payment_Method')"
                          />
                        </b-form-group>
                      </b-col>
                      <b-col md="12">
                        <b-form-group :label="$t('Notes')">
                          <b-form-textarea v-model="paymentForm.notes" rows="2" />
                        </b-form-group>
                      </b-col>
                    </b-row>
                    <div class="text-right">
                      <b-button variant="secondary" class="mr-2" @click="paymentModalShow = false">{{ $t('Cancel') }}</b-button>
                      <b-button type="submit" variant="primary" :disabled="paymentSaving">
                        {{ paymentSaving ? ($t('Saving') || 'Saving...') : ($t('Save') || 'Save') }}
                      </b-button>
                    </div>
                  </b-form>
                </b-modal>
              </b-tab>
            </b-tabs>

            <div class="mt-4 text-right">
              <b-button variant="secondary" class="mr-2" @click="$router.back()">
                {{ $t('Cancel') }}
              </b-button>
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing">
                {{ SubmitProcessing ? ($t('Saving') || 'Saving...') : $t('Save') }}
              </b-button>
            </div>
          </b-form>
        </validation-observer>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ServiceJobForm',
  data() {
    return {
      isLoading: true,
      isEdit: false,
      SubmitProcessing: false,
      creatingQuotation: false,
      activeTab: 0,
      currencySymbol: '$',

      form: {
        client_id: null,
        technician_id: null,
        service_item: '',
        job_type: '',
        status: 'pending',
        scheduled_date: '',
        notes: '',

        device_brand: '',
        device_model: '',
        device_serial: '',
        device_imei: '',
        device_color: '',
        device_password: '',
        accessories: [],

        condition_on_arrival: '',
        reported_issue: '',
        diagnosis: '',
        diagnostic_fee: 0,

        quote_amount: 0,
        quote_valid_until: '',

        warranty_days: 30,
        parent_job_id: null,

        items: []
      },
      jobMeta: null,
      totals: { total_amount: 0, paid_amount: 0, balance_due: 0 },

      clients: [],
      technicians: [],
      warehouses: [],
      paymentMethods: [],

      checklistCategories: [],
      checklistItems: [],
      checklistState: {},

      selectedWarehouseId: null,
      warehouseProducts: [],
      productPick: null,

      photos: [],
      photoFiles: null,
      photoStage: 'intake',
      photoCaption: '',
      photoUploading: false,
      previewPhoto: null,

      payments: [],
      paymentModalShow: false,
      paymentModalTitle: '',
      paymentEditingId: null,
      paymentSaving: false,
      paymentForm: {
        date: new Date().toISOString().slice(0, 10),
        montant: 0,
        payment_kind: 'payment',
        payment_method_id: null,
        notes: ''
      },

      approveBy: '',

      statusOptions: [
        { value: 'pending', text: 'Pending' },
        { value: 'intake', text: 'Intake' },
        { value: 'diagnostic', text: 'Diagnostic' },
        { value: 'quoted', text: 'Quoted' },
        { value: 'approved', text: 'Approved' },
        { value: 'in_progress', text: 'In Progress' },
        { value: 'ready', text: 'Ready for Pickup' },
        { value: 'delivered', text: 'Delivered' },
        { value: 'declined', text: 'Declined' },
        { value: 'cancelled', text: 'Cancelled' },
        { value: 'completed', text: 'Completed' }
      ],
      accessoryOptions: ['Charger', 'Cable', 'Case', 'SIM card', 'Memory card', 'Headphones', 'Box'],
      photoStageOptions: [
        { value: 'intake', text: 'Intake' },
        { value: 'before', text: 'Before repair' },
        { value: 'after', text: 'After repair' },
        { value: 'delivery', text: 'Delivery' }
      ],
      paymentKindOptions: [
        { value: 'deposit', text: 'Deposit' },
        { value: 'payment', text: 'Payment' },
        { value: 'refund', text: 'Refund' }
      ]
    };
  },
  computed: {
    jobId() {
      return this.$route.params.id ? Number(this.$route.params.id) : null;
    },
    itemsSubtotal() {
      return this.form.items.reduce((s, r) => s + Number(r.total || 0), 0);
    },
    grandTotal() {
      const fallback = (Number(this.form.quote_amount) || 0);
      const items = this.itemsSubtotal;
      const base = items > 0 ? items : fallback;
      return base + (Number(this.form.diagnostic_fee) || 0);
    },
    canMarkDelivered() {
      // Allow delivery when:
      //  - balance is settled (paid in full or no charge), AND
      //  - the job exists and is not already delivered.
      // The previous "paid_amount > 0" guard wrongly hid the button for free / warranty repairs.
      if (!this.jobMeta || this.jobMeta.status === 'delivered') return false;
      return Number(this.totals.balance_due || 0) <= 0.0001;
    },
    paidPercent() {
      const total = Number(this.totals.total_amount || 0);
      const paid = Number(this.totals.paid_amount || 0);
      if (total <= 0) return paid > 0 ? 100 : 0;
      return Math.min(100, Math.max(0, Math.round((paid / total) * 100)));
    }
  },
  async mounted() {
    await this.bootstrap();
  },
  watch: {
    // The same component is reused for /create and /edit/:id — when the route changes
    // (e.g. after creating a job we redirect to its edit URL), Vue won't fire mounted again.
    '$route'(to, from) {
      if (to.path !== from.path || to.params.id !== from.params.id) {
        this.bootstrap();
      }
    }
  },
  methods: {
    async bootstrap() {
      this.isLoading = true;
      this.isEdit = !!this.jobId;
      // Reset form state for create mode (route changes from edit→create or to a different id)
      if (!this.isEdit) {
        this.form.items = [];
        this.payments = [];
        this.photos = [];
        this.jobMeta = null;
      }
      await this.loadMeta();
      const { data: catData } = await axios.get('service_checklist/categories');
      this.checklistCategories = catData.categories || [];
      await this.loadChecklist();
      await this.loadJobIfNeeded();
      this.isLoading = false;
    },
    async loadMeta() {
      const { data } = await axios.get('service_jobs/create');
      this.clients = (data.clients || []).map(c => ({ id: c.id, name: c.name }));
      this.technicians = (data.technicians || []).map(t => ({ id: t.id, full_name: t.name || `#${t.id}` }));
      this.warehouses = data.warehouses || [];
      this.paymentMethods = data.payment_methods || [];
    },
    async loadChecklist() {
      const { data } = await axios.get('service_checklist/items');
      this.checklistItems = (data.items || []).map(item => {
        const cat = this.checklistCategories.find(c => c.id === item.category_id);
        return {
          id: item.id,
          name: item.name,
          category_id: item.category_id,
          category_name: cat ? cat.name : null
        };
      });
      if (!this.isEdit) {
        this.checklistItems.forEach(it => { this.$set(this.checklistState, it.id, false); });
      }
    },
    async loadJobIfNeeded() {
      if (!this.jobId) return;
      const { data } = await axios.get(`service_jobs/${this.jobId}`);
      const job = data.job || {};
      this.jobMeta = job;

      this.form.client_id = job.client_id || null;
      this.form.technician_id = job.technician_id || null;
      this.form.service_item = job.service_item || '';
      this.form.job_type = job.job_type || '';
      this.form.status = job.status || 'pending';
      this.form.scheduled_date = job.scheduled_date ? String(job.scheduled_date).slice(0, 16).replace(' ', 'T') : '';
      this.form.notes = job.notes || '';

      this.form.device_brand = job.device_brand || '';
      this.form.device_model = job.device_model || '';
      this.form.device_serial = job.device_serial || '';
      this.form.device_imei = job.device_imei || '';
      this.form.device_color = job.device_color || '';
      this.form.device_password = job.device_password || '';
      this.form.accessories = Array.isArray(job.accessories) ? job.accessories : [];

      this.form.condition_on_arrival = job.condition_on_arrival || '';
      this.form.reported_issue = job.reported_issue || '';
      this.form.diagnosis = job.diagnosis || '';
      this.form.diagnostic_fee = Number(job.diagnostic_fee) || 0;

      this.form.quote_amount = Number(job.quote_amount) || 0;
      this.form.quote_valid_until = job.quote_valid_until || '';
      this.form.warranty_days = Number(job.warranty_days) || 30;

      (data.checklist || []).forEach(row => {
        if (row.item_id) this.$set(this.checklistState, row.item_id, !!row.is_completed);
      });

      this.form.items = (data.items || []).map(it => ({
        id: it.id,
        type: it.type || 'part',
        product_id: it.product_id,
        product_variant_id: it.product_variant_id,
        warehouse_id: it.warehouse_id,
        description: it.description,
        quantity: Number(it.quantity) || 1,
        unit_price: Number(it.unit_price) || 0,
        discount: Number(it.discount) || 0,
        discount_method: it.discount_method || '1',
        tax_rate: Number(it.tax_rate) || 0,
        tax_method: it.tax_method || '1',
        total: Number(it.total) || 0,
        notes: it.notes || ''
      }));

      // pick warehouse from first item if any
      const firstWithWh = this.form.items.find(i => i.warehouse_id);
      if (firstWithWh) {
        this.selectedWarehouseId = firstWithWh.warehouse_id;
        await this.loadWarehouseProducts();
      }

      this.payments = data.payments || [];
      this.photos = data.photos || [];

      this.totals = {
        total_amount: Number(job.total_amount) || 0,
        paid_amount: Number(job.paid_amount) || 0,
        balance_due: Number(job.balance_due) || 0
      };
    },

    /* ---------- Items / parts ---------- */
    async onWarehouseChange() {
      this.warehouseProducts = [];
      this.productPick = null;
      if (this.selectedWarehouseId) {
        await this.loadWarehouseProducts();
      }
    },
    async loadWarehouseProducts() {
      try {
        const { data } = await axios.get(`get_Products_by_warehouse/${this.selectedWarehouseId}?stock=1&is_sale=1&product_service=1&product_combo=0`);
        this.warehouseProducts = (data || []).map(p => ({
          id: p.id,
          product_variant_id: p.product_variant_id,
          name: p.name,
          code: p.code,
          qte_sale: p.qte_sale,
          price: p.Net_price || p.price || 0
        }));
      } catch (e) {
        this.warehouseProducts = [];
      }
    },
    onPickProduct(p) {
      if (!p) return;
      const row = {
        type: 'part',
        product_id: p.id,
        product_variant_id: p.product_variant_id || null,
        warehouse_id: this.selectedWarehouseId,
        description: p.name,
        quantity: 1,
        unit_price: Number(p.price) || 0,
        discount: 0,
        discount_method: '1',
        tax_rate: 0,
        tax_method: '1',
        total: 0,
        notes: ''
      };
      this.recomputeRow(row);
      this.form.items.push(row);
      this.productPick = null;
    },
    addLaborLine() {
      const row = {
        type: 'labor',
        product_id: null,
        product_variant_id: null,
        warehouse_id: null,
        description: 'Labor',
        quantity: 1,
        unit_price: 0,
        discount: 0,
        discount_method: '1',
        tax_rate: 0,
        tax_method: '1',
        total: 0,
        notes: ''
      };
      this.form.items.push(row);
    },
    addOtherLine() {
      const row = {
        type: 'other',
        product_id: null,
        product_variant_id: null,
        warehouse_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount: 0,
        discount_method: '1',
        tax_rate: 0,
        tax_method: '1',
        total: 0,
        notes: ''
      };
      this.form.items.push(row);
    },
    removeItem(idx) {
      this.form.items.splice(idx, 1);
    },
    recomputeRow(row) {
      const qty = Number(row.quantity) || 0;
      const price = Number(row.unit_price) || 0;
      const disc = Number(row.discount) || 0;
      const tax = Number(row.tax_rate) || 0;
      const subtotal = qty * price;
      const discValue = row.discount_method === '2' ? subtotal * disc / 100 : disc;
      const afterDisc = Math.max(0, subtotal - discValue);
      const taxValue = afterDisc * tax / 100;
      row.total = Number((afterDisc + taxValue).toFixed(2));
    },

    /* ---------- Checklist ---------- */
    buildChecklistPayload() {
      return this.checklistItems.map(item => ({
        category_id: item.category_id,
        category_name: item.category_name || '',
        item_id: item.id,
        item_name: item.name,
        is_completed: !!this.checklistState[item.id]
      }));
    },

    /* ---------- Submit job ---------- */
    submit() {
      this.$refs.Create_Service_Job.validate().then(success => {
        if (!success) {
          this.makeToast('danger', this.$t('Please_fill_the_form_correctly'), this.$t('Failed'));
        } else {
          this.Submit_Service_Job();
        }
      });
    },
    async Submit_Service_Job() {
      this.SubmitProcessing = true;
      const payload = {
        ...this.form,
        checklist: this.buildChecklistPayload(),
        items: this.form.items.map(it => ({
          type: it.type,
          product_id: it.product_id,
          product_variant_id: it.product_variant_id,
          warehouse_id: it.warehouse_id,
          description: it.description,
          quantity: it.quantity,
          unit_price: it.unit_price,
          discount: it.discount,
          discount_method: it.discount_method,
          tax_rate: it.tax_rate,
          tax_method: it.tax_method,
          notes: it.notes
        }))
      };
      try {
        if (this.isEdit) {
          await axios.put(`service_jobs/${this.jobId}`, payload);
          this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'));
          await this.loadJobIfNeeded();
        } else {
          const { data } = await axios.post('service_jobs', payload);
          this.makeToast('success', this.$t('Successfully_Created'), this.$t('Success'));
          if (data && data.id) {
            this.$router.replace({ name: 'service_job_edit', params: { id: data.id } });
          } else {
            this.$router.push({ name: 'service_jobs_index' });
          }
        }
      } catch (error) {
        this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'));
      } finally {
        this.SubmitProcessing = false;
      }
    },

    /* ---------- Quote actions ---------- */
    async approveQuote() {
      try {
        await axios.post(`service_jobs/${this.jobId}/approve_quote`, { approved_by: this.approveBy || null });
        this.makeToast('success', this.$t('Quote_Approved') || 'Quote approved', this.$t('Success'));
        await this.loadJobIfNeeded();
      } catch (e) {
        this.makeToast('danger', this.$t('Operation_Failed') || 'Operation failed', this.$t('Failed'));
      }
    },
    async declineQuote() {
      try {
        await axios.post(`service_jobs/${this.jobId}/decline_quote`);
        this.makeToast('success', this.$t('Quote_Declined') || 'Quote declined', this.$t('Success'));
        await this.loadJobIfNeeded();
      } catch (e) {
        this.makeToast('danger', this.$t('Operation_Failed') || 'Operation failed', this.$t('Failed'));
      }
    },
    async markDelivered() {
      try {
        await axios.post(`service_jobs/${this.jobId}/mark_delivered`);
        this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'));
        await this.loadJobIfNeeded();
      } catch (e) {
        const msg = e.response && e.response.data && e.response.data.message;
        this.makeToast('danger', msg || (this.$t('Operation_Failed') || 'Operation failed'), this.$t('Failed'));
      }
    },

    /* ---------- Send to Quotations ---------- */
    async sendToQuotations() {
      if (!this.jobId) return;
      this.creatingQuotation = true;
      try {
        const { data } = await axios.post(`service_jobs/${this.jobId}/create_quotation`);
        if (this.jobMeta) {
          this.jobMeta = { ...this.jobMeta, quotation_id: data.quotation_id, quotation_ref: data.Ref };
        } else {
          this.jobMeta = { quotation_id: data.quotation_id, quotation_ref: data.Ref };
        }
        const msg = data.duplicate
          ? `Already linked to quotation ${data.Ref}.`
          : `Quotation ${data.Ref} created.`;
        this.makeToast('success', msg, this.$t('Success'));
      } catch (err) {
        const msg = (err.response && err.response.data && err.response.data.message) || 'Could not create quotation.';
        this.makeToast('danger', msg, this.$t('Failed'));
      } finally {
        this.creatingQuotation = false;
      }
    },

    /* ---------- Quote PDF ---------- */
    async downloadQuotePdf() {
      try {
        const response = await axios.get(`service_quote_pdf/${this.jobId}`, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `Service_Quote_${this.jobId}.pdf`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      } catch (e) {
        this.makeToast('danger', this.$t('Operation_Failed') || 'Operation failed', this.$t('Failed'));
      }
    },

    /* ---------- Photos ---------- */
    async uploadPhotos() {
      if (!this.photoFiles || this.photoFiles.length === 0) return;
      this.photoUploading = true;
      try {
        const fd = new FormData();
        fd.append('stage', this.photoStage);
        if (this.photoCaption) fd.append('caption', this.photoCaption);
        const files = Array.isArray(this.photoFiles) ? this.photoFiles : [this.photoFiles];
        files.forEach(f => fd.append('photos[]', f));
        // Don't set Content-Type explicitly — axios/browser must add the multipart boundary.
        await axios.post(`service_jobs/${this.jobId}/photos`, fd);
        this.photoFiles = null;
        this.photoCaption = '';
        await this.refreshPhotos();
        this.makeToast('success', this.$t('Successfully_Created'), this.$t('Success'));
      } catch (e) {
        this.makeToast('danger', this.$t('Upload_failed') || 'Upload failed', this.$t('Failed'));
      } finally {
        this.photoUploading = false;
      }
    },
    async refreshPhotos() {
      const { data } = await axios.get(`service_jobs/${this.jobId}/photos`);
      this.photos = data.photos || [];
    },
    async deletePhoto(ph) {
      const ok = await this.$swal({
        title: this.$t('Are_you_sure'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: this.$t('Yes_delete')
      });
      if (!ok.value) return;
      await axios.delete(`service_jobs/${this.jobId}/photos/${ph.id}`);
      await this.refreshPhotos();
    },

    /* ---------- Payments ---------- */
    openPaymentModal(p = null) {
      if (p) {
        this.paymentModalTitle = this.$t('Edit_Payment') || 'Edit Payment';
        this.paymentEditingId = p.id;
        this.paymentForm = {
          date: p.date || new Date().toISOString().slice(0, 10),
          montant: Number(p.montant) || 0,
          payment_kind: p.payment_kind || 'payment',
          payment_method_id: p.payment_method_id || null,
          notes: p.notes || ''
        };
      } else {
        this.paymentModalTitle = this.$t('Add_Payment') || 'Add Payment';
        this.paymentEditingId = null;
        this.paymentForm = {
          date: new Date().toISOString().slice(0, 10),
          montant: this.totals.balance_due > 0 ? this.totals.balance_due : 0,
          payment_kind: 'payment',
          payment_method_id: null,
          notes: ''
        };
      }
      this.paymentModalShow = true;
    },
    async submitPayment() {
      this.paymentSaving = true;
      try {
        if (this.paymentEditingId) {
          await axios.put(`service_jobs/${this.jobId}/payments/${this.paymentEditingId}`, this.paymentForm);
        } else {
          await axios.post(`service_jobs/${this.jobId}/payments`, this.paymentForm);
        }
        this.paymentModalShow = false;
        await this.loadJobIfNeeded();
        this.makeToast('success', this.$t('Successfully_Created'), this.$t('Success'));
      } catch (e) {
        this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'));
      } finally {
        this.paymentSaving = false;
      }
    },
    async deletePayment(p) {
      const ok = await this.$swal({
        title: this.$t('Are_you_sure'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: this.$t('Yes_delete')
      });
      if (!ok.value) return;
      await axios.delete(`service_jobs/${this.jobId}/payments/${p.id}`);
      await this.loadJobIfNeeded();
    },

    /* ---------- Helpers ---------- */
    statusBadgeClass(s) {
      const map = {
        delivered: 'badge-outline-success',
        ready: 'badge-outline-success',
        approved: 'badge-outline-primary',
        in_progress: 'badge-outline-primary',
        quoted: 'badge-outline-info',
        diagnostic: 'badge-outline-info',
        intake: 'badge-outline-info',
        declined: 'badge-outline-danger',
        cancelled: 'badge-outline-danger'
      };
      return map[s] || 'badge-outline-secondary';
    },
    statusLabel(s) { return s ? s.replace('_', ' ') : ''; },
    paymentBadgeClass(s) {
      if (s === 'paid') return 'badge-outline-success';
      if (s === 'partial') return 'badge-outline-warning';
      return 'badge-outline-danger';
    },
    kindIcon(kind) {
      if (kind === 'deposit') return 'piggy-bank';
      if (kind === 'refund') return 'rotate-ccw';
      return 'banknote';
    },
    formatNumber(n) {
      const v = Number(n) || 0;
      return v.toFixed(2);
    },
    formatDate(d) {
      if (!d) return '';
      try {
        return new Date(d).toLocaleString();
      } catch (e) { return d; }
    },
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    }
  }
};
</script>

<style scoped>
.repair-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  padding: 12px 16px;
  background: #f6f8fb;
  border-radius: 6px;
}
.rs-block { min-width: 130px; }
.rs-label { font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: .04em; }
.rs-value { font-size: 16px; font-weight: 600; }

.quote-status-box {
  background: #fafbfc;
  border: 1px dashed #d8dde3;
  border-radius: 6px;
  padding: 12px;
}

.repair-items-table th { font-size: 12px; text-transform: uppercase; }
.repair-items-table tfoot td { background: #fafbfc; }

.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}
.photo-tile {
  border: 1px solid #e7eaee;
  border-radius: 6px;
  padding: 6px;
  background: #fff;
}
.photo-tile img {
  width: 100%;
  height: 140px;
  object-fit: cover;
  cursor: zoom-in;
  border-radius: 4px;
}
.photo-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 6px;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-weight: 500;
  border-radius: 8px;
  padding: 7px 14px;
  transition: all .15s ease;
}
.back-btn:hover {
  background: #f1f5f9;
  color: #6366f1;
  border-color: #c7d2fe;
}
.back-btn svg { width: 16px; height: 16px; }

/* ===== Payments tab ===== */
.pay-stats { margin: 0 -8px; }
.pay-stats > [class*="col-"] { padding: 0 8px; }

.pay-stat {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 10px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  position: relative;
  overflow: hidden;
  height: 100%;
}
.pay-stat::before {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 4px;
  background: #cbd5e1;
}
.pay-stat--total::before { background: linear-gradient(180deg, #6366f1, #8b5cf6); }
.pay-stat--paid::before { background: linear-gradient(180deg, #10b981, #059669); }
.pay-stat--due::before { background: linear-gradient(180deg, #ef4444, #dc2626); }
.pay-stat--settled::before { background: linear-gradient(180deg, #10b981, #059669); }

.pay-stat__icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 44px;
  background: #f1f5f9;
  color: #475569;
}
.pay-stat--total .pay-stat__icon { background: #eef2ff; color: #6366f1; }
.pay-stat--paid .pay-stat__icon { background: #ecfdf5; color: #059669; }
.pay-stat--due .pay-stat__icon { background: #fef2f2; color: #dc2626; }
.pay-stat--settled .pay-stat__icon { background: #ecfdf5; color: #059669; }

.pay-stat__body { flex: 1; min-width: 0; }
.pay-stat__label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #6b7280;
  font-weight: 600;
}
.pay-stat__value {
  font-size: 20px;
  font-weight: 700;
  color: #0f172a;
  margin-top: 2px;
  line-height: 1.2;
}
.pay-stat__sub {
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.pay-progress {
  flex: 1;
  height: 6px;
  background: #e5e7eb;
  border-radius: 999px;
  overflow: hidden;
}
.pay-progress__bar {
  height: 100%;
  background: linear-gradient(90deg, #34d399, #10b981);
  transition: width .3s ease;
}
.pay-progress__label {
  font-size: 11px;
  font-weight: 600;
  color: #059669;
  min-width: 32px;
  text-align: right;
}

.pay-card {
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 10px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  overflow: hidden;
}
.pay-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
  border-bottom: 1px solid #eef0f4;
}
.pay-card__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}
.pay-card__title svg { color: #6366f1; }
.pay-card__count {
  margin-left: 4px;
  background: #fff !important;
  color: #475569 !important;
  border: 1px solid #e2e8f0;
  font-weight: 600;
}
.pay-add-btn {
  display: inline-flex;
  align-items: center;
  border-radius: 8px;
  font-weight: 500;
  padding: 6px 14px;
}

.pay-table-wrap { overflow-x: auto; }
.pay-table { margin: 0; }
.pay-table thead th {
  background: #fafbfc;
  border-top: none;
  border-bottom: 1px solid #eef0f4;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #64748b;
  font-weight: 600;
  padding: 12px 14px;
}
.pay-table tbody td {
  padding: 14px;
  border-top: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 13.5px;
  color: #1f2937;
}
.pay-table .pay-row { transition: background .15s ease; }
.pay-table .pay-row:hover { background: #fafbff; }

.pay-ref {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12.5px;
  background: #f1f5f9;
  color: #475569;
  padding: 3px 8px;
  border-radius: 6px;
  font-weight: 500;
}
.pay-date {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #475569;
}
.pay-date svg { width: 14px; height: 14px; color: #94a3b8; }

.pay-kind {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid transparent;
}
.pay-kind svg { width: 14px; height: 14px; }
.pay-kind--payment { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
.pay-kind--deposit { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.pay-kind--refund { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

.pay-method {
  display: inline-block;
  padding: 4px 10px;
  background: #eef2ff;
  color: #4338ca;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 500;
}

.pay-amount {
  font-weight: 700;
  font-size: 14px;
  font-variant-numeric: tabular-nums;
}

.pay-notes {
  display: inline-block;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
  color: #64748b;
}

.pay-action {
  background: #f1f5f9 !important;
  border: none !important;
  color: #475569 !important;
  border-radius: 6px;
  padding: 6px 8px;
  transition: all .15s ease;
}
.pay-action:hover { background: #6366f1 !important; color: #fff !important; }
.pay-action--danger:hover { background: #ef4444 !important; color: #fff !important; }
.pay-action svg { width: 14px; height: 14px; }

.pay-empty {
  text-align: center;
  padding: 48px 16px !important;
  color: #94a3b8;
  background: #fafbfc;
}
.pay-empty svg {
  width: 40px;
  height: 40px;
  color: #cbd5e1;
  margin-bottom: 8px;
}
.pay-empty div {
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 4px;
}
</style>
