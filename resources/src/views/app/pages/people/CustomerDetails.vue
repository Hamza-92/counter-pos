<template>
  <div class="main-content">
    <breadcumb :page="$t('Customer_details')" :folder="$t('Customers')" />

    <!-- Full Page Loading Overlay -->
    <div v-if="isLoading" class="full-page-loading">
      <div class="loading-content">
        <div class="spinner spinner-primary"></div>
        <div class="loading-text mt-3">{{ $t('Loading') }}...</div>
      </div>
    </div>
    
    <div v-show="!isLoading">
      <!-- Customer Header Card -->
      <b-card class="mb-4 shadow-sm">
        <b-row class="align-items-center">
          <b-col md="8">
            <h4 class="mb-2"><lucide-icon class="mr-2 text-primary" name="user" />{{ client.name }}</h4>
            <div class="text-muted">
              <span class="mr-3"><strong>{{ $t('Code') }}:</strong> {{ client.code }}</span>
              <span class="mr-3"><strong>{{ $t('Email') }}:</strong> {{ client.email || '-' }}</span>
              <span class="mr-3"><strong>{{ $t('Phone') }}:</strong> {{ client.phone || '-' }}</span>
            </div>
            <div class="text-muted mt-2">
              <span class="mr-3"><strong>{{ $t('City') }}:</strong> {{ client.city || '-' }}</span>
              <span class="mr-3"><strong>{{ $t('Country') }}:</strong> {{ client.country || '-' }}</span>
              <span class="mr-3"><strong>{{ $t('Tax_Number') }}:</strong> {{ client.tax_number || '-' }}</span>
              <span class="mr-3"><strong>{{ $t('Credit_Limit') }}:</strong>
                {{ (client.credit_limit && client.credit_limit > 0)
                  ? formatPriceWithSymbol(currentUser.currency, client.credit_limit, 2)
                  : $t('No_limit') }}
              </span>
            </div>
          </b-col>
          <b-col md="4" class="text-right">
            <b-button variant="secondary" @click="$router.push({ name: 'Customers' })" class="mr-2">
              <lucide-icon class="mr-1" name="chevron-left" /> {{ $t('Back') }}
            </b-button>
            <b-button 
              v-if="totalDue > 0 && currentUserPermissions && currentUserPermissions.includes('pay_due')"
              variant="primary" 
              @click="showPayDueModal"
            >
              <lucide-icon class="mr-1" name="dollar-sign" /> {{ $t('Pay_Due') }}
            </b-button>
          </b-col>
        </b-row>
      </b-card>

      <!-- Summary Cards -->
      <b-row class="mb-4">
        <!-- Opening Balance Card -->
        <b-col lg="4" md="4" sm="12" class="mb-3">
          <b-card class="text-center shadow-sm border-left-primary h-100" :class="{'border-left-danger': client.opening_balance > 0}">
            <div class="mb-2">
              <lucide-icon class="text-primary" name="calendar-days" style="font-size: 2.5rem;" />
            </div>
            <h6 class="text-muted mb-2">{{ $t('Opening_Balance') }}</h6>
            <h3 class="mb-0" :class="client.opening_balance > 0 ? 'text-danger font-weight-bold' : 'text-success'">
              {{ formatPriceWithSymbol(currentUser.currency, client.opening_balance || 0, 2) }}
            </h3>
            <small class="text-muted">{{ $t('Previous_Dues') }}</small>
          </b-card>
        </b-col>

        <!-- Sales Due Card -->
        <b-col lg="4" md="4" sm="12" class="mb-3">
          <b-card class="text-center shadow-sm border-left-warning h-100" :class="{'border-left-danger': salesDue > 0}">
            <div class="mb-2">
              <lucide-icon class="text-warning" name="shopping-cart" style="font-size: 2.5rem;" />
            </div>
            <h6 class="text-muted mb-2">{{ $t('Sales_Due') }}</h6>
            <h3 class="mb-0" :class="salesDue > 0 ? 'text-danger font-weight-bold' : 'text-success'">
              {{ formatPriceWithSymbol(currentUser.currency, salesDue, 2) }}
            </h3>
            <small class="text-muted">{{ $t('Current_Sales') }}</small>
          </b-card>
        </b-col>

        <!-- Credit Limit Card -->
        <b-col lg="4" md="4" sm="12" class="mb-3">
          <b-card class="text-center shadow-sm border-left-info h-100">
            <div class="mb-2">
              <lucide-icon class="text-info" name="credit-card" style="font-size: 2.5rem;" />
            </div>
            <h6 class="text-muted mb-2">{{ $t('Credit_Limit') }}</h6>
            <h3 class="mb-0 text-info font-weight-bold">
              {{ (client.credit_limit && client.credit_limit > 0)
                ? formatPriceWithSymbol(currentUser.currency, client.credit_limit, 2)
                : $t('No_limit') }}
            </h3>
            <small class="text-muted">{{ $t('Maximum_credit_amount_allowed_for_this_customer') }}</small>
          </b-card>
        </b-col>
      </b-row>

      <!-- Tabs for Sales, Payments, Returns, Payment Returns -->
      <b-card class="shadow-sm">
        <b-tabs v-model="activeTab" lazy>
          <!-- Sales Tab -->
          <b-tab :title="$t('Sales')">
            <div class="mb-3">
              <b-input-group>
                <b-form-input v-model="salesSearch" :placeholder="$t('Search')" @input="fetchSales"></b-form-input>
                <b-input-group-append>
                  <b-button variant="primary" @click="fetchSales">{{ $t('Search') }}</b-button>
                </b-input-group-append>
              </b-input-group>
            </div>
            <b-table 
              :items="sales" 
              :fields="salesFields" 
              striped 
              hover 
              responsive
              :busy="salesLoading"
            >
              <template #table-busy>
                <div class="text-center text-danger my-2">
                  <b-spinner class="align-middle"></b-spinner>
                  <strong> Loading...</strong>
                </div>
              </template>
              <template #cell(GrandTotal)="{ item }">
                {{ formatPriceWithSymbol(currentUser.currency, item.GrandTotal, 2) }}
              </template>
              <template #cell(paid_amount)="{ item }">
                {{ formatPriceWithSymbol(currentUser.currency, item.paid_amount, 2) }}
              </template>
              <template #cell(due)="{ item }">
                <span :class="item.due > 0 ? 'text-danger font-weight-bold' : 'text-success'">
                  {{ formatPriceWithSymbol(currentUser.currency, item.due, 2) }}
                </span>
              </template>
              <template #cell(payment_status)="{ item }">
                <b-badge :variant="getPaymentStatusBadge(item.payment_status)">
                  {{ item.payment_status }}
                </b-badge>
              </template>
            </b-table>
            <b-pagination
              v-model="salesPage"
              :total-rows="salesTotalRows"
              :per-page="salesLimit"
              @change="fetchSales"
              class="mt-3"
            ></b-pagination>
          </b-tab>

          <!-- Payments Tab -->
          <b-tab :title="$t('Payments')">
            <div class="mb-3">
              <b-input-group>
                <b-form-input v-model="paymentsSearch" :placeholder="$t('Search')" @input="fetchPayments"></b-form-input>
                <b-input-group-append>
                  <b-button variant="primary" @click="fetchPayments">{{ $t('Search') }}</b-button>
                </b-input-group-append>
              </b-input-group>
            </div>
            <b-table 
              :items="payments" 
              :fields="paymentsFields" 
              striped 
              hover 
              responsive
              :busy="paymentsLoading"
            >
              <template #table-busy>
                <div class="text-center text-danger my-2">
                  <b-spinner class="align-middle"></b-spinner>
                  <strong> Loading...</strong>
                </div>
              </template>
              <template #cell(payment_type)="{ item }">
                <b-badge v-if="item.payment_type === 'opening_balance'" variant="info">{{ $t('Opening_Balance') }}</b-badge>
                <b-badge v-else variant="success">{{ $t('Sale') }}</b-badge>
              </template>
              <template #cell(Sale_Ref)="{ item }">
                <span v-if="item.Sale_Ref">{{ item.Sale_Ref }}</span>
                <span v-else class="text-muted">-</span>
              </template>
              <template #cell(montant)="{ item }">
                <span class="text-success font-weight-bold">
                  {{ formatPriceWithSymbol(currentUser.currency, item.montant, 2) }}
                </span>
              </template>
            </b-table>
            <b-pagination
              v-model="paymentsPage"
              :total-rows="paymentsTotalRows"
              :per-page="paymentsLimit"
              @change="fetchPayments"
              class="mt-3"
            ></b-pagination>
          </b-tab>

          <!-- Returns Tab -->
          <b-tab :title="$t('Returns')">
            <div class="mb-3">
              <b-input-group>
                <b-form-input v-model="returnsSearch" :placeholder="$t('Search')" @input="fetchReturns"></b-form-input>
                <b-input-group-append>
                  <b-button variant="primary" @click="fetchReturns">{{ $t('Search') }}</b-button>
                </b-input-group-append>
              </b-input-group>
            </div>
            <b-table 
              :items="returns" 
              :fields="returnsFields" 
              striped 
              hover 
              responsive
              :busy="returnsLoading"
            >
              <template #table-busy>
                <div class="text-center text-danger my-2">
                  <b-spinner class="align-middle"></b-spinner>
                  <strong> Loading...</strong>
                </div>
              </template>
              <template #cell(GrandTotal)="{ item }">
                {{ formatPriceWithSymbol(currentUser.currency, item.GrandTotal, 2) }}
              </template>
              <template #cell(paid_amount)="{ item }">
                {{ formatPriceWithSymbol(currentUser.currency, item.paid_amount, 2) }}
              </template>
              <template #cell(due)="{ item }">
                <span :class="item.due > 0 ? 'text-warning font-weight-bold' : 'text-success'">
                  {{ formatPriceWithSymbol(currentUser.currency, item.due, 2) }}
                </span>
              </template>
            </b-table>
            <b-pagination
              v-model="returnsPage"
              :total-rows="returnsTotalRows"
              :per-page="returnsLimit"
              @change="fetchReturns"
              class="mt-3"
            ></b-pagination>
          </b-tab>

          <!-- Payment Returns Tab -->
          <b-tab :title="$t('Payment_Returns')">
            <div class="mb-3">
              <b-input-group>
                <b-form-input v-model="paymentReturnsSearch" :placeholder="$t('Search')" @input="fetchPaymentReturns"></b-form-input>
                <b-input-group-append>
                  <b-button variant="primary" @click="fetchPaymentReturns">{{ $t('Search') }}</b-button>
                </b-input-group-append>
              </b-input-group>
            </div>
            <b-table 
              :items="paymentReturns" 
              :fields="paymentReturnsFields" 
              striped 
              hover 
              responsive
              :busy="paymentReturnsLoading"
            >
              <template #table-busy>
                <div class="text-center text-danger my-2">
                  <b-spinner class="align-middle"></b-spinner>
                  <strong> Loading...</strong>
                </div>
              </template>
              <template #cell(montant)="{ item }">
                <span class="text-warning font-weight-bold">
                  {{ formatPriceWithSymbol(currentUser.currency, item.montant, 2) }}
                </span>
              </template>
            </b-table>
            <b-pagination
              v-model="paymentReturnsPage"
              :total-rows="paymentReturnsTotalRows"
              :per-page="paymentReturnsLimit"
              @change="fetchPaymentReturns"
              class="mt-3"
            ></b-pagination>
          </b-tab>
        </b-tabs>
      </b-card>

      <!-- Custom Fields Section -->
      <b-card v-if="clientCustomFields && clientCustomFields.length > 0" class="shadow-sm mt-4">
        <h6 class="text-primary mb-3">
          <lucide-icon class="mr-2" name="database-zap" />
          {{ $t('CustomFields') }}
        </h6>
        <b-row>
          <b-col 
            v-for="field in clientCustomFields" 
            :key="field.id" 
            md="6" 
            sm="12" 
            class="mb-3"
          >
            <div class="custom-field-item">
              <strong class="text-muted d-block mb-1">{{ field.name }}</strong>
              <span class="d-block">{{ getCustomFieldDisplayValue(field) }}</span>
            </div>
          </b-col>
        </b-row>
      </b-card>
    </div>

    <!-- Pay Due Modal -->
    <validation-observer ref="ref_pay_due">
      <b-modal
        hide-footer
        size="lg"
        id="modal_Pay_due"
        title="Pay Due"
      >
        <b-form @submit.prevent="Submit_Payment_sell_due">
          <b-row>
            <!-- Customer Name -->
            <b-col lg="12" md="12" sm="12" class="mb-3">
              <h5 class="text-primary"><lucide-icon class="mr-2" name="user" />{{ client.name }}</h5>
            </b-col>

            <!-- Summary Cards -->
            <b-col lg="12" md="12" sm="12" class="mb-4">
              <b-row>
                <!-- Opening Balance Card -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <b-card 
                    class="text-center shadow-sm border-left-primary"
                    :class="{'border-left-danger': client.opening_balance > 0}"
                  >
                    <div class="mb-2">
                      <lucide-icon class="text-primary" name="calendar-days" style="font-size: 2rem;" />
                    </div>
                    <h6 class="text-muted mb-2">{{ $t('Opening_Balance') }}</h6>
                    <h4 class="mb-0" :class="client.opening_balance > 0 ? 'text-danger font-weight-bold' : 'text-success'">
                      {{ formatPriceWithSymbol(currentUser.currency, client.opening_balance || 0, 2) }}
                    </h4>
                    <small class="text-muted">{{ $t('Previous_Dues') }}</small>
                  </b-card>
                </b-col>

                <!-- Sales Due Card -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <b-card 
                    class="text-center shadow-sm border-left-warning"
                    :class="{'border-left-danger': salesDue > 0}"
                  >
                    <div class="mb-2">
                      <lucide-icon class="text-warning" name="shopping-cart" style="font-size: 2rem;" />
                    </div>
                    <h6 class="text-muted mb-2">{{ $t('Sales_Due') }}</h6>
                    <h4 class="mb-0" :class="salesDue > 0 ? 'text-danger font-weight-bold' : 'text-success'">
                      {{ formatPriceWithSymbol(currentUser.currency, salesDue, 2) }}
                    </h4>
                    <small class="text-muted">{{ $t('Current_Sales') }}</small>
                  </b-card>
                </b-col>

                <!-- Total Due Card -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <b-card 
                    class="text-center shadow-sm border-left-danger"
                    :class="{'border-left-success': totalDue <= 0}"
                  >
                    <div class="mb-2">
                      <lucide-icon class="text-danger" name="wallet" style="font-size: 2rem;" />
                    </div>
                    <h6 class="text-muted mb-2">{{ $t('Total_Due') }}</h6>
                    <h4 class="mb-0 font-weight-bold" :class="totalDue > 0 ? 'text-danger' : 'text-success'">
                      {{ formatPriceWithSymbol(currentUser.currency, totalDue, 2) }}
                    </h4>
                    <small class="text-muted">{{ $t('Grand_Total') }}</small>
                  </b-card>
                </b-col>
              </b-row>
            </b-col>

            <!-- Payment Allocation Info -->
            <b-col lg="12" md="12" sm="12" class="mb-3">
              <b-alert variant="info" show class="mb-0">
                <div class="d-flex align-items-center">
                  <lucide-icon class="mr-2" name="info" style="font-size: 1.5rem;" />
                  <div>
                    <strong>{{ $t('Payment_Allocation') }}:</strong> {{ $t('Payment_Allocation_description') }}
                  </div>
                </div>
              </b-alert>
            </b-col>
          
            <!-- Paying Amount  -->
            <b-col lg="12" md="12" sm="12" class="mt-3">
              <validation-provider
                name="Amount"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Paying_Amount') + ' ' + '*'">
                  <b-form-input
                   @keyup="Verified_paidAmount(payment.amount)"
                    label="Amount"
                    :placeholder="$t('Paying_Amount')"
                    v-model.number="payment.amount"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Amount-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Amount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                  <small class="text-muted mt-1 d-block">
                    {{ $t('Maximum_payment') }}: <strong>{{ formatPriceWithSymbol(currentUser.currency, totalDue, 2) }}</strong>
                  </small>
                </b-form-group>
              </validation-provider>
            </b-col>

             <!-- Payment choice -->
             <b-col lg="12" md="12" sm="12">
              <validation-provider name="Payment choice" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Paymentchoice')+ ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="payment.payment_method_id"
                    :reduce="label => label.value"
                    :placeholder="$t('PleaseSelect')"
                    :options="payment_methods.map(payment_methods => ({label: payment_methods.name, value: payment_methods.id}))"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

             <!-- Account -->
             <b-col lg="12" md="6" sm="12">
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
            <b-col lg="12" md="12" sm="12" class="mt-3">
              <b-form-group :label="$t('Please_provide_any_details')">
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

    <!-- Modal Show Customer Credit Note Receipt -->
    <b-modal hide-footer size="sm" scrollable id="Show_invoice" :title="$t('Customer_Credit_Note')">
        <div id="invoice-POS">
          <div style="max-width:400px;margin:0px auto">
          <div class="info" >
            <h2 class="text-center">{{company_info.CompanyName}}</h2>

            <p>
                <span>{{$t('date')}} : {{payment.date}} <br></span>
                <span >{{$t('Adress')}} : {{company_info.CompanyAdress}} <br></span>
                <span >{{$t('Phone')}} : {{company_info.CompanyPhone}} <br></span>
                <span >{{$t('Customer')}} : {{payment.client_name}} <br></span>
              </p>
          </div>

           <table
                class="change mt-3"
                style=" font-size: 10px;width: 100%;"
              >
                <tbody>
                  <tr>
                    <th style="text-align: left; background: #eee; width: 50%;">{{$t('Payment_Method')}}:</th>
                    <td style="text-align: right; width: 50%;">{{ paymentMethodName }}</td>
                  </tr>
                  <tr>
                    <th style="text-align: left; background: #eee;">{{$t('Amount')}}:</th>
                    <td style="text-align: right;">{{ formatPriceWithSymbol(currentUser.currency, payment.amount, 2) }}</td>
                  </tr>
                  <tr>
                    <th style="text-align: left; background: #eee;">{{$t('Due_Before')}}:</th>
                    <td style="text-align: right;">{{ formatPriceWithSymbol(currentUser.currency, totalDue, 2) }}</td>
                  </tr>
                  <tr>
                    <th style="text-align: left; background: #eee;">{{$t('Due_After')}}:</th>
                    <td style="text-align: right;">{{ formatPriceWithSymbol(currentUser.currency, totalDue - (parseFloat(payment.amount) || 0), 2) }}</td>
                  </tr>
                </tbody>
              </table>
          </div>
        </div>
      <button @click="print_it()" class="btn btn-outline-primary">
        <lucide-icon name="receipt" />
        {{$t('print')}}
      </button>
    </b-modal>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting,
} from "../../../../utils/priceFormat";

