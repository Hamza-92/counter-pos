<template>
  <div class="main-content">
    <breadcumb :page="$t('ListSales')" :folder="$t('Sales')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="sales"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        placeholder: $t('Search_this_table'),
        enabled: true,
      }"
        :select-options="{ 
          enabled: true ,
          clearSelectionText: '',
        }"
        @on-selected-rows-change="selectionChanged"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        :styleClass="showDropdown?'tableOne table-hover vgt-table full-height':'tableOne table-hover vgt-table non-height'"
      >
        <div slot="selected-row-actions" v-if="currentUserPermissions.includes('Sales_delete')">
          <button class="btn btn-danger btn-sm" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Sales_PDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="sales"
              :columns="columns"
              :file-name="'sales'"
              :file-type="'xlsx'"
              :sheet-name="'sales'"
              >
              <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
          <router-link
            class="btn-sm btn btn-primary ripple btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_add')"
            to="/app/sales/store"
          >
            <span class="ul-btn__icon">
              <lucide-icon name="plus" />
            </span>
            <span class="ul-btn__text ml-1">{{$t('Add')}}</span>
          </router-link>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <div>
              <b-dropdown
                id="dropdown-right"
                variant="link"
                text="right align"
                toggle-class="text-decoration-none"
                size="lg"
                right
                no-caret
              >
                <template v-slot:button-content class="_r_btn border-0">
                  <span class="_dot _r_block-dot bg-dark"></span>
                  <span class="_dot _r_block-dot bg-dark"></span>
                  <span class="_dot _r_block-dot bg-dark"></span>
                </template>
                <b-navbar-nav>
                  <b-dropdown-item title="Show" :to="'/app/sales/detail/'+props.row.id">
                    <lucide-icon class="nav-icon font-weight-bold mr-2" name="eye" />
                    {{$t('SaleDetail')}}
                  </b-dropdown-item>
                </b-navbar-nav>

                 <b-dropdown-item 
                  title="Edit"
                  v-if="currentUserPermissions.includes('Sales_edit') && props.row.sale_has_return == 'no'"
                  :to="'/app/sales/edit/'+props.row.id"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="pen" />
                  {{$t('EditSale')}}
                </b-dropdown-item>

                <b-dropdown-item
                  title="Sell Return"
                  v-if="currentUserPermissions.includes('Sale_Returns_add') && props.row.sale_has_return == 'no' && props.row.statut == 'completed'"
                  :to="'/app/sales/sale_return/'+props.row.id"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="arrow-left" />
                  {{$t('Sell_Return')}}
                </b-dropdown-item>

                <b-dropdown-item
                  title="Sell Return"
                  v-if="currentUserPermissions.includes('Sale_Returns_add') && props.row.sale_has_return == 'yes'"
                  :to="'/app/sale_return/edit/'+props.row.salereturn_id+'/'+props.row.id"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="arrow-left" />
                  {{$t('Sell_Return')}}
                </b-dropdown-item>

                <b-dropdown-item
                  v-if="currentUserPermissions.includes('payment_sales_view')"
                  @click="Show_Payments(props.row.id , props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="wallet" />
                  {{$t('ShowPayment')}}
                </b-dropdown-item>

                <b-dropdown-item
                  v-if="currentUserPermissions.includes('payment_sales_add') && props.row.statut =='completed'"
                  @click="New_Payment(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="plus" />
                  {{$t('AddPayment')}}
                </b-dropdown-item>

                <b-dropdown-item
                  v-if="currentUserPermissions.includes('shipment')"
                  @click="Edit_Shipment(props.row.id)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="pen" />
                  {{$t('Edit_Shipping')}}
                </b-dropdown-item>


                <b-dropdown-item title="Invoice" @click="Invoice_POS(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="file-text" />
                  {{$t('Invoice_POS')}}
                </b-dropdown-item>

                <b-dropdown-item title="PDF" @click="Invoice_PDF(props.row , props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="file-text" />
                  {{$t('DownloadPdf')}}
                </b-dropdown-item>

                <b-dropdown-item title=" WhatsApp Notification" @click="Send_WhatsApp(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="mail" />
                  WhatsApp Notification
                </b-dropdown-item>

                <b-dropdown-item title="Email" @click="Send_Email(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="mail" />
                  {{$t('email_notification')}}
                </b-dropdown-item>

                <b-dropdown-item title="SMS" @click="Sale_SMS(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="message-square" />
                  {{$t('sms_notification')}}
                </b-dropdown-item>

                <b-dropdown-item title="Attach Documents" @click="Manage_Documents(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="file" />
                  {{$t('Attach_Documents')}}
                </b-dropdown-item>

                <b-dropdown-item
                  title="Delete"
                  v-if="currentUserPermissions.includes('Sales_delete')"
                  @click="Remove_Sale(props.row.id , props.row.sale_has_return)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="x" />
                  {{$t('DeleteSale')}}
                </b-dropdown-item>
              </b-dropdown>
            </div>
          </span>
          <span v-else-if="props.column.field == 'date'">
            {{ formatDisplayDate(props.row.date) }}
          </span>
          <div v-else-if="props.column.field == 'statut'">
            <span
              v-if="props.row.statut == 'completed'"
              class="badge badge-outline-success"
            >{{$t('complete')}}</span>
            <span
              v-else-if="props.row.statut == 'pending'"
              class="badge badge-outline-info"
            >{{$t('Pending')}}</span>
            <span v-else class="badge badge-outline-warning">{{$t('Ordered')}}</span>
          </div>

          <div v-else-if="props.column.field == 'payment_status'">
            <span
              v-if="props.row.payment_status == 'paid'"
              class="badge badge-outline-success"
            >{{$t('Paid')}}</span>
            <span
              v-else-if="props.row.payment_status == 'partial'"
              class="badge badge-outline-primary"
            >{{$t('partial')}}</span>
            <span v-else class="badge badge-outline-warning">{{$t('Unpaid')}}</span>
          </div>
          <div v-else-if="props.column.field == 'shipping_status'">
            <span
              v-if="props.row.shipping_status == 'ordered'"
              class="badge badge-outline-warning"
            >{{$t('Ordered')}}</span>

            <span
              v-else-if="props.row.shipping_status == 'packed'"
              class="badge badge-outline-info"
            >{{$t('Packed')}}</span>

            <span
              v-else-if="props.row.shipping_status == 'shipped'"
              class="badge badge-outline-secondary"
            >{{$t('Shipped')}}</span>

             <span
              v-else-if="props.row.shipping_status == 'delivered'"
              class="badge badge-outline-success"
            >{{$t('Delivered')}}</span>

            <span v-else-if="props.row.shipping_status == 'cancelled'" class="badge badge-outline-danger">{{$t('Cancelled')}}</span>
          </div>
          <span v-else-if="props.column.field == 'GrandTotal'">
            {{ formatPriceWithSymbol(currentUser.currency, props.row.GrandTotal, 2) }}
          </span>
          <span v-else-if="props.column.field == 'paid_amount'">
            {{ formatPriceWithSymbol(currentUser.currency, props.row.paid_amount, 2) }}
          </span>
          <span v-else-if="props.column.field == 'due'">
            {{ formatPriceWithSymbol(currentUser.currency, props.row.due, 2) }}
          </span>
           <div v-else-if="props.column.field == 'Ref'">
              <router-link
                :to="'/app/sales/detail/'+props.row.id"
              >
                <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
              </router-link> <br>
              <small v-if="props.row.sale_has_return == 'yes'"><lucide-icon class="text-15 text-danger" name="arrow-left" /></small>
            </div>
            <div v-else-if="props.column.field == 'documents'">
              <span v-if="props.row.documents_count > 0" class="badge badge-info">
                <lucide-icon name="file" /> {{props.row.documents_count}}
              </span>
              <span v-else class="text-muted">-</span>
            </div>
        </template>
      </vue-good-table>
    </div>

    <!-- Sidebar Filter -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- date  -->
          <b-col md="12">
            <b-form-group :label="$t('date')">
              <b-form-input type="date" v-model="Filter_date"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Reference -->
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input label="Reference" :placeholder="$t('Reference')" v-model="Filter_Ref"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Customer  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_Client"
                :options="customers.map(customers => ({label: customers.name, value: customers.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- warehouse -->
          <b-col md="12">
            <b-form-group :label="$t('warehouse')">
              <v-select
                v-model="Filter_warehouse"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Status  -->
          <b-col md="12">
            <b-form-group :label="$t('Status')">
              <v-select
                v-model="Filter_status"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Status')"
                :options="
                      [
                        {label: 'completed', value: 'completed'},
                        {label: 'Pending', value: 'pending'},
                        {label: 'Ordered', value: 'ordered'},
                      ]"
              ></v-select>
            </b-form-group>
          </b-col>

          <!-- Payment Status  -->
          <b-col md="12">
            <b-form-group :label="$t('PaymentStatus')">
              <v-select
                v-model="Filter_Payment"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Status')"
                :options="
                      [
                        {label: 'Paid', value: 'paid'},
                        {label: 'partial', value: 'partial'},
                        {label: 'UnPaid', value: 'unpaid'},
                      ]"
              ></v-select>
            </b-form-group>
          </b-col>

           <!-- Shipping Status  -->
          <b-col md="12">
            <b-form-group :label="$t('Shipping_status')">
              <v-select
                v-model="Filter_shipping"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Status')"
                :options="
                      [
                        {label: 'Ordered', value: 'ordered'},
                        {label: 'Packed', value: 'packed'},
                        {label: 'Shipped', value: 'shipped'},
                        {label: 'Delivered', value: 'delivered'},
                        {label: 'Cancelled', value: 'cancelled'},
                      ]"
              ></v-select>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Get_Sales(serverParams.page)"
              variant="primary btn-block ripple m-1"
              size="sm"
            >
              <lucide-icon name="filter" />
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple btn-block m-1" size="sm">
              <lucide-icon name="power" />
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>

    <!-- Modal Show Payments-->
    <b-modal hide-footer size="lg" id="Show_payment" :title="$t('ShowPayment')">
      <b-row>
        <b-col lg="12" md="12" sm="12" class="mt-3">
          <div class="table-responsive">
            <table class="table table-hover table-bordered table-md">
              <thead>
                <tr>
                  <th scope="col">{{$t('date')}}</th>
                  <th scope="col">{{$t('Reference')}}</th>
                  <th scope="col">{{$t('Amount')}}</th>
                  <th scope="col">{{$t('PayeBy')}}</th>
                  <th scope="col">{{$t('Action')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="payments.length <= 0">
                  <td colspan="5">{{$t('NodataAvailable')}}</td>
                </tr>
                <tr v-for="payment in payments">
                  <td>{{payment.date}}</td>
                  <td>{{payment.Ref}}</td>
                  <td>{{ formatPriceWithSymbol(currentUser.currency, payment.montant, 2) }}</td>
                  <td>{{payment.payment_method?payment.payment_method.name:'---'}}</td>
                  <td>
                    <div role="group" aria-label="Basic example" class="btn-group">
                      <span
                        title="Print"
                        class="btn btn-icon btn-info btn-sm"
                        @click="Payment_Sale_PDF(payment,payment.id)"
                      >
                        <lucide-icon name="receipt" />
                      </span>
                      <span
                        v-if="currentUserPermissions.includes('payment_sales_edit')"
                        title="Edit"
                        class="btn btn-icon btn-success btn-sm"
                        @click="Edit_Payment(payment)"
                      >
                        <lucide-icon name="pen" />
                      </span>
                      <span
                        title="Email"
                        class="btn btn-icon btn-primary btn-sm"
                        @click="Send_Email_Payment(payment.id)"
                      >
                        <lucide-icon name="mail" />
                      </span>
                      <span
                        title="SMS"
                        class="btn btn-icon btn-secondary btn-sm"
                        @click="Payment_Sale_SMS(payment.id)"
                      >
                        <lucide-icon name="message-square" />
                      </span>
                      <span
                        v-if="currentUserPermissions.includes('payment_sales_delete')"
                        title="Delete"
                        class="btn btn-icon btn-danger btn-sm"
                        @click="Remove_Payment(payment.id)"
                      >
                        <lucide-icon name="x" />
                      </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </b-col>
      </b-row>
    </b-modal>

    <!-- Modal Add Payment-->
    <validation-observer ref="Add_payment">
      <b-modal
        hide-footer
        size="lg"
        id="Add_Payment"
        :title="EditPaiementMode?$t('EditPayment'):$t('AddPayment')"
      >
        <b-form @submit.prevent="Submit_Payment">
          <h1 class="text-center mt-3 mb-3">{{client_name}}</h1>
          <b-row>
            <!-- date -->
            <b-col lg="4" md="12" sm="12">
              <validation-provider
                name="date"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('date')">
                  <b-form-input
                    label="date"
                    :state="getValidationState(validationContext)"
                    aria-describedby="date-feedback"
                    v-model="payment.date"
                    type="date"
                  ></b-form-input>
                  <b-form-invalid-feedback id="date-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Reference  -->
            <b-col lg="4" md="12" sm="12">
              <b-form-group :label="$t('Reference')">
                <b-form-input
                  disabled="disabled"
                  label="Reference"
                  :placeholder="$t('Reference')"
                  v-model="payment.Ref"
                ></b-form-input>
              </b-form-group>
            </b-col>

             <!-- Payment choice -->
             <b-col lg="4" md="12" sm="12">
              <validation-provider name="Payment choice" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Paymentchoice')">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="payment.payment_method_id"
                    @input="Selected_PaymentMethod"
                    :disabled="EditPaiementMode"
                    :reduce="label => label.value"
                    :placeholder="$t('PleaseSelect')"
                    :options="payment_methods.map(payment_methods => ({label: payment_methods.name, value: payment_methods.id}))"

                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Received  Amount  -->
            <b-col lg="4" md="12" sm="12">
                <validation-provider
                  name="Received Amount"
                  :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                  v-slot="validationContext"
                >
                <b-form-group :label="$t('Received_Amount')">
                  <b-form-input
                    @keyup="Verified_Received_Amount(payment.received_amount)"
                    label="Received_Amount"
                    :placeholder="$t('Received_Amount')"
                    v-model.number="payment.received_amount"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Received_Amount-feedback"
                    :disabled="EditPaiementMode && (payment.payment_method_id == '1' || payment.payment_method_id == 1)"
                  ></b-form-input>
                  <b-form-invalid-feedback
                    id="Received_Amount-feedback"
                  >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Paying Amount  -->
            <b-col lg="4" md="12" sm="12">
              <validation-provider
                name="Amount"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Paying_Amount')">
                  <b-form-input
                   @keyup="Verified_paidAmount(payment.montant)"
                    label="Amount"
                    :placeholder="$t('Paying_Amount')"
                    v-model.number="payment.montant"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Amount-feedback"
                    :disabled="EditPaiementMode && (payment.payment_method_id == '1' || payment.payment_method_id == 1)"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Amount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- change Amount  -->
            <b-col lg="4" md="12" sm="12">
              <label>{{$t('Change')}} :</label>
              <p
                class="change_amount"
              >{{parseFloat(payment.received_amount - payment.montant).toFixed(2)}}</p>
            </b-col>

            <!-- Account -->
            <b-col lg="6" md="6" sm="12">
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

            <!-- Note -->
            <b-col lg="6" md="6" sm="12">
              <b-form-group :label="$t('Note')">
                <b-form-textarea id="textarea" v-model="payment.notes" rows="3" max-rows="6"></b-form-textarea>
              </b-form-group>
            </b-col>
            <b-col md="12" class="mt-3">
              <b-button
                variant="primary"
                type="submit"
                :disabled="paymentProcessing"
              ><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
              <div v-once class="typo__p" v-if="paymentProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>

     <!-- Modal Edit Shipment -->
    <validation-observer ref="shipment_ref">
      <b-modal hide-footer size="md" id="modal_shipment" :title="$t('Edit')">
        <b-form @submit.prevent="Submit_Shipment">
          <b-row>
            <!-- Status  -->
            <b-col md="12">
              <validation-provider name="Status" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Status') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="shipment.status"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Status')"
                    :options="
                                [
                                  {label: 'Ordered', value: 'ordered'},
                                  {label: 'Packed', value: 'packed'},
                                  {label: 'Shipped', value: 'shipped'},
                                  {label: 'Delivered', value: 'delivered'},
                                  {label: 'Cancelled', value: 'cancelled'},
                                ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('delivered_to')">
                <b-form-input
                  label="delivered_to"
                  v-model="shipment.delivered_to"
                  :placeholder="$t('delivered_to')"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Adress')">
                <textarea
                  v-model="shipment.shipping_address"
                  rows="4"
                  class="form-control"
                  :placeholder="$t('Enter_Address')"
                ></textarea>
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Please_provide_any_details')">
                <textarea
                  v-model="shipment.shipping_details"
                  rows="4"
                  class="form-control"
                  :placeholder="$t('Please_provide_any_details')"
                ></textarea>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button
                variant="primary"
                type="submit"
                :disabled="Submit_Processing_shipment"
              ><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
              <div v-once class="typo__p" v-if="Submit_Processing_shipment">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>

    <!-- Modal Show Invoice POS-->
    <b-modal hide-footer size="sm" scrollable id="Show_invoice" :title="$t('Invoice_POS')" @shown="onInvoiceModalShown">
      <div id="invoice-POS">
        <div style="max-width:400px;margin:0px auto">

          <!-- Layout 1 - Standard -->
          <div v-if="currentReceiptLayout === 1">
            <div class="info">
              <div class="invoice_logo text-center mb-2">
                <img
                  v-show="pos_settings.show_logo !== 0"
                  :src="'/images/'+invoice_pos.setting.logo"
                  alt
                  :width="pos_settings.logo_size || 60"
                  :height="pos_settings.logo_size || 60"
                >
              </div>
              <p>
                <span v-show="pos_settings.show_store_name !== 0">
                  <strong>{{invoice_pos.setting.CompanyName}}</strong><br>
                </span>
                <span v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0">
                  {{$t('Reference')}} : {{invoice_pos.sale.Ref}}<br>
                </span>
                <span v-show="pos_settings.show_date !== 0">
                  {{$t('date')}} : {{invoice_pos.sale.date}}<br>
                </span>
                <span v-show="pos_settings.show_seller !== 0">
                  {{$t('Seller')}} : {{invoice_pos.sale.seller_name}}<br>
                </span>
                <span v-show="pos_settings.show_address">
                  {{$t('Adress')}} : {{invoice_pos.setting.CompanyAdress}}<br>
                </span>
                <span v-show="pos_settings.show_email">
                  {{$t('Email')}} : {{invoice_pos.setting.email}}<br>
                </span>
                <span v-show="pos_settings.show_phone">
                  {{$t('Phone')}} : {{invoice_pos.setting.CompanyPhone}}<br>
                </span>
                <span v-show="pos_settings.show_customer">
                  {{$t('Customer')}} : {{invoice_pos.sale.client_name}}<br>
                </span>
                <span v-show="pos_settings.show_Warehouse">
                  {{$t('warehouse')}} : {{invoice_pos.sale.warehouse_name}}<br>
                </span>
              </p>
            </div>

            <table style="width: 100%;">
              <tbody>
                <tr v-for="detail_invoice in invoice_pos.details">
                  <td colspan="3">
                    {{detail_invoice.name}}
                    <br v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null">
                    <span v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null ">
                      {{$t('IMEI_SN')}} : {{detail_invoice.imei_number}}
                    </span>
                    <br>
                    <span>
                      {{formatNumber(detail_invoice.quantity,2)}} {{detail_invoice.unit_sale}}
                      x
                      {{ formatPriceDisplay(detail_invoice.total/detail_invoice.quantity,2) }}
                    </span>
                  </td>
                  <td style="text-align:right;vertical-align:bottom">
                    {{ formatPriceDisplay(detail_invoice.total,2) }}
                  </td>
                </tr>

                <!-- Subtotal (before tax/discount/shipping) -->
                <tr style="margin-top:10px">
                  <td colspan="3" class="total">{{$t('pos.Subtotal')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoiceSubtotal, 2) }}
                  </td>
                </tr>

                <tr style="margin-top:10px" v-show="pos_settings.show_tax">
                  <td colspan="3" class="total">{{$t('OrderTax')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.taxe ,2) }}
                    ({{formatNumber(invoice_pos.sale.tax_rate,2)}} %)
                  </td>
                </tr>

                <tr style="margin-top:10px" v-show="pos_settings.show_discount">
                  <td colspan="3" class="total">{{$t('Discount')}}</td>
                  <td style="text-align:right;" class="total">
                    <!-- If percentage: show percent value AND manual discount amount; else amount only -->
                    <template v-if="String(invoice_pos.sale.discount_Method || '2') === '1'">
                      {{ formatNumber(invoice_pos.sale.discount, 2) }}%
                      ({{ formatPriceWithSymbol(invoice_pos.symbol, manualSaleDiscountAmount ,2) }})
                    </template>
                    <template v-else>
                      {{ formatPriceWithSymbol(invoice_pos.symbol, manualSaleDiscountAmount ,2) }}
                    </template>
                  </td>
                </tr>

                <tr
                  style="margin-top:2px"
                  v-show="pos_settings.show_discount && invoice_pos.sale.discount_from_points && Number(invoice_pos.sale.discount_from_points) > 0"
                >
                  <td colspan="3" class="total">{{$t('Discount_from_Points')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.discount_from_points ,2) }}
                  </td>
                </tr>

                <tr style="margin-top:10px" v-show="pos_settings.show_shipping">
                  <td colspan="3" class="total">{{$t('Shipping')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.shipping ,2) }}
                  </td>
                </tr>

                <tr style="margin-top:10px">
                  <td colspan="3" class="total">{{$t('Total')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.GrandTotal ,2) }}
                  </td>
                </tr>

                <tr v-show="pos_settings.show_paid !== 0">
                  <td colspan="3" class="total">{{$t('Paid')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.paid_amount ,2) }}
                  </td>
                </tr>

                <tr v-show="pos_settings.show_due !== 0">
                  <td colspan="3" class="total">{{$t('Due')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, (invoice_pos.sale.GrandTotal - invoice_pos.sale.paid_amount), 2) }}
                  </td>
                </tr>
              </tbody>
            </table>

            <table
              class="change mt-3"
              style="font-size: 10px;width: 100%;"
              v-show="pos_settings.show_payments !== 0 && invoice_pos.sale.paid_amount > 0"
            >
              <thead>
                <tr style="background: #eee;">
                  <th style="text-align: left;" colspan="1">{{$t('PayeBy')}}:</th>
                  <th style="text-align: center;" colspan="2">{{$t('Amount')}}:</th>
                  <th style="text-align: right;" colspan="1">{{$t('Change')}}:</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="payment_pos in payments">
                  <tr :key="'pay-' + payment_pos.id">
                    <td style="text-align: left;" colspan="1">
                      {{payment_pos.payment_method?payment_pos.payment_method.name:'---'}}
                    </td>
                    <td style="text-align: center;" colspan="2">
                      {{ formatPriceDisplay(payment_pos.montant ,2) }}
                    </td>
                    <td style="text-align: right;" colspan="1">
                      {{ formatPriceDisplay(payment_pos.change ,2) }}
                    </td>
                  </tr>
                  <tr v-if="payment_pos.notes" :key="'pay-note-' + payment_pos.id">
                    <td colspan="4" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                      {{$t('Payment_note')}}: {{payment_pos.notes}}
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <div id="legalcopy" class="ml-2">
              <p v-if="invoice_pos.sale && invoice_pos.sale.notes" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;margin:0;">
                {{$t('sale_note')}}: {{invoice_pos.sale.notes}}
              </p>
              <p class="legal" v-show="pos_settings.show_note">
                <strong>{{pos_settings.note_customer}}</strong>
              </p>
              <!-- Receipt QR codes (ZATCA + Invoice URL) -->
              <div
                v-if="(invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0) || (pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref)"
                class="receipt-qr-row mt-2"
              >
                <div
                  v-if="invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0"
                  class="receipt-qr-block"
                >
                  <div class="receipt-qr-title">ZATCA QR</div>
                  <div class="receipt-qr-canvas" ref="zatcaQrcode"></div>
                </div>
                <div
                  v-if="pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref"
                  class="receipt-qr-block"
                >
                  <div class="receipt-qr-title">Invoice QR</div>
                  <div class="receipt-qr-canvas" ref="invoiceUrlQr"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Layout 2 - Compact -->
          <div v-else-if="currentReceiptLayout === 2">
            <div class="info text-center">
              <div class="invoice_logo mb-1" v-show="pos_settings.show_logo !== 0">
                <img
                  :src="'/images/'+invoice_pos.setting.logo"
                  alt
                  :width="pos_settings.logo_size || 60"
                  :height="pos_settings.logo_size || 60"
                >
              </div>
              <div v-show="pos_settings.show_store_name !== 0">
                {{invoice_pos.setting.CompanyName}}
              </div>
              <small v-show="pos_settings.show_address">
                {{invoice_pos.setting.CompanyAdress}}
              </small>
              <br v-show="pos_settings.show_address">
              <small v-show="pos_settings.show_phone">
                {{invoice_pos.setting.CompanyPhone}}
              </small>
              <br v-show="pos_settings.show_phone">
              <small v-show="pos_settings.show_email">
                {{invoice_pos.setting.email}}
              </small>
              <div class="mt-1">
                <small
                  v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0"
                >
                  {{$t('Reference')}} : {{invoice_pos.sale.Ref}}
                </small>
                <br v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0">
                <small v-show="pos_settings.show_date !== 0">
                  {{$t('date')}} : {{invoice_pos.sale.date}}
                </small>
                <br>
                <small v-show="pos_settings.show_seller !== 0">
                  {{$t('Seller')}} : {{invoice_pos.sale.seller_name}}
                </small>
                <br>
                <small v-show="pos_settings.show_customer">
                  {{$t('Customer')}} : {{invoice_pos.sale.client_name}}
                </small>
                <br>
                <small v-show="pos_settings.show_Warehouse">
                  {{$t('warehouse')}} : {{invoice_pos.sale.warehouse_name}}
                </small>
              </div>
            </div>

            <table class="table_data mt-2" style="width:100%; font-size:11px;">
              <thead>
                <tr>
                  <th style="text-align:left">{{$t('ProductName')}}</th>
                  <th style="text-align:center">{{$t('Quantity')}}</th>
                  <th style="text-align:right">{{$t('Price')}}</th>
                  <th style="text-align:right">{{$t('Total')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="detail_invoice in invoice_pos.details">
                  <td>
                    {{detail_invoice.name}}
                    <br v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null">
                    <small
                      v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null "
                    >
                      {{$t('IMEI_SN')}} : {{detail_invoice.imei_number}}
                    </small>
                  </td>
                  <td style="text-align:center">
                    {{formatNumber(detail_invoice.quantity,2)}} {{detail_invoice.unit_sale}}
                  </td>
                  <td style="text-align:right">
                    {{formatNumber(detail_invoice.total/detail_invoice.quantity,2)}}
                  </td>
                  <td style="text-align:right">
                    {{formatNumber(detail_invoice.total,2)}}
                  </td>
                </tr>
              </tbody>
            </table>

            <table class="table_data mt-2" style="width:100%; font-size:11px;">
              <tbody>
                <tr>
                  <td class="total">{{$t('pos.Subtotal')}}</td>
                  <td style="text-align:right" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoiceSubtotal, 2) }}
                  </td>
                </tr>
                <tr v-show="pos_settings.show_tax">
                  <td class="total">{{$t('OrderTax')}}</td>
                  <td style="text-align:right" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.taxe ,2) }}
                    ({{formatNumber(invoice_pos.sale.tax_rate,2)}} %)
                  </td>
                </tr>
                <tr v-show="pos_settings.show_discount">
                  <td class="total">{{$t('Discount')}}</td>
                  <td style="text-align:right" class="total">
                    <!-- If percentage: show percent value AND manual discount amount; else amount only -->
                    <template v-if="String(invoice_pos.sale.discount_Method || '2') === '1'">
                      {{ formatNumber(invoice_pos.sale.discount, 2) }}%
                      ({{ formatPriceWithSymbol(invoice_pos.symbol, manualSaleDiscountAmount ,2) }})
                    </template>
                    <template v-else>
                      {{ formatPriceWithSymbol(invoice_pos.symbol, manualSaleDiscountAmount ,2) }}
                    </template>
                  </td>
                </tr>
                <tr
                  v-show="pos_settings.show_discount && invoice_pos.sale.discount_from_points && Number(invoice_pos.sale.discount_from_points) > 0"
                >
                  <td class="total">{{$t('Discount_from_Points')}}</td>
                  <td style="text-align:right" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.discount_from_points ,2) }}
                  </td>
                </tr>
                <tr v-show="pos_settings.show_shipping">
                  <td class="total">{{$t('Shipping')}}</td>
                  <td style="text-align:right" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.shipping ,2) }}
                  </td>
                </tr>
                <tr>
                  <td class="total">{{$t('Total')}}</td>
                  <td style="text-align:right" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.GrandTotal ,2) }}
                  </td>
                </tr>
                <tr v-show="pos_settings.show_paid !== 0">
                  <td class="total">{{$t('Paid')}}</td>
                  <td style="text-align:right" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.paid_amount ,2) }}
                  </td>
                </tr>
                <tr v-show="pos_settings.show_due !== 0">
                  <td class="total">{{$t('Due')}}</td>
                  <td style="text-align:right" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, (invoice_pos.sale.GrandTotal - invoice_pos.sale.paid_amount), 2) }}
                  </td>
                </tr>
              </tbody>
            </table>

            <table
              class="change mt-2"
              style="font-size: 10px;width: 100%;"
              v-show="pos_settings.show_payments !== 0 && invoice_pos.sale.paid_amount > 0"
            >
              <thead>
                <tr style="background: #eee;">
                  <th style="text-align: left;" colspan="1">{{$t('PayeBy')}}:</th>
                  <th style="text-align: center;" colspan="2">{{$t('Amount')}}:</th>
                  <th style="text-align: right;" colspan="1">{{$t('Change')}}:</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="payment_pos in payments">
                  <tr :key="'pay2-' + payment_pos.id">
                    <td style="text-align: left;" colspan="1">
                      {{payment_pos.payment_method?payment_pos.payment_method.name:'---'}}
                    </td>
                    <td style="text-align: center;" colspan="2">
                      {{formatNumber(payment_pos.montant ,2)}}
                    </td>
                    <td style="text-align: right;" colspan="1">
                      {{formatNumber(payment_pos.change ,2)}}
                    </td>
                  </tr>
                  <tr v-if="payment_pos.notes" :key="'pay2-note-' + payment_pos.id">
                    <td colspan="4" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                      {{$t('Payment_note')}}: {{payment_pos.notes}}
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <div id="legalcopy" class="ml-2">
              <p v-if="invoice_pos.sale && invoice_pos.sale.notes" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;margin:0;">
                {{$t('sale_note')}}: {{invoice_pos.sale.notes}}
              </p>
              <p class="legal" v-show="pos_settings.show_note">
                <strong>{{pos_settings.note_customer}}</strong>
              </p>
              <!-- Receipt QR codes (ZATCA + Invoice URL) -->
              <div
                v-if="(invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0) || (pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref)"
                class="receipt-qr-row mt-2"
              >
                <div
                  v-if="invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0"
                  class="receipt-qr-block"
                >
                  <div class="receipt-qr-title">ZATCA QR</div>
                  <div class="receipt-qr-canvas" ref="zatcaQrcode"></div>
                </div>
                <div
                  v-if="pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref"
                  class="receipt-qr-block"
                >
                  <div class="receipt-qr-title">Invoice QR</div>
                  <div class="receipt-qr-canvas" ref="invoiceUrlQr"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Layout 3 - Detailed -->
          <div v-else-if="currentReceiptLayout === 3">
            <div class="info mb-2">
              <div class="d-flex justify-content-between">
                <div>
                  <strong v-show="pos_settings.show_store_name !== 0">
                    {{invoice_pos.setting.CompanyName}}
                  </strong>
                  <br>
                  <span v-show="pos_settings.show_address">
                    {{invoice_pos.setting.CompanyAdress}}
                  </span>
                  <br v-show="pos_settings.show_address">
                  <span v-show="pos_settings.show_phone">
                    {{invoice_pos.setting.CompanyPhone}}
                  </span>
                  <br v-show="pos_settings.show_phone">
                  <span v-show="pos_settings.show_email">
                    {{invoice_pos.setting.email}}
                  </span>
                </div>
                <div class="invoice_logo text-center mb-2" v-show="pos_settings.show_logo !== 0">
                  <img
                    :src="'/images/'+invoice_pos.setting.logo"
                    alt
                    :width="pos_settings.logo_size || 60"
                    :height="pos_settings.logo_size || 60"
                  >
                </div>
              </div>
              <div class="mt-2" style="font-size:11px;">
                <div
                  v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0"
                >
                  {{$t('Reference')}} : {{invoice_pos.sale.Ref}}
                </div>
                <div v-show="pos_settings.show_date !== 0">
                  {{$t('date')}} : {{invoice_pos.sale.date}}
                </div>
                <div v-show="pos_settings.show_seller !== 0">
                  {{$t('Seller')}} : {{invoice_pos.sale.seller_name}}
                </div>
                <div v-show="pos_settings.show_customer">
                  {{$t('Customer')}} : {{invoice_pos.sale.client_name}}
                </div>
                <div v-show="pos_settings.show_Warehouse">
                  {{$t('warehouse')}} : {{invoice_pos.sale.warehouse_name}}
                </div>
              </div>
            </div>

            <table class="table_data w-100 mb-2" style="font-size:11px;">
              <tbody>
                <tr v-for="detail_invoice in invoice_pos.details">
                  <td colspan="2">
                    <strong>{{detail_invoice.name}}</strong>
                    <br v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null">
                    <span
                      v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null "
                    >
                      {{$t('IMEI_SN')}} : {{detail_invoice.imei_number}}
                    </span>
                    <br>
                    <small>
                      {{formatNumber(detail_invoice.quantity,2)}} {{detail_invoice.unit_sale}}
                      x
                      {{ formatPriceDisplay(detail_invoice.total/detail_invoice.quantity,2) }}
                    </small>
                  </td>
                  <td style="text-align:right;vertical-align:bottom">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, detail_invoice.total, 2) }}
                  </td>
                </tr>
              </tbody>
            </table>

            <table class="table_data w-100 mt-2" style="font-size:11px;">
              <tbody>
                <tr>
                  <td class="total">{{$t('pos.Subtotal')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoiceSubtotal, 2) }}
                  </td>
                </tr>
                <tr v-show="pos_settings.show_tax">
                  <td class="total">{{$t('OrderTax')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.taxe ,2) }}
                    ({{formatNumber(invoice_pos.sale.tax_rate,2)}} %)
                  </td>
                </tr>
                <tr v-show="pos_settings.show_discount">
                  <td class="total">{{$t('Discount')}}</td>
                  <td style="text-align:right;" class="total">
                    <!-- If percentage: show percent value AND manual discount amount; else amount only -->
                    <template v-if="String(invoice_pos.sale.discount_Method || '2') === '1'">
                      {{ formatNumber(invoice_pos.sale.discount, 2) }}%
                      ({{ formatPriceWithSymbol(invoice_pos.symbol, manualSaleDiscountAmount ,2) }})
                    </template>
                    <template v-else>
                      {{ formatPriceWithSymbol(invoice_pos.symbol, manualSaleDiscountAmount ,2) }}
                    </template>
                  </td>
                </tr>
                <tr
                  v-show="pos_settings.show_discount && invoice_pos.sale.discount_from_points && Number(invoice_pos.sale.discount_from_points) > 0"
                >
                  <td class="total">{{$t('Discount_from_Points')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.discount_from_points ,2) }}
                  </td>
                </tr>
                <tr v-show="pos_settings.show_shipping">
                  <td class="total">{{$t('Shipping')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.shipping ,2) }}
                  </td>
                </tr>
                <tr>
                  <td class="total">{{$t('Total')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.GrandTotal ,2) }}
                  </td>
                </tr>
                <tr v-show="pos_settings.show_paid !== 0">
                  <td class="total">{{$t('Paid')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.paid_amount ,2) }}
                  </td>
                </tr>
                <tr v-show="pos_settings.show_due !== 0">
                  <td class="total">{{$t('Due')}}</td>
                  <td style="text-align:right;" class="total">
                    {{ formatPriceWithSymbol(invoice_pos.symbol, (invoice_pos.sale.GrandTotal - invoice_pos.sale.paid_amount), 2) }}
                  </td>
                </tr>
              </tbody>
            </table>

            <table
              class="change mt-3"
              style="font-size: 10px;width: 100%;"
              v-show="pos_settings.show_payments !== 0 && invoice_pos.sale.paid_amount > 0"
            >
              <thead>
                <tr style="background: #eee;">
                  <th style="text-align: left;" colspan="1">{{$t('PayeBy')}}:</th>
                  <th style="text-align: center;" colspan="2">{{$t('Amount')}}:</th>
                  <th style="text-align: right;" colspan="1">{{$t('Change')}}:</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="payment_pos in payments">
                  <tr :key="'pay3-' + payment_pos.id">
                    <td style="text-align: left;" colspan="1">
                      {{payment_pos.payment_method?payment_pos.payment_method.name:'---'}}
                    </td>
                    <td style="text-align: center;" colspan="2">
                      {{formatNumber(payment_pos.montant ,2)}}
                    </td>
                    <td style="text-align: right;" colspan="1">
                      {{formatNumber(payment_pos.change ,2)}}
                    </td>
                  </tr>
                  <tr v-if="payment_pos.notes" :key="'pay3-note-' + payment_pos.id">
                    <td colspan="4" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                      {{$t('Payment_note')}}: {{payment_pos.notes}}
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <div id="legalcopy" class="ml-2">
              <p v-if="invoice_pos.sale && invoice_pos.sale.notes" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;margin:0;">
                {{$t('sale_note')}}: {{invoice_pos.sale.notes}}
              </p>
              <p class="legal" v-show="pos_settings.show_note">
                <strong>{{pos_settings.note_customer}}</strong>
              </p>
              <!-- Receipt QR codes (ZATCA + Invoice URL) -->
              <div
                v-if="(invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0) || (pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref)"
                class="receipt-qr-row mt-2"
              >
                <div
                  v-if="invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0"
                  class="receipt-qr-block"
                >
                  <div class="receipt-qr-title">ZATCA QR</div>
                  <div class="receipt-qr-canvas" ref="zatcaQrcode"></div>
                </div>
                <div
                  v-if="pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref"
                  class="receipt-qr-block"
                >
                  <div class="receipt-qr-title">Invoice QR</div>
                  <div class="receipt-qr-canvas" ref="invoiceUrlQr"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Layout 4 - Bilingual (Arabic + English) -->
          <div v-else class="receipt-layout-4">
            <div class="info text-center">
              <div class="invoice_logo mb-2" v-show="pos_settings.show_logo !== 0">
                <img :src="'/images/'+invoice_pos.setting.logo" alt :width="pos_settings.logo_size || 60" :height="pos_settings.logo_size || 60">
              </div>
              <div>
                <strong style="font-size:13px;">{{invoice_pos.setting.company_name_ar}}</strong><br>
                <strong style="font-size:12px;">{{invoice_pos.setting.CompanyName}}</strong>
              </div>
              <div v-if="invoice_pos.setting.CompanyAdress" style="font-size:10px;margin-top:2px;">{{invoice_pos.setting.CompanyAdress}}</div>
              <div v-if="invoice_pos.setting.CompanyPhone" style="font-size:10px;">{{invoice_pos.setting.CompanyPhone}}</div>
              <div v-if="invoice_pos.setting.email" v-show="pos_settings.show_email" style="font-size:10px;">{{invoice_pos.setting.email}}</div>
              <div v-if="invoice_pos.setting.vat_number" style="font-size:11px;font-weight:bold;margin-top:4px;">
                الرقم الضريبي / TRN : {{invoice_pos.setting.vat_number}}
              </div>
              <div class="mt-2 mb-2" style="border-top:1px dashed #000;border-bottom:1px dashed #000;padding:4px 0;">
                <strong>فاتورة ضريبية مبسطة</strong><br>
                <strong>Simplified Tax Invoice</strong>
              </div>
            </div>

            <div style="font-size:10px;">
              <div v-if="invoice_pos.sale && invoice_pos.sale.Ref && pos_settings.show_reference !== 0" style="display:flex;justify-content:space-between;">
                <span>Invoice No</span>
                <span>{{invoice_pos.sale.Ref}}</span>
                <span>رقم الفاتورة</span>
              </div>
              <div v-show="pos_settings.show_date !== 0" style="display:flex;justify-content:space-between;">
                <span>Date</span>
                <span>{{invoice_pos.sale.date}}</span>
                <span>تاريخ</span>
              </div>
              <div v-show="pos_settings.show_seller !== 0" style="display:flex;justify-content:space-between;">
                <span>Seller</span>
                <span>{{invoice_pos.sale.seller_name}}</span>
                <span>البائع</span>
              </div>
              <div v-show="pos_settings.show_customer" style="display:flex;justify-content:space-between;">
                <span>Customer</span>
                <span>{{invoice_pos.sale.client_name}}</span>
                <span>العميل</span>
              </div>
              <div v-show="pos_settings.show_Warehouse" style="display:flex;justify-content:space-between;">
                <span>Warehouse</span>
                <span>{{invoice_pos.sale.warehouse_name}}</span>
                <span>المستودع</span>
              </div>
            </div>

            <table style="width:100%;margin-top:8px;font-size:10px;border-top:1px dashed #000;">
              <thead>
                <tr>
                  <th style="text-align:left;padding:4px 0;">Product<br>المنتج</th>
                  <th style="text-align:center;padding:4px 0;">Qty<br>كمية</th>
                  <th style="text-align:center;padding:4px 0;">Rate<br>معدل</th>
                  <th style="text-align:right;padding:4px 0;">Amount<br>مجموع</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="detail_invoice in invoice_pos.details" style="border-bottom:1px dashed #eee;">
                  <td>
                    {{detail_invoice.name}}
                    <br v-if="Number(detail_invoice.tax_percent || detail_invoice.tax_rate || 0) > 0">
                    <small v-if="Number(detail_invoice.tax_percent || detail_invoice.tax_rate || 0) > 0">VAT @ {{ formatNumber(Number(detail_invoice.tax_percent || detail_invoice.tax_rate || 0),2) }}% ({{ formatPriceDisplay(detail_invoice.total * Number(detail_invoice.tax_percent || detail_invoice.tax_rate || 0) / 100, 2) }})</small>
                    <br v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null">
                    <span v-show="detail_invoice.is_imei && detail_invoice.imei_number !==null ">IMEI/SN الرقم التسلسلي : {{detail_invoice.imei_number}}</span>
                  </td>
                  <td style="text-align:center">{{formatNumber(detail_invoice.quantity,2)}} {{detail_invoice.unit_sale}}</td>
                  <td style="text-align:center">{{ formatPriceDisplay(detail_invoice.total/detail_invoice.quantity,2) }}</td>
                  <td style="text-align:right">{{ formatPriceDisplay(detail_invoice.total,2) }}</td>
                </tr>
              </tbody>
            </table>

            <table style="width:100%;font-size:10px;border-top:1px dashed #000;margin-top:4px;">
              <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
              <tbody>
                <tr>
                  <td style="text-align:left" class="total">Sub Total</td>
                  <td class="total">:</td>
                  <td style="text-align:center" class="total">{{ formatPriceWithSymbol(invoice_pos.symbol, invoiceSubtotal - invoiceDetailsTaxTotal, 2) }}</td>
                  <td style="text-align:right" class="total">المجموع الفرعي</td>
                </tr>
                <tr v-show="pos_settings.show_discount">
                  <td style="text-align:left" class="total">Discount</td>
                  <td class="total">:</td>
                  <td style="text-align:center" class="total">
                    <template v-if="String(invoice_pos.sale.discount_Method || '2') === '1'">
                      {{ formatNumber(invoice_pos.sale.discount, 2) }}% ({{ formatPriceWithSymbol(invoice_pos.symbol, manualSaleDiscountAmount ,2) }})
                    </template>
                    <template v-else>
                      {{ formatPriceWithSymbol(invoice_pos.symbol, manualSaleDiscountAmount ,2) }}
                    </template>
                  </td>
                  <td style="text-align:right" class="total">تخفيض</td>
                </tr>
                <tr v-show="pos_settings.show_discount && invoice_pos.sale.discount_from_points && Number(invoice_pos.sale.discount_from_points) > 0">
                  <td style="text-align:left" class="total">Discount from Points</td>
                  <td class="total">:</td>
                  <td style="text-align:center" class="total">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.discount_from_points ,2) }}</td>
                  <td style="text-align:right" class="total">خصم من النقاط</td>
                </tr>
                <tr v-show="pos_settings.show_tax && Number(invoice_pos.sale.taxe || 0) > 0">
                  <td style="text-align:left" class="total">VAT @ Total</td>
                  <td class="total">:</td>
                  <td style="text-align:center" class="total">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.taxe ,2) }}</td>
                  <td style="text-align:right" class="total">قيمة الضريبة</td>
                </tr>
                <tr v-show="pos_settings.show_shipping">
                  <td style="text-align:left" class="total">Shipping</td>
                  <td class="total">:</td>
                  <td style="text-align:center" class="total">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.shipping ,2) }}</td>
                  <td style="text-align:right" class="total">الشحن</td>
                </tr>
              </tbody>
            </table>

            <table style="width:100%;font-size:10px;font-weight:bold;border-top:1px dashed #000;border-bottom:1px dashed #000;margin-top:4px;padding:4px 0;">
              <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
              <tbody>
                <tr>
                  <td style="text-align:left">Grand Total</td>
                  <td>:</td>
                  <td style="text-align:center">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.GrandTotal ,2) }}</td>
                  <td style="text-align:right">المبلغ الإجمالي</td>
                </tr>
              </tbody>
            </table>

            <table style="width:100%;font-size:10px;margin-top:4px;">
              <colgroup><col style="width:35%"><col style="width:5%"><col style="width:25%"><col style="width:35%"></colgroup>
              <tbody>
                <tr v-show="pos_settings.show_paid !== 0">
                  <td style="text-align:left"><strong>Paid Amount</strong></td>
                  <td><strong>:</strong></td>
                  <td style="text-align:center">{{ formatPriceWithSymbol(invoice_pos.symbol, invoice_pos.sale.paid_amount ,2) }}</td>
                  <td style="text-align:right"><strong>المبلغ المدفوع</strong></td>
                </tr>
                <tr v-show="pos_settings.show_due !== 0">
                  <td style="text-align:left"><strong>Balance</strong></td>
                  <td><strong>:</strong></td>
                  <td style="text-align:center">{{ formatPriceWithSymbol(invoice_pos.symbol, (invoice_pos.sale.GrandTotal - invoice_pos.sale.paid_amount), 2) }}</td>
                  <td style="text-align:right"><strong>الرصيد</strong></td>
                </tr>
              </tbody>
            </table>

            <table class="change mt-3" style="font-size:10px;width:100%;" v-show="pos_settings.show_payments !== 0 && invoice_pos.sale.paid_amount > 0">
              <thead>
                <tr style="background:#eee;">
                  <th style="text-align:left;" colspan="1">Paid By / طريقة الدفع:</th>
                  <th style="text-align:center;" colspan="2">Amount / المبلغ:</th>
                  <th style="text-align:right;" colspan="1">Change / الباقي:</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="payment_pos in payments">
                  <tr :key="'pay4-' + payment_pos.id">
                    <td style="text-align:left;" colspan="1">{{payment_pos.payment_method?payment_pos.payment_method.name:'---'}}</td>
                    <td style="text-align:center;" colspan="2">{{ formatPriceDisplay(payment_pos.montant ,2) }}</td>
                    <td style="text-align:right;" colspan="1">{{ formatPriceDisplay(payment_pos.change ,2) }}</td>
                  </tr>
                  <tr v-if="payment_pos.notes" :key="'pay4-note-' + payment_pos.id">
                    <td colspan="4" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                      {{$t('Payment_note')}} / ملاحظة الدفع: {{payment_pos.notes}}
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <div id="legalcopy" class="ml-2">
              <div v-if="invoice_pos.sale && invoice_pos.sale.notes" style="font-size:9px;font-style:italic;padding-bottom:4px;white-space:pre-line;">
                {{$t('sale_note')}} / ملاحظة البيع: {{invoice_pos.sale.notes}}
              </div>
              <div v-show="pos_settings.show_note && pos_settings.note_customer" class="mt-3" style="border-top:1px dashed #000;padding-top:6px;font-size:9px;line-height:1.5;text-align:center;white-space:pre-line;">
                <strong>{{pos_settings.note_customer}}</strong>
              </div>

              <!-- Receipt QR codes (ZATCA + Invoice URL) -->
              <div
                v-if="(invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0) || (pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref)"
                class="receipt-qr-row mt-2"
              >
                <div
                  v-if="invoice_pos.setting && invoice_pos.setting.zatca_enabled && invoice_pos.zatca_qr && pos_settings.show_zatca_qr !== 0"
                  class="receipt-qr-block"
                >
                  <div class="receipt-qr-title">ZATCA QR</div>
                  <div class="receipt-qr-canvas" ref="zatcaQrcode"></div>
                </div>
                <div
                  v-if="pos_settings.show_barcode !== 0 && invoice_pos.sale && invoice_pos.sale.Ref"
                  class="receipt-qr-block"
                >
                  <div class="receipt-qr-title">Invoice QR</div>
                  <div class="receipt-qr-canvas" ref="invoiceUrlQr"></div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
      <button @click="print_it()" class="btn btn-outline-primary mt-3">
        <lucide-icon name="receipt" />
        {{$t('print')}}
      </button>
    </b-modal>

    <!-- Modal Manage Documents -->
    <b-modal
      hide-footer
      size="lg"
      id="Manage_Documents"
      :title="$t('Attach_Documents')"
    >
      <b-row>
        <!-- Upload Section -->
        <b-col lg="12" md="12" sm="12" class="mb-3">
          <b-form-group :label="$t('Upload_Documents')">
            <b-form-file
              v-model="selectedFiles"
              :placeholder="$t('Choose_files_or_drop_them_here')"
              :drop-placeholder="$t('Drop_files_here')"
              multiple
              accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif"
              @change="onFileChange"
            ></b-form-file>
          </b-form-group>
          <b-button
            variant="primary"
            size="sm"
            @click="Upload_Documents"
            :disabled="!selectedFiles || selectedFiles.length === 0 || uploadProcessing"
          >
            <lucide-icon name="upload" /> {{$t('Upload')}}
          </b-button>
          <div v-if="uploadProcessing" class="mt-2">
            <div class="spinner sm spinner-primary"></div>
          </div>
        </b-col>

        <!-- Documents List -->
        <b-col lg="12" md="12" sm="12">
          <h5>{{$t('Attached_Documents')}}</h5>
          <div class="table-responsive">
            <table class="table table-hover table-bordered table-sm">
              <thead>
                <tr>
                  <th scope="col">{{$t('File_Name')}}</th>
                  <th scope="col">{{$t('Size')}}</th>
                  <th scope="col">{{$t('Uploaded_Date')}}</th>
                  <th scope="col">{{$t('Action')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="documents.length <= 0">
                  <td colspan="4" class="text-center">{{$t('NodataAvailable')}}</td>
                </tr>
                <tr v-for="document in documents" :key="document.id">
                  <td>
                    <lucide-icon class="mr-1" name="file" />
                    {{document.name}}
                  </td>
                  <td>{{formatFileSize(document.size)}}</td>
                  <td>{{formatDateTime(document.created_at)}}</td>
                  <td>
                    <div role="group" aria-label="Document actions" class="btn-group">
                      <button
                        title="Download"
                        class="btn btn-icon btn-success btn-sm"
                        @click="Download_Document(document)"
                      >
                        <lucide-icon name="download" />
                      </button>
                      <button
                        title="Delete"
                        class="btn btn-icon btn-danger btn-sm"
                        @click="Remove_Document(document.id)"
                      >
                        <lucide-icon name="x" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </b-col>
      </b-row>
    </b-modal>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import vueEasyPrint from "vue-easy-print";
import VueBarcode from "vue-barcode";
import Util from "../../../../utils";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";
export default {
  components: {
    vueEasyPrint,
    barcode: VueBarcode
  },
  metaInfo: {
    title: "Sales"
  },
  data() {
    return {
      pos_settings:{},
      paymentProcessing: false,
      Submit_Processing_shipment:false,

      

      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      search: "",
      totalRows: "",
      barcodeFormat: "CODE128",
      showDropdown: false,
      EditPaiementMode: false,
      Filter_Client: "",
      Filter_Ref: "",
      Filter_date: "",
      Filter_status: "",
      Filter_Payment: "",
      Filter_warehouse: "",
      Filter_shipping:"",
      customers: [],
      warehouses: [],
      payment_methods: [],
      shipment: {},
      sales: [],
      sale_due:'',
      due:0,
      client_name:'',
      invoice_pos: {
        sale: {
          Ref: "",
          client_name: "",
          warehouse_name: "",
          discount: "",
          taxe: "",
          tax_rate: "",
          shipping: "",
          GrandTotal: "",
          paid_amount:'',
        },
        details: [],
        setting: {
          logo: "",
          CompanyName: "",
          CompanyAdress: "",
          email: "",
          CompanyPhone: "",
          vat_number: "",
          company_name_ar: "",
          zatca_enabled: false
        },
        zatca_qr: ""
      },
      public_invoice_url: '',
      accounts: [],
      payments: [],
      payment: {},
      zatcaRendered: false,
      Sale_id: "",
      limit: "10",
      sale: {},
      email: {
        to: "",
        subject: "",
        message: "",
        client_name: "",
        Sale_Ref: ""
      },
      emailPayment: {
        id: "",
        to: "",
        subject: "",
        message: "",
        client_name: "",
        Ref: ""
      },
      documents: [],
      selectedFiles: [],
      uploadProcessing: false,
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null,
      currentSaleId: null
    };
  },
   mounted() {
    this.$root.$on("bv::dropdown::show", bvEvent => {
      this.showDropdown = true;
    });
    this.$root.$on("bv::dropdown::hide", bvEvent => {
      this.showDropdown = false;
    });
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),

    // Sum of per-product VAT (total * tax_percent / 100)
    invoiceDetailsTaxTotal() {
      const details = (this.invoice_pos && Array.isArray(this.invoice_pos.details)) ? this.invoice_pos.details : [];
      return details.reduce((sum, d) => {
        const total = Number(d.total || 0);
        const rate = Number(d.tax_percent || d.tax_rate || 0);
        return sum + (total * rate / 100);
      }, 0);
    },

    // Signed public URL for barcode so scanning opens the invoice (no login required)
    invoiceBarcodeUrl() {
      return this.public_invoice_url || '';
    },

    // Normalize POS receipt layout selection (1, 2, 3, or 4)
    currentReceiptLayout() {
      const raw = this.pos_settings && this.pos_settings.receipt_layout != null
        ? this.pos_settings.receipt_layout
        : 1;
      const n = Number(raw) || 1;
      return [1, 2, 3, 4].includes(n) ? n : 1;
    },

    // Calculate order-level discount amount for invoice display based on discount_Method
    // Manual discount amount only (excluding discount from points)
    manualSaleDiscountAmount() {
      try {
        const sale = (this.invoice_pos && this.invoice_pos.sale) ? this.invoice_pos.sale : {};
        const discMethod = String(sale.discount_Method || '2');
        const discVal = Number(sale.discount || 0);
        const taxNet = Number(sale.taxe || sale.TaxNet || 0);
        const shipping = Number(sale.shipping || 0);
        const grand = Number(sale.GrandTotal || 0);

        // Reconstruct subtotal before discount: subtotal = GrandTotal - shipping - TaxNet
        const subtotal = grand - shipping - taxNet;
        if (!Number.isFinite(subtotal) || subtotal <= 0) {
          return 0;
        }

        if (discMethod === '1') {
          // Percentage discount: use subtotal * %
          return parseFloat((subtotal * (discVal / 100)).toFixed(2));
        }
        // Fixed discount
        return parseFloat(Math.min(discVal, subtotal).toFixed(2));
      } catch (e) {
        return 0;
      }
    },

    // Total discount amount (manual + points) – kept for compatibility if needed elsewhere
    saleDiscountAmount() {
      try {
        const sale = (this.invoice_pos && this.invoice_pos.sale) ? this.invoice_pos.sale : {};
        const discMethod = String(sale.discount_Method || '2');
        const discVal = Number(sale.discount || 0);
        const taxNet = Number(sale.taxe || sale.TaxNet || 0);
        const shipping = Number(sale.shipping || 0);
        const grand = Number(sale.GrandTotal || 0);

        // Reconstruct subtotal before discount: subtotal = GrandTotal - shipping - TaxNet
        const subtotal = grand - shipping - taxNet;
        if (!Number.isFinite(subtotal) || subtotal <= 0) {
          return 0;
        }

        if (discMethod === '1') {
          // Percentage discount: use subtotal * %
          return parseFloat((subtotal * (discVal / 100)).toFixed(2));
        }
        // Fixed discount
        return parseFloat(Math.min(discVal, subtotal).toFixed(2));
      } catch (e) {
        return 0;
      }
    },

    // Receipt subtotal (sum of invoice detail totals; before order tax/discount/shipping)
    invoiceSubtotal() {
      try {
        const details = (this.invoice_pos && Array.isArray(this.invoice_pos.details)) ? this.invoice_pos.details : [];
        return details.reduce((sum, d) => {
          const n = Number(d && d.total != null ? d.total : 0);
          return sum + (Number.isFinite(n) ? n : 0);
        }, 0);
      } catch (e) {
        return 0;
      }
    },


    columns() {
      return [
        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Created_by"),
          field: "created_by",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Status"),
          field: "statut",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Total"),
          field: "GrandTotal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Paid"),
          field: "paid_amount",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Due"),
          field: "due",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("PaymentStatus"),
          field: "payment_status",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Shipping_status"),
          field: "shipping_status",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Documents"),
          field: "documents",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }
  },
  watch: {
    'invoice_pos.zatca_qr'(val){
      if(val){
        this.$nextTick(() => { this.renderZatcaQr(); this.renderInvoiceUrlQr(); });
      }
    }
  },
  methods: {

  
    //------------------------------ Print -------------------------\\
    // Gated on awaitQrReady() — the popup gets a clone of #invoice-POS via
    // innerHTML, and a <canvas>'s pixel data does not survive that clone.
    // Only the data-URL <img> created by ensureQrImg() does, so we must
    // wait for that conversion before snapshotting (otherwise the printed
    // QR is blank, especially on slow mobile networks where the qrcodejs
    // CDN takes longer to load).
    print_it() {
      this.awaitQrReady().then(() => {
        var el = document.getElementById("invoice-POS");
        if (!el) return;
        var divContents = el.innerHTML;
        var a = window.open("", "", "height=500, width=500");
        if (!a) return;
        a.document.write(
          '<html><head><link rel="stylesheet" href="/css/pos_print.css"></head>'
        );
        // Wrap in #invoice-POS so all the receipt CSS in pos_print.css
        // (which is scoped under that id, including the .receipt-qr-row
        // QR layout rules) actually applies to the printed content.
        a.document.write('<body><div id="invoice-POS">');
        a.document.write(divContents);
        a.document.write("</div></body></html>");
        a.document.close();

        setTimeout(() => {
          a.print();
        }, 300);
      });
    },


    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Sales(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Sales(1);
      }
    },

    //---- Event Select Rows
    selectionChanged({ selectedRows }) {
      this.selectedIds = [];
      selectedRows.forEach((row, index) => {
        this.selectedIds.push(row.id);
      });
    },

    //---- Event Sort change
    onSortChange(params) {
      let field = "";
      if (params[0].field == "client_name") {
        field = "client_id";
      } else if (params[0].field == "warehouse_name") {
        field = "warehouse_id";
      }else if (params[0].field == "created_by") {
        field = "user_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Get_Sales(this.serverParams.page);
    },

    
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Sales(this.serverParams.page);
    },

     //---------- keyup paid Amount

    Verified_paidAmount() {
      if (isNaN(this.payment.montant)) {
        this.payment.montant = 0;
      } else if (this.payment.montant > this.payment.received_amount) {
        this.makeToast(
          "warning",
          this.$t("Paying_amount_is_greater_than_Received_amount"),
          this.$t("Warning")
        );
        this.payment.montant = 0;
      } 
      else if (this.payment.montant > this.due) {
        this.makeToast(
          "warning",
          this.$t("Paying_amount_is_greater_than_Grand_Total"),
          this.$t("Warning")
        );
        this.payment.montant = 0;
      }
    },

    //---------- keyup Received Amount

    Verified_Received_Amount() {
      if (isNaN(this.payment.received_amount)) {
        this.payment.received_amount = 0;
      } 
    },


    //------ Validate Form Submit_Payment
    Submit_Payment() {
      this.$refs.Add_payment.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else if (this.payment.montant > this.payment.received_amount) {
          this.makeToast(
            "warning",
            this.$t("Paying_amount_is_greater_than_Received_amount"),
            this.$t("Warning")
          );
          this.payment.received_amount = 0;
        }
        else if (this.payment.montant > this.due) {
          this.makeToast(
            "warning",
            this.$t("Paying_amount_is_greater_than_Grand_Total"),
            this.$t("Warning")
          );
          this.payment.montant = 0;

        }else if (!this.EditPaiementMode) {
            this.Create_Payment();
        } else {
            this.Update_Payment();
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
    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_Client = "";
      this.Filter_status = "";
      this.Filter_Payment = "";
      this.Filter_shipping = "";
      this.Filter_Ref = "";
      this.Filter_date = "";
      this.Filter_warehouse = "";
      this.Get_Sales(this.serverParams.page);
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

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing formatNumber helper to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 0;
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(number, decimals, effectiveKey);
      } catch (e) {
        return this.formatNumber(number, dec);
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    //----------------------------------------- Format File Size -------------------------------\\
    formatFileSize(bytes) {
      if (bytes === 0 || bytes === null || bytes === undefined) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    },

    //----------------------------------------- Format Date Time -------------------------------\\
    formatDateTime(value) {
      if (!value) return '';
      const d = new Date(value);
      if (isNaN(d.getTime())) return value;

      const pad = n => (n < 10 ? '0' + n : n);
      const year = d.getFullYear();
      const month = pad(d.getMonth() + 1);
      const day = pad(d.getDate());
      const hours = pad(d.getHours());
      const minutes = pad(d.getMinutes());

      return `${year}-${month}-${day} ${hours}:${minutes}`;
    },
    //----------------------------------------- Format Display Date (for tables) -------------------------------\\
    formatDisplayDate(value) {
      if (!value) return '';
      // Get date format from Vuex store (loaded from database) or fallback
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
      return Util.formatDisplayDate(value, dateFormat);
    },

    //----------------------------------- Sales PDF ------------------------------\\
    
    Sales_PDF() {
      const pdf = new jsPDF('p','pt');
      const fontPath = '/fonts/Vazirmatn-Bold.ttf';
      try { 
        pdf.addFont(fontPath,'Vazirmatn','normal'); 
        pdf.addFont(fontPath,'Vazirmatn','bold'); 
      } catch(e){}
      pdf.setFont('Vazirmatn','normal');

      const headers = [ 
        this.$t('Reference'), 
        this.$t('Customer'), 
        this.$t('warehouse'), 
        this.$t('Status'), 
        this.$t('Total'), 
        this.$t('Paid'), 
        this.$t('Due'), 
        this.$t('PaymentStatus') 
      ];
      
      const body = (this.sales||[]).map(r => [ 
        r.Ref, 
        r.client_name, 
        r.warehouse_name, 
        r.statut, 
        r.GrandTotal, 
        r.paid_amount, 
        r.due, 
        r.payment_status 
      ]);

      const totals = (this.sales||[]).reduce((a,r) => ({
        t: a.t + parseFloat(r.GrandTotal||0),
        p: a.p + parseFloat(r.paid_amount||0),
        d: a.d + parseFloat(r.due||0)
      }), {t:0,p:0,d:0});
      
      const foot = [[ 
        this.$t('Total'), 
        '', 
        '', 
        '', 
        totals.t.toFixed(2), 
        totals.p.toFixed(2), 
        totals.d.toFixed(2), 
        '' 
      ]];

      const marginX = 40;
      const rtl = (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) || 
                  (typeof document!=='undefined' && document.documentElement.dir==='rtl');

      autoTable(pdf, {
        head: [headers], 
        body, 
        foot: foot, 
        startY: 110, 
        theme: 'striped', 
        margin: { left: marginX, right: marginX },
        styles: { 
          font: 'Vazirmatn', 
          fontSize: 9, 
          cellPadding: 4, 
          halign: rtl ? 'right' : 'left', 
          textColor: 33 
        },
        headStyles: { 
          font: 'Vazirmatn', 
          fontStyle: 'bold', 
          fillColor: [63,81,181], 
          textColor: 255 
        },
        alternateRowStyles: { 
          fillColor: [245,247,250] 
        },
        footStyles: { 
          font: 'Vazirmatn', 
          fontStyle: 'bold', 
          fillColor: [63,81,181], 
          textColor: 255 
        },
        columnStyles: { 
          0: { halign: rtl ? 'right' : 'left' },  // Reference
          1: { halign: rtl ? 'right' : 'left' },  // Customer
          2: { halign: rtl ? 'right' : 'left' },  // Warehouse
          3: { halign: rtl ? 'right' : 'left' },  // Status
          4: { halign: 'left' },                  // Total
          5: { halign: 'left' },                  // Paid
          6: { halign: 'left' },                  // Due
          7: { halign: rtl ? 'right' : 'left' }   // Payment Status
        },
        didDrawPage: (d) => {
          const pageW = pdf.internal.pageSize.getWidth();
          const pageH = pdf.internal.pageSize.getHeight();
          
          // Header banner
          pdf.setFillColor(63,81,181);
          pdf.rect(0, 0, pageW, 60, 'F');
          
          // Title
          pdf.setTextColor(255);
          pdf.setFont('Vazirmatn', 'bold');
          pdf.setFontSize(16);
          const title = this.$t('ListSales') || 'Sales List';
          rtl ? pdf.text(title, pageW - marginX, 38, { align: 'right' }) 
              : pdf.text(title, marginX, 38);
          
          // Reset text color
          pdf.setTextColor(33);
          
          // Footer page numbers
          pdf.setFontSize(8);
          const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
          rtl ? pdf.text(pn, marginX, pageH - 14, { align: 'left' }) 
              : pdf.text(pn, pageW - marginX, pageH - 14, { align: 'right' });
        }
      });

      pdf.save('Sales_List.pdf');
    },


    //-------------------------------- Invoice POS ------------------------------\\
    Invoice_POS(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("sales_print_invoice/" + id)
        .then(response => {
          this.invoice_pos = response.data;
          this.payments = response.data.payments;
          this.pos_settings = response.data.pos_settings;
          this.public_invoice_url = response.data.public_invoice_url || '';
          this.zatcaRendered = false;
          // Auto-print is gated on awaitQrReady() so the popup snapshot is
          // taken AFTER canvas → <img> conversion — otherwise the canvas
          // pixels are lost on innerHTML clone and the QR prints blank.
          const autoPrintable = !!(response.data.pos_settings && response.data.pos_settings.is_printable);
          setTimeout(() => {
            // Complete the animation of the  progress bar.
            NProgress.done();
            this.$bvModal.show("Show_invoice");
            this.$nextTick(() => {
              const qrReady = this.awaitQrReady();
              if (autoPrintable) {
                qrReady.then(() => { try { this.print_it(); } catch (e) {} });
              }
            });
          }, 500);

        })
        .catch(() => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },

    //---------------------------------- Get_pos_Settings ----------------\\
    get_pos_Settings() {
      axios
        .get("get_pos_Settings")
        .then(response => {
          if (response.data && response.data.pos_settings) {
            this.pos_settings = response.data.pos_settings;
          }
        })
        .catch(error => {
          // Silently fail if settings can't be loaded
        });
    },

    onInvoiceModalShown() {
      try { this.renderZatcaQr(); } catch (e) {}
      try { this.renderInvoiceUrlQr(); } catch (e) {}
    },

    // Loads the qrcodejs library exactly once across all callers.
    // Returns a Promise that resolves when window.QRCode is available
    // (or all sources have been exhausted). Cached on the instance so
    // overlapping render calls share one in-flight script load.
    // Sanity-check window.QRCode by encoding a non-trivial string and
    // verifying the canvas actually has dark pixels. Detects the broken
    // minimal build previously shipped at /vendor/qrcode/qrcode.min.js
    // (its mapData was missing bit-index increments → all-white canvas).
    _qrLibIsWorking() {
      try {
        if (!window.QRCode) return false;
        if (this._qrLibVerified) return true; // memoized per instance
        const probe = document.createElement('div');
        probe.style.cssText = 'position:absolute;left:-9999px;top:-9999px;';
        document.body.appendChild(probe);
        try {
          new window.QRCode(probe, {
            text: 'https://example.com/test/abcdefghij',
            width: 32, height: 32,
            correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.L : 1
          });
          const cv = probe.querySelector('canvas');
          if (!cv) return false;
          const data = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height).data;
          let darkPixels = 0;
          for (let i = 0; i < data.length; i += 4) {
            if (data[i] < 128 && data[i + 1] < 128 && data[i + 2] < 128) {
              if (++darkPixels > 16) break;
            }
          }
          this._qrLibVerified = darkPixels > 16;
          return this._qrLibVerified;
        } finally {
          try { document.body.removeChild(probe); } catch (e) {}
        }
      } catch (e) { return false; }
    },

    loadQRCodeLib() {
      if (window.QRCode && this._qrLibIsWorking()) return Promise.resolve();
      // window.QRCode may be the old broken local lib cached from a
      // previous page visit — discard it so a known-good source replaces it.
      if (window.QRCode) {
        try { delete window.QRCode; } catch (e) { window.QRCode = undefined; }
        this._qrLibVerified = false;
      }
      if (this._qrLoaderPromise) return this._qrLoaderPromise;
      const tryLoad = (src, next) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = () => { if (window.QRCode) next(true); else next(false); };
        s.onerror = () => next(false);
        document.head.appendChild(s);
      };
      // The local /vendor/qrcode/qrcode.min.js previously shipped a broken
      // minimal build (missing bit-index increments in mapData → blank
      // canvas). We replaced it with the full davidshimjs/qrcodejs
      // library. The cache-buster forces mobile browsers to fetch the new
      // version instead of the cached broken one.
      const localQrUrl = '/vendor/qrcode/qrcode.min.js?v=full-2';
      // After each load attempt, validate the library actually produces QR
      // pixels — otherwise fall through to the next source. Catches a
      // corrupted CDN response or any future broken minimal build.
      const validateOrFalse = () => {
        if (!window.QRCode) return false;
        this._qrLibVerified = false;
        return this._qrLibIsWorking();
      };
      const discardBroken = () => {
        try { delete window.QRCode; } catch (e) { window.QRCode = undefined; }
        this._qrLibVerified = false;
      };
      this._qrLoaderPromise = new Promise((resolve) => {
        tryLoad('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', (ok) => {
          if (ok && validateOrFalse()) return resolve();
          if (window.QRCode) discardBroken();
          tryLoad(localQrUrl, (ok2) => {
            if (ok2 && validateOrFalse()) return resolve();
            if (window.QRCode) discardBroken();
            tryLoad('/assets_setup/js/qrcode.js', () => resolve());
          });
        });
      }).then(() => {
        // Allow a future retry if no working library was ever obtained.
        if (!window.QRCode) this._qrLoaderPromise = null;
      });
      return this._qrLoaderPromise;
    },

    // Render public invoice URL as QR code (replaces barcode).
    // Returns a Promise that resolves when the <img> data URL is in the DOM
    // (or when there is nothing to render). Callers that intend to clone
    // #invoice-POS via innerHTML for printing must await this — canvas pixel
    // data is lost on innerHTML clone, only the <img> survives.
    renderInvoiceUrlQr() {
      return new Promise((resolve) => {
        try {
          if (this.pos_settings && Number(this.pos_settings.show_barcode) === 0) return resolve();
          if (!this.invoice_pos || !this.invoice_pos.sale || !this.invoice_pos.sale.Ref) return resolve();
          if (!this.$refs.invoiceUrlQr) return resolve();
          const text = String(this.invoiceBarcodeUrl || this.invoice_pos.sale.Ref || '');
          if (!text) return resolve();

          // Per-mount render token: each call wins, older pending draws bail.
          this._invoiceQrToken = (this._invoiceQrToken || 0) + 1;
          const myToken = this._invoiceQrToken;

          this.loadQRCodeLib().then(() => {
            if (myToken !== this._invoiceQrToken) return resolve();
            if (!window.QRCode) return resolve();
            const m = this.$refs.invoiceUrlQr;
            if (!m) return resolve();
            m.innerHTML = '';
            try { m.setAttribute('title', text); } catch (e) {}
            try {
              new window.QRCode(m, {
                text,
                width: 140,
                height: 140,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.L : undefined
              });
            } catch (e1) {
              try { new window.QRCode(m, text); } catch (e2) {}
            }
            setTimeout(() => {
              if (myToken !== this._invoiceQrToken) return resolve();
              try { this.ensureQrImg(m); } catch (e) {}
              resolve();
            }, 150);
          }).catch(() => resolve());
        } catch (e) { resolve(); }
      });
    },

    // Ensure the QR mount contains an <img> (data URL) — qrcode.js' CDN build
    // emits one already, but the local minimal build only emits a <canvas>,
    // and a <canvas>'s pixel data is lost when the receipt is cloned via
    // innerHTML into the print popup. The <img> survives that clone.
    ensureQrImg(mount) {
      if (!mount) return;
      let img = null;
      try { img = mount.querySelector('img'); } catch (e) {}
      if (!img) {
        let canvas = null;
        try { canvas = mount.querySelector('canvas'); } catch (e) {}
        if (canvas) {
          try {
            const dataUrl = canvas.toDataURL('image/png');
            img = document.createElement('img');
            img.src = dataUrl;
            img.alt = 'QR';
            mount.appendChild(img);
          } catch (e) {}
        }
      }
      if (img) {
        try {
          img.style.display = '';
          img.style.marginLeft = 'auto';
          img.style.marginRight = 'auto';
        } catch (e) {}
      }
    },

    // Render ZATCA QR code if enabled.
    // Returns a Promise — see renderInvoiceUrlQr for why callers must await
    // this before snapshotting #invoice-POS for the print popup.
    renderZatcaQr() {
      return new Promise((resolve) => {
        try {
          if (!this.invoice_pos || !this.invoice_pos.setting || !this.invoice_pos.setting.zatca_enabled || !this.invoice_pos.zatca_qr) return resolve();
          if (!this.$refs.zatcaQrcode) return resolve();
          const text = String(this.invoice_pos.zatca_qr || '');
          if (!text) return resolve();

          this._zatcaQrToken = (this._zatcaQrToken || 0) + 1;
          const myToken = this._zatcaQrToken;

          this.loadQRCodeLib().then(() => {
            if (myToken !== this._zatcaQrToken) return resolve();
            if (!window.QRCode) return resolve();
            const m = this.$refs.zatcaQrcode;
            if (!m) return resolve();
            m.innerHTML = '';
            try { m.setAttribute('title', text); } catch (e) {}
            try {
              new window.QRCode(m, {
                text,
                width: 180,
                height: 180,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.L : undefined
              });
            } catch (e1) {
              try { new window.QRCode(m, text); } catch (e2) {}
            }
            this.zatcaRendered = true;
            setTimeout(() => {
              if (myToken !== this._zatcaQrToken) return resolve();
              if (m && !m.childNodes.length && window.QRCode) {
                try { new window.QRCode(m, text); } catch (e) {}
              }
              try { this.ensureQrImg(m); } catch (e) {}
              resolve();
            }, 150);
          }).catch(() => resolve());
        } catch (e) { resolve(); }
      });
    },

    // Resolve once both QR mounts (Invoice URL + ZATCA) have an <img> in the
    // DOM. Used to gate print so the popup snapshot is not taken before
    // canvas → <img> conversion completes.
    awaitQrReady() {
      const a = (() => { try { return this.renderInvoiceUrlQr(); } catch (e) { return Promise.resolve(); } })();
      const b = (() => { try { return this.renderZatcaQr(); } catch (e) { return Promise.resolve(); } })();
      return Promise.all([
        Promise.resolve(a).catch(() => {}),
        Promise.resolve(b).catch(() => {})
      ]);
    },

    //-----------------------------  Invoice PDF ------------------------------\\
    Invoice_PDF(sale, id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
       axios
        .get("sale_pdf/" + id, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Sale-" + sale.Ref + ".pdf");
          document.body.appendChild(link);
          link.click();
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },

    Send_WhatsApp(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("sales_send_whatsapp", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);

          var phone = response.data.phone;
          var message = response.data.message;

          // Encode phone number and message
          var encodedPhone = encodeURIComponent(phone);
          var encodedMessage = encodeURIComponent(message);

          // Create WhatsApp URL
          var whatsappUrl = `https://web.whatsapp.com/send/?phone=${encodedPhone}&text=${encodedMessage}`;

          // Open the WhatsApp URL in a new window
          window.open(whatsappUrl, '_blank');
          
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", "Failed to send the Message", this.$t("Failed"));
        });
    },

    //------------------------ Payments Sale PDF ------------------------------\\
    Payment_Sale_PDF(payment, id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
     
      axios
        .get("payment_sale_pdf/" + id, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Payment-" + payment.Ref + ".pdf");
          document.body.appendChild(link);
          link.click();
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },
    //---------------------------------------- Set To Strings-------------------------\\
    setToStrings() {
      // Simply replaces null values with strings=''
      if (this.Filter_Client === null) {
        this.Filter_Client = "";
      } else if (this.Filter_warehouse === null) {
        this.Filter_warehouse = "";
      } else if (this.Filter_status === null) {
        this.Filter_status = "";
      } else if (this.Filter_Payment === null) {
        this.Filter_Payment = "";
      }else if (this.Filter_shipping === null) {
        this.Filter_shipping = "";
      }
    },
    //----------------------------------------- Get all Sales ------------------------------\\
    Get_Sales(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "sales?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&date=" +
            this.Filter_date +
            "&client_id=" +
            this.Filter_Client +
            "&statut=" +
            this.Filter_status +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&payment_statut=" +
            this.Filter_Payment +
            "&shipping_status=" +
            this.Filter_shipping +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.sales = response.data.sales;
          this.customers = response.data.customers;
          this.accounts = response.data.accounts;
          this.warehouses = response.data.warehouses;
          this.payment_methods = response.data.payment_methods;
          this.totalRows = response.data.totalRows;
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //---------SMS notification
     Payment_Sale_SMS(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("payment_sale_send_sms", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("sms_send_successfully"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("sms_config_invalid"), this.$t("Failed"));
        });
    },


    Send_Email_Payment(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("payment_sale_send_email", {
          id: id,
         
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("SendEmail"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("SMTPIncorrect"), this.$t("Failed"));
        });
    },

    Send_Email(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("sales_send_email", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("SendEmail"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("SMTPIncorrect"), this.$t("Failed"));
        });
    },

      //---------SMS notification
     Sale_SMS(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("sales_send_sms", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("sms_send_successfully"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("sms_config_invalid"), this.$t("Failed"));
        });
    },


    Number_Order_Payment() {
      axios
        .get("payment_sale_get_number")
        .then(({ data }) => (this.payment.Ref = data));
    },


    //----------------------------------- New Payment Sale ------------------------------\\
    New_Payment(sale) {
      if (sale.payment_status == "paid") {
        this.$swal({
          icon: "error",
          title: "Oops...",
          text: this.$t("PaymentComplete")
        });
      } else {
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        this.reset_form_payment();
        this.EditPaiementMode = false;
        this.sale = sale;
        this.payment.date = new Date().toISOString().slice(0, 10);
        this.Number_Order_Payment();
        this.payment.montant = sale.due;
        this.payment.payment_method_id = 2;
        this.payment.received_amount = sale.due;
        this.due = parseFloat(sale.due);
        this.client_name = sale.client_name;
        setTimeout(() => {
          // Complete the animation of the  progress bar.
          NProgress.done();
          this.$bvModal.show("Add_Payment");
        }, 500);
      }
    },
    //------------------------------------Edit Payment ------------------------------\\
    Edit_Payment(payment) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.reset_form_payment();
      this.EditPaiementMode = true;

      this.payment.id        = payment.id;
      this.payment.Ref       = payment.Ref;
      this.payment.payment_method_id = payment.payment_method_id;
      this.payment.account_id = payment.account_id;
      this.payment.date    = payment.date;
      this.payment.change  = payment.change;
      this.payment.montant = payment.montant;
      this.payment.received_amount = parseFloat(payment.montant + payment.change).toFixed(2);
      this.payment.notes   = payment.notes;

      this.due = parseFloat(this.sale_due) + payment.montant;
      setTimeout(() => {
        // Complete the animation of the  progress bar.
        NProgress.done();
        this.$bvModal.show("Add_Payment");
      }, 1000);
     
    },
    //-------------------------------Show All Payment with Sale ---------------------\\
    Show_Payments(id, sale) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.reset_form_payment();
      this.Sale_id = id;
      this.sale = sale;
      this.client_name = sale.client_name;
      this.Get_Payments(id);
    },
    //----------------------------------Process Payment (Mode Create) ------------------------------\\
    async processPayment_Create() {
      // Legacy helper retained; Stripe processing removed, use Create_Payment instead.
      return this.Create_Payment();
    },

    //----------------------------------Create Payment sale ------------------------------\\
    Create_Payment() {
      this.paymentProcessing = true;
      NProgress.start();
      NProgress.set(0.1);
        axios
          .post("payment_sale", {
            sale_id: this.sale.id,
            date: this.payment.date,
            montant: parseFloat(this.payment.montant).toFixed(2),
            received_amount: parseFloat(this.payment.received_amount).toFixed(2),
            change: parseFloat(this.payment.received_amount - this.payment.montant).toFixed(2),
            payment_method_id: this.payment.payment_method_id,
            account_id: this.payment.account_id,
            notes: this.payment.notes,
          })
          .then(response => {
            this.paymentProcessing = false;
            Fire.$emit("Create_Facture_sale");
            this.makeToast(
              "success",
              this.$t("Successfully_Created"),
              this.$t("Success")
            );
          })
          .catch(error => {
            this.paymentProcessing = false;
            NProgress.done();
          });
    },
    //---------------------------------------- Update Payment ------------------------------\\
    Update_Payment() {
      this.paymentProcessing = true;
      NProgress.start();
      NProgress.set(0.1);
      
        axios
          .put("payment_sale/" + this.payment.id, {
            sale_id: this.sale.id,
            date: this.payment.date,
            montant: parseFloat(this.payment.montant).toFixed(2),
            received_amount: parseFloat(this.payment.received_amount).toFixed(2),
            change: parseFloat(this.payment.received_amount - this.payment.montant).toFixed(2),
            payment_method_id: this.payment.payment_method_id,
            account_id: this.payment.account_id,
            notes: this.payment.notes
          })
          .then(response => {
            this.paymentProcessing = false;
            Fire.$emit("Update_Facture_sale");
            this.makeToast(
              "success",
              this.$t("Successfully_Updated"),
              this.$t("Success")
            );
          })
          .catch(error => {
            this.paymentProcessing = false;
            NProgress.done();
          });
    },
    //----------------------------------------- Remove Payment ------------------------------\\
    Remove_Payment(id) {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .delete("payment_sale/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("Delete_Facture_sale");
            })
            .catch(() => {
              // Complete the animation of the  progress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },
    //----------------------------------------- Get Payments  -------------------------------\\
    Get_Payments(id) {
      axios
        .get("get_payments_by_sale/" + id)
        .then(response => {
          this.payments = response.data.payments;
          this.sale_due = response.data.due;
          setTimeout(() => {
            // Complete the animation of the  progress bar.
            NProgress.done();
            this.$bvModal.show("Show_payment");
          }, 500);
        })
        .catch(() => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },
    //------------------------------------------ Reset Form Payment ------------------------------\\
    reset_form_payment() {
      this.due = 0;
      this.payment = {
        id: "",
        Sale_id: "",
        date: "",
        Ref: "",
        montant: "",
        received_amount: "",
        payment_method_id: "",
        account_id: "",
        notes: ""
      };
    },

    //----------------------------------------- Manage Documents -------------------------------\\
    Manage_Documents(saleId) {
      this.currentSaleId = saleId;
      this.selectedFiles = [];
      NProgress.start();
      NProgress.set(0.1);
      this.Get_Documents(saleId);
    },

    //----------------------------------------- Get Documents -------------------------------\\
    Get_Documents(saleId) {
      axios
        .get("sales/" + saleId + "/documents")
        .then(response => {
          this.documents = response.data.documents || [];
          setTimeout(() => {
            NProgress.done();
            this.$bvModal.show("Manage_Documents");
          }, 500);
        })
        .catch(error => {
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("Failed_to_load_documents"), this.$t("Failed"));
        });
    },

    //----------------------------------------- On File Change -------------------------------\\
    onFileChange(event) {
      this.selectedFiles = event.target.files || [];
    },

    //----------------------------------------- Upload Documents -------------------------------\\
    Upload_Documents() {
      if (!this.selectedFiles || this.selectedFiles.length === 0) {
        this.makeToast("warning", this.$t("Please_select_files"), this.$t("Warning"));
        return;
      }

      this.uploadProcessing = true;
      NProgress.start();
      NProgress.set(0.1);

      const formData = new FormData();
      for (let i = 0; i < this.selectedFiles.length; i++) {
        formData.append('documents[]', this.selectedFiles[i]);
      }
      formData.append('sale_id', this.currentSaleId);

      axios
        .post("sales/" + this.currentSaleId + "/documents", formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        .then(response => {
          this.uploadProcessing = false;
          this.selectedFiles = [];
          this.Get_Documents(this.currentSaleId);
          this.Get_Sales(this.serverParams.page);
          this.makeToast("success", this.$t("Documents_uploaded_successfully"), this.$t("Success"));
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(error => {
          this.uploadProcessing = false;
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("Failed_to_upload_documents"), this.$t("Failed"));
        });
    },

    //----------------------------------------- Download Document -------------------------------\\
    Download_Document(doc) {
      NProgress.start();
      NProgress.set(0.1);
      
      axios
        .get("sales/documents/" + doc.id + "/download", {
          responseType: "blob"
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = window.document.createElement("a");
          link.href = url;
          link.setAttribute("download", doc.name);
          window.document.body.appendChild(link);
          link.click();
          window.document.body.removeChild(link);
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(error => {
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("Failed_to_download_document"), this.$t("Failed"));
        });
    },

    //----------------------------------------- Remove Document -------------------------------\\
    Remove_Document(documentId) {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          NProgress.start();
          NProgress.set(0.1);
          axios
            .delete("sales/documents/" + documentId)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.Get_Documents(this.currentSaleId);
              this.Get_Sales(this.serverParams.page);
              setTimeout(() => NProgress.done(), 500);
            })
            .catch(() => {
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

     //---------------------- Get_Data_Create  ------------------------------\\

      Get_shipment_by_sale(sale_id) {
        axios
            .get("/shipments/" + sale_id)
            .then(response => {
                this.shipment   = response.data.shipment;

                 setTimeout(() => {
                    NProgress.done();
                    this.$bvModal.show("modal_shipment");
                }, 1000);
            })
            .catch(error => {
              NProgress.done();
                
            });
    },

      //------------- Submit Validation Edit shipment
      Submit_Shipment() {
      this.$refs.shipment_ref.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Update_Shipment();
        }
      });
    },

      //----------------------- Update_Shipment ---------------------------\\
    Update_Shipment() {
      var self = this;
      self.Submit_Processing_shipment = true;
      axios
        .post("shipments", {
          Ref: self.shipment.Ref,
          sale_id: self.shipment.sale_id,
          shipping_address: self.shipment.shipping_address,
          delivered_to: self.shipment.delivered_to,
          shipping_details: self.shipment.shipping_details,
          status: self.shipment.status
        })
        .then(response => {
          this.makeToast(
            "success",
            this.$t("Updated_in_successfully"),
            this.$t("Success")
          );
          Fire.$emit("event_update_shipment");
          self.Submit_Processing_shipment = false;
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          self.Submit_Processing_shipment = false;
        });
    },


     //------------------------------ Show Modal (Edit shipment) -------------------------------\\
    Edit_Shipment(sale_id) {
      NProgress.start();
      NProgress.set(0.1);
      this.reset_Form_shipment();
      this.Get_shipment_by_sale(sale_id);
    },

      //-------------------------------- Reset Form -------------------------------\\
    reset_Form_shipment() {
      this.shipment = {
        id: "",
        date: "",
        Ref: "",
        sale_id: "",
        attachment: "",
        delivered_to: "",
        shipping_address: "",
        status: "",
        shipping_details: ""
      };
    },

    //------------------------------------------ Remove Sale ------------------------------\\
    Remove_Sale(id , sale_has_return) {
      if(sale_has_return == 'yes'){
        this.makeToast("danger", this.$t("Return_exist_for_the_Transaction"), this.$t("Failed"));
      }else{
        this.$swal({
          title: this.$t("Delete_Title"),
          text: this.$t("Delete_Text"),
          type: "warning",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          cancelButtonText: this.$t("Delete_cancelButtonText"),
          confirmButtonText: this.$t("Delete_confirmButtonText")
        }).then(result => {
          if (result.value) {
            // Start the progress bar.
            NProgress.start();
            NProgress.set(0.1);
            axios
              .delete("sales/" + id)
              .then(() => {
                this.$swal(
                  this.$t("Delete_Deleted"),
                  this.$t("Deleted_in_successfully"),
                  "success"
                );
                Fire.$emit("Delete_sale");
              })
              .catch(() => {
                // Complete the animation of the  progress bar.
                setTimeout(() => NProgress.done(), 500);
                this.$swal(
                  this.$t("Delete_Failed"),
                  this.$t("Delete_Therewassomethingwronge"),
                  "warning"
                );
              });
          }
        });
      }
    },
    //---- Delete sales by selection
    delete_by_selected() {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText")
      }).then(result => {
        if (result.value) {
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .post("sales_delete_by_selection", {
              selectedIds: this.selectedIds
            })
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("Delete_sale");
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    }
  },
  //----------------------------- Created function-------------------\\
  created() {
    this.Get_Sales(1);
    this.get_pos_Settings();

    Fire.$on("Create_Facture_sale", () => {
      setTimeout(() => {
        this.Get_Sales(this.serverParams.page);
        NProgress.done();
        this.$bvModal.hide("Add_Payment");
      }, 800);
    });


    Fire.$on("Update_Facture_sale", () => {

      setTimeout(() => {
        NProgress.done();
        this.$bvModal.hide("Add_Payment");
        this.$bvModal.hide("Show_payment");
        this.Get_Sales(this.serverParams.page);
      }, 800);
    });


    Fire.$on("Delete_Facture_sale", () => {
      setTimeout(() => {
        NProgress.done();
        this.$bvModal.hide("Show_payment");
        this.Get_Sales(this.serverParams.page);
      }, 800);
    });


    Fire.$on("Delete_sale", () => {
      setTimeout(() => {
        this.Get_Sales(this.serverParams.page);
        // Complete the animation of the  progress bar.
        NProgress.done();
      }, 800);
    });

     Fire.$on("event_update_shipment", () => {
      setTimeout(() => {
        this.Get_Sales(this.serverParams.page);
        this.$bvModal.hide("modal_shipment");
      }, 800);
    });
  }
};
</script>

<style>
  .total{
    font-weight: bold;
    font-size: 14px;
    /* text-transform: uppercase;
    height: 50px; */
  }

  /* ============================================
     Sales receipt modal — QR codes row (ZATCA + Invoice URL)
     `flex-wrap: nowrap` forces the two blocks to stay inline even inside
     the narrow size="sm" modal; QRs are 100px so 2 × 100 + 10 gap = 210px
     comfortably fits a ~280px content column.
     ============================================ */
  #invoice-POS .receipt-qr-row {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    justify-content: center !important;
    align-items: flex-start !important;
    gap: 10px !important;
    width: 100%;
  }
  #invoice-POS .receipt-qr-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 0 0 auto;
    width: 100px;
    margin: 0;
  }
  #invoice-POS .receipt-qr-title {
    font-weight: 700;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    text-align: center;
    margin: 0 0 4px;
    line-height: 1.2;
    display: block;
    width: 100%;
  }
  #invoice-POS .receipt-qr-canvas {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100px;
    height: 100px;
    margin: 0 auto;
  }
  /* qrcode.js' CDN build emits a <canvas> + an <img>; the local minimal
     vendor build only emits a <canvas>. Our render code (ensureQrImg) makes
     sure an <img> always exists by converting canvas → toDataURL when
     needed, then we show only the <img>. The <img>'s data URL survives
     `innerHTML` cloning into the print popup (canvas pixel data does not). */
  #invoice-POS .receipt-qr-canvas img {
    display: block !important;
    margin: 0 auto !important;
    width: 100px !important;
    height: 100px !important;
    max-width: 100px !important;
  }
  #invoice-POS .receipt-qr-canvas canvas,
  #invoice-POS .receipt-qr-canvas table {
    display: none !important;
  }
</style>
