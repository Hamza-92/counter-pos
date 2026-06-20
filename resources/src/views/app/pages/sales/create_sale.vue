<template>
  <div class="main-content">
    <breadcumb :page="$t('AddSale')" :folder="$t('ListSales')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="create_sale" v-if="!isLoading">
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
                      <b-input-group class="category-input-group">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="selectedClientId"
                          @input="Selected_customer"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Customer')"
                          :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
                        />
                        <b-input-group-append
                          v-if="currentUserPermissions && currentUserPermissions.includes('Customers_add')"
                        >
                          <b-button
                            variant="primary"
                            @click="Quick_Add_Client"
                            :title="$t('Quick_Add_Customer')"
                            class="category-add-btn"
                          >
                            <lucide-icon name="plus" />
                          </b-button>
                        </b-input-group-append>
                      </b-input-group>
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

                <!-- order products  -->
                <b-col md="12" class="mb-4">
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
                            <lucide-icon class="text-25" name="x" />
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="details.length <=0">
                          <td colspan="9">{{$t('NodataAvailable')}}</td>
                        </tr>
                        <template v-for="detail in details">
                        <tr :key="'row-' + detail.detail_id">
                          <td >{{detail.detail_id}}</td>
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
                          <td>
                            <div class="d-flex align-items-center">
                              <div class="mr-2">
                                <span>{{currentUser.currency}} {{formatNumber(detail.Net_price, 3)}}</span>
                                <small
                                  v-if="detail.min_price && detail.Net_price < detail.min_price"
                                  class="text-danger d-block"
                                >{{ $t('Price_below_min_not_allowed') }}</small>
                              </div>
                              <v-select
                                class="ml-2"
                                :options="[
                                  {label: $t('Retail Price'), value: 'retail'},
                                  {label: $t('Wholesale Price'), value: 'wholesale'}
                                ]"
                                :reduce="opt => opt.value"
                                v-model="detail.price_type"
                                style="min-width: 160px"
                                @input="val => onChangePriceType(detail, val)"
                              />
                            </div>
                          </td>
                          <td>
                            <span class="badge badge-warning" v-if="detail.product_type == 'is_service'">----</span>
                            <span class="badge badge-warning" v-else>{{detail.stock}} {{detail.unitSale}}</span>
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
                                  :min="0.00"
                                  :max="detail.stock"
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
                          <td>{{currentUser.currency}} {{formatNumber(detail.taxe  * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          <td>
                            <lucide-icon class="text-25 text-success cursor-pointer" name="pencil" v-if="currentUserPermissions && currentUserPermissions.includes('edit_product_sale')" @click="Modal_Updat_Detail(detail)" />
                            <lucide-icon class="text-25 text-danger cursor-pointer" name="x" @click="delete_Product_Detail(detail.detail_id)" />
                          </td>
                        </tr>

                        <!-- Batch selection row for batch-tracked products -->
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
                                  <tr v-for="(b, bIdx) in detail.batches" :key="'b-' + detail.detail_id + '-' + bIdx" :style="{ background: bIdx % 2 === 1 ? '#f8faff' : '#ffffff', borderTop: '1px solid #e0e7ff' }">
                                    <td style="padding: 6px 8px; vertical-align: middle; min-width: 220px;">
                                      <v-select
                                        :value="b.product_batch_id"
                                        :options="detail.available_batches.map(ab => ({
                                          label: ab.batch_no + (ab.expiry_date ? ' · ' + $t('Exp') + ' ' + ab.expiry_date : '') + ' · ' + ab.qty_available,
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

                              <div v-if="detail.available_batches && detail.available_batches.length && batch_qty_mismatch(detail)" style="padding: 8px 14px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600; border-top: 1px solid #fde68a; display: flex; align-items: center;">
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
                          <span>{{currentUser.currency}} {{sale.TaxNet.toFixed(2)}} ({{formatNumber(sale.tax_rate,2)}} %)</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Discount')}}</td>
                        <td>
                          <!-- If percentage: show percent value AND discount amount; else amount only -->
                          <template v-if="String(sale.discount_Method || '2') === '1'">
                            {{ formatNumber(sale.discount, 2) }}% ({{ currentUser.currency }} {{ getManualDiscountAmount().toFixed(2) }})
                          </template>
                          <template v-else>
                            {{currentUser.currency}} {{getManualDiscountAmount().toFixed(2)}}
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

                <b-col lg="4" md="4" sm="12" class="mb-3" v-if="clientIsEligible && currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_sale')">
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
                    <div class="hint mt-1">Total available: <strong>{{ selectedClientPoints }}</strong> pts</div>
                  </div>

                  <div class="actions d-flex align-items-center" style="gap:10px;">
                    <b-button
                      :variant="pointsConverted ? 'secondary' : 'dark'"
                      @click="convertPointsToDiscount"
                      :disabled="(!pointsConverted && (selectedClientPoints === 0 || !pointsInputValid))"
                    >
                      <template v-if="!pointsConverted">Convert</template>
                      <template v-else>Unconverted</template>
                    </b-button>
                    <small v-if="!pointsConverted && points_to_convert && !pointsInputValid" class="warn">Enter a value from 1 to your available points.</small>
                    <small v-if="!pointsConverted && pointsInputValid" class="ok">Looks good.</small>
                  </div>

                  <div class="result mt-2" v-if="discount_from_points > 0">
                    ✅ Discount of <strong>{{ discount_from_points }}</strong> {{ currentUser.currency }} will be applied
                  </div>

                  <input type="hidden" name="discount_from_points" :value="discount_from_points">
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
                        @input="Selected_Status"
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

                <!-- PaymentStatus  -->
                <b-col md="4" v-if="sale.statut == 'completed'">
                  <validation-provider name="PaymentStatus">
                    <b-form-group :label="$t('PaymentStatus')">
                      <v-select
                        @input="Selected_PaymentStatus"
                        :disabled="Number(GrandTotal) < 0"
                        :reduce="label => label.value"
                        v-model="payment.status"
                        :placeholder="$t('Choose_Status')"
                        :options="
                                [
                                  {label: 'Paid', value: 'paid'},
                                  {label: 'partial', value: 'partial'},
                                  {label: 'Pending', value: 'pending'},
                                ]"
                      ></v-select>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Payment choice -->
                <b-col md="4" v-if="payment.status != 'pending' && sale.statut == 'completed'">
                  <validation-provider name="Payment choice" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Paymentchoice') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        :reduce="label => label.value"
                        v-model="payment.payment_method_id"
                        :placeholder="$t('PleaseSelect')"
                        :options="payment_methods.map(payment_methods => ({label: payment_methods.name, value: payment_methods.id}))"

                      ></v-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>


                  <!-- Received  Amount  -->
                  <b-col md="4" v-if="payment.status != 'pending' && sale.statut == 'completed'">
                      <validation-provider
                        name="Received Amount"
                        :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                        v-slot="validationContext"
                      >
                        <b-form-group :label="$t('Received_Amount') + ' ' + '*'">
                          <b-form-input
                            @keyup="Verified_Received_Amount(payment.received_amount)"
                            label="Received_Amount"
                            :placeholder="$t('Received_Amount')"
                            v-model.number="payment.received_amount"
                            :state="getValidationState(validationContext)"
                            aria-describedby="Received_Amount-feedback"
                          ></b-form-input>
                          <b-form-invalid-feedback
                            id="Received_Amount-feedback"
                          >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                        </b-form-group>
                      </validation-provider>
                    </b-col>


                <!-- Amount  -->
                <b-col md="4" v-if="payment.status != 'pending' && sale.statut == 'completed'">
                  <validation-provider
                    name="Amount"
                    :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Paying_Amount') + ' ' + '*'">
                      <b-form-input
                        :disabled="payment.status == 'paid'"
                        label="Amount"
                        :placeholder="$t('Paying_Amount')"
                        v-model.number="payment.amount"
                        @keyup="Verified_paidAmount(payment.amount)"
                        :state="getValidationState(validationContext)"
                        aria-describedby="Amount-feedback"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="Amount-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- change  Amount  -->
                <b-col md="4" v-if="payment.status != 'pending' && sale.statut == 'completed'">
                  <label>{{$t('Change')}} :</label>
                  <p
                    class="change_amount"
                  >{{parseFloat(payment.received_amount - payment.amount).toFixed(2)}}</p>
                </b-col>

               
                   <!-- Account -->
                  <b-col lg="4" md="4" sm="12" v-if="payment.status != 'pending' && sale.statut == 'completed'">
                    <validation-provider name="Account">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('Account')">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="payment.account_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Account')"
                          :options="accounts.map(accounts => ({label: accounts.account_name, value: accounts.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                <b-col md="12" class="mt-3">
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
                  <div style="padding: 10px 14px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 10px; font-size: 13px; font-weight: 600; display: flex; align-items: center; margin-bottom: 14px;">
                    <lucide-icon name="info" style="margin-right: 8px; font-size: 16px;" />
                    {{ firstBatchErrorMessage }}
                  </div>
                </b-col>

                <b-col md="12">
                  <b-form-group>
                    <b-button variant="primary" :disabled="paymentProcessing || hasMinPriceViolation || (sale.statut === 'completed' && hasBatchValidationErrors)" @click="Submit_Sale"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                    <div v-once class="typo__p" v-if="paymentProcessing">
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

    <!-- Quick Add Customer Modal -->
    <validation-observer ref="Quick_Add_Customer_Form">
      <b-modal hide-footer size="lg" id="Quick_Add_Customer" :title="$t('Quick_Add_Customer')">
        <b-form @submit.prevent="Submit_Quick_Add_Customer" class="quick-add-customer-form">
          <b-row>
            <!-- Customer Name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Name Customer"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('CustomerName') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="name-feedback"
                    label="name"
                    :placeholder="$t('CustomerName')"
                    v-model="client.name"
                  ></b-form-input>
                  <b-form-invalid-feedback id="name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Customer Email -->
            <b-col md="6" sm="12">
              <b-form-group :label="$t('Email')">
                <b-form-input
                  label="email"
                  v-model="client.email"
                  :placeholder="$t('Email')"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <!-- Customer Phone -->
            <b-col md="6" sm="12">
              <b-form-group :label="$t('Phone')">
                <b-form-input
                  label="Phone"
                  v-model="client.phone"
                  :placeholder="$t('Phone')"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <!-- Customer Country -->
            <b-col md="6" sm="12">
              <b-form-group :label="$t('Country')">
                <b-form-input
                  label="Country"
                  v-model="client.country"
                  :placeholder="$t('Country')"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <!-- Customer City -->
            <b-col md="6" sm="12">
              <b-form-group :label="$t('City')">
                <b-form-input
                  label="City"
                  v-model="client.city"
                  :placeholder="$t('City')"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <!-- Customer Tax Number -->
            <b-col md="6" sm="12">
              <b-form-group :label="$t('Tax_Number')">
                <b-form-input
                  label="Tax Number"
                  v-model="client.tax_number"
                  :placeholder="$t('Tax_Number')"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <!-- Customer Address -->
            <b-col md="12" sm="12">
              <b-form-group :label="$t('Adress')">
                <textarea
                  label="Adress"
                  class="form-control"
                  rows="4"
                  v-model="client.adresse"
                  :placeholder="$t('Adress')"
                ></textarea>
              </b-form-group>
            </b-col>

            <!-- Loyalty eligibility -->
            <b-col md="6" sm="12" class="mt-4 mb-4">
              <div class="psx-form-check">
                <input
                  type="checkbox"
                  v-model="client.is_royalty_eligible"
                  class="psx-checkbox psx-form-check-input"
                  id="is_royalty_eligible_quick"
                >
                <label class="psx-form-check-label" for="is_royalty_eligible_quick">
                  <h5>{{ $t('Is_Royalty_Eligible') }}</h5>
                </label>
              </div>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button variant="secondary" class="mr-2" @click="$bvModal.hide('Quick_Add_Customer')">{{ $t('Cancel') }}</b-button>
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing">{{$t('submit')}}</b-button>
              <div v-once class="typo__p" v-if="SubmitProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>

    <!-- Modal Update detail Product -->
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
                    v-model="detail.Unit_price"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Price-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Price-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                  <small v-if="detailHasMinPriceViolation" class="text-danger">{{ $t('Price_below_min_not_allowed') }}</small>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Price Type -->
            <b-col lg="6" md="6" sm="12">
              <validation-provider name="Price Type">
                <b-form-group :label="$t('Price Type')">
                  <v-select
                    :reduce="opt => opt.value"
                    v-model="detail.price_type"
                    @input="val => onChangeDetailPriceType(val)"
                    :options="[
                      {label: $t('Retail Price'), value: 'retail'},
                      {label: $t('Wholesale Price'), value: 'wholesale'}
                    ]"
                  />
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
                      v-model="detail.tax_percent"
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

            <!-- Unit Sale -->
            <b-col lg="6" md="6" sm="12" v-if="detail.product_type != 'is_service'">
              <validation-provider name="Unit Sale" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('UnitSale') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="detail.sale_unit_id"
                    :placeholder="$t('Choose_Unit_Sale')"
                    :reduce="label => label.value"
                    :options="units.map(units => ({label: units.name, value: units.id}))"
                  />
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
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
                  :disabled="Submit_Processing_detail || detailHasMinPriceViolation"
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
    title: "Create Sale"
  },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],

      paymentProcessing: false,
      Submit_Processing_detail:false,
      SubmitProcessing: false,
      isLoading: true,
      // POS settings — only `allow_overselling` is consumed here, but we keep
      // the same shape as elsewhere for future-proofing. Default OFF preserves
      // the historical strict stock-check behavior for upgraded installs.
      pos_settings: {
        allow_overselling: false,
      },
      warehouses: [],
      clients: [],
      accounts: [],
      client: {},
      products: [],
      details: [],
      detail: {
        detail_id: "",
        sale_unit_id: "",
        product_type: "",
        name: "",
        Unit_price: "",
        fix_price: "",
        fix_stock: "",
        stock: "",
        tax_method: "",
        discount_Method: "",
        discount: "",
        quantity: "",
        tax_percent: "",
        is_imei: "",
        imei_number: "",
        // modal price type and baselines for correct toggling
        price_type: 'retail',
        retail_unit_price: "",
        wholesale_unit_price: "",
        min_price: 0
      },
      sales: [],
      payment_methods:[],
      payment: {
        status: "pending",
        payment_method_id: "2",
        amount: "",
        received_amount: "",
        account_id: "",
      },
      selectedClientPoints: 0,
      initialClientPoints: 0,
      showPointsSection: false,
      points_to_convert: 0,
      discount_from_points: 0,
      used_points: 0,
      clientIsEligible: false,
      pointsConverted: false,
      point_to_amount_rate: 0,
      sale: {
        id: "",
        date: new Date().toISOString().slice(0, 10),
        statut: "completed",
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
      sales_agents: [],
      // Credit control
      selectedClientCreditLimit: 0,
      selectedClientNetBalance: 0,
      timer:null,
      total: 0,
      GrandTotal: 0,
      units:[],
      product: {
        id: "",
        product_type: "",
        code: "",
        stock: "",
        quantity: 1,
        discount: "",
        DiscountNet: "",
        discount_Method: "",
        name: "",
        sale_unit_id:"",
        fix_stock:"",
        fix_price:"",
        unitSale: "",
        Net_price: "",
        Unit_price: "",
        Unit_price_wholesale: "",
        // immutable baselines used when toggling price types
        retail_unit_price: "",
        wholesale_unit_price: "",
        wholesale_Net_price: "",
        min_price: 0,
        price_type: 'retail',
        Total_price: "",
        subtotal: "",
        product_id: "",
        detail_id: "",
        taxe: "",
        tax_percent: "",
        tax_method: "",
        product_variant_id: "",
        is_imei: "",
        imei_number:"",
        is_batch_tracked: false,
        batches: [],
        available_batches: [],
        batches_loading: false,
      }
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions","currentUser"]),
    pointsInputValid() {
      const max = Number(this.selectedClientPoints) || 0;
      const val = Number(this.points_to_convert);
      return Number.isInteger(val) && val >= 1 && val <= max;
    },

    

    hasMinPriceViolation() {
      return this.details.some(d => (d.min_price || 0) > 0 && d.Net_price < d.min_price);
    },

    hasBatchValidationErrors() {
      if (!Array.isArray(this.details)) return false;
      for (const d of this.details) {
        if (!d || !d.is_batch_tracked) continue;
        const batches = Array.isArray(d.batches) ? d.batches : [];
        if (batches.length === 0) return true;
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
        const label = d.name || d.code || "";
        if (batches.length === 0) {
          return (this.$t("Select_Batch_Required_For") || "Select a batch for") + " " + label;
        }
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
    },

    // Overselling Control: when ON, all stock checks on this page are bypassed
    // (matches POS behavior). Default OFF preserves the strict check.
    isOversellingAllowed() {
      return !!(this.pos_settings && this.pos_settings.allow_overselling);
    },

    // Disable modal submit if the edited detail would violate min price
    detailHasMinPriceViolation() {
      const unit = parseFloat(this.detail.Unit_price) || 0;
      const discount = parseFloat(this.detail.discount) || 0;
      const taxPercent = parseFloat(this.detail.tax_percent) || 0;

      const discountNet = this.detail.discount_Method == "2"
        ? discount
        : parseFloat((unit * discount) / 100);

      let netPrice = 0;
      if (this.detail.tax_method == "1") {
        // Exclusive
        netPrice = parseFloat(unit - discountNet);
      } else {
        // Inclusive
        const taxe = parseFloat((unit - discountNet) * (taxPercent / 100));
        netPrice = parseFloat(unit - taxe - discountNet);
      }

      return (this.detail.min_price || 0) > 0 && netPrice < this.detail.min_price;
    },

  },

  watch: {
    GrandTotal(val) {
      if (Number(val) < 0) {
        this.payment.status = 'pending';
      }
    },
    // Recalculate totals whenever discount type changes (fixed / percentage)
    'sale.discount_Method'(newVal, oldVal) {
      // Ensure totals reflect the new interpretation of sale.discount and discount_from_points
      this.CalculTotal();
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

    async Selected_customer(selectedClientId) {
      this.payment.payment_method_id = 2;
      this.savedPaymentMethods= [];
      this.selectedClientPoints = 0;
      this.initialClientPoints = 0;
      this.points_to_convert = 0;
      this.discount_from_points = 0;
      this.used_points = 0;
      this.clientIsEligible = false;
      this.pointsConverted = false; // 👈 Reset conversion state
      this.sale.discount = 0;       // 👈 Reset applied discount
      this.sale.discount_Method = '2'; // Reset to fixed (default)

      const client = this.clients.find(c => c.id === selectedClientId);
      if (client) {
        this.client_name = client.name;
        this.selectedClientId = selectedClientId;

        // Fetch customer points
        try {
          const response = await axios.get(`/get_points_client/${selectedClientId}`);
          const data = response.data;

          if (data.is_royalty_eligible) {
            this.selectedClientPoints = data.points;
            this.initialClientPoints = data.points;
            this.clientIsEligible = true;
          } else {
            this.selectedClientPoints = 0;
            this.initialClientPoints = 0;
            this.clientIsEligible = false;
          }
        } catch (error) {
          console.error('Error fetching client points:', error);
        }

        // Fetch client credit limit and current balance
        try {
          const briefResponse = await axios.get(`/clients/${selectedClientId}/brief`);
          const briefData = briefResponse.data;
          this.selectedClientCreditLimit = parseFloat(briefData.credit_limit || 0);
          this.selectedClientNetBalance = parseFloat(briefData.netBalance || 0);
        } catch (error) {
          console.error('Error fetching client credit limit:', error);
          this.selectedClientCreditLimit = 0;
          this.selectedClientNetBalance = 0;
        }

      } else {
        this.selectedClientId = "";
        this.selectedClientCreditLimit = 0;
        this.selectedClientNetBalance = 0;
      }

      // ✅ Recalculate totals after client change
      this.CalculTotal();
    },


    convertPointsToDiscount() {
      if (this.pointsConverted) {
        // Reset conversion - sale.discount remains unchanged (it only contains manual discount)
        this.discount_from_points = 0;
        this.used_points = 0;
        this.points_to_convert = 0;
        this.pointsConverted = false;
        // restore available points display
        if (Number.isFinite(this.initialClientPoints)) {
          this.selectedClientPoints = Number(this.initialClientPoints) || 0;
        }
      } else {
        const maxPoints = Number(this.selectedClientPoints) || 0;
        let pts = Number(this.points_to_convert);
        if (!Number.isFinite(pts) || pts <= 0) {
          this.makeToast && this.makeToast('warning', this.$t ? this.$t('Please_enter_points_to_convert') : 'Please enter points to convert', this.$t ? this.$t('Warning') : 'Warning');
          return;
        }
        if (pts > maxPoints) {
          this.makeToast && this.makeToast('warning', this.$t ? this.$t('Entered_points_exceed_available') : 'Entered points exceed available', this.$t ? this.$t('Warning') : 'Warning');
          this.points_to_convert = maxPoints;
          pts = maxPoints;
          this.$nextTick && this.$nextTick(() => {
            const r = this.$refs && this.$refs.pointsInput;
            if (r && r.$el) { try { r.$el.value = String(this.points_to_convert); } catch(e) {} }
          });
        }
        const discount = parseFloat((pts * this.point_to_amount_rate).toFixed(2));
        this.discount_from_points = discount;
        // Don't merge points into sale.discount - keep them separate so input shows only manual discount
        // Points discount is stored in discount_from_points and applied separately in calculations
        this.used_points = pts;
        // ensure input reflects final used points
        this.points_to_convert = pts;
        this.$nextTick && this.$nextTick(() => {
          const r = this.$refs && this.$refs.pointsInput;
          if (r && r.$el) { try { r.$el.value = String(this.points_to_convert); } catch(e) {} }
        });
        this.pointsConverted = true;
        // reduce available points display until saved
        const baseAvail = Number(this.initialClientPoints || this.selectedClientPoints) || 0;
        this.selectedClientPoints = Math.max(0, baseAvail - pts);
      }

      this.CalculTotal(); // Recalculate grand total
    },

    onPointsToConvertInput() {
      let max = Number(this.selectedClientPoints) || 0;
      let val = Number(this.points_to_convert);
      if (!Number.isFinite(val)) val = 0;
      if (val < 0) val = 0;
      val = Math.floor(val);
      if (val > max) {
        // warn and clamp
        this.makeToast && this.makeToast('warning', this.$t ? this.$t('Entered_points_exceed_available') : 'Entered points exceed available', this.$t ? this.$t('Warning') : 'Warning');
        val = max;
      }
      this.points_to_convert = val;
    },
    


     //---------------------- Event Select Status ------------------------------\\

     Selected_Status(value){
      if (value != "completed") {
        this.payment.status = 'pending';
      }
    
    },

    //---------------------- Event Select Payment Status ------------------------------\\

    Selected_PaymentStatus(value){
      if (value == "paid") {
        var payment_amount = this.GrandTotal.toFixed(2);
        this.payment.amount = this.formatNumber(payment_amount, 2);
        this.payment.received_amount = this.formatNumber(payment_amount, 2);
      }else{
        this.payment.amount = 0;
        this.payment.received_amount = 0;
      }
    },

    //---------- keyup paid Amount

    Verified_paidAmount() {
      if (isNaN(this.payment.amount)) {
        this.payment.amount = 0;
      } else if (this.payment.amount > this.payment.received_amount) {
          this.makeToast(
            "warning",
            this.$t("Paying_amount_is_greater_than_Received_amount"),
            this.$t("Warning")
          );
          this.payment.amount = 0;
      } 
      else if (this.payment.amount > this.GrandTotal) {
        this.makeToast(
          "warning",
          this.$t("Paying_amount_is_greater_than_Grand_Total"),
          this.$t("Warning")
        );
        this.payment.amount = 0;
      }
    },

    //---------- keyup Received Amount

    Verified_Received_Amount() {
      if (isNaN(this.payment.received_amount)) {
        this.payment.received_amount = 0;
      } 
    },


  
    //--- Submit Validate Create Sale
    Submit_Sale() {
      // hard block if any line violates min price
      if (this.hasMinPriceViolation) {
        this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
        return;
      }
      this.$refs.create_sale.validate().then(success => {
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
        } else if (this.payment.amount > this.payment.received_amount) {
          this.makeToast(
            "warning",
            this.$t("Paying_amount_is_greater_than_Received_amount"),
            this.$t("Warning")
          );
          this.payment.received_amount = 0;
        }
          else if (this.payment.amount > this.GrandTotal) {
            this.makeToast(
              "warning",
              this.$t("Paying_amount_is_greater_than_Grand_Total"),
              this.$t("Warning")
            );
            this.payment.amount = 0;
          } else {
            // Credit Limit Validation (0 means no limit)
            // Only applies when this sale is adding new credit (paid amount < sale total)
            if (this.selectedClientId && this.selectedClientCreditLimit > 0) {
              const totalPaid = parseFloat(this.payment.amount || 0);
              const saleTotal = parseFloat(this.GrandTotal || 0);

              if (totalPaid < saleTotal) {
                const currentDue = parseFloat(this.selectedClientNetBalance || 0);
                const newSaleDue = saleTotal - totalPaid; // Remaining due from this sale
                const newTotalDue = currentDue + newSaleDue;

                if (newTotalDue > this.selectedClientCreditLimit) {
                  const exceededAmount = newTotalDue - this.selectedClientCreditLimit;
                  this.makeToast(
                    "danger",
                    this.$t("Credit_Limit_Exceeded") + ": " +
                      this.formatNumber(exceededAmount, 2) + " " +
                      this.$t("exceeds_credit_limit_of") + " " +
                      this.formatNumber(this.selectedClientCreditLimit, 2),
                    this.$t("Warning")
                  );
                  return;
                }
              }
            }

            this.Create_Sale();
          }
      });
    },
    //---Submit Validation Update Detail
    submit_Update_Detail() {
      this.$refs.Update_Detail.validate().then(success => {
        if (!success) {
          return;
        } else {
          // block if current edited detail violates min price
          if (this.detailHasMinPriceViolation) {
            this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
            return;
          }
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

    //---------------------- get_units ------------------------------\\
    get_units(value) {
      axios
        .get("get_units?id=" + value)
        .then(({ data }) => (this.units = data));
    },

    //------ Show Modal Update Detail Product
    Modal_Updat_Detail(detail) {
      NProgress.start();
      NProgress.set(0.1);
      this.get_units(detail.product_id);
      this.detail.detail_id = detail.detail_id;
      this.detail.sale_unit_id = detail.sale_unit_id;
      this.detail.product_type = detail.product_type;
      this.detail.name = detail.name;
      this.detail.Unit_price = detail.Unit_price;
      this.detail.fix_price = detail.fix_price;
      this.detail.fix_stock = detail.fix_stock;
      this.detail.stock = detail.stock;
      this.detail.tax_method = detail.tax_method;
      this.detail.discount_Method = detail.discount_Method;
      this.detail.discount = detail.discount;
      this.detail.quantity = detail.quantity;
      this.detail.tax_percent = detail.tax_percent;
      this.detail.is_imei = detail.is_imei;
      this.detail.imei_number = detail.imei_number;
      this.detail.min_price = detail.min_price || 0;
      // sync price type and baselines into modal detail
      this.detail.price_type = detail.price_type || 'retail';
      this.detail.retail_unit_price = detail.retail_unit_price !== undefined ? detail.retail_unit_price : detail.Unit_price;
      this.detail.wholesale_unit_price = detail.wholesale_unit_price !== undefined ? detail.wholesale_unit_price : detail.Unit_price_wholesale;

       setTimeout(() => {
        NProgress.done();
        this.$bvModal.show("form_Update_Detail");
      }, 1000);
    },


    //------ Submit Update Detail Product

    Update_Detail() {
      NProgress.start();
      NProgress.set(0.1);
      this.Submit_Processing_detail = true;
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === this.detail.detail_id) {

          // this.convert_unit();
           for(var k=0; k<this.units.length; k++){
              if (this.units[k].id == this.detail.sale_unit_id) {
                if(this.units[k].operator == '/'){
                  this.details[i].stock       = this.detail.fix_stock  * this.units[k].operator_value;
                  this.details[i].unitSale    = this.units[k].ShortName;

                }else{
                  this.details[i].stock       = this.detail.fix_stock  / this.units[k].operator_value;
                  this.details[i].unitSale    = this.units[k].ShortName;
                }
              }
            }

            // When overselling is allowed, do not cap quantity to the
            // recalculated stock after a unit change — preserve user intent.
            if (!this.isOversellingAllowed && this.details[i].stock < this.details[i].quantity) {
              this.details[i].quantity = this.details[i].stock;
            } else if (this.details[i].stock < this.details[i].quantity) {
              // overselling allowed: keep user quantity as-is
            } else {
              this.details[i].quantity =1;
            }
                      
          // persist selected price type from modal BEFORE adjusting baselines
          this.details[i].price_type = this.detail.price_type || this.details[i].price_type;
          this.details[i].Unit_price = this.detail.Unit_price;
          // update baseline for the selected price type
          if (this.details[i].price_type === 'wholesale') {
            this.details[i].wholesale_unit_price = this.detail.Unit_price;
          } else {
            this.details[i].retail_unit_price = this.detail.Unit_price;
          }
          this.details[i].tax_percent = this.detail.tax_percent;
          this.details[i].tax_method = this.detail.tax_method;
          this.details[i].discount_Method = this.detail.discount_Method;
          this.details[i].discount = this.detail.discount;
          this.details[i].sale_unit_id = this.detail.sale_unit_id;
          this.details[i].imei_number = this.detail.imei_number;
          this.details[i].product_type = this.detail.product_type;

          if (this.details[i].discount_Method == "2") {
            //Fixed
            this.details[i].DiscountNet = this.details[i].discount;
          } else {
            //Percentage %
            this.details[i].DiscountNet = parseFloat(
              (this.details[i].Unit_price * this.details[i].discount) / 100
            );
          }

          if (this.details[i].tax_method == "1") {
            //Exclusive
            this.details[i].Net_price = parseFloat(
              this.details[i].Unit_price - this.details[i].DiscountNet
            );

            this.details[i].taxe = parseFloat(
              (this.details[i].tax_percent *
                (this.details[i].Unit_price - this.details[i].DiscountNet)) /
                100
            );
          } else {
            //Inclusive
            this.details[i].taxe = parseFloat(
              (this.details[i].Unit_price - this.details[i].DiscountNet) *
                (this.details[i].tax_percent / 100)
            );

            this.details[i].Net_price = parseFloat(
              this.details[i].Unit_price -
                this.details[i].taxe -
                this.details[i].DiscountNet
            );
          }

          // Validate against min price after any manual edit
          if (this.details[i].Net_price < (this.details[i].min_price || 0)) {
            this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
            // revert to retail baseline
            this.details[i].price_type = 'retail';
            this.applyPriceType(this.details[i]);
          }

          this.$forceUpdate();
        }
      }
      this.CalculTotal();

      setTimeout(() => {
        NProgress.done();
        this.Submit_Processing_detail = false;
        this.$bvModal.hide("form_Update_Detail");
      }, 1000);

    },

    onChangeDetailPriceType(newType){
      if (newType) {
        this.detail.price_type = newType;
      }
      this.applyPriceType(this.detail);
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

    //------------------------- get Result Value Search Product

    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },

    //------------------------- Submit Search Product


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
            this.product.stock = '---';
            this.product.fix_stock = '---';
          }else{

            this.product.code = result.code;
            this.product.stock = result.qte_sale;
            this.product.fix_stock = result.qte;

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

      // Refresh batch availability on existing batch-tracked lines since it is warehouse-specific.
      if (Array.isArray(this.details)) {
        for (const d of this.details) {
          if (d && d.is_batch_tracked) {
            this.$set(d, "batches", []);
            this.fetch_batches_for_detail(d);
          }
        }
      }
    },

    // ---------------- Quick Add Customer (like POS) ---------------- \\
    Quick_Add_Client() {
      this.reset_Form_client();
      this.$bvModal.show("Quick_Add_Customer");
    },

    reset_Form_client() {
      this.client = {
        id: "",
        name: "",
        email: "",
        phone: "",
        tax_number: "",
        country: "",
        city: "",
        adresse: "",
        is_royalty_eligible: false
      };
    },

    Submit_Quick_Add_Customer() {
      NProgress.start();
      NProgress.set(0.1);
      this.SubmitProcessing = true;
      this.$refs.Quick_Add_Customer_Form &&
        this.$refs.Quick_Add_Customer_Form.validate().then(success => {
          if (!success) {
            NProgress.done();
            this.SubmitProcessing = false;
            this.makeToast(
              "danger",
              this.$t("Please_fill_the_form_correctly"),
              this.$t("Failed")
            );
            return;
          }

          axios
            .post("clients", {
              name: this.client.name,
              email: this.client.email || "",
              phone: this.client.phone || "",
              tax_number: this.client.tax_number || "",
              country: this.client.country || "",
              city: this.client.city || "",
              adresse: this.client.adresse || "",
              is_royalty_eligible: this.client.is_royalty_eligible || false
            })
            .then(({ data }) => {
              NProgress.done();
              this.SubmitProcessing = false;

              const newClient = data;
              if (newClient && newClient.id) {
                this.clients.push({
                  id: newClient.id,
                  name: newClient.name,
                  phone: newClient.phone || ""
                });
                this.selectedClientId = newClient.id;
                // Reuse existing selection logic (points, credit, etc.)
                this.Selected_customer(newClient.id);
              }

              this.makeToast(
                "success",
                this.$t("Successfully_Created"),
                this.$t("Success")
              );
              this.$bvModal.hide("Quick_Add_Customer");
              this.reset_Form_client();
            })
            .catch(() => {
              NProgress.done();
              this.SubmitProcessing = false;
              this.makeToast(
                "danger",
                this.$t("InvalidData"),
                this.$t("Failed")
              );
            });
        });
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
    fetch_batches_for_detail(detail) {
      if (!detail) return;
      // Ensure all batch-related fields are reactive on the detail (some are added post-push via
      // direct assignment, which in Vue 2 is non-reactive — $set fixes that).
      if (!("batches_loading" in detail)) this.$set(detail, "batches_loading", false);
      if (!("available_batches" in detail)) this.$set(detail, "available_batches", []);
      if (!Array.isArray(detail.batches)) this.$set(detail, "batches", []);

      if (!detail.is_batch_tracked) {
        this.$set(detail, "batches_loading", false);
        return;
      }
      const wid = this.sale && this.sale.warehouse_id;
      if (!wid || !detail.product_id) {
        // Can't fetch yet — make sure we're not stuck in loading.
        this.$set(detail, "batches_loading", false);
        return;
      }
      const variantSeg = (detail.product_variant_id != null && detail.product_variant_id !== "")
        ? detail.product_variant_id
        : 0;
      this.$set(detail, "batches_loading", true);
      axios
        .get(`batches_for_sale/${detail.product_id}/${wid}/${variantSeg}`, { timeout: 15000 })
        .then(response => {
          const list = (response && response.data && Array.isArray(response.data.batches))
            ? response.data.batches
            : [];
          this.$set(detail, "available_batches", list);
          if (!Array.isArray(detail.batches)) this.$set(detail, "batches", []);
          // Seed a first empty batch row if we have availability and the row is empty.
          if (detail.batches.length === 0 && list.length > 0) {
            this.add_batch_to_detail(detail);
          }
        })
        .catch(() => {
          this.$set(detail, "available_batches", []);
        })
        .then(() => {
          // finally-equivalent: always clear the loading flag, even if unexpected errors slip through.
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

    //-----------------------------------Verified QTY ------------------------------\\
    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === id) {
          if (isNaN(detail.quantity)) {
            this.details[i].quantity = detail.stock;
          }

          // Stock cap skipped when overselling is allowed.
          if (!this.isOversellingAllowed && detail.quantity > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
            this.details[i].quantity = detail.stock;
          } else {
            this.details[i].quantity = detail.quantity;
          }
        }
      }
      this.$forceUpdate();
      this.CalculTotal();
    },

    //-----------------------------------increment QTY ------------------------------\\

    increment(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          // Stock guard skipped when overselling is allowed.
          if (!this.isOversellingAllowed && detail.quantity + 1 > detail.stock) {
            this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
          } else {
            this.formatNumber(this.details[i].quantity++, 2);
          }
        }
      }
      this.$forceUpdate();
      this.CalculTotal();
    },

    //-----------------------------------decrement QTY ------------------------------\\

    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.quantity - 1 > 0) {
            // Stock guard skipped when overselling is allowed.
            if (!this.isOversellingAllowed && detail.quantity - 1 > detail.stock) {
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
      this.CalculTotal();
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
    CalculTotal() {
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

      if(this.payment.status == 'paid'){
          this.payment.amount = this.formatNumber(this.GrandTotal, 2);
      }

    },

    //-----------------------------------Delete Detail Product ------------------------------\\
    delete_Product_Detail(id) {
      for (var i = 0; i < this.details.length; i++) {
        if (id === this.details[i].detail_id) {
          this.details.splice(i, 1);
          this.CalculTotal();
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
          // Empty/zero quantity is always invalid; the stock-exceeded branch
          // is skipped when overselling is allowed.
          const overStock = !this.isOversellingAllowed && this.details[i].quantity > this.details[i].stock;
          if (
            this.details[i].quantity == "" ||
            this.details[i].quantity === 0 ||
            overStock
          ) {
            count += 1;
            if (overStock) {
              this.makeToast("warning", this.$t("LowStock"), this.$t("Warning"));
              return false;
            }
          }
          // enforce min price per line
          if ((this.details[i].min_price || 0) > 0 && this.details[i].Net_price < this.details[i].min_price) {
            this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
            return false;
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

    //---------- keyup OrderTax
    keyup_OrderTax() {
      if (isNaN(this.sale.tax_rate)) {
        this.sale.tax_rate = 0;
      } else if(this.sale.tax_rate == ''){
         this.sale.tax_rate = 0;
        this.CalculTotal();
      }else {
        this.CalculTotal();
      }
    },

    //---------- keyup Discount

    keyup_Discount() {
      if (isNaN(this.sale.discount)) {
        this.sale.discount = 0;
      } else if(this.sale.discount == ''){
         this.sale.discount = 0;
        this.CalculTotal();
      }else {
        this.CalculTotal();
      }
    },

    // Calculate discount amount for current sale (for display in summary card)
    // Calculate manual discount amount only (excluding points) for display
    getManualDiscountAmount() {
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
    
    // Calculate total discount amount (includes both manual and points) for display
    getCurrentSaleDiscountAmount() {
      try {
        const discountMethod = String(this.sale.discount_Method || '2'); // Default to fixed for backward compatibility
        const discountValue = Number(this.sale.discount || 0);
        const subtotal = this.total || 0;

        if (discountMethod === '1') {
          // Percentage discount on subtotal
          const percentAmount = parseFloat((subtotal * (discountValue / 100)).toFixed(2));
          // Points-based discount is always a fixed amount; add it for display
          const remainingAfterPercent = Math.max(subtotal - percentAmount, 0);
          const pointsAmount = parseFloat(
            Math.min(Number(this.discount_from_points || 0), remainingAfterPercent).toFixed(2)
          );
          return percentAmount + pointsAmount;
        } else {
          // Fixed discount: apply both manual discount and points discount separately
          const manualDiscount = parseFloat(Math.min(discountValue, subtotal).toFixed(2));
          const remainingAfterManual = Math.max(subtotal - manualDiscount, 0);
          const pointsDiscount = parseFloat(
            Math.min(Number(this.discount_from_points || 0), remainingAfterManual).toFixed(2)
          );
          return manualDiscount + pointsDiscount;
        }
      } catch (e) {
        return Number(this.sale.discount || 0);
      }
    },

    //---------- keyup Shipping

    keyup_Shipping() {
      if (isNaN(this.sale.shipping)) {
        this.sale.shipping = 0;
      } else if(this.sale.shipping == ''){
         this.sale.shipping = 0;
        this.CalculTotal();
      }else {
        this.CalculTotal();
      }
    },

    async processPayment() {
      // Legacy helper kept for backward compatibility; Stripe processing removed.
      return this.Create_Sale();
    },
    //--------------------------------- Create Sale -------------------------\\
    Create_Sale() {
      if (this.verifiedForm()) {
        if (Number(this.GrandTotal) < 0) {
          const msg = this.$t ? `${this.$t('pos.Total_Payable')} ${this.$t('cannot_be_negative') || 'cannot be negative'}` : 'Total Payable cannot be negative';
          this.makeToast('warning', msg, this.$t ? this.$t('Warning') : 'Warning');
          return;
        }

        // Batch validation for batch-tracked products (only enforced when completed).
        if (this.sale.statut === 'completed' && this.hasBatchValidationErrors) {
          this.makeToast('warning', this.firstBatchErrorMessage, this.$t('Warning') || 'Warning');
          return;
        }

        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);

        {
          this.paymentProcessing = true;
          const detailsPayload = this.details.map(d => {
            const out = Object.assign({}, d);
            // Strip helper UI-only fields and pass clean batches
            delete out.available_batches;
            delete out.batches_loading;
            if (d.is_batch_tracked && Array.isArray(d.batches)) {
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
            .post("sales", {
              date: this.sale.date,
              client_id: this.selectedClientId,
              warehouse_id: this.sale.warehouse_id,
              sales_agent_id: this.sale.sales_agent_id || null,
              statut: this.sale.statut,
              notes: this.sale.notes,
              tax_rate: this.sale.tax_rate?this.sale.tax_rate:0,
              TaxNet: this.sale.TaxNet?this.sale.TaxNet:0,
              discount: this.sale.discount?this.sale.discount:0,
              discount_Method: String(this.sale.discount_Method || '2'), // '1' = percent, '2' = fixed
              shipping: this.sale.shipping?this.sale.shipping:0,
              GrandTotal: this.GrandTotal,
              details: detailsPayload,
              payment: this.payment,
              amount: parseFloat(this.payment.amount).toFixed(2),
              received_amount: parseFloat(this.payment.received_amount).toFixed(2),
              change: parseFloat(this.payment.received_amount - this.payment.amount).toFixed(2),
              discount_from_points: this.discount_from_points,
              used_points: this.used_points,
            })
            .then(response => {
              this.makeToast(
                "success",
                this.$t("Successfully_Created"),
                this.$t("Success")
              );
              NProgress.done();
              this.paymentProcessing = false;
              this.$router.push({ name: "index_sales" });
            })
            .catch(error => {
              NProgress.done();
              this.paymentProcessing = false;
              this.makeToast(
                "danger",
                this.$t("InvalidData"),
                this.$t("Failed")
              );
            });
        }
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
        this.product.discount           = response.data.discount;
        this.product.DiscountNet        = response.data.DiscountNet;
        this.product.discount_Method    = response.data.discount_method;
        this.product.product_id = response.data.id;
        this.product.product_type = response.data.product_type;
        this.product.name = response.data.name;
        this.product.Net_price = response.data.Net_price;
        this.product.Unit_price = response.data.Unit_price;
        this.product.Unit_price_wholesale = response.data.Unit_price_wholesale;
        // store immutable baselines to allow correct toggling between price types
        this.product.retail_unit_price = response.data.Unit_price;
        this.product.wholesale_unit_price = response.data.Unit_price_wholesale;
        this.product.wholesale_Net_price = response.data.wholesale_Net_price;
        this.product.min_price = response.data.min_price;
        this.product.taxe = response.data.tax_price;
        this.product.tax_method = response.data.tax_method;
        this.product.tax_percent = response.data.tax_percent;
        this.product.unitSale = response.data.unitSale;
        this.product.fix_price = response.data.fix_price;
        this.product.sale_unit_id = response.data.sale_unit_id;
        this.product.is_imei = response.data.is_imei;
        this.product.imei_number = '';
        this.product.warehouse_location = response.data.warehouse_location
          ? (response.data.warehouse_location.name
              ? `${response.data.warehouse_location.code} - ${response.data.warehouse_location.name}`
              : response.data.warehouse_location.code)
          : null;
        this.product.price_type = 'retail';
        this.applyPriceType(this.product);

        // ensure min price respected
        if (this.product.Net_price < (this.product.min_price || 0)) {
          this.product.price_type = 'retail';
          this.applyPriceType(this.product);
        }

        // Reset batch data for this product; will be hydrated after push if batch-tracked.
        // Use $set so Vue 2 tracks these fields reactively even though this.product was
        // reassigned to a bare {} in SearchProduct (direct assignment afterwards is non-reactive).
        this.$set(this.product, "is_batch_tracked", !!response.data.is_batch_tracked);
        this.$set(this.product, "batches", []);
        this.$set(this.product, "available_batches", []);
        this.$set(this.product, "batches_loading", false);

        this.add_product();
        this.CalculTotal();

        if (this.product.is_batch_tracked) {
          // Fetch available batches for the just-added line (last detail).
          const last = this.details[this.details.length - 1];
          if (last) {
            this.fetch_batches_for_detail(last);
          }
        }
      });
    },

    applyPriceType(prod){
      // choose immutable baseline based on selected price type
      const selectedIsWholesale = prod.price_type === 'wholesale';
      const hasWholesaleBaseline = prod.wholesale_unit_price !== undefined && prod.wholesale_unit_price !== null && prod.wholesale_unit_price !== '';
      const hasRetailBaseline = prod.retail_unit_price !== undefined && prod.retail_unit_price !== null && prod.retail_unit_price !== '';

      if (selectedIsWholesale && hasWholesaleBaseline) {
        prod.Unit_price = parseFloat(prod.wholesale_unit_price);
      } else if (hasRetailBaseline) {
        prod.Unit_price = parseFloat(prod.retail_unit_price);
      }

      // Recompute discount/tax derived values based on Unit_price and method
      if (prod.discount_Method == "2") {
        prod.DiscountNet = parseFloat(prod.discount || 0);
      } else {
        prod.DiscountNet = parseFloat(((parseFloat(prod.Unit_price) || 0) * (parseFloat(prod.discount) || 0)) / 100);
      }

      const unitAfterDiscount = (parseFloat(prod.Unit_price) || 0) - (parseFloat(prod.DiscountNet) || 0);
      const taxPercent = parseFloat(prod.tax_percent) || 0;

      if (prod.tax_method == "1") {
        // Exclusive
        prod.Net_price = parseFloat(unitAfterDiscount);
        prod.taxe = parseFloat((taxPercent * unitAfterDiscount) / 100);
      } else {
        // Inclusive
        prod.taxe = parseFloat(unitAfterDiscount * (taxPercent / 100));
        prod.Net_price = parseFloat((parseFloat(prod.Unit_price) || 0) - (parseFloat(prod.taxe) || 0) - (parseFloat(prod.DiscountNet) || 0));
      }
    },

    onChangePriceType(detail, newType){
      if (newType) {
        detail.price_type = newType;
      }
      this.applyPriceType(detail);
      // enforce min price rule on change
      if(detail.Net_price < detail.min_price){
        this.makeToast('warning', this.$t('Price_below_min_not_allowed'), this.$t('Warning'));
        // revert to retail if wholesale violates minimum
        detail.price_type = 'retail';
        this.applyPriceType(detail);
      }
      this.$forceUpdate();
      this.CalculTotal();
    },

    //---------------------------------------Get Elements ------------------------------\\
    GetElements() {
      axios
        .get("sales/create")
        .then(response => {
          this.clients = response.data.clients;
          this.warehouses = response.data.warehouses;
          this.sales_agents = response.data.sales_agents || [];
          this.accounts = response.data.accounts;
          this.payment_methods = response.data.payment_methods;
          this.point_to_amount_rate = response.data.point_to_amount_rate;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });

      // Load POS settings to read the `allow_overselling` flag. Runs in parallel
      // with the main GetElements call. Failures are silent — page works as
      // before (strict stock checks) when the setting cannot be retrieved.
      axios
        .get("get_pos_Settings")
        .then(response => {
          const ps = response && response.data && response.data.pos_settings;
          if (ps) {
            this.pos_settings = { ...this.pos_settings, ...ps };
          }
        })
        .catch(() => { /* ignore — fall back to default OFF */ });
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

  /* Points section helpers (lightweight, scoped to this page) */
  .hint { font-size: 13px; color: #6b7280; }
  .hint strong { color: #111827; }
  .warn { color: #b45309; font-size: 12px; }
  .ok { color: #065f46; font-size: 12px; }
  .result { font-size: 13px; color: #1e3a8a; background: #eef2ff; border: 1px dashed #c7d2fe; border-radius: 10px; padding: 8px 10px; }

  .table-responsive::after {
    content: '';
    display: block;
    height: 150px; /* gives breathing space for last dropdown */
  }

  /* ===== v-select in input-group =====
     A global rule (specificity 0,4,0) sets
       .input-group:not(.input-group-sm):not(.input-group-lg) .btn { height: 42px }
     which makes the quick-add button 42px and — via align-items: stretch —
     drags the whole input-group to 42px, while the standalone warehouse
     v-select beside it stays at its natural height. Override only the
     button height (with !important to beat that 0,4,0 global selector)
     so the customer select matches the warehouse select. */
  .input-group.category-input-group {
    display: flex;
    align-items: stretch;
    flex-wrap: nowrap;
  }

  .input-group.category-input-group .v-select {
    flex: 1 1 auto;
    min-width: 0;
  }

  .input-group.category-input-group .v-select .vs__dropdown-toggle {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
  }

  .input-group.category-input-group .input-group-append {
    align-items: stretch;
  }

  .input-group.category-input-group .category-add-btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    white-space: nowrap;
    height: calc(1.5em + 0.7rem + 0px) !important;
  }
</style>