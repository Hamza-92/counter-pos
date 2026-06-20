<template>
  <div class="main-content">
    <breadcumb :page="$t('EditSale')" :folder="$t('ListSales')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="edit_sale" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Sale">
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
                        v-model="sale.date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="OrderTax-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                <!-- Customer -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Customer" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Customer') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="sale.client_id"
                        disabled
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Customer')"
                        :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- warehouse -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('warehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        :disabled="details.length > 0"
                        @input="Selected_Warehouse"
                        v-model="sale.warehouse_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Sales Agent -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
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

                <!-- Order products  -->
                <b-col md="12">
                  <h5>{{$t('order_products')}} *</h5>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="bg-gray-300">
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">{{$t('ProductName')}}</th>
                          <th scope="col">{{$t('Net_Unit_Price')}}</th>
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
                        <tr
                          :class="{'row_deleted': detail.del === 1 || (detail.no_unit === 0 && detail.product_type != 'is_service')}"
                          :key="'d-' + detail.detail_id"

                          >
                          <td>{{detail.detail_id}}</td>
                          <td>
                            <span>{{detail.code}}</span>
                            <br>
                            <span class="badge badge-success">{{detail.name}}</span>
                            <div v-if="detail.warehouse_location" class="text-muted mt-1" style="font-size: 12px;">
                              {{ $t('Warehouse_Locations') }}: <strong>{{ detail.warehouse_location }}</strong>
                            </div>
                            <div v-if="detail.is_batch_tracked" class="mt-1">
                              <span class="badge" style="background: #eef2ff; color: #4f46e5; font-weight: 600; letter-spacing: 0.3px;">
                                <lucide-icon name="package" style="margin-right: 3px;" />{{ $t('Batches') || 'Batches' }}
                              </span>
                            </div>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.Net_price, 3)}}</td>
                          <td>
                            <span class="badge badge-warning" v-if="detail.product_type == 'is_service'">----</span>
                            <span class="badge badge-warning" v-else>{{detail.stock}} {{detail.unitSale}}</span>
                          </td>
                          <td>
                            <div class="quantity">
                              <b-input-group>
                                <b-input-group-prepend>
                                  <span v-show="detail.no_unit !== 0 || detail.product_type == 'is_service'"
                                    class="btn btn-primary btn-sm"
                                    @click="decrement(detail ,detail.detail_id)"
                                  >-</span>
                                </b-input-group-prepend>
                                <input
                                  class="form-control"
                                  @keyup="Verified_Qty(detail,detail.detail_id)"
                                  :min="0.00"
                                  :max="detail.stock"
                                  v-model.number="detail.quantity"
                                  :disabled="detail.del === 1 || (detail.no_unit === 0 && detail.product_type != 'is_service')"
                                >
                                <b-input-group-append>
                                  <span v-show="detail.no_unit !== 0 || detail.product_type == 'is_service'"
                                    class="btn btn-primary btn-sm"
                                    @click="increment(detail ,detail.detail_id)"
                                  >+</span>
                                </b-input-group-append>
                              </b-input-group>
                            </div>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.taxe * detail.quantity , 2)}}</td>
                          <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          <td v-show="detail.no_unit !== 0 || detail.product_type == 'is_service'">
                            <lucide-icon class="text-25 text-success cursor-pointer" name="pencil" v-if="currentUserPermissions && currentUserPermissions.includes('edit_product_sale')" @click="Modal_Updat_Detail(detail)" />
                            <lucide-icon class="text-25 text-danger cursor-pointer" name="x" @click="delete_Product_Detail(detail.detail_id)" />
                          </td>
                        </tr>

                        <!-- Optional batch override row for batch-tracked products.
                             Leaving batches empty makes the backend auto-FEFO on save. -->
                        <tr v-if="detail.is_batch_tracked" :key="'batches-' + detail.detail_id" style="background: transparent;">
                          <td colspan="9" style="padding: 0; border-top: 0;">
                            <div style="margin: 6px 8px 14px 8px; border: 1px solid #e0e7ff; border-radius: 10px; overflow: hidden; background: linear-gradient(180deg, #f8faff 0%, #ffffff 100%);">
                              <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 14px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #fff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                  <lucide-icon name="package" />
                                  <span>{{ $t('Batches') || 'Batches' }}</span>
                                  <span style="background: rgba(255,255,255,0.22); padding: 1px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">
                                    {{ (detail.batches || []).length }} {{ $t('items') || 'items' }}
                                    <span v-if="(detail.batches || []).length" style="margin-left: 4px;">
                                      · {{ $t('Total') }}: {{ formatNumber(batch_total_qty(detail), 2) }} / {{ formatNumber(Number(detail.quantity) || 0, 2) }}
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
                                {{ $t('No_Batches_Available') || 'No available batches for this product in the selected warehouse' }}
                              </div>

                              <div v-else-if="!detail.batches || detail.batches.length === 0" style="padding: 12px 14px; text-align: center; color: #6b7280; font-size: 12px;">
                                <lucide-icon name="info" style="margin-right: 6px;" />
                                {{ $t('Leave_Empty_For_Auto_FEFO') || 'Leave empty to auto-allocate oldest-expiring batches first (FEFO), or click "Add" to override.' }}
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
                                  <tr v-for="(b, bIdx) in detail.batches" :key="'b-' + detail.detail_id + '-' + bIdx" :style="{ background: bIdx % 2 === 1 ? '#f8faff' : '#ffffff', borderTop: '1px solid #e0e7ff' }">
                                    <td style="padding: 6px 8px; vertical-align: middle; min-width: 220px;">
                                      <v-select
                                        :value="b.product_batch_id"
                                        :options="detail.available_batches.map(ab => ({
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
                                        :style="{ textAlign: 'right', fontSize: '12px', padding: '5px 8px', height: '30px', borderRadius: '6px', borderColor: (Number(b.qty) > (Number(b.qty_available) || 0)) ? '#fca5a5' : undefined }"
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

                              <div v-if="detail.available_batches && detail.available_batches.length && (detail.batches || []).length > 0 && batch_qty_mismatch(detail)" style="padding: 8px 14px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600; border-top: 1px solid #fde68a; display: flex; align-items: center;">
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

                <div class="offset-md-8 col-md-4 mt-4">
                  <table class="table table-striped table-sm">
                    <tbody>
                      <tr>
                        <td class="bold">{{$t('OrderTax')}}</td>
                        <td>
                          <span>{{currentUser.currency}} {{sale.TaxNet.toFixed(2)}} ({{formatNumber(sale.tax_rate ,2)}} %)</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Discount')}}</td>
                        <td>
                          <!-- If percentage: show percent value AND discount amount; else amount only -->
                          <template v-if="String(sale.discount_Method || '2') === '1'">
                            {{ formatNumber(sale.discount, 2) }}% ({{ currentUser.currency }} {{ getCurrentSaleDiscountAmount().toFixed(2) }})
                          </template>
                          <template v-else>
                            {{currentUser.currency}} {{ getCurrentSaleDiscountAmount().toFixed(2) }}
                          </template>
                        </td>
                      </tr>
                      <tr v-if="discount_from_points && discount_from_points > 0">
                        <td class="bold">{{$t('Discount_from_Points')}}</td>
                        <td>{{currentUser.currency}} {{discount_from_points.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Shipping')}}</td>
                        <td>{{currentUser.currency}} {{sale.shipping.toFixed(2)}}</td>
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
                <b-col lg="4" md="4" sm="12" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')">
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
                          v-model.number="sale.tax_rate"
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
                <b-col lg="4" md="4" sm="12" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')">
                  <validation-provider
                    name="Discount"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Discount')">
                      <div class="d-flex" style="gap:8px; align-items:center;">
                        <b-input-group :append="sale.discount_Method === '1' ? '%' : currentUser.currency" class="flex-grow-1">
                          <b-form-input
                            :state="getValidationState(validationContext)"
                            aria-describedby="Discount-feedback"
                            label="Discount"
                            v-model.number="sale.discount"
                            @keyup="keyup_Discount()"
                          ></b-form-input>
                        </b-input-group>
                        <b-form-select
                          v-model="sale.discount_Method"
                          :options="[
                            { text: 'Fixed', value: '2' },
                            { text: 'Percent %', value: '1' }
                          ]"
                          style="max-width: 110px;"
                        ></b-form-select>
                      </div>
                      <b-form-invalid-feedback
                        id="Discount-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Points to convert (loyalty) -->
                <b-col
                  lg="4"
                  md="4"
                  sm="12"
                  class="mb-3"
                  v-if="showPointsSection && currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')"
                >
                  <label>Points to convert</label>
                  <div class="field mb-2">
                    <b-form-input
                      ref="pointsInput"
                      v-model.number="points_to_convert"
                      @input="onPointsToConvertInput"
                      type="text"
                      min="1"
                      :max="selectedClientPoints"
                      step="1"
                      :disabled="selectedClientPoints === 0 || pointsConverted"
                      placeholder="e.g., 200"
                    ></b-form-input>
                    <div class="hint mt-1">
                      Total available:
                      <strong>{{ selectedClientPoints }}</strong> pts
                    </div>
                  </div>

                  <div class="actions d-flex align-items-center" style="gap:10px;">
                    <b-button
                      :variant="pointsConverted ? 'secondary' : 'dark'"
                      @click="convertPointsToDiscount"
                      :disabled="(!pointsConverted && (selectedClientPoints === 0 || !pointsInputValid))"
                    >
                      <template v-if="!pointsConverted">Convert</template>
                      <template v-else>Unconvert</template>
                    </b-button>
                    <small
                      v-if="!pointsConverted && points_to_convert && !pointsInputValid"
                      class="warn"
                    >
                      Enter a value from 1 to your available points.
                    </small>
                    <small
                      v-if="!pointsConverted && pointsInputValid"
                      class="ok"
                    >
                      Looks good.
                    </small>
                  </div>

                  <div class="result mt-2" v-if="discount_from_points > 0">
                    ✅ Discount of
                    <strong>{{ discount_from_points }}</strong>
                    {{ currentUser.currency }}
                    will be applied
                  </div>
                </b-col>
                

                <!-- Shipping  -->
                <b-col lg="4" md="4" sm="12" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')">
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
                          v-model.number="sale.shipping"
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
                        v-model="sale.statut"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Status')"
                        :options="
                                [
                                  {label: 'completed', value: 'completed'},
                                  {label: 'Pending', value: 'pending'},
                                  {label: 'ordered', value: 'ordered'}
                                ]"
                      ></v-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col md="12">
                  <b-form-group :label="$t('Note')">
                    <textarea
                      v-model="sale.notes"
                      rows="4"
                      class="form-control"
                      :placeholder="$t('Afewwords')"
                    ></textarea>
                  </b-form-group>
                </b-col>
                <b-col md="12" v-if="sale.statut === 'completed' && hasBatchValidationErrors">
                  <div class="alert alert-warning mt-2" style="font-size: 13px; font-weight: 600;">
                    <lucide-icon class="me-1" name="info" />
                    {{ firstBatchErrorMessage }}
                  </div>
                </b-col>
                <b-col md="12">
                  <b-form-group>
                    <b-button variant="primary" @click="Submit_Sale" :disabled="SubmitProcessing || (sale.statut === 'completed' && hasBatchValidationErrors)"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
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

    <!-- Modal Update Detail Product -->
    <validation-observer ref="Update_Detail">
      <b-modal hide-footer size="lg" id="form_Update_Detail" :title="detail.name">
        <b-form @submit.prevent="submit_Update_Detail">
          <b-row>
            <!-- Unit Price -->
           <b-col lg="6" md="6" sm="12">
              <validation-provider
                name="Product Price"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('ProductPrice') + ' ' + '*'" id="Price-input">
                  <b-form-input
                    label="Product Price"
                    v-model.number="detail.Unit_price"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Price-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Price-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Tax Method -->
           <b-col lg="6" md="6" sm="12">
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
           <b-col lg="6" md="6" sm="12">
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
           <b-col lg="6" md="6" sm="12">
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
            <b-col lg="6" md="6" sm="12">
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

             <!-- Imei or serial numbers -->
              <b-col lg="12" md="12" sm="12" v-show="detail.is_imei">
                <b-form-group :label="$t('Add_product_IMEI_Serial_number')">
                  <b-form-input
                    label="Add_product_IMEI_Serial_number"
                    v-model="detail.imei_number"
                    :placeholder="$t('Add_product_IMEI_Serial_number')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <b-col md="12">
               <b-form-group>
                <b-button
                  variant="primary"
                  type="submit"
                  :disabled="Submit_Processing_detail"
                ><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                <div v-once class="typo__p" v-if="Submit_Processing_detail">
                  <div class="spinner sm spinner-primary mt-3"></div>
                </div>
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
    title: "Edit Sale"
  },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],
      isLoading: true,
      SubmitProcessing:false,
      Submit_Processing_detail:false,
      warehouses: [],
      clients: [],
      sales_agents: [],
      products: [],
      details: [],
      detail: {},
      sales: [],
      showPointsSection: false,
      // Points / loyalty state
      selectedClientPoints: 0,
      initialClientPoints: 0,
      points_to_convert: 0,
      discount_from_points: 0,
      used_points: 0,
      clientIsEligible: false,
      pointsConverted: false,
      point_to_amount_rate: 0,
      sale: {
        id: "",
        date: "",
        statut: "",
        notes: "",
        client_id: "",
        warehouse_id: "",
        sales_agent_id: null,
        tax_rate: 0,
        TaxNet: 0,
        shipping: 0,
        discount: 0,
        discount_Method: "2", // "1" for percentage, "2" for fixed (default)
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
        sale_unit_id: "",
        no_unit:"",
        name: "",
        unitSale: "",
        Net_price: "",
        Total_price: "",
        Unit_price: "",
        subtotal: "",
        product_id: "",
        detail_id: "",
        taxe: "",
        tax_percent: "",
        tax_method: "",
        product_variant_id: "",
        del: "",
        etat: "",
        is_imei: "",
        imei_number:"",
        // Batch override (optional on edit): if empty on save backend auto-FEFOs.
        is_batch_tracked: false,
        batches: [],
        available_batches: [],
        batches_loading: false,
      }
    };
  },

  watch: {
    // Recalculate totals whenever discount type changes (fixed / percentage)
    'sale.discount_Method'(newVal, oldVal) {
      this.Calcul_Total();
    }
  },

  computed: {
    ...mapGetters(["currentUserPermissions","currentUser"]),

    // Simple validity check for points_to_convert (same behavior as create_sale)
    pointsInputValid() {
      const max = Number(this.selectedClientPoints) || 0;
      const val = Number(this.points_to_convert);
      return Number.isInteger(val) && val >= 1 && val <= max;
    },

    // Batch validation is only triggered when the user has added/prefilled batches
    // for a tracked line. Empty = backend auto-FEFO path is acceptable.
    hasBatchValidationErrors() {
      if (!Array.isArray(this.details)) return false;
      for (const d of this.details) {
        if (!d || !d.is_batch_tracked) continue;
        const batches = Array.isArray(d.batches) ? d.batches : [];
        if (batches.length === 0) continue; // empty = FEFO fallback, no error
        for (const b of batches) {
          if (!b.product_batch_id) return true;
          const q = Number(b.qty);
          if (!(q > 0)) return true;
          if (q > (Number(b.qty_available) || 0)) return true;
        }
        if (this.batch_duplicates(d)) return true;
        if (this.batch_qty_mismatch(d)) return true;
      }
      return false;
    },

    firstBatchErrorMessage() {
      if (!Array.isArray(this.details)) return "";
      for (const d of this.details) {
        if (!d || !d.is_batch_tracked) continue;
        const batches = Array.isArray(d.batches) ? d.batches : [];
        if (batches.length === 0) continue;
        const label = d.name || d.code || "";
        for (const b of batches) {
          if (!b.product_batch_id) {
            return (this.$t("Select_Batch_Required_For") || "Select a batch for") + " " + label;
          }
          const q = Number(b.qty);
          if (!(q > 0)) {
            return (this.$t("Batch_Qty_Required_For") || "Batch quantity must be greater than 0 for") + " " + label;
          }
          if (q > (Number(b.qty_available) || 0)) {
            return (this.$t("Batch_Qty_Exceeds_Available") || "Batch quantity exceeds available stock for") + " " + label;
          }
        }
        if (this.batch_duplicates(d)) {
          return (this.$t("Duplicate_Batch_Selected") || "The same batch is selected twice for") + " " + label;
        }
        if (this.batch_qty_mismatch(d)) {
          return (this.$t("Total_batch_qty_mismatch") || "Total batch quantity does not match the line quantity") + " — " + label;
        }
      }
      return "";
    }
  },

  methods: {

    showModal() {
      this.$bvModal.show('open_scan');
      
    },

    onScan (decodedText, decodedResult) {
      const code = decodedText;
      this.search_input = code;
      this.search();
      this.$bvModal.hide('open_scan');
    },

     handleFocus() {
      this.focused = true
    },

    handleBlur() {
      this.focused = false
    },
    

    //--- Submit Validate Update Sale
    Submit_Sale() {
      this.$refs.edit_sale.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else if (Number(this.GrandTotal) < 0) {
          const msg = this.$t ? `${this.$t('pos.Total_Payable')} ${this.$t('cannot_be_negative') || 'cannot be negative'}` : 'Total Payable cannot be negative';
          this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
          return;
        } else {
          this.Update_Sale();
        }
      });
    },
    //---Submit Validation Update Detail
    submit_Update_Detail() {
      this.$refs.Update_Detail.validate().then(success => {
        if (!success) {
          return;
        } else {
          this.Update_Detail();
        }
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

    //---------------------------- Show Modal Update Detail Product
    Modal_Updat_Detail(detail) {
      NProgress.start();
      NProgress.set(0.1);
      this.detail = {};
      this.detail.name = detail.name;
      this.detail.detail_id = detail.detail_id;
      this.detail.Unit_price = detail.Unit_price;
      this.detail.tax_method = detail.tax_method;
      this.detail.discount_Method = detail.discount_Method;
      this.detail.discount = detail.discount;
      this.detail.quantity = detail.quantity;
      this.detail.tax_percent = detail.tax_percent;
      this.detail.is_imei = detail.is_imei;
      this.detail.imei_number = detail.imei_number;

      setTimeout(() => {
        NProgress.done();
        this.$bvModal.show("form_Update_Detail");
      }, 1000);

    },

    //---------------------------- Submit Update Detail Product

    Update_Detail() {
      NProgress.start();
      NProgress.set(0.1);
      this.Submit_Processing_detail = true;
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === this.detail.detail_id) {
          this.details[i].tax_percent = this.detail.tax_percent;
          this.details[i].Unit_price = this.detail.Unit_price;
          this.details[i].quantity = this.detail.quantity;
          this.details[i].tax_method = this.detail.tax_method;
          this.details[i].discount_Method = this.detail.discount_Method;
          this.details[i].discount = this.detail.discount;
          this.details[i].imei_number = this.detail.imei_number;

          if (this.details[i].discount_Method == "2") {
            //Fixed
            this.details[i].DiscountNet = this.detail.discount;
          } else {
            //Percentage %
            this.details[i].DiscountNet = parseFloat(
              (this.detail.Unit_price * this.details[i].discount) / 100
            );
          }

          if (this.details[i].tax_method == "1") {
            //Exclusive
            this.details[i].Net_price = parseFloat(
              this.detail.Unit_price - this.details[i].DiscountNet
            );

            this.details[i].taxe = parseFloat(
              (this.detail.tax_percent *
                (this.detail.Unit_price - this.details[i].DiscountNet)) /
                100
            );
          } else {
            //Inclusive
            this.details[i].taxe = parseFloat(
              (this.detail.Unit_price - this.details[i].DiscountNet) *
                (this.detail.tax_percent / 100)
            );

            this.details[i].Net_price = parseFloat(
              this.detail.Unit_price -
                this.details[i].taxe -
                this.details[i].DiscountNet
            );
          }

          this.$forceUpdate();
        }
      }
      this.Calcul_Total();

       setTimeout(() => {
        NProgress.done();
        this.Submit_Processing_detail = false;
        this.$bvModal.hide("form_Update_Detail");
      }, 1000);

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
      if (this.sale.warehouse_id != "" &&  this.sale.warehouse_id != null) {
        this.timer = setTimeout(() => {

          let barcode = this.search_input.trim();
          let weight = null;
          // Check if the barcode is from a weighing scale (13 digits)
          if (barcode.length === 13 && !isNaN(barcode)) {
            // Find the product by product code
            let product = this.products.find(prod => prod.code === barcode);
            if (product) {
              this.SearchProduct(product, weight);
              return;
            }else{

              let productCode = barcode.substring(0, 7); // First 7 digits → Product Code
              let weight = parseFloat(barcode.substring(7, 12)) / 1000; // Convert weight (grams to kg)
              let product = this.products.find(prod => prod.code === productCode);
              if (product) {
                product.quantity = weight; // Assign weight to product
                this.SearchProduct(product, weight);
                return;
              }
            }

            this.makeToast("danger", "Invalid product code scanned", this.$t("Error"));
            this.search_input= '';
            this.$refs.product_autocomplete.value = "";
            this.product_filter = [];
          }
          // else{
          //   //  No product found - Display Error Alert
          //   this.makeToast("danger", "Invalid product code scanned", this.$t("Error"));
          //   this.search_input= '';
          //   this.$refs.product_autocomplete.value = "";
          //   this.product_filter = [];

          // }
          
          
          // Regular product search (for non-weighing scale barcodes)
          const product_filter = this.products.filter(product => product.code === this.search_input || product.barcode.includes(this.search_input));
              if(product_filter.length === 1){
                this.SearchProduct(product_filter[0], weight);
              }else {
                this.product_filter=  this.products.filter(product => {
                  return (
                    product.name.toLowerCase().includes(this.search_input.toLowerCase()) ||
                    product.code.toLowerCase().includes(this.search_input.toLowerCase()) ||
                    product.barcode.toLowerCase().includes(this.search_input.toLowerCase())
                    );
                });
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

    //-------------- get Result Value Search Product

    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },



    //-------------- Submit Search Product


    SearchProduct(result, weight = null) {
        this.product = {};
        if (
          this.details.length > 0 &&
          this.details.some(detail => detail.code === result.code)
        ) {
          this.makeToast("warning", this.$t("AlreadyAdd"), this.$t("Warning"));
        } else {

            if( result.product_type =='is_service'){
              this.product.quantity = 1;
              this.product.code = result.code;
            }else{

              this.product.code = result.code;
              this.product.no_unit = 1;
              this.product.stock = result.qte_sale;

              // Check if it's a weighing scale product
              if (weight !== null) {
                this.product.quantity = weight; // Assign extracted weight
              } else {
                this.product.quantity = result.qte_sale < 1 ? result.qte_sale : 1;
              }

           
            }
          this.product.product_variant_id = result.product_variant_id;
          this.Get_Product_Details(result.id, result.product_variant_id);
        }

        this.search_input= '';
        this.$refs.product_autocomplete.value = "";
        this.product_filter = [];
    },

    //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      this.search_input= '';
      this.product_filter = [];
      this.Get_Products_By_Warehouse(value);
      // Refresh available batches for existing batch-tracked lines since batches
      // are warehouse-scoped.
      if (Array.isArray(this.details)) {
        for (const d of this.details) {
          if (d && d.is_batch_tracked) {
            this.fetch_batches_for_detail(d);
          }
        }
      }
    },

     //------------------------------------ Get Products By Warehouse -------------------------\\

    Get_Products_By_Warehouse(id) {
      // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
      axios
        .get("get_Products_by_warehouse/" + id + "?stock=" + 1 + "&is_sale=" + 1 + "&product_service=" + 1 + "&product_combo=" + 1)
         .then(response => {
            this.products = response.data;
             NProgress.done();

            })
          .catch(error => {
          });
    },

    //----------------------------------------- Add Product to order list -------------------------\\
    add_product() {
      if (this.details.length > 0) {
        this.Last_Detail_id();
      } else if (this.details.length === 0) {
        this.product.detail_id = 1;
      }

      this.details.push(this.product);

      if(this.product.is_imei){
        this.Modal_Updat_Detail(this.product);
      }
    },

    //----------------------------------------- Batch handling -------------------------\\
    // Called after GetElements — normalizes prefilled batch rows coming from the
    // edit endpoint and loads the available pool for each batch-tracked line.
    prefillBatchesForTrackedDetails() {
      if (!Array.isArray(this.details)) return;
      for (const d of this.details) {
        if (!d || !d.is_batch_tracked) continue;
        const prefilled = Array.isArray(d.batches) ? d.batches : [];
        const normalized = prefilled.map(b => ({
          product_batch_id: Number(b.product_batch_id) || null,
          batch_no: b.batch_no || "",
          expiry_date: b.expiry_date || null,
          qty_available: 0, // will be filled in by fetch_batches_for_detail
          qty: Number(b.qty) || 0,
        }));
        this.$set(d, "batches", normalized);
        this.$set(d, "available_batches", []);
        this.$set(d, "batches_loading", false);
        this.fetch_batches_for_detail(d);
      }
    },

    fetch_batches_for_detail(detail) {
      if (!detail || !detail.is_batch_tracked) return;
      const wid = this.sale && this.sale.warehouse_id;
      if (!wid || !detail.product_id) return;
      const variantSeg = (detail.product_variant_id != null && detail.product_variant_id !== "")
        ? detail.product_variant_id
        : 0;
      this.$set(detail, "batches_loading", true);
      // Snapshot the prefilled batches before we rebuild availability.
      const existing = Array.isArray(detail.batches) ? detail.batches.slice() : [];
      const existingQtyById = {};
      for (const b of existing) {
        if (b && b.product_batch_id != null) {
          existingQtyById[b.product_batch_id] = (existingQtyById[b.product_batch_id] || 0) + (Number(b.qty) || 0);
        }
      }
      axios
        .get(`batches_for_sale/${detail.product_id}/${wid}/${variantSeg}`)
        .then(response => {
          const apiList = (response.data && Array.isArray(response.data.batches))
            ? response.data.batches
            : [];
          // Merge: ProductBatch.qty on the server already reflects the CURRENT state
          // (post-consumption for this sale, if completed). To let the user keep
          // editing their allocation we add the prefilled qty back onto each batch
          // that was already assigned to this detail. Backend reversal before
          // re-apply keeps everything consistent on save.
          const merged = apiList.map(ab => ({
            id: ab.id,
            batch_no: ab.batch_no,
            expiry_date: ab.expiry_date,
            mfg_date: ab.mfg_date,
            qty_available: (Number(ab.qty_available) || 0) + (existingQtyById[ab.id] || 0),
            unit_cost: ab.unit_cost,
            status: ab.status,
          }));
          // Also surface any prefilled batches that no longer appear as "available"
          // (e.g. the batch was fully consumed elsewhere). Their effective available
          // is the prefilled qty itself — editing them down still works.
          for (const b of existing) {
            if (b && b.product_batch_id != null && !merged.some(m => m.id === b.product_batch_id)) {
              merged.push({
                id: b.product_batch_id,
                batch_no: b.batch_no || "",
                expiry_date: b.expiry_date || null,
                mfg_date: b.mfg_date || null,
                qty_available: existingQtyById[b.product_batch_id] || 0,
                status: b.status || "active",
              });
            }
          }
          this.$set(detail, "available_batches", merged);
          // Rebuild each prefilled batch row with the fresh availability number.
          const rebuilt = existing.map(b => {
            const match = merged.find(m => m.id === b.product_batch_id);
            return {
              product_batch_id: b.product_batch_id,
              batch_no: match ? match.batch_no : (b.batch_no || ""),
              expiry_date: match ? match.expiry_date : (b.expiry_date || null),
              qty_available: match ? Number(match.qty_available) || 0 : Number(b.qty_available) || 0,
              qty: Number(b.qty) || 0,
            };
          });
          this.$set(detail, "batches", rebuilt);
          this.$set(detail, "batches_loading", false);
        })
        .catch(() => {
          this.$set(detail, "available_batches", []);
          this.$set(detail, "batches_loading", false);
        });
    },

    add_batch_to_detail(detail) {
      if (!Array.isArray(detail.batches)) this.$set(detail, "batches", []);
      const used = this.batch_total_qty(detail);
      const remaining = Math.max(0, (Number(detail.quantity) || 0) - used);
      detail.batches.push({
        product_batch_id: null,
        batch_no: "",
        expiry_date: null,
        qty_available: 0,
        qty: remaining,
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
      const picked = list.find(b => b.id === batchId);
      row.product_batch_id = batchId || null;
      if (picked) {
        row.batch_no = picked.batch_no;
        row.expiry_date = picked.expiry_date;
        row.qty_available = Number(picked.qty_available) || 0;
      } else {
        row.batch_no = "";
        row.expiry_date = null;
        row.qty_available = 0;
      }
    },

    on_batch_qty_input(batchRow, raw) {
      let s = raw == null ? "" : String(raw);
      s = s.replace(",", ".").replace(/[^0-9.]/g, "");
      const firstDot = s.indexOf(".");
      if (firstDot !== -1) {
        s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, "");
      }
      this.$set(batchRow, "qty", s);
    },

    batch_total_qty(detail) {
      if (!detail || !Array.isArray(detail.batches)) return 0;
      return detail.batches.reduce((sum, b) => {
        const n = Number(b.qty);
        return sum + (Number.isFinite(n) ? n : 0);
      }, 0);
    },

    batch_qty_mismatch(detail) {
      if (!detail || !detail.is_batch_tracked) return false;
      const lineQty = Number(detail.quantity) || 0;
      return Math.abs(this.batch_total_qty(detail) - lineQty) > 0.0001;
    },

    batch_over_allocates(detail) {
      if (!detail || !detail.is_batch_tracked) return false;
      if (!Array.isArray(detail.batches)) return false;
      for (const b of detail.batches) {
        const q = Number(b.qty) || 0;
        const avail = Number(b.qty_available) || 0;
        if (q > avail) return true;
      }
      return false;
    },

    batch_duplicates(detail) {
      if (!detail || !detail.is_batch_tracked) return false;
      if (!Array.isArray(detail.batches)) return false;
      const seen = {};
      for (const b of detail.batches) {
        if (!b.product_batch_id) continue;
        if (seen[b.product_batch_id]) return true;
        seen[b.product_batch_id] = true;
      }
      return false;
    },

    expiry_pill_style(dateStr) {
      const base = {
        display: "inline-block",
        padding: "2px 8px",
        fontSize: "11px",
        fontWeight: "600",
        borderRadius: "10px",
      };
      if (!dateStr) return Object.assign(base, { background: "#f3f4f6", color: "#6b7280" });
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const exp = new Date(dateStr);
      if (isNaN(exp.getTime())) return Object.assign(base, { background: "#f3f4f6", color: "#6b7280" });
      exp.setHours(0, 0, 0, 0);
      const diffDays = Math.round((exp - today) / (1000 * 60 * 60 * 24));
      if (diffDays < 0) return Object.assign(base, { background: "#fee2e2", color: "#991b1b" });
      if (diffDays <= 30) return Object.assign(base, { background: "#fef3c7", color: "#92400e" });
      return Object.assign(base, { background: "#dcfce7", color: "#166534" });
    },

    //-----------------------------------Verified QTY ------------------------------\\
    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (isNaN(detail.quantity)) {
            this.details[i].quantity = detail.qte_copy;
          }

          if (detail.etat == "new" && detail.quantity > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            this.details[i].quantity = detail.stock;
          } else if (
            detail.etat == "current" &&
            detail.quantity > detail.stock + detail.qte_copy
          ) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            this.details[i].quantity = detail.qte_copy;
          } else {
            this.details[i].quantity = detail.quantity;
          }
        }
      }

      this.$forceUpdate();
      this.Calcul_Total();
    },

    //-----------------------------------increment QTY ------------------------------\\

    increment(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.etat == "new" && detail.quantity + 1 > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
          } else if (
            detail.etat == "current" &&
            detail.quantity + 1 > detail.stock + detail.qte_copy
          ) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
          } else {
            this.formatNumber(this.details[i].quantity++, 2);
          }
        }
      }
      this.$forceUpdate();
      this.Calcul_Total();
    },

    //-----------------------------------decrement QTY ------------------------------\\

    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.quantity - 1 > 0) {
            if (detail.etat == "new" && detail.quantity - 1 > detail.stock) {
              this.makeToast(
                "warning",
                this.$t("LowStock"),
                this.$t("Warning")
              );
            } else if (
              detail.etat == "current" &&
              detail.quantity - 1 > detail.stock + detail.qte_copy
            ) {
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
      this.$forceUpdate();
      this.Calcul_Total();
    },

    //---------- keyup OrderTax
    keyup_OrderTax() {
      if (isNaN(this.sale.tax_rate)) {
        this.sale.tax_rate = 0;
      } else if(this.sale.tax_rate == ''){
         this.sale.tax_rate = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //---------- keyup Discount

    keyup_Discount() {
      if (isNaN(this.sale.discount)) {
        this.sale.discount = 0;
      } else if(this.sale.discount == ''){
         this.sale.discount = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    // Calculate discount amount for current sale (for display in summary card)
    getCurrentSaleDiscountAmount() {
      try {
        const discountMethod = String(this.sale.discount_Method || '2'); // Default to fixed for backward compatibility
        const discountValue = Number(this.sale.discount || 0);
        const subtotal = this.total || 0;

        if (discountMethod === '1') {
          // Percentage discount on subtotal (manual discount only, no points)
          return parseFloat((subtotal * (discountValue / 100)).toFixed(2));
        } else {
          // Fixed discount (manual discount only, no points)
          return parseFloat(Math.min(discountValue, subtotal).toFixed(2));
        }
      } catch (e) {
        return Number(this.sale.discount || 0);
      }
    },

    // Handle manual input for points to convert (keep it within [0, available])
    onPointsToConvertInput() {
      let max = Number(this.selectedClientPoints) || 0;
      let val = Number(this.points_to_convert);
      if (!Number.isFinite(val)) val = 0;
      if (val < 0) val = 0;
      val = Math.floor(val);
      if (val > max) {
        this.makeToast &&
          this.makeToast(
            "warning",
            this.$t ? this.$t("Entered_points_exceed_available") : "Entered points exceed available",
            this.$t ? this.$t("Warning") : "Warning"
          );
        val = max;
      }
      this.points_to_convert = val;
    },

    // Convert / unconvert points to discount (same behavior as create_sale)
    convertPointsToDiscount() {
      if (this.pointsConverted) {
        // We are UN-converting points for this sale.
        // Increase the visible available points by the amount that was used on this sale,
        // to reflect the rollback that will happen on save.
        const prevUsed = Number(this.used_points || 0);
        if (prevUsed > 0) {
          const currentAvail = Number(this.selectedClientPoints || 0);
          this.selectedClientPoints = currentAvail + prevUsed;
          this.initialClientPoints = this.selectedClientPoints;
        }

        // Reset conversion - sale.discount remains unchanged (it only contains manual discount)
        this.discount_from_points = 0;
        this.used_points = 0;
        this.points_to_convert = 0;

        this.pointsConverted = false;
      } else {
        const maxPoints = Number(this.selectedClientPoints) || 0;
        let pts = Number(this.points_to_convert);
        if (!Number.isFinite(pts) || pts <= 0) {
          this.makeToast &&
            this.makeToast(
              "warning",
              this.$t ? this.$t("Please_enter_points_to_convert") : "Please enter points to convert",
              this.$t ? this.$t("Warning") : "Warning"
            );
          return;
        }
        if (pts > maxPoints) {
          this.makeToast &&
            this.makeToast(
              "warning",
              this.$t ? this.$t("Entered_points_exceed_available") : "Entered points exceed available",
              this.$t ? this.$t("Warning") : "Warning"
            );
          this.points_to_convert = maxPoints;
          pts = maxPoints;
          this.$nextTick &&
            this.$nextTick(() => {
              const r = this.$refs && this.$refs.pointsInput;
              if (r && r.$el) {
                try {
                  r.$el.value = String(this.points_to_convert);
                } catch (e) {}
              }
            });
        }
        const discount = parseFloat((pts * this.point_to_amount_rate).toFixed(2));
        this.discount_from_points = discount;
        // Don't merge points into sale.discount - keep them separate so input shows only manual discount
        this.used_points = pts;
        // ensure input reflects final used points
        this.points_to_convert = pts;
        this.$nextTick &&
          this.$nextTick(() => {
            const r = this.$refs && this.$refs.pointsInput;
            if (r && r.$el) {
              try {
                r.$el.value = String(this.points_to_convert);
              } catch (e) {}
            }
          });
        this.pointsConverted = true;
        // reduce available points display until saved
        const baseAvail = Number(this.initialClientPoints || this.selectedClientPoints) || 0;
        this.selectedClientPoints = Math.max(0, baseAvail - pts);
      }

      this.Calcul_Total(); // Recalculate grand total
    },

    //---------- keyup Shipping

    keyup_Shipping() {
      if (isNaN(this.sale.shipping)) {
        this.sale.shipping = 0;
      } else if(this.sale.shipping == ''){
         this.sale.shipping = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
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

    //-----------------------------------------Calcul Total ------------------------------\\
    Calcul_Total() {
      this.total = 0;
      for (var i = 0; i < this.details.length; i++) {
        var tax = this.details[i].taxe * this.details[i].quantity;
        this.details[i].subtotal = parseFloat(
          this.details[i].quantity * this.details[i].Net_price + tax
        );
        this.total = parseFloat(this.total + this.details[i].subtotal);
      }

      // Calculate discount based on type (backward compatible: default to fixed if not set)
      const discountMethod = String(this.sale.discount_Method || '2');
      const discountValue = Number(this.sale.discount || 0);
      let discountAmount = 0;

      if (discountMethod === '1') {
        // Percentage discount on subtotal
        const percentAmount = parseFloat((this.total * (discountValue / 100)).toFixed(2));
        // Points-based discount is always a fixed amount; apply it in addition, but never exceed remaining subtotal
        const remainingAfterPercent = Math.max(this.total - percentAmount, 0);
        const pointsAmount = parseFloat(
          Math.min(Number(this.discount_from_points || 0), remainingAfterPercent).toFixed(2)
        );
        discountAmount = percentAmount + pointsAmount;
      } else {
        // Fixed discount: apply both manual discount and points discount separately
        const manualDiscount = parseFloat(Math.min(discountValue, this.total).toFixed(2));
        const remainingAfterManual = Math.max(this.total - manualDiscount, 0);
        const pointsDiscount = parseFloat(
          Math.min(Number(this.discount_from_points || 0), remainingAfterManual).toFixed(2)
        );
        discountAmount = manualDiscount + pointsDiscount;
      }

      const total_without_discount = parseFloat(
        (this.total - discountAmount).toFixed(2)
      );
      this.sale.TaxNet = parseFloat(
        (total_without_discount * this.sale.tax_rate) / 100
      );
      this.GrandTotal = parseFloat(
        total_without_discount + this.sale.TaxNet + this.sale.shipping
      );

      var grand_total =  this.GrandTotal.toFixed(2);
      this.GrandTotal = parseFloat(grand_total);
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

    //-----------------------------------verified Order List ------------------------------\\

    verifiedForm() {
      if (this.details.length <= 0) {
        this.makeToast(
          "warning",
          this.$t("AddProductToList"),
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
        } else {
          return true;
        }
      }
    },

    //--------------------------------- Update Sale -------------------------\\
    Update_Sale() {
      if (this.verifiedForm()) {
        if (Number(this.GrandTotal) < 0) {
          const msg = this.$t ? `${this.$t('pos.Total_Payable')} ${this.$t('cannot_be_negative') || 'cannot be negative'}` : 'Total Payable cannot be negative';
          this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
          return;
        }
        // Batch validation (only enforced when completed and the user has
        // touched the optional override). Empty batches => backend auto-FEFO.
        if (this.sale.statut === 'completed' && this.hasBatchValidationErrors) {
          this.makeToast('warning', this.firstBatchErrorMessage, this.$t('Warning') || 'Warning');
          return;
        }
        this.SubmitProcessing = true;
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        let id = this.$route.params.id;
        const detailsPayload = this.details.map(d => {
          const out = Object.assign({}, d, { price_type: d.price_type || 'retail' });
          delete out.available_batches;
          delete out.batches_loading;
          if (d.is_batch_tracked && Array.isArray(d.batches) && d.batches.length > 0) {
            out.batches = d.batches
              .filter(b => b && b.product_batch_id && Number(b.qty) > 0)
              .map(b => ({
                product_batch_id: Number(b.product_batch_id),
                qty: Number(b.qty),
                unit_price: d.Unit_price,
              }));
          } else {
            out.batches = [];
          }
          return out;
        });
        axios
          .put(`sales/${id}`, {
            date: this.sale.date,
            client_id: this.sale.client_id,
            GrandTotal: this.GrandTotal,
            warehouse_id: this.sale.warehouse_id,
            sales_agent_id: this.sale.sales_agent_id || null,
            statut: this.sale.statut,
            notes: this.sale.notes,
            tax_rate: this.sale.tax_rate?this.sale.tax_rate:0,
            TaxNet: this.sale.TaxNet?this.sale.TaxNet:0,
            discount: this.sale.discount?this.sale.discount:0,
            // Ensure order-level discount method is sent when editing
            discount_Method: String(this.sale.discount_Method || '2'),
            shipping: this.sale.shipping?this.sale.shipping:0,
            details: detailsPayload,
            discount_from_points: this.discount_from_points,
            used_points: this.used_points,
          })
          .then(response => {
            this.makeToast(
              "success",
              this.$t("Successfully_Updated"),
              this.$t("Success")
            );
            NProgress.done();
            this.SubmitProcessing = false;

            this.$router.push({ name: "index_sales" });
          })
          .catch(error => {
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

    //---------------------------------Get Product Details ------------------------\\

    Get_Product_Details(product_id, variant_id) {
      const wid = this.sale && this.sale.warehouse_id ? this.sale.warehouse_id : null;
      const url = wid
        ? `/show_product_data/${product_id}/${variant_id}/${wid}`
        : `/show_product_data/${product_id}/${variant_id}`;

      axios.get(url).then(response => {
        this.product.del = 0;
        this.product.id = 0;
        this.product.etat = "new";
        this.product.discount           = response.data.discount;
        this.product.DiscountNet        = response.data.DiscountNet;
        this.product.discount_Method    = response.data.discount_method;
        this.product.product_id = response.data.id;
        this.product.name = response.data.name;
        this.product.product_type = response.data.product_type;
        this.product.Net_price = response.data.Net_price;
        this.product.Unit_price = response.data.Unit_price;
        this.product.Unit_price_wholesale = response.data.Unit_price_wholesale;
        this.product.wholesale_Net_price = response.data.wholesale_Net_price;
        this.product.min_price = response.data.min_price;
        // baselines for toggle
        this.product.retail_unit_price = response.data.Unit_price;
        this.product.wholesale_unit_price = response.data.Unit_price_wholesale;
        this.product.price_type = 'retail';
        this.product.taxe = response.data.tax_price;
        this.product.tax_method = response.data.tax_method;
        this.product.tax_percent = response.data.tax_percent;
        this.product.unitSale = response.data.unitSale;
        this.product.sale_unit_id = response.data.sale_unit_id;
        this.product.is_imei = response.data.is_imei;
        this.product.imei_number = '';
        this.product.warehouse_location = response.data.warehouse_location
          ? (response.data.warehouse_location.name
              ? `${response.data.warehouse_location.code} - ${response.data.warehouse_location.name}`
              : response.data.warehouse_location.code)
          : null;
        this.product.is_batch_tracked = !!response.data.is_batch_tracked;
        this.product.batches = [];
        this.product.available_batches = [];
        this.product.batches_loading = false;
        // ensure min price respected on default
        if (this.product.Net_price < (this.product.min_price || 0)) {
          this.product.price_type = 'retail';
        }
        this.add_product();
        this.Calcul_Total();
        if (this.product.is_batch_tracked) {
          const last = this.details[this.details.length - 1];
          if (last) {
            this.fetch_batches_for_detail(last);
          }
        }
      });
    },

    //--------------------------------------- Get Elements ------------------------------\\
    GetElements() {
      let id = this.$route.params.id;
      axios
        .get(`sales/${id}/edit`)
        .then(response => {
          const rawSale = response.data.sale || {};

          // Normalize discount method coming from backend:
          // 1 / '1' / 'percent' / 'percentage'  => '1'
          // 2 / '2' / 'fixed'                   => '2'
          // null/undefined                      => '2' (fixed by default)
          let methodRaw = rawSale.discount_Method;
          let normalizedMethod = '2';
          if (methodRaw !== undefined && methodRaw !== null) {
            const dm = String(methodRaw).toLowerCase().trim();
            if (dm === '1' || dm === 'percent' || dm === 'percentage') {
              normalizedMethod = '1';
            } else if (dm === '2' || dm === 'fixed') {
              normalizedMethod = '2';
            }
          }

          this.sale = {
            ...rawSale,
            discount_Method: normalizedMethod,
          };

          this.details = response.data.details;
          this.clients = response.data.clients;
          this.warehouses = response.data.warehouses;
          this.sales_agents = response.data.sales_agents || [];
          this.point_to_amount_rate = response.data.point_to_amount_rate;
          this.discount_from_points = response.data.discount_from_points || 0;
          this.used_points = this.sale.used_points > 0 ? this.sale.used_points : 0;

          // Fetch current loyalty points for this client to drive the points UI
          if (this.sale.client_id) {
            axios
              .get(`/get_points_client/${this.sale.client_id}`)
              .then(res => {
                const data = res.data || {};
                if (data.is_royalty_eligible || this.discount_from_points > 0 || this.used_points > 0) {
                  this.selectedClientPoints = Number(data.points || 0);
                  this.initialClientPoints = Number(data.points || 0);
                  this.clientIsEligible = this.selectedClientPoints > 0;
                } else {
                  this.selectedClientPoints = 0;
                  this.initialClientPoints = 0;
                  this.clientIsEligible = false;
                }
                // Show section if client has points OR this sale already used points/discount_from_points
                this.showPointsSection =
                  (this.clientIsEligible && this.selectedClientPoints > 0) ||
                  (this.used_points && this.used_points > 0) ||
                  (this.discount_from_points && this.discount_from_points > 0);
                // If sale already has discount_from_points, treat as converted
                if (this.discount_from_points > 0 && this.used_points > 0) {
                  this.pointsConverted = true;
                  this.points_to_convert = this.used_points;
                }
              })
              .catch(() => {
                // On failure, just keep points UI hidden by default
                this.selectedClientPoints = 0;
                this.initialClientPoints = 0;
                this.clientIsEligible = false;
                this.showPointsSection =
                  (this.used_points && this.used_points > 0) ||
                  (this.discount_from_points && this.discount_from_points > 0);
              })
              .finally(() => {
                this.Get_Products_By_Warehouse(this.sale.warehouse_id);
                this.prefillBatchesForTrackedDetails();
                this.Calcul_Total();
                this.isLoading = false;
              });
          } else {
            // No client id, just proceed with existing data
            this.showPointsSection =
              (this.used_points && this.used_points > 0) ||
              (this.discount_from_points && this.discount_from_points > 0);
            this.Get_Products_By_Warehouse(this.sale.warehouse_id);
            this.prefillBatchesForTrackedDetails();
            this.Calcul_Total();
            this.isLoading = false;
          }
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  //----------------------------- Created function-------------------
  created() {
    this.GetElements();
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