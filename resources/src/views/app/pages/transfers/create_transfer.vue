<template>
  <div class="main-content">
    <breadcumb :page="$t('Create_Transfer')" :folder="$t('ListTransfers')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="Create_transfer" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Transfer">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>

                <b-modal hide-footer id="open_scan" size="md" title="Barcode Scanner">
                  <qrcode-scanner
                    :qrbox="250" 
                    :fps="10" 
                    style="width: 100%; height: calc(100vh - 56px);"
                    @result="onScan"
                  />
                </b-modal>

                 <!-- date  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="date"
                    :rules="{ required: true}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('date') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="date-feedback"
                        type="date"
                        v-model="transfer.date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="OrderTax-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                <!-- From warehouse -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="From Warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('FromWarehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        :disabled="details.length > 0"
                        @input="Selected_From_Warehouse"
                        v-model="transfer.from_warehouse"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- To warehouse -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="To Warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('ToWarehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="transfer.to_warehouse"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="to_warehouses.map(to_warehouses => ({label: to_warehouses.name, value: to_warehouses.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                 <!-- Product -->
                 <b-col md="12" class="mb-5">
                  <h6>{{$t('ProductName')}}</h6>
                 
                  <div id="autocomplete" class="autocomplete">
                    <div class="input-with-icon">
                      <img src="/assets_setup/scan.png" alt="Scan" class="scan-icon" @click="showModal">
                    <input 
                     :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                       @input='e => search_input = e.target.value' 
                      @keyup="search(search_input)"
                      @focus="handleFocus"
                      @blur="handleBlur"
                      ref="product_autocomplete"
                      class="autocomplete-input" />
                    </div>
                    <ul class="autocomplete-result-list" v-show="focused">
                      <li class="autocomplete-result" v-for="product_fil in product_filter" @mousedown="SearchProduct(product_fil)">{{getResultValue(product_fil)}}</li>
                    </ul>
                </div>
                </b-col>

                <!-- order products  -->
                <b-col md="12">
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="bg-gray-300">
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">{{$t('ProductName')}}</th>
                          <th scope="col">{{$t('Net_Unit_Cost')}}</th>
                          <th scope="col">{{$t('CurrentStock')}}</th>
                          <th scope="col">{{$t('Qty')}}</th>
                          <th scope="col">{{$t('Discount')}}</th>
                          <th scope="col">{{$t('Tax')}}</th>
                          <th scope="col">{{$t('SubTotal')}}</th>
                          <th scope="col" class="text-center">
                            <i class="fa fa-trash"></i>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="details.length <=0">
                          <td colspan="9">{{$t('NodataAvailable')}}</td>
                        </tr>
                        <template v-for="detail in details">
                        <tr :key="'r-' + detail.detail_id">
                          <td>{{detail.detail_id}}</td>
                          <td>
                            <span>{{detail.code}}</span>
                            <br>
                            <span class="badge badge-success">{{detail.name}}</span>
                            <div v-if="detail.warehouse_location" class="text-muted mt-1" style="font-size: 12px;">
                              {{ $t('Warehouse_Locations') }}: <strong>{{ detail.warehouse_location }}</strong>
                            </div>
                            <div v-if="detail.is_batch_tracked" class="mt-1">
                              <span class="badge" style="background:#eef2ff; color:#4f46e5; font-weight:600; letter-spacing:0.3px;">
                                <lucide-icon name="package" style="margin-right:3px;" />{{ $t('Batches') }}
                              </span>
                            </div>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.Net_cost, 3)}}</td>
                          <td>
                            <span
                              class="badge badge-outline-warning"
                            >{{detail.stock}} {{detail.unitPurchase}}</span>
                          </td>
                          <td>
                            <div class="quantity">
                              <b-input-group>
                                <b-input-group-prepend>
                                  <span
                                    class="btn btn-primary btn-sm"
                                    @click="decrement(detail ,detail.detail_id)"
                                  >-</span>
                                </b-input-group-prepend>
                                <input
                                  class="form-control"
                                  @keyup="Verified_Qty(detail,detail.detail_id)"
                                  v-model.number="detail.quantity"
                                >
                                <b-input-group-append>
                                  <span
                                    class="btn btn-primary btn-sm"
                                    @click="increment(detail ,detail.detail_id)"
                                  >+</span>
                                </b-input-group-append>
                              </b-input-group>
                            </div>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.taxe * detail.quantity, 2)}}</td>
                           <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          <td>
                            <lucide-icon class="text-25 text-success cursor-pointer" name="pencil" @click="Modal_Updat_Detail(detail)" />
                            <lucide-icon class="text-25 text-danger cursor-pointer" name="x" @click="delete_Product_Detail(detail.detail_id)" />
                          </td>
                        </tr>

                        <!-- Batch picker row for batch-tracked products -->
                        <tr v-if="detail.is_batch_tracked" :key="'b-' + detail.detail_id" style="background: transparent;">
                          <td colspan="9" style="padding: 0; border-top: 0;">
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
                                <button
                                  type="button"
                                  @click="add_batch_to_detail(detail)"
                                  style="padding: 4px 10px; font-size: 11px; font-weight: 600; background: #ffffff; color: #4f46e5; border: none; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center;"
                                >
                                  <lucide-icon name="plus" style="margin-right: 4px;" />{{ $t('Add') || 'Add' }}
                                </button>
                              </div>

                              <div v-if="detail.batches_loading" style="padding: 10px 14px; text-align: center; color: #6b7280; font-size: 12px;">
                                <div class="spinner sm spinner-primary" style="display: inline-block; margin-right: 8px;"></div>
                                {{ $t('Loading') || 'Loading...' }}
                              </div>

                              <div v-else-if="!(detail.available_batches && detail.available_batches.length)" style="padding: 12px 14px; text-align: center; color: #b91c1c; font-size: 12px; background: #fef2f2;">
                                <lucide-icon name="info" style="margin-right: 6px;" />
                                {{ $t('No_Batches_Available') || 'No available batches for this product in the source warehouse' }}
                              </div>

                              <div v-else-if="!detail.batches || detail.batches.length === 0" style="padding: 12px 14px; text-align: center; color: #6b7280; font-size: 12px;">
                                <lucide-icon name="info" style="margin-right: 6px;" />
                                {{ $t('Click_Add_To_Pick_Batch') || 'Click "Add" to pick a batch (or leave empty to auto-allocate FEFO)' }}
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
                                  <tr v-for="(b, bIdx) in detail.batches" :key="'tb-' + detail.detail_id + '-' + bIdx" :style="{ background: bIdx % 2 === 1 ? '#f8faff' : '#ffffff', borderTop: '1px solid #e0e7ff' }">
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
                                      <button
                                        type="button"
                                        @click="remove_batch_from_detail(detail, bIdx)"
                                        style="width: 26px; height: 26px; padding: 0; display: inline-flex; align-items: center; justify-content: center; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; font-size: 12px;"
                                      >
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

                <div class="offset-md-9 col-md-3 mt-4">
                  <table class="table table-striped table-sm">
                    <tbody>
                      <tr>
                        <td class="bold">{{$t('OrderTax')}}</td>
                        <td>
                          <span>{{currentUser.currency}} {{transfer.TaxNet.toFixed(2)}} ({{formatNumber(transfer.tax_rate ,2)}} %)</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Discount')}}</td>
                        <td>{{currentUser.currency}} {{transfer.discount.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Shipping')}}</td>
                        <td>{{currentUser.currency}} {{transfer.shipping.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td>
                          <span class="font-weight-bold">{{$t('Total')}}</span>
                        </td>
                        <td>
                          <span
                            class="font-weight-bold"
                          >{{currentUser.currency}} {{GrandTotal.toFixed(2)}}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Order Tax  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="Order Tax"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('OrderTax')">
                      <b-input-group append="%">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="OrderTax-feedback"
                          label="Order Tax"
                          v-model.number="transfer.tax_rate"
                          @keyup="keyup_OrderTax()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="OrderTax-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Discount -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="Discount"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Discount')">
                      <b-input-group :append="currentUser.currency">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Discount-feedback"
                          label="Discount"
                          v-model.number="transfer.discount"
                          @keyup="keyup_Discount()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="Discount-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Shipping  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="Shipping"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Shipping')">
                      <b-input-group :append="currentUser.currency">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Shipping-feedback"
                          label="Shipping"
                          v-model.number="transfer.shipping"
                          @keyup="keyup_Shipping()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="Shipping-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                 <!-- Status  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Status" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Status') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="transfer.statut"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Status')"
                        :options="
                                [{label: 'Completed', value: 'completed'},
                                {label: 'Sent', value: 'sent'},
                                {label: 'Pending', value: 'pending'}
                            ]"
                      ></v-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col md="12">
                  <b-form-group :label="$t('Note')">
                    <textarea
                      v-model="transfer.notes"
                      rows="4"
                      class="form-control"
                      :placeholder="$t('Afewwords')"
                    ></textarea>
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
                    <b-button variant="primary" @click="Submit_Transfer" :disabled="SubmitProcessing || hasBatchValidationErrors"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                     <div v-once class="typo__p" v-if="SubmitProcessing">
                      <div class="spinner sm spinner-primary mt-3"></div>
                    </div>
                  </b-form-group>
                </b-col>
              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>

    <!-- Modal Update detail Product -->
    <validation-observer ref="Update_Detail_transfer">
      <b-modal hide-footer size="md" id="form_Update_Detail" :title="detail.name">
        <b-form @submit.prevent="submit_Update_Detail">
          <b-row>
            <!-- Unit Cost -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="Product Cost"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('ProductCost') + ' ' + '*'" id="cost-input">
                  <b-form-input
                    label="Product Cost"
                    v-model.number="detail.Unit_cost"
                    :state="getValidationState(validationContext)"
                    aria-describedby="cost-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="cost-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Tax Method -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider name="Tax Method" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('TaxMethod') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="detail.tax_method"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Method')"
                    :options="
                           [
                            {label: 'Exclusive', value: '1'},
                            {label: 'Inclusive', value: '2'}
                           ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Tax Rate -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="Order Tax"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('OrderTax') + ' ' + '*'">
                  <b-input-group append="%">
                    <b-form-input
                      label="Order Tax"
                      v-model.number="detail.tax_percent"
                      :state="getValidationState(validationContext)"
                      aria-describedby="OrderTax-feedback"
                    ></b-form-input>
                  </b-input-group>
                  <b-form-invalid-feedback id="OrderTax-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Discount Method -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider name="Discount Method" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Discount_Method') + ' ' + '*'">
                  <v-select
                    v-model="detail.discount_Method"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Method')"
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    :options="
                           [
                            {label: 'Percent %', value: '1'},
                            {label: 'Fixed', value: '2'}
                           ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Discount Rate -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="Discount Rate"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Discount') + ' ' + '*'">
                  <b-form-input
                    label="Discount"
                    v-model.number="detail.discount"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Discount-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Discount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

             <!-- Unit Purchase -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider name="Unit Purchase" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('UnitPurchase') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="detail.purchase_unit_id"
                    :placeholder="$t('Choose_Unit_Purchase')"
                    :reduce="label => label.value"
                    :options="units.map(units => ({label: units.name, value: units.id}))"
                  />
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="12">
              <b-form-group>
                <b-button variant="primary" type="submit">{{$t('submit')}}</b-button>
              </b-form-group>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Create Transfer"
  },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],
      isLoading: true,
      SubmitProcessing:false,
      details: [],
      detail: {
        quantity: "",
        discount: "",
        Unit_cost: "",
        discount_Method: "",
        tax_percent: "",
        tax_method: ""
      },
      warehouses: [],
      to_warehouses: [],
      products: [],
      units: [],
      symbol: "",
      transfer: {
        id: "",
        from_warehouse: "",
        to_warehouse: "",
        statut: "completed",
        notes: "",
        date: new Date().toISOString().slice(0, 10),
        items: 0,
        tax_rate: 0,
        TaxNet: 0,
        shipping: 0,
        discount: 0
      },
      total: 0,
      GrandTotal: 0,
      product: {
        id: "",
        code: "",
        stock: "",
        quantity: 1,
        discount: "",
        DiscountNet: "",
        discount_Method: "",
        name: "",
        unitPurchase: "",
        purchase_unit_id:"",
        fix_stock:"",
        fix_cost:"",
        Net_cost: "",
        Unit_cost: "",
        Total_cost: "",
        subtotal: "",
        product_id: "",
        detail_id: "",
        taxe: "",
        tax_percent: "",
        tax_method: "",
        product_variant_id: "",
        is_batch_tracked: false,
        batches: [],
        available_batches: [],
        batches_loading: false
      }
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),

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
          if (!b.product_batch_id) {
            return (this.$t("Select_Batch_Required_For") || "Select a batch for") + " " + label;
          }
          const q = Number(b.qty);
          if (!(q > 0)) {
            return (this.$t("Batch_Qty_Required_For") || "Batch quantity must be greater than 0 for") + " " + label;
          }
          if (q > (Number(b.qty_available) || 0) + 0.01) {
            return (this.$t("Batch_Qty_Exceeds_Available") || "Batch quantity exceeds available stock for") + " " + label;
          }
          if (seen.has(b.product_batch_id)) {
            return (this.$t("Duplicate_Batch_Selected") || "The same batch is selected twice for") + " " + label;
          }
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

     handleFocus() {
      this.focused = true
    },

    handleBlur() {
      this.focused = false
    },

    showModal() {
      this.$bvModal.show('open_scan');
      
    },

    onScan (decodedText, decodedResult) {
      const code = decodedText;
      this.search_input = code;
      this.search();
      this.$bvModal.hide('open_scan');
    },

    
    //------------- Submit Validation Create Transfer
    Submit_Transfer() {
      this.$refs.Create_transfer.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Transfer();
        }
      });
    },

    //---Submit Validation Update Detail
    submit_Update_Detail() {
      this.$refs.Update_Detail_transfer.validate().then(success => {
        if (!success) {
          return;
        } else {
          this.Update_Detail();
        }
      });
    },

     //---------------------- get_units ------------------------------\\
    get_units(value) {
      axios
        .get("get_units?id=" + value)
        .then(({ data }) => (this.units = data));
    },

    //------ Show Modal Update Detail Product
    Modal_Updat_Detail(detail) {
      this.detail = {};
      this.detail.name = detail.name;
      this.get_units(detail.product_id);
      this.detail.detail_id = detail.detail_id;
      this.detail.purchase_unit_id = detail.purchase_unit_id;
      this.detail.Unit_cost = detail.Unit_cost;
      this.detail.tax_method = detail.tax_method;
      this.detail.fix_cost = detail.fix_cost;
      this.detail.fix_stock = detail.fix_stock;
      this.detail.stock = detail.stock;
      this.detail.discount_Method = detail.discount_Method;
      this.detail.discount = detail.discount;
      this.detail.quantity = detail.quantity;
      this.detail.tax_percent = detail.tax_percent;
      this.$bvModal.show("form_Update_Detail");
    },

    //------ Submit Update Detail Product

    Update_Detail() {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === this.detail.detail_id) {

          // this.convert_unit();
           for(var k=0; k<this.units.length; k++){
              if (this.units[k].id == this.detail.purchase_unit_id) {
                if(this.units[k].operator == '/'){
                  this.details[i].stock       = this.detail.fix_stock  * this.units[k].operator_value;
                  this.details[i].unitPurchase    = this.units[k].ShortName;

                }else{
                  this.details[i].stock       = this.detail.fix_stock  / this.units[k].operator_value;
                  this.details[i].unitPurchase    = this.units[k].ShortName;
                }
              }
            }


            if (this.details[i].stock < this.details[i].quantity) {
              this.details[i].quantity = this.details[i].stock;
            } else {
              this.details[i].quantity =1;
            }
            
          
          this.details[i].Unit_cost = this.detail.Unit_cost;
          this.details[i].tax_percent = this.detail.tax_percent;
          this.details[i].tax_method = this.detail.tax_method;
          this.details[i].discount_Method = this.detail.discount_Method;
          this.details[i].discount = this.detail.discount;
          this.details[i].purchase_unit_id = this.detail.purchase_unit_id;

          if (this.details[i].discount_Method == "2") {
            //Fixed
            this.details[i].DiscountNet = this.details[i].discount;
          } else {
            //Percentage %
            this.details[i].DiscountNet = parseFloat(
              (this.details[i].Unit_cost * this.details[i].discount) / 100
            );
          }

          if (this.details[i].tax_method == "1") {
            //Exclusive
            this.details[i].Net_cost = parseFloat(
              this.details[i].Unit_cost - this.details[i].DiscountNet
            );

            this.details[i].taxe = parseFloat(
              (this.details[i].tax_percent *
                (this.details[i].Unit_cost - this.details[i].DiscountNet)) /
                100
            );
          } else {
            //Inclusive
            this.details[i].taxe = parseFloat(
              (this.details[i].Unit_cost - this.details[i].DiscountNet) *
                (this.details[i].tax_percent / 100)
            );

            this.details[i].Net_cost = parseFloat(
              this.details[i].Unit_cost -
                this.details[i].taxe -
                this.details[i].DiscountNet
            );
          }

          this.$forceUpdate();
        }
      }
      this.Calcul_Total();
      this.$bvModal.hide("form_Update_Detail");
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    
    // Search Products
    search(){

      if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
      }

      if (this.search_input.length < 2) {

        return this.product_filter= [];
      }
      if (this.transfer.from_warehouse != "" &&  this.transfer.from_warehouse != null) {
        this.timer = setTimeout(() => {
          const product_filter = this.products.filter(product => product.code === this.search_input || product.barcode.includes(this.search_input));
            if(product_filter.length === 1){
                this.SearchProduct(product_filter[0])
            }else{
                this.product_filter=  this.products.filter(product => {
                  return (
                    product.name.toLowerCase().includes(this.search_input.toLowerCase()) ||
                    product.code.toLowerCase().includes(this.search_input.toLowerCase()) ||
                    product.barcode.toLowerCase().includes(this.search_input.toLowerCase())
                    );
                });

                // Check if product_filter is empty and show alert
                if (this.product_filter.length <= 0) {
                  this.makeToast(
                    "warning",
                    "Product Not Found",
                    "Warning"
                  );
                }
            }
        }, 800);
      } else {
        this.makeToast(
          "warning",
          this.$t("SelectWarehouse"),
          this.$t("Warning")
        );
      }

    },

       

    //-------------------- get Result Value Search Product

    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },

    //--------------------  Submit Search Product

    SearchProduct(result) {
      this.product = {};
      if (
        this.details.length > 0 &&
        this.details.some(detail => detail.code === result.code)
      ) {
        this.makeToast("warning", this.$t("AlreadyAdd"), this.$t("Warning"));
      } else {
        this.product.code = result.code;
        this.product.stock = result.qte_purchase;
        this.product.fix_stock = result.qte;
        if (result.qte_purchase < 1) {
          this.product.quantity = result.qte_purchase;
        } else {
          this.product.quantity = 1;
        }
        this.product.product_variant_id = result.product_variant_id;
        this.Get_Product_Details(result.id, result.product_variant_id);
      }

      this.search_input= '';
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },

    //-----------------------------------------Calcul Total ------------------------------\\
    Calcul_Total() {
      this.total = 0;
      for (let index = 0; index < this.details.length; index++) {
        var tax = this.details[index].taxe * this.details[index].quantity;
        this.details[index].subtotal = parseFloat(
          this.details[index].quantity * this.details[index].Net_cost + tax
        );
        this.total = parseFloat(this.total + this.details[index].subtotal);
      }

      const total_without_discount = parseFloat(
        this.total - this.transfer.discount
      );
      this.transfer.TaxNet = parseFloat(
        (total_without_discount * this.transfer.tax_rate) / 100
      );
      this.GrandTotal = parseFloat(
        total_without_discount + this.transfer.TaxNet + this.transfer.shipping
      );

      var grand_total =  this.GrandTotal.toFixed(2);
      this.GrandTotal = parseFloat(grand_total);
    },

    //---------- keyup OrderTax
    keyup_OrderTax() {
      if (isNaN(this.transfer.tax_rate)) {
        this.transfer.tax_rate = 0;
      } else if(this.transfer.tax_rate == ''){
         this.transfer.tax_rate = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //---------- keyup Discount

    keyup_Discount() {
      if (isNaN(this.transfer.discount)) {
        this.transfer.discount = 0;
      } else if(this.transfer.discount == ''){
         this.transfer.discount = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //---------- keyup Shipping

    keyup_Shipping() {
      if (isNaN(this.transfer.shipping)) {
        this.transfer.shipping = 0;
      } else if(this.transfer.shipping == ''){
         this.transfer.shipping = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //-----------------------------------Delete Detail Product ------------------------------\\
    delete_Product_Detail(id) {
      for (var i = 0; i < this.details.length; i++) {
        if (id === this.details[i].detail_id) {
          this.details.splice(i, 1);
          this.Calcul_Total();
        }
      }
    },

    //-----------------------------------Verified Form ------------------------------\\

    verifiedForm() {
      if (this.details.length <= 0) {
        this.makeToast(
          "warning",
          this.$t("AddProductToList"),
          this.$t("Warning")
        );
        return false;
      } else if (this.transfer.from_warehouse === this.transfer.to_warehouse) {
        this.makeToast(
          "warning",
          this.$t("WarehouseIdentical"),
          this.$t("Warning")
        );
        return false;
      } else {
        var count = 0;
        for (var i = 0; i < this.details.length; i++) {
          if (
            this.details[i].quantity == "" ||
            this.details[i].quantity === 0
          ) {
            count += 1;
          }
        }

        if (count > 0) {
          this.makeToast("warning", this.$t("AddQuantity"), this.$t("Warning"));
          return false;
        }
        if (this.hasBatchValidationErrors) {
          this.makeToast(
            "danger",
            this.firstBatchErrorMessage || (this.$t("Total_batch_qty_mismatch") || "Batch quantities are invalid"),
            this.$t("Failed") || "Failed"
          );
          return false;
        }
        return true;
      }
    },

    //-------------------------------- Create Transfer ----------------------\\
    Create_Transfer() {
      if (this.verifiedForm()) {
        this.SubmitProcessing = true;
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        axios
          .post("transfers", {
            transfer: this.transfer,
            details: this.buildSubmitDetails(),
            GrandTotal: this.GrandTotal
          })
          .then(response => {
            // Complete the animation of theprogress bar.
            NProgress.done();
            this.SubmitProcessing = false;
            this.$router.push({
              name: "index_transfer"
            });

            this.makeToast(
              "success",
              this.$t("Successfully_Created"),
              this.$t("Success")
            );
          })
          .catch(error => {
            // Complete the animation of theprogress bar.
            NProgress.done();
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            this.SubmitProcessing = false;
          });
      }
    },

    //-------------------------------- Get Last Detail Id -------------------------\\
    Last_Detail_id() {
      this.product.detail_id = 0;
      var len = this.details.length;
      this.product.detail_id = this.details[len - 1].detail_id + 1;
    },

    //----------------------------------------- Add Detail of Transfer -------------------------\\
    add_Detail() {
      if (this.details.length > 0) {
        this.Last_Detail_id();
      } else if (this.details.length === 0) {
        this.product.detail_id = 1;
      }

      this.details.push(this.product);
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
      const wid = this.transfer && this.transfer.from_warehouse;
      const productId = detail.product_id || detail.id;
      if (!wid || !productId) {
        this.$set(detail, "batches_loading", false);
        return;
      }
      const variantSeg = (detail.product_variant_id != null && detail.product_variant_id !== "")
        ? detail.product_variant_id
        : 0;
      // Existing batch picks already debited the source — add their qty back to
      // qty_available so the user can re-edit allocations without false over-allocation.
      const existingQtyById = {};
      for (const b of (Array.isArray(detail.batches) ? detail.batches : [])) {
        if (b && b.product_batch_id != null) {
          existingQtyById[b.product_batch_id] = (existingQtyById[b.product_batch_id] || 0) + (Number(b.qty) || 0);
        }
      }
      this.$set(detail, "batches_loading", true);
      axios
        .get(`batches_for_transfer/${productId}/${wid}/${variantSeg}`, { timeout: 15000 })
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
        .catch(() => {
          this.$set(detail, "available_batches", []);
        })
        .then(() => {
          this.$set(detail, "batches_loading", false);
        });
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
      const base = {
        display: "inline-block",
        padding: "2px 8px",
        fontSize: "11px",
        fontWeight: "600",
        borderRadius: "10px",
      };
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
            .map(b => ({
              product_batch_id: Number(b.product_batch_id),
              qty: Number(b.qty) || 0,
            }));
        } else {
          delete out.batches;
        }
        return out;
      });
    },

    //-----------------------------------Verified QTY ------------------------------\\
    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (isNaN(detail.quantity)) {
            this.details[i].quantity = detail.stock;
          }

          if (detail.quantity > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            this.details[i].quantity = detail.stock;
          } else {
            this.details[i].quantity = detail.quantity;
          }
        }
      }
      this.Calcul_Total();
      this.$forceUpdate();
    },

    //-----------------------------------increment QTY ------------------------------\\

    increment(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.quantity + 1 > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
          } else {
            this.formatNumber(this.details[i].quantity++, 2);
          }
        }
      }
      this.Calcul_Total();
      this.$forceUpdate();
    },

    //-----------------------------------decrement QTY ------------------------------\\

    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (detail.quantity - 1 >= 1) {
            if (detail.quantity - 1 > detail.stock) {
              this.makeToast(
                "warning",
                this.$t("LowStock"),
                this.$t("Warning")
              );
            } else {
              this.formatNumber(this.details[i].quantity--, 2);
            }
          }
        }
      }
      this.Calcul_Total();
      this.$forceUpdate();
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

     //------------------------------------ Get Products By Warehouse -------------------------\\

    Get_Products_By_Warehouse(id) {
      // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
      axios
        .get("get_Products_by_warehouse/" + id + "?stock=" + 1 + "&product_service=" + 0 + "&product_combo=" + 1)
         .then(response => {
            this.products = response.data;
             NProgress.done();

            })
          .catch(error => {
          });
    },

    //---------------------------------Get Product Details ------------------------\\

    Get_Product_Details(product_id, variant_id) {
      const wid = this.transfer && this.transfer.from_warehouse ? this.transfer.from_warehouse : null;
      const url = wid
        ? `/show_product_data/${product_id}/${variant_id}/${wid}`
        : `/show_product_data/${product_id}/${variant_id}`;

      axios.get(url).then(response => {
        this.product.discount           = response.data.discount;
        this.product.DiscountNet        = response.data.DiscountNet;
        this.product.discount_Method    = response.data.discount_method;
        this.product.product_id = response.data.id;
        this.product.name = response.data.name;
        this.product.Net_cost = response.data.Net_cost;
        this.product.Unit_cost = response.data.Unit_cost;
        this.product.taxe = response.data.tax_cost;
        this.product.tax_method = response.data.tax_method;
        this.product.tax_percent = response.data.tax_percent;
        this.product.unitPurchase = response.data.unitPurchase;
        this.product.fix_cost = response.data.fix_cost;
        this.product.purchase_unit_id = response.data.purchase_unit_id;
        this.product.warehouse_location = response.data.warehouse_location
          ? (response.data.warehouse_location.name
              ? `${response.data.warehouse_location.code} - ${response.data.warehouse_location.name}`
              : response.data.warehouse_location.code)
          : null;
        this.$set(this.product, "is_batch_tracked", !!response.data.is_batch_tracked);
        this.$set(this.product, "batches", []);
        this.$set(this.product, "available_batches", []);
        this.$set(this.product, "batches_loading", false);
        this.add_Detail();
        this.Calcul_Total();

        if (this.product.is_batch_tracked) {
          const last = this.details[this.details.length - 1];
          if (last) this.fetch_batches_for_detail(last);
        }
      });
    },

    //---------------------- Event Select From Warehouse ------------------------------\\
    Selected_From_Warehouse(value) {
      this.search_input= '';
      this.product_filter = [];
      this.Get_Products_By_Warehouse(value);

      // Pharmacy: source warehouse changed → refetch available batches per cart line.
      if (Array.isArray(this.details)) {
        for (const d of this.details) {
          if (d && d.is_batch_tracked) {
            this.$set(d, "batches", []);
            this.fetch_batches_for_detail(d);
          }
        }
      }
    },

    //-------------------------------------- Get Elements Create Transfer----------------------\\
    Get_Elements() {
      axios
        .get("transfers/create")
        .then(response => { 
          this.warehouses = response.data.warehouses;
          this.to_warehouses = response.data.to_warehouses;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  //----------------------------- Created function-------------------
  created: function() {
    this.Get_Elements();
  }
};
</script>

<style>

  .input-with-icon {
    display: flex;
    align-items: center;
  }

  .scan-icon {
    width: 50px; /* Adjust size as needed */
    height: 50px;
    margin-right: 8px; /* Adjust spacing as needed */
    cursor: pointer;
  }
</style>