export default {
  metaInfo: {
    title: "Customer Details"
  },
  data() {
    return {
      isLoading: true,
      client: {},
      activeTab: 0,
      price_format_key: null,
      
      // Sales
      sales: [],
      salesLoading: false,
      salesSearch: "",
      salesPage: 1,
      salesLimit: 10,
      salesTotalRows: 0,
      salesFields: [
        { key: "Ref", label: this.$t("Ref"), sortable: true },
        { key: "date", label: this.$t("date"), sortable: true },
        { key: "warehouse_name", label: this.$t("warehouse"), sortable: false },
        { key: "GrandTotal", label: this.$t("Grand_Total"), sortable: true },
        { key: "paid_amount", label: this.$t("Paid"), sortable: true },
        { key: "due", label: this.$t("Due"), sortable: true },
        { key: "payment_status", label: this.$t("Payment_Status"), sortable: true },
        { key: "statut", label: this.$t("Status"), sortable: true }
      ],

      // Payments
      payments: [],
      paymentsLoading: false,
      paymentsSearch: "",
      paymentsPage: 1,
      paymentsLimit: 10,
      paymentsTotalRows: 0,
      paymentsFields: [
        { key: "Ref", label: this.$t("Ref"), sortable: true },
        { key: "date", label: this.$t("date"), sortable: true },
        { key: "payment_type", label: this.$t("Type"), sortable: false },
        { key: "Sale_Ref", label: this.$t("Sale_Ref"), sortable: false },
        { key: "payment_method", label: this.$t("Payment_Method"), sortable: false },
        { key: "montant", label: this.$t("Amount"), sortable: true }
      ],

      // Returns
      returns: [],
      returnsLoading: false,
      returnsSearch: "",
      returnsPage: 1,
      returnsLimit: 10,
      returnsTotalRows: 0,
      returnsFields: [
        { key: "Ref", label: this.$t("Ref"), sortable: true },
        { key: "date", label: this.$t("date"), sortable: true },
        { key: "sale_ref", label: this.$t("Sale_Ref"), sortable: false },
        { key: "warehouse_name", label: this.$t("warehouse"), sortable: false },
        { key: "GrandTotal", label: this.$t("Grand_Total"), sortable: true },
        { key: "paid_amount", label: this.$t("Paid"), sortable: true },
        { key: "due", label: this.$t("Due"), sortable: true },
        { key: "payment_status", label: this.$t("Payment_Status"), sortable: true },
        { key: "statut", label: this.$t("Status"), sortable: true }
      ],

      // Payment Returns
      paymentReturns: [],
      paymentReturnsLoading: false,
      paymentReturnsSearch: "",
      paymentReturnsPage: 1,
      paymentReturnsLimit: 10,
      paymentReturnsTotalRows: 0,
      paymentReturnsFields: [
        { key: "Ref", label: this.$t("Ref"), sortable: true },
        { key: "date", label: this.$t("date"), sortable: true },
        { key: "Sale_Return_Ref", label: this.$t("Sale_Return_Ref"), sortable: false },
        { key: "payment_method", label: this.$t("Payment_Method"), sortable: false },
        { key: "montant", label: this.$t("Amount"), sortable: true }
      ],

      // Payment
      payment: {
        client_id: "",
        client_name: "",
        account_id: "",
        date: "",
        due: "",
        opening_balance: 0,
        amount: "",
        notes: "",
        payment_method_id: "",
      },
      payment_methods: [],
      accounts: [],
      paymentProcessing: false,

      // Custom Fields
      clientCustomFields: [],
      
      // Company Info for Receipt
      company_info: {}
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),

    salesDue() {
      return this.sales.reduce((sum, sale) => sum + (parseFloat(sale.due) || 0), 0);
    },

    totalDue() {
      // Use payment data if available (for modal display), otherwise use client data (for button visibility)
      if (this.payment.due !== "" && this.payment.due !== null && this.payment.due !== undefined) {
        const openingBalance = parseFloat(this.payment.opening_balance) || 0;
        const salesDue = parseFloat(this.payment.due) || 0;
        return openingBalance + salesDue;
      } else {
        // Use client data for button visibility and display
        const openingBalance = parseFloat(this.client.opening_balance) || 0;
        return openingBalance + this.salesDue;
      }
    },

    paymentMethodName() {
      if (!this.payment.payment_method_id || !this.payment_methods.length) {
        return '-';
      }
      const methodId = parseInt(this.payment.payment_method_id);
      const method = this.payment_methods.find(m => parseInt(m.id) === methodId);
      return method ? method.name : '-';
    }
  },
  methods: {
    // Get Customer Data
    Get_Customer() {
      NProgress.start();
      this.isLoading = true;
      let id = this.$route.params.id;
      
      // Fetch all data in parallel with error handling for each request
      Promise.allSettled([
        axios.get("clients/" + id),
        axios.get("custom-fields?entity_type=client"),
        axios.get("custom-field-values", {
          params: {
            entity_type: "App\\Models\\Client",
            entity_id: id
          }
        }),
        axios.get(`sales_client?id=${id}&page=${this.salesPage}&limit=${this.salesLimit}&search=${this.salesSearch}`),
        axios.get(`payments_client?id=${id}&page=${this.paymentsPage}&limit=${this.paymentsLimit}&search=${this.paymentsSearch}`),
        axios.get(`returns_client?id=${id}&page=${this.returnsPage}&limit=${this.returnsLimit}&search=${this.returnsSearch}`),
        axios.get(`payment_returns_client?id=${id}&page=${this.paymentReturnsPage}&limit=${this.paymentReturnsLimit}&search=${this.paymentReturnsSearch}`)
      ])
        .then((results) => {
          // Process client data (required)
          if (results[0].status === 'fulfilled' && results[0].value.data.client) {
            this.client = results[0].value.data.client;
            // Extract payment_methods and accounts from the client response
            this.payment_methods = results[0].value.data.payment_methods || [];
            this.accounts = results[0].value.data.accounts || [];
          } else {
            throw new Error('Failed to load client data');
          }
          
          // Process custom fields (optional)
          if (results[1].status === 'fulfilled' && results[2].status === 'fulfilled') {
            const allFields = results[1].value.data.custom_fields || [];
            const fieldValues = results[2].value.data.success && results[2].value.data.values 
              ? results[2].value.data.values 
              : {};

            this.clientCustomFields = allFields.map(field => {
              const fieldValue = fieldValues[field.id];
              return {
                id: field.id,
                name: field.name,
                field_type: field.field_type,
                value: fieldValue ? fieldValue.value : null
              };
            });
          } else {
            this.clientCustomFields = [];
            console.error('Error loading custom fields:', results[1].reason || results[2].reason);
          }

          // Process sales data (optional)
          if (results[3].status === 'fulfilled') {
            this.sales = results[3].value.data.sales || [];
            this.salesTotalRows = results[3].value.data.totalRows || 0;
          } else {
            this.sales = [];
            this.salesTotalRows = 0;
            console.error('Error loading sales:', results[3].reason);
          }
          this.salesLoading = false;

          // Process payments data (optional)
          if (results[4].status === 'fulfilled') {
            this.payments = results[4].value.data.payments || [];
            this.paymentsTotalRows = results[4].value.data.totalRows || 0;
          } else {
            this.payments = [];
            this.paymentsTotalRows = 0;
            console.error('Error loading payments:', results[4].reason);
          }
          this.paymentsLoading = false;

          // Process returns data (optional)
          if (results[5].status === 'fulfilled') {
            this.returns = results[5].value.data.returns || [];
            this.returnsTotalRows = results[5].value.data.totalRows || 0;
          } else {
            this.returns = [];
            this.returnsTotalRows = 0;
            console.error('Error loading returns:', results[5].reason);
          }
          this.returnsLoading = false;

          // Process payment returns data (optional)
          if (results[6].status === 'fulfilled') {
            this.paymentReturns = results[6].value.data.payment_returns || [];
            this.paymentReturnsTotalRows = results[6].value.data.totalRows || 0;
          } else {
            this.paymentReturns = [];
            this.paymentReturnsTotalRows = 0;
            console.error('Error loading payment returns:', results[6].reason);
          }
          this.paymentReturnsLoading = false;
          
          // Fetch company info from settings
          axios.get("get_Settings_data").then(response => {
            if (response.data && response.data.setting) {
              this.company_info = {
                CompanyName: response.data.setting.CompanyName || '',
                CompanyAdress: response.data.setting.CompanyAdress || '',
                CompanyPhone: response.data.setting.CompanyPhone || ''
              };
            }
          }).catch(() => {
            // If settings endpoint fails, use empty values
            this.company_info = {
              CompanyName: '',
              CompanyAdress: '',
              CompanyPhone: ''
            };
          });

          NProgress.done();
          this.isLoading = false;
        })
        .catch(error => {
          console.error('Error loading customer data:', error);
          NProgress.done();
          this.isLoading = false;
          this.makeToast("danger", this.$t("Failed_to_load_customer"), this.$t("Failed"));
          setTimeout(() => {
            this.$router.push({ name: 'Customers' });
          }, 500);
        });
    },

    // Fetch Sales
    fetchSales() {
      this.salesLoading = true;
      axios
        .get(`sales_client?id=${this.$route.params.id}&page=${this.salesPage}&limit=${this.salesLimit}&search=${this.salesSearch}`)
        .then(response => {
          this.sales = response.data.sales;
          this.salesTotalRows = response.data.totalRows;
          this.salesLoading = false;
        })
        .catch(error => {
          this.salesLoading = false;
        });
    },

    // Fetch Payments
    fetchPayments() {
      this.paymentsLoading = true;
      axios
        .get(`payments_client?id=${this.$route.params.id}&page=${this.paymentsPage}&limit=${this.paymentsLimit}&search=${this.paymentsSearch}`)
        .then(response => {
          this.payments = response.data.payments;
          this.paymentsTotalRows = response.data.totalRows;
          this.paymentsLoading = false;
        })
        .catch(error => {
          this.paymentsLoading = false;
        });
    },

    // Fetch Returns
    fetchReturns() {
      this.returnsLoading = true;
      axios
        .get(`returns_client?id=${this.$route.params.id}&page=${this.returnsPage}&limit=${this.returnsLimit}&search=${this.returnsSearch}`)
        .then(response => {
          this.returns = response.data.returns;
          this.returnsTotalRows = response.data.totalRows;
          this.returnsLoading = false;
        })
        .catch(error => {
          this.returnsLoading = false;
        });
    },

    // Fetch Payment Returns
    fetchPaymentReturns() {
      this.paymentReturnsLoading = true;
      // Note: You may need to create this endpoint or use existing one
      axios
        .get(`payment_returns_client?id=${this.$route.params.id}&page=${this.paymentReturnsPage}&limit=${this.paymentReturnsLimit}&search=${this.paymentReturnsSearch}`)
        .then(response => {
          this.paymentReturns = response.data.payment_returns || [];
          this.paymentReturnsTotalRows = response.data.totalRows || 0;
          this.paymentReturnsLoading = false;
        })
        .catch(error => {
          this.paymentReturnsLoading = false;
        });
    },

    // Get Payment Methods and Accounts (now loaded in Get_Customer)
    Get_Payment_Methods_Accounts() {
      // This method is now called within Get_Customer to load all data together
      // Keeping the method for backwards compatibility if needed elsewhere
    },

    // Show Pay Due Modal
    showPayDueModal() {
      this.reset_Form_payment();
      this.payment.client_id = this.client.id;
      this.payment.client_name = this.client.name;
      this.payment.due = this.salesDue;
      this.payment.opening_balance = this.client.opening_balance || 0;
      this.payment.date = new Date().toISOString().slice(0, 10);
      setTimeout(() => {
        this.$bvModal.show("modal_Pay_due");
      }, 500);
    },

    // Submit Payment
    Submit_Payment_sell_due() {
      this.$refs.ref_pay_due.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else if (this.payment.amount > this.totalDue) {
          this.makeToast(
            "warning",
            this.$t("Paying_amount_is_greater_than_Total_Due"),
            this.$t("Warning")
          );
          this.payment.amount = 0;
        } else {
          this.Submit_Pay_due();
        }
      });
    },

    // Submit Pay Due
    Submit_Pay_due() {
      this.paymentProcessing = true;
      // Store payment data for receipt before closing modal
      const paymentDataForReceipt = {
        client_id: this.payment.client_id,
        client_name: this.payment.client_name,
        account_id: this.payment.account_id,
        date: this.payment.date,
        due: this.payment.due,
        opening_balance: this.payment.opening_balance,
        amount: this.payment.amount,
        notes: this.payment.notes,
        payment_method_id: this.payment.payment_method_id,
      };
      
      axios
        .post("clients_pay_due", {
          client_id: this.payment.client_id,
          amount: this.payment.amount,
          notes: this.payment.notes,
          payment_method_id: this.payment.payment_method_id,
          account_id: this.payment.account_id,
        })
        .then(response => {
          // Update payment object with stored data for receipt
          Object.assign(this.payment, paymentDataForReceipt);
          
          // Close payment modal and show receipt
          this.$bvModal.hide("modal_Pay_due");
          setTimeout(() => {
            this.$bvModal.show("Show_invoice");
          }, 300);
          
          // Refresh data without affecting receipt
          this.Get_Customer();
          
          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
          this.paymentProcessing = false;
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.paymentProcessing = false;
        });
    },

    // Verified Paid Amount
    Verified_paidAmount() {
      if (isNaN(this.payment.amount)) {
        this.payment.amount = 0;
      } else if (this.payment.amount > this.totalDue) {
        this.makeToast(
          "warning",
          this.$t("Paying_amount_is_greater_than_Total_Due"),
          this.$t("Warning")
        );
        this.payment.amount = 0;
      }
    },

    // Reset Form Payment
    reset_Form_payment() {
      this.payment = {
        client_id: "",
        client_name: "",
        account_id: "",
        date: "",
        due: "",
        opening_balance: 0,
        amount: "",
        notes: "",
        payment_method_id: "",
      };
    },

    // Price formatting using global price settings
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 2;
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(number, decimals, effectiveKey);
      } catch (e) {
        const n = Number(number || 0);
        return n.toLocaleString(undefined, {
          minimumFractionDigits: dec || 2,
          maximumFractionDigits: dec || 2,
        });
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    // Fallback simple formatter (kept for non-money cases if needed)
    formatNumber(value) {
      if (value === null || value === undefined) return '0.00';
      const num = parseFloat(value) || 0;
      return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    // Get Payment Status Badge
    getPaymentStatusBadge(status) {
      const s = (status || '').toLowerCase();
      if (s.includes('paid')) return 'success';
      if (s.includes('partial')) return 'warning';
      if (s.includes('unpaid')) return 'danger';
      return 'secondary';
    },

    // Get Validation State
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    // Make Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    // Print Receipt
    print_it() {
      var divContents = document.getElementById("invoice-POS").innerHTML;
      var a = window.open("", "", "height=500, width=500");
      a.document.write(
        '<link rel="stylesheet" href="/css/pos_print.css"><html>'
      );
      a.document.write("<body >");
      a.document.write(divContents);
      a.document.write("</body></html>");
      a.document.close();
      setTimeout(() => {
         a.print();
      }, 1000);
    },

    // Get Custom Field Display Value
    getCustomFieldDisplayValue(field) {
      if (!field.value && field.value !== 0 && field.value !== false) {
        return '-';
      }
      
      if (field.field_type === 'checkbox') {
        return field.value === '1' || field.value === 1 || field.value === true 
          ? this.$t('Yes') 
          : this.$t('No');
      }
      
      return field.value;
    }
  },
  created() {
    this.Get_Customer();
  }
};
</script>

<style scoped>
.border-left-primary {
  border-left: 4px solid #007bff !important;
}

.border-left-warning {
  border-left: 4px solid #ffc107 !important;
}

.border-left-danger {
  border-left: 4px solid #dc3545 !important;
}

.border-left-success {
  border-left: 4px solid #28a745 !important;
}

.shadow-sm {
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.card {
  transition: transform 0.2s;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.custom-field-item {
  padding: 0.75rem;
  background-color: #f8f9fa;
  border-radius: 0.25rem;
  border-left: 3px solid #007bff;
}

.custom-field-item strong {
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.custom-field-item span {
  font-size: 1rem;
  color: #212529;
  font-weight: 500;
}

/* Full Page Loading Overlay */
.full-page-loading {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.95);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(2px);
}

.loading-content {
  text-align: center;
}

.loading-text {
  color: #6b7280;
  font-size: 16px;
  font-weight: 500;
}

.full-page-loading .spinner {
  width: 50px;
  height: 50px;
  border-width: 4px;
}
</style>

