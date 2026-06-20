<template>
  <div class="main-content">
    <breadcumb :page="$t('CustomerManagement')" :folder="$t('Customers')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <div class="mb-5">
        <span class="alert alert-danger" v-show="clients_without_ecommerce > 0">
          {{$t('There_are')}} <strong>{{ clients_without_ecommerce}}</strong> 
          {{$t('Customers_without_ecommerce_notice')}}
          <router-link  to="/app/People/Customers_without_ecommerce">
          {{$t('View_Details')}}
          </router-link>
        </span>
      </div>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="clients"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        enabled: true,
        placeholder: $t('Search_this_table'),  
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
        <div slot="selected-row-actions" v-if="currentUserPermissions.includes('Customers_delete')">
          <button class="btn btn-danger btn-sm" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info m-1" size="sm" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="clients_PDF()" size="sm" variant="outline-success m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
           <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="clients"
              :columns="columns"
              :file-name="'clients'"
              :file-type="'xlsx'"
              :sheet-name="'clients'"
              >
              <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
         <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('customers_import')"
            :to="{ name: 'Import_Customers' }"
            class="btn btn-info btn-sm m-1"
          >
            <lucide-icon name="download" />
            Import Customers
          </router-link>
          <b-button
            @click="New_Client()"
            size="sm"
            variant="btn btn-primary btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('Customers_add')"
          >
            <lucide-icon name="plus" />
            {{$t('Add')}}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'opening_balance'">
            <span :class="props.row.opening_balance > 0 ? 'text-danger font-weight-bold' : ''">
              {{ formatPriceWithSymbol(currentUser.currency, props.row.opening_balance || 0, 2) }}
            </span>
          </span>
          <span v-else-if="props.column.field == 'credit_limit'">
            <span class="text-info font-weight-bold">
              {{ (props.row.credit_limit && props.row.credit_limit > 0)
                  ? formatPriceWithSymbol(currentUser.currency, props.row.credit_limit, 2)
                  : $t('No_limit') }}
            </span>
          </span>
          <span v-else-if="props.column.field == 'actions'">
            <div>
              <b-dropdown
                id="dropdown-right"
                variant="primary"
                text="Action"
                toggle-class="text-decoration-none"
                size="sm"
                right
                no-caret
              >
                <template v-slot:button-content>
                  {{$t('Action')}}
                </template>

                 <b-dropdown-item @click="$router.push({ name: 'CustomerLedger', params: { id: props.row.id } })">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="receipt" />
                 {{$t('Customer_Ledger')}}
                </b-dropdown-item>

                <b-dropdown-item
                 v-if="props.row.client_ecommerce == 'yes' && 
                 (currentUserPermissions && currentUserPermissions.includes('Customers_edit'))"
                  @click="Edit_Online_Store_Account(props.row)"
                >
                <lucide-icon class="nav-icon font-weight-bold mr-2" name="pencil" />
                  {{$t('Edit_Online_Store_Account')}}
                </b-dropdown-item>

                <b-dropdown-item
                  v-if="props.row.due > 0 && currentUserPermissions && currentUserPermissions.includes('pay_due')"
                  @click="Pay_due(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="dollar-sign" />
                  {{$t('pay_all_sell_due_at_a_time')}}
                </b-dropdown-item>

                <b-dropdown-item
                  v-if="props.row.return_Due > 0 && currentUserPermissions && currentUserPermissions.includes('pay_sale_return_due')"
                  @click="Pay_return_due(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="dollar-sign" />
                  {{$t('pay_all_sell_return_due_at_a_time')}}
                </b-dropdown-item>

                 <b-dropdown-item 
                  @click="$router.push({ name: 'CustomerDetails', params: { id: props.row.id } })"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="eye" />
                  {{$t('Customer_details')}}
                </b-dropdown-item>

                <b-dropdown-item @click="openPointsModal(props.row)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="pencil" />
                  {{$t('Adjust_Customer_Points')}}
                </b-dropdown-item>

                <b-dropdown-item @click="openOpeningBalanceModal(props.row)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="calculator" />
                  {{ $t('Adjust_Opening_Balance') || 'Adjust Opening Balance' }}
                </b-dropdown-item>

                <b-dropdown-item @click="openPortalClientModal(props.row)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="key" />
                  {{ $t('Portal_Client') || 'Portal Client' }}
                </b-dropdown-item>
               
                <b-dropdown-item
                 v-if="currentUserPermissions && currentUserPermissions.includes('Customers_edit')"
                  @click="Edit_Client(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="pencil" />
                  {{$t('Edit_Customer')}}
                </b-dropdown-item>

                

                <b-dropdown-item
                  title="Delete"
                  v-if="currentUserPermissions.includes('Customers_delete')"
                  @click="Remove_Client(props.row.id)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="x" />
                  {{$t('Delete_Customer')}}
                </b-dropdown-item>
                </b-dropdown>
            </div>
          </span>
        </template>

      </vue-good-table>
    </div>

    <!-- Multiple filters -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- Code Customer   -->
          <b-col md="12">
            <b-form-group :label="$t('CustomerCode')">
              <b-form-input label="Code" :placeholder="$t('SearchByCode')" v-model="Filter_Code"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Name Customer   -->
          <b-col md="12">
            <b-form-group :label="$t('CustomerName')">
              <b-form-input label="Name" :placeholder="$t('SearchByName')" v-model="Filter_Name"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Phone Customer   -->
          <b-col md="12">
            <b-form-group :label="$t('Phone')">
              <b-form-input label="Phone" :placeholder="$t('SearchByPhone')" v-model="Filter_Phone"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Email Customer   -->
          <b-col md="12">
            <b-form-group :label="$t('Email')">
              <b-form-input label="Email" :placeholder="$t('SearchByEmail')" v-model="Filter_Email"></b-form-input>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button @click="Get_Clients(serverParams.page)" variant="primary m-1" size="sm" block>
              <lucide-icon name="filter" />
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger m-1" size="sm" block>
              <lucide-icon name="power" />
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>

    <b-modal
      id="adjust-points-modal"
      ref="pointsModal"
      :title="$t('Adjust_Customer_Points')"
      hide-footer
    >
      <b-form @submit.prevent="updateCustomerPoints">
        <b-form-group :label="$t('Points')">
          <b-form-input
            v-model.number="adjustPointsForm.points"
            type="text"
            required
          />
        </b-form-group>

        <div class="text-right">
          <b-button variant="secondary" @click="$refs.pointsModal.hide()">
            {{ $t('Cancel') }}
          </b-button>
          <b-button variant="primary" type="submit">
             {{ $t('Save') }}
          </b-button>
        </div>
      </b-form>
    </b-modal>

    <b-modal
      id="adjust-opening-balance-modal"
      ref="openingBalanceModal"
      :title="$t('Adjust_Opening_Balance') || 'Adjust Opening Balance'"
      hide-footer
    >
      <div class="mb-3">
        <small class="text-muted">{{ $t('Customer') }}</small>
        <h5 class="mb-1">{{ adjustOpeningBalanceForm.customer_name }}</h5>
        <small class="text-muted">{{ $t('Current_Opening_Balance') || 'Current opening balance' }}</small>
        <h4 :class="adjustOpeningBalanceForm.current > 0 ? 'text-danger font-weight-bold' : 'text-success font-weight-bold'">
          {{ formatPriceWithSymbol(currentUser.currency, adjustOpeningBalanceForm.current || 0, 2) }}
        </h4>
      </div>

      <b-form @submit.prevent="submitOpeningBalanceAdjustment">
        <b-form-group :label="$t('Adjustment_Type') || 'Adjustment'">
          <b-form-radio-group
            v-model="adjustOpeningBalanceForm.mode"
            :options="[
              { text: '+ ' + ($t('Increase') || 'Increase'), value: 'add' },
              { text: '- ' + ($t('Decrease') || 'Decrease'), value: 'subtract' },
              { text: ($t('Set_To') || 'Set to'), value: 'set' }
            ]"
            buttons
            button-variant="outline-primary"
            size="sm"
          />
        </b-form-group>

        <b-form-group :label="$t('Amount')">
          <b-form-input
            v-model.number="adjustOpeningBalanceForm.amount"
            type="number"
            step="0.01"
            :min="adjustOpeningBalanceForm.mode === 'set' ? null : 0"
            placeholder="0.00"
            required
          />
          <small class="text-muted" v-if="adjustOpeningBalanceForm.mode !== 'set'">
            {{ $t('Enter_amount_as_positive_number') || 'Enter the amount as a positive number.' }}
          </small>
        </b-form-group>

        <div class="alert alert-info py-2 px-3 mb-3" v-if="adjustOpeningBalanceForm.amount !== '' && adjustOpeningBalanceForm.amount !== null">
          <small>{{ $t('New_Opening_Balance') || 'New opening balance' }}:</small>
          <strong class="ml-1">
            {{ formatPriceWithSymbol(currentUser.currency, openingBalancePreview, 2) }}
          </strong>
        </div>

        <div class="text-right">
          <b-button variant="secondary" @click="$refs.openingBalanceModal.hide()" :disabled="adjustOpeningBalanceForm.submitting">
            {{ $t('Cancel') }}
          </b-button>
          <b-button variant="primary" type="submit" :disabled="adjustOpeningBalanceForm.submitting">
            {{ $t('Save') }}
          </b-button>
        </div>
      </b-form>
    </b-modal>


    <!-- Modal Pay_due-->
    <b-modal
      hide-footer
      size="lg"
      id="modal_Pay_due"
      title="Pay Due"
    >
      <validation-observer ref="ref_pay_due">
        <b-form @submit.prevent="Submit_Payment_sell_due">
          <b-row>
            <!-- Customer Name -->
            <b-col lg="12" md="12" sm="12" class="mb-3">
              <h5 class="text-primary"><lucide-icon class="mr-2" name="user" />{{ payment.client_name }}</h5>
            </b-col>

            <!-- Summary Cards -->
            <b-col lg="12" md="12" sm="12" class="mb-4">
              <b-row>
                <!-- Opening Balance Card -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <b-card 
                    class="text-center shadow-sm border-left-primary"
                    :class="{'border-left-danger': payment.opening_balance > 0}"
                  >
                    <div class="mb-2">
                      <lucide-icon class="text-primary" name="calendar-days" style="font-size: 2rem;" />
                    </div>
                    <h6 class="text-muted mb-2">{{ $t('Opening_Balance') }}</h6>
                    <h4 class="mb-0" :class="payment.opening_balance > 0 ? 'text-danger font-weight-bold' : 'text-success'">
                      {{ formatPriceWithSymbol(currentUser.currency, payment.opening_balance || 0, 2) }}
                    </h4>
                    <small class="text-muted">{{ $t('Previous_Dues') }}</small>
                  </b-card>
                </b-col>

                <!-- Sales Due Card -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <b-card 
                    class="text-center shadow-sm border-left-warning"
                    :class="{'border-left-danger': payment.due > 0}"
                  >
                    <div class="mb-2">
                      <lucide-icon class="text-warning" name="shopping-cart" style="font-size: 2rem;" />
                    </div>
                    <h6 class="text-muted mb-2">Sales Due</h6>
                    <h4 class="mb-0" :class="payment.due > 0 ? 'text-danger font-weight-bold' : 'text-success'">
                      {{ formatPriceWithSymbol(currentUser.currency, payment.due || 0, 2) }}
                    </h4>
                    <small class="text-muted">Current Sales</small>
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
                    <h6 class="text-muted mb-2">Total Due</h6>
                    <h4 class="mb-0 font-weight-bold" :class="totalDue > 0 ? 'text-danger' : 'text-success'">
                      {{ formatPriceWithSymbol(currentUser.currency, totalDue, 2) }}
                    </h4>
                    <small class="text-muted">Grand Total</small>
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
                    Maximum payment: <strong>{{ formatPriceWithSymbol(currentUser.currency, totalDue, 2) }}</strong>
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
      </validation-observer>
    </b-modal>

    <!-- Modal Pay_return_Due-->
    <validation-observer ref="ref_pay_return_due">
      <b-modal
        hide-footer
        size="md"
        id="modal_Pay_return_due"
        title="Pay Sell Return Due"
      >
        <b-form @submit.prevent="Submit_Payment_sell_return_due">
          <b-row>
          
            <!-- Paying Amount -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider
                name="Amount"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Paying_Amount') + ' ' + '*'">
                  <b-form-input
                   @keyup="Verified_return_paidAmount(payment_return.amount)"
                    label="Amount"
                    :placeholder="$t('Paying_Amount')"
                    v-model.number="payment_return.amount"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Amount-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Amount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                  <span class="badge badge-danger">{{$t('Due')}} : {{currentUser.currency}} {{payment_return.return_Due}}</span>
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
                    v-model="payment_return.payment_method_id"
                    :reduce="label => label.value"
                    :placeholder="$t('PleaseSelect')"
                    :options="payment_methods.map(payment_methods => ({label: payment_methods.name, value: payment_methods.id}))"

                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Account -->
            <b-col lg="12" md="12" sm="12">
              <validation-provider name="Account">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Account')">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="payment_return.account_id"
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
                <b-form-textarea id="textarea" v-model="payment_return.notes" rows="3" max-rows="6"></b-form-textarea>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button
                variant="primary"
                type="submit"
                :disabled="payment_return_Processing"
              ><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
              <div v-once class="typo__p" v-if="payment_return_Processing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>

          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>

    <!-- Modal Show Customer_Invoice-->
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

    <!-- Modal Show_invoice_return-->
    <b-modal hide-footer size="sm" scrollable id="Show_invoice_return" :title="$t('Sell_return_due')">
        <div id="invoice-POS-return">
          <div style="max-width:400px;margin:0px auto">
          <div class="info" >
            <h2 class="text-center">{{company_info.CompanyName}}</h2>

            <p>
                <span>{{$t('date')}} : {{payment_return.date}} <br></span>
                <span >{{$t('Adress')}} : {{company_info.CompanyAdress}} <br></span>
                <span >{{$t('Phone')}} : {{company_info.CompanyPhone}} <br></span>
                <span >{{$t('Customer')}} : {{payment_return.client_name}} <br></span>
              </p>
          </div>

           <table
                class="change mt-3"
                style=" font-size: 10px;"
              >
                <thead>
                  <tr style="background: #eee; ">
                    <!-- <th style="text-align: left;" colspan="1">{{$t('PayeBy')}}:</th> -->
                    <th style="text-align: left;" colspan="2">{{$t('Amount')}}:</th>
                    <th style="text-align: right;" colspan="2">{{$t('Due')}}:</th>
                  </tr>
                </thead>

                <tbody>
                  <tr>
                    <!-- <td style="text-align: left;" colspan="1">{{payment_return.Reglement}}</td> -->
                    <td
                      style="text-align: left;"
                      colspan="2"
                    >{{formatNumber(payment_return.amount ,2)}}</td>
                    <td
                      style="text-align: right;"
                      colspan="2"
                    >{{formatNumber(payment_return.return_Due - payment_return.amount ,2)}}</td>
                  </tr>
                </tbody>
              </table>
          </div>
        </div>
      <button @click="print_return_due()" class="btn btn-outline-primary">
        <lucide-icon name="receipt" />
        {{$t('print')}}
      </button>
    </b-modal>


    <!-- Modal Show Customer Details -->
    <b-modal ok-only size="md" id="showDetails" :title="$t('CustomerDetails')">
      <b-row>
        <b-col lg="12" md="12" sm="12" class="mt-3">
          <table class="table table-striped table-md">
            <tbody>
              <tr>
                <!-- Customer Code -->
                <td>{{$t('CustomerCode')}}</td>
                <th>{{client.code}}</th>
              </tr>
              <tr>
                <!-- Customer Name -->
                <td>{{$t('CustomerName')}}</td>
                <th>{{client.name}}</th>
              </tr>
              <tr>
                <!-- Customer Phone -->
                <td>{{$t('Phone')}}</td>
                <th>{{client.phone}}</th>
              </tr>
              <tr>
                <!-- Customer Email -->
                <td>{{$t('Email')}}</td>
                <th>{{client.email}}</th>
              </tr>
              <tr>
                <!-- Customer country -->
                <td>{{$t('Country')}}</td>
                <th>{{client.country}}</th>
              </tr>
              <tr>
                <!-- Customer City -->
                <td>{{$t('City')}}</td>
                <th>{{client.city}}</th>
              </tr>
              <tr>
                <!-- Customer Adress -->
                <td>{{$t('Adress')}}</td>
                <th>{{client.adresse}}</th>
              </tr>
              <tr>
                <!-- Tax Number -->
                <td>{{$t('Tax_Number')}}</td>
                <th>{{client.tax_number}}</th>
              </tr>

              <tr>
                <!-- Total_Sale_Due -->
                <td>{{$t('Total_Sale_Due')}}</td>
                <th>{{currentUser.currency}} {{client.due}}</th>
              </tr>

               <tr>
                <!-- Total_Sell_Return_Due -->
                <td>{{$t('Total_Sell_Return_Due')}}</td>
                <th>{{currentUser.currency}} {{client.return_Due}}</th>
              </tr>

              <tr>
                <!-- Points -->
                <td>{{$t('Points')}}</td>
                <th>{{client.points}}</th>
              </tr>

            </tbody>
          </table>
          
          <!-- Custom Fields Section -->
          <div v-if="clientCustomFields && clientCustomFields.length > 0" class="mt-4">
            <h6 class="text-primary mb-3">
              <lucide-icon class="mr-2" name="database-zap" />
              {{ $t('CustomFields') }}
            </h6>
            <table class="table table-striped table-md">
              <tbody>
                <tr v-for="field in clientCustomFields" :key="field.id">
                  <td>{{ field.name }}</td>
                  <th>{{ getCustomFieldDisplayValue(field) }}</th>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else-if="clientCustomFields && clientCustomFields.length === 0" class="mt-4">
            <p class="text-muted">{{ $t('NoCustomFields') || 'No custom fields defined for customers.' }}</p>
          </div>
        </b-col>
      </b-row>
    </b-modal>

    <!-- Modal Show Import Clients -->
    <b-modal ok-only ok-title="Cancel" size="md" id="importClients" :title="$t('Import_Customers')">
      <b-form @submit.prevent="Submit_import" enctype="multipart/form-data">
        <b-row>
          <!-- File -->
          <b-col md="12" sm="12" class="mb-3">
            <b-form-group>
              <input type="file" @change="onFileSelected" label="Choose File">
              <b-form-invalid-feedback
                id="File-feedback"
                class="d-block"
              >File must be in xlsx format </b-form-invalid-feedback>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button type="submit" variant="primary" :disabled="ImportProcessing" size="sm" block>{{ $t("submit") }}</b-button>
              <div v-once class="typo__p" v-if="ImportProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              :href="'/import/exemples/import_clients.xlsx'"
              variant="info"
              size="sm"
              block
            >{{ $t("Download_exemple") }}</b-button>
          </b-col>

          <b-col md="12" sm="12">
            <table class="table table-bordered table-sm mt-4">
              <tbody>
                <tr>
                  <td>{{$t('Name')}}</td>
                  <th>
                    <span class="badge badge-outline-success">{{$t('Field_is_required')}}</span>
                  </th>
                </tr>

                <tr>
                  <td>{{$t('Phone')}}</td>
                 
                </tr>

                <tr>
                  <td>{{$t('Email')}}</td>
                  <th>
                    <span class="badge badge-outline-success"></span>
                  </th>
                </tr>

                <tr>
                  <td>{{$t('Country')}}</td>
                </tr>

                <tr>
                  <td>{{$t('City')}}</td>
                </tr>

                <tr>
                  <td>{{$t('Adress')}}</td>
                </tr>
                 <tr>
                  <td>{{$t('Tax_Number')}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>
        </b-row>
      </b-form>
    </b-modal>

     <!-- Modal edit store account for Customer -->
     <validation-observer ref="Edit_Online_Store_Account">
      <b-modal hide-footer size="md" id="form_Edit_Online_Store_Account" title="Edit Store Account">
        <b-form @submit.prevent="Submit_Edit_Online_Store_Account">
          <b-row>
            <b-col md="12" sm="12">
              <h1 class="text-center mt-3 mb-3">{{client_store.name}}</h1>
            </b-col>
            <!-- Customer email -->
            <b-col md="12" sm="12">
              <validation-provider
                name="email Customer"
                :rules="{ required: true, email: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Email') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="email-feedback"
                    label="email"
                    :placeholder="$t('Email')"
                    v-model="client_store.email"
                  ></b-form-input>
                  <b-form-invalid-feedback id="email-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                  <b-alert
                    show
                    variant="danger"
                    class="error mt-1"
                    v-if="email_exist !=''"
                  >{{email_exist}}</b-alert>
                </b-form-group>
              </validation-provider>
            </b-col>

             <!-- New Password -->
             <b-col md="12" sm="12">
              <validation-provider
                name="New password"
                :rules="{min:6 , max:14}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Newpassword')">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Nawpassword-feedback"
                    :placeholder="$t('LeaveBlank')"
                    label="New password"
                    v-model="client_store.NewPassword"
                  ></b-form-input>
                  <b-form-invalid-feedback
                    id="Nawpassword-feedback"
                  >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
          

            <b-col md="12" class="mt-3">
                <b-button variant="primary" type="submit"  :disabled="SubmitProcessing">{{$t('submit')}}</b-button>
                  <div v-once class="typo__p" v-if="SubmitProcessing">
                    <div class="spinner sm spinner-primary mt-3"></div>
                  </div>
            </b-col>

          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>

    <!-- Portal Client Modal -->
    <b-modal
      id="modal_portal_client"
      :title="$t('Portal_Client') || 'Portal Client'"
      hide-footer
      size="md"
      class="portal-client-modal"
      @show="onPortalModalShow"
      @hidden="resetPortalClientForm"
    >
      <div v-if="portalClient.loading" class="text-center py-4">
        <div class="spinner spinner-primary"></div>
        <p class="text-muted mt-2 mb-0">{{ $t('Loading') || 'Loading...' }}</p>
      </div>
      <b-form v-else @submit.prevent="submitPortalClient" class="portal-client-form">
        <div class="portal-client-header mb-4">
          <div class="d-flex align-items-center">
            <div class="portal-client-avatar rounded-circle d-flex align-items-center justify-content-center">
              <lucide-icon class="text-white" name="user" />
            </div>
            <div class="ml-3">
              <h6 class="mb-0 font-weight-bold text-dark">{{ portalClient.client_name }}</h6>
              <small class="text-muted">{{ $t('Customer') || 'Customer' }}</small>
            </div>
          </div>
        </div>

        <b-alert v-if="portalClient.errors.length" variant="danger" dismissible show class="small">
          <ul class="mb-0 pl-3" v-if="portalClient.errors.length > 1">
            <li v-for="(err, idx) in portalClient.errors" :key="idx">{{ err }}</li>
          </ul>
          <span v-else>{{ portalClient.errors[0] }}</span>
        </b-alert>

        <div class="portal-status-card mb-4" :class="portalClient.enabled ? 'portal-enabled' : 'portal-disabled'">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
              <div class="portal-status-icon rounded-circle d-flex align-items-center justify-content-center mr-3">
                <lucide-icon class="text-white" :name="portalClient.enabled ? 'key' : 'lock'" />
              </div>
              <div>
                <span class="font-weight-bold d-block">{{ portalClient.enabled ? ($t('Portal_Enabled') || 'Portal enabled') : ($t('Portal_Disabled') || 'Portal disabled') }}</span>
                <small class="text-muted" v-if="portalClient.portal_email">{{ portalClient.portal_email }}</small>
              </div>
            </div>
            <b-form-checkbox v-model="portalClient.enabled">
              {{ $t('Portal_Enable_Access') }}
            </b-form-checkbox>
          </div>
        </div>

        <template v-if="portalClient.enabled">
          <b-form-group
            :label="$t('Email') + ' *'"
            label-class="font-weight-semibold"
            :invalid-feedback="portalClient.emailError"
            :state="portalClient.emailError ? false : null"
          >
            <b-form-input
              v-model="portalClient.email"
              type="email"
              :placeholder="$t('Email')"
              class="form-control-lg"
              :state="portalClient.emailError ? false : null"
              aria-describedby="portal-email-error"
              @input="portalClient.emailError = ''"
            />
            <small class="text-muted d-block mt-1">{{ $t('Portal_Email_Help') || 'Email the client will use to log in.' }}</small>
            <b-form-invalid-feedback id="portal-email-error" v-if="portalClient.emailError">
              {{ portalClient.emailError }}
            </b-form-invalid-feedback>
          </b-form-group>

          <b-form-group :label="($t('password') || 'Password') + ' (' + ($t('LeaveBlank') || 'Leave blank') + ')'" label-class="font-weight-semibold">
            <div class="position-relative">
              <b-form-input
                v-model="portalClient.password"
                :type="portalClient.showPassword ? 'text' : 'password'"
                :placeholder="$t('Portal_Password_Optional') || 'Leave blank to keep current password'"
                class="form-control-lg pr-5"
                autocomplete="new-password"
              />
              <b-button
                variant="link"
                class="portal-password-toggle position-absolute"
                @click="portalClient.showPassword = !portalClient.showPassword"
              >
                <lucide-icon :name="portalClient.showPassword ? 'eye-off' : 'eye'" />
              </b-button>
            </div>
          </b-form-group>
        </template>
        <template v-else>
          <p class="text-muted small mb-0">{{ $t('Portal_Disable_Confirm') || 'Disable portal access for this customer?' }}</p>
        </template>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
          <b-button variant="secondary" @click="$bvModal.hide('modal_portal_client')">
            {{ $t('Cancel') }}
          </b-button>
          <b-button
            variant="primary"
            type="submit"
            :disabled="portalClient.sending || (portalClient.enabled && !portalClient.email.trim())"
          >
            <span v-if="portalClient.sending" class="d-inline-block mr-2">
              <b-spinner small></b-spinner>
            </span>
            {{ $t('Save') }}
          </b-button>
        </div>
      </b-form>
    </b-modal>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: {
    title: "Customer"
  },
  data() {
    return {

      clients_without_ecommerce:'',
      module_name:'',
      
      isLoading: true,
      SubmitProcessing:false,
      ImportProcessing:false,
      paymentProcessing:false,
      payment_return_Processing:false,
      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      showDropdown: false,
      payment: {
        client_id: "",
        client_name: "",
        account_id: "",
        date:"",
        due: "",
        opening_balance: 0,
        amount: "",
        notes: "",
        payment_method_id: "",
      },
      payment_methods:[],
       payment_return: {
        client_id: "",
        client_name: "",
        account_id: "",
        date:"",
        return_Due: "",
        amount: "",
        notes: "",
        payment_method_id: "",
      },
      company_info:{},
      price_format_key: null,
      selectedIds: [],
      totalRows: "",
      search: "",
      limit: "10",
      Filter_Name: "",
      Filter_Code: "",
      Filter_Phone: "",
      Filter_Email: "",
      clients: [],
      accounts: [],
      editmode: false,
      import_clients: "",
      data: new FormData(),
      client: {
        id: "",
        name: "",
        code: "",
        email: "",
        phone: "",
        country: "",
        city: "",
        adresse: "",
        tax_number: "",
        is_royalty_eligible: "",

      },
      adjustPointsForm: {
        customer_id: null,
        points: 0
      },
      adjustOpeningBalanceForm: {
        customer_id: null,
        customer_name: "",
        current: 0,
        mode: "add",
        amount: "",
        submitting: false
      },
      client_store: {
        name: "",
        email: "",
        password: "",
        NewPassword: null,
      },
      email_exist : "",
      clientCustomFields: [],
      portalClient: {
        client_id: null,
        client_name: "",
        email: "",
        password: "",
        portal_email: "",
        enabled: false,
        loading: false,
        sending: false,
        showPassword: false,
        errors: [],
        emailError: "",
      },
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


    totalDue() {
      const openingBalance = parseFloat(this.payment.opening_balance) || 0;
      const salesDue = parseFloat(this.payment.due) || 0;
      return openingBalance + salesDue;
    },

    openingBalancePreview() {
      const current = parseFloat(this.adjustOpeningBalanceForm.current) || 0;
      const amount = Math.abs(parseFloat(this.adjustOpeningBalanceForm.amount) || 0);
      switch (this.adjustOpeningBalanceForm.mode) {
        case "add": return current + amount;
        case "subtract": return current - amount;
        case "set": return parseFloat(this.adjustOpeningBalanceForm.amount) || 0;
        default: return current;
      }
    },

    paymentMethodName() {
      if (!this.payment.payment_method_id || !this.payment_methods.length) {
        return '-';
      }
      const methodId = parseInt(this.payment.payment_method_id);
      const method = this.payment_methods.find(m => parseInt(m.id) === methodId);
      return method ? method.name : '-';
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
          label: this.$t("Code"),
          field: "code",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Name"),
          field: "name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Firstname"),
          field: "firstname",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("lastname"),
          field: "lastname",
          tdClass: "text-left",
          thClass: "text-left"
        },

        {
          label: this.$t("Phone"),
          field: "phone",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Email"),
          field: "email",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Points"),
          field: "points",
          tdClass: "text-left",
          thClass: "text-left"
        },
          {
          label: this.$t("Credit_Limit"),
          field: "credit_limit",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Opening_Balance"),
          field: "opening_balance",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
      
        {
          label: this.$t("Total_Sale_Due"),
          field: "due",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Total_Sell_Return_Due"),
          field: "return_Due",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }
  },

  methods: {

    openPointsModal(customer) {
      this.adjustPointsForm.customer_id = customer.id;
      this.adjustPointsForm.points = customer.points; // preload existing
      this.$refs.pointsModal.show();
    },

    openPortalClientModal(row) {
      this.portalClient.client_id = row.id;
      this.portalClient.client_name = row.name;
      this.portalClient.email = row.email || "";
      this.portalClient.password = "";
      this.portalClient.errors = [];
      this.$bvModal.show("modal_portal_client");
    },

    onPortalModalShow() {
      if (!this.portalClient.client_id) return;
      this.portalClient.loading = true;
      this.portalClient.errors = [];
      axios
        .get("clients/" + this.portalClient.client_id + "/portal-status")
        .then(({ data }) => {
          this.portalClient.portal_email = data.portal_email || "";
          this.portalClient.enabled = !!data.portal_enabled;
          if (!this.portalClient.email && data.portal_email) {
            this.portalClient.email = data.portal_email;
          }
          if (!this.portalClient.email) {
            const client = this.clients.find(c => c.id === this.portalClient.client_id);
            if (client && client.email) this.portalClient.email = client.email;
          }
        })
        .catch(() => {
          this.portalClient.enabled = false;
          this.portalClient.portal_email = "";
        })
        .finally(() => {
          this.portalClient.loading = false;
        });
    },

    resetPortalClientForm() {
      this.portalClient.client_id = null;
      this.portalClient.client_name = "";
      this.portalClient.email = "";
      this.portalClient.password = "";
      this.portalClient.portal_email = "";
      this.portalClient.enabled = false;
      this.portalClient.loading = false;
      this.portalClient.sending = false;
      this.portalClient.showPassword = false;
      this.portalClient.errors = [];
      this.portalClient.emailError = "";
    },

    submitPortalClient() {
      this.portalClient.errors = [];
      this.portalClient.emailError = "";
      if (this.portalClient.enabled && !this.portalClient.email.trim()) {
        const msg = this.$t("Email") + " " + (this.$t("is_required") || "is required");
        this.portalClient.errors.push(msg);
        this.portalClient.emailError = msg;
        return;
      }
      this.portalClient.sending = true;
      const clientId = this.portalClient.client_id;
      if (this.portalClient.enabled) {
        axios
          .post("clients/" + clientId + "/portal-enable", {
            email: this.portalClient.email.trim(),
            password: this.portalClient.password ? this.portalClient.password : undefined,
          })
          .then(({ data }) => {
            this.makeToast("success", data.message || this.$t("Successfully_Updated"), this.$t("Success"));
            this.$bvModal.hide("modal_portal_client");
            this.Get_Clients(this.serverParams.page);
          })
          .catch((e) => {
            const data = e?.response?.data || e;
            const messages = [];
            if (data?.errors) {
              Object.keys(data.errors).forEach((field) => {
                const msgs = Array.isArray(data.errors[field]) ? data.errors[field] : [data.errors[field]];
                msgs.forEach((msg) => messages.push(msg));
                if (field === "email" && msgs.length) {
                  this.portalClient.emailError = msgs[0];
                }
              });
              this.portalClient.errors = messages.length ? messages : [data.message || "Validation failed."];
            } else {
              const msg = data?.message || this.$t("InvalidData");
              this.portalClient.errors = [msg];
              if (msg && !this.portalClient.emailError) this.portalClient.emailError = msg;
            }
            this.makeToast("danger", this.portalClient.errors.join(" ") || this.$t("InvalidData"), this.$t("Failed"));
          })
          .finally(() => {
            this.portalClient.sending = false;
          });
      } else {
        axios
          .post("clients/" + clientId + "/portal-disable")
          .then(({ data }) => {
            this.makeToast("success", data.message || (this.$t("Portal_Disabled") || "Portal disabled"), this.$t("Success"));
            this.$bvModal.hide("modal_portal_client");
            this.Get_Clients(this.serverParams.page);
          })
          .catch(() => {
            this.portalClient.errors = [this.$t("InvalidData")];
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          })
          .finally(() => {
            this.portalClient.sending = false;
          });
      }
    },

    updateCustomerPoints() {
      axios.post(`customers/${this.adjustPointsForm.customer_id}/update-points`, {
        points: this.adjustPointsForm.points
      }).then(() => {
        this.makeToast(
            "success",
            'Points updated successfully',
            'success'
          );

        this.$refs.pointsModal.hide();
        this.Get_Clients(this.serverParams.page);
      }).catch((error) => {
        this.makeToast(
            "danger",
            'Error updating points',
            this.$t("Failed")
          );
      });
    },

    openOpeningBalanceModal(customer) {
      this.adjustOpeningBalanceForm.customer_id = customer.id;
      this.adjustOpeningBalanceForm.customer_name = customer.name;
      this.adjustOpeningBalanceForm.current = parseFloat(customer.opening_balance) || 0;
      this.adjustOpeningBalanceForm.mode = "add";
      this.adjustOpeningBalanceForm.amount = "";
      this.adjustOpeningBalanceForm.submitting = false;
      this.$refs.openingBalanceModal.show();
    },

    submitOpeningBalanceAdjustment() {
      const amount = parseFloat(this.adjustOpeningBalanceForm.amount);
      if (isNaN(amount)) {
        this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        return;
      }

      this.adjustOpeningBalanceForm.submitting = true;
      axios.post(`customers/${this.adjustOpeningBalanceForm.customer_id}/adjust-opening-balance`, {
        mode: this.adjustOpeningBalanceForm.mode,
        amount: amount
      }).then(() => {
        this.makeToast(
          "success",
          this.$t("Successfully_Updated") || "Opening balance updated",
          this.$t("Success")
        );
        this.$refs.openingBalanceModal.hide();
        this.Get_Clients(this.serverParams.page);
      }).catch(() => {
        this.makeToast(
          "danger",
          this.$t("Failed_to_update_opening_balance") || "Failed to update opening balance",
          this.$t("Failed")
        );
      }).finally(() => {
        this.adjustOpeningBalanceForm.submitting = false;
      });
    },



     //-------------------------------- Reset Form -------------------------------\\
     reset_Form_account_store() {
        this.client_store = {
          id: "",
          name: "",
          email: "",
          password: "",
          NewPassword: null,
        };
        this.email_exist= "";
    },


     //------------------------------ Show Modal (Edit Client) -------------------------------\\
     Edit_Online_Store_Account(client) {
      this.Get_Clients(this.serverParams.page);
      this.reset_Form_account_store();
      this.get_client_store_data(client.id);
      this.client_store.NewPassword = null;
      setTimeout(() => {
        this.$bvModal.show("form_Edit_Online_Store_Account");
      }, 500);
    },

      //------------------------------------ Get Products By Warehouse -------------------------\\

      get_client_store_data(id) {
        NProgress.start();
        NProgress.set(0.1);
      axios
        .get("get_client_store_data/" + id)
         .then(response => {
            this.client_store = response.data;
             NProgress.done();

            })
          .catch(error => {
            NProgress.done();
          });
    },



    //------------- Submit Validation Edit_Online_Store_Account
    Submit_Edit_Online_Store_Account() {
      this.$refs.Edit_Online_Store_Account.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.Update_Online_Store_Account();
        }
      });
    },

    //---------------------------------------- Update_Online_Store_Account-------------------------------\\
    Update_Online_Store_Account() {
      this.SubmitProcessing = true;
      axios

      .put("clients_without_ecommerce/" + this.client_store.client_id, {
          client_id: this.client_store.client_id,
          email: this.client_store.email,
          NewPassword: this.client_store.NewPassword,
        })

        .then(response => {
          Fire.$emit("Event_edit_store_account");

          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          this.SubmitProcessing = false;
        })
        .catch(error => {

          if (error.errors.email.length > 0) {
            this.email_exist = error.errors.email[0];
          }
          
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },


    //------ update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Clients(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Clients(1);
      }
    },

    //---- Event Select Rows
    selectionChanged({ selectedRows }) {
      this.selectedIds = [];
      selectedRows.forEach((row, index) => {
        this.selectedIds.push(row.id);
      });
    },

    //------ Event Sort Change
    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Clients(this.serverParams.page);
    },

    //------ Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Clients(this.serverParams.page);
    },

    //------ Event Validation State
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_Name = "";
      this.Filter_Code = "";
      this.Filter_Phone = "";
      this.Filter_Email = "";
      this.Get_Clients(this.serverParams.page);
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //------ Format Number
    formatNumber(value) {
      if (value === null || value === undefined) return '0.00';
      const num = parseFloat(value) || 0;
      return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    //--------------------------------- Customers PDF -------------------------------\\
    clients_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        pdf.addFont(fontPath, "Vazirmatn", "normal");
        pdf.addFont(fontPath, "Vazirmatn", "bold");
      } catch(e) {}
      pdf.setFont("Vazirmatn", "normal");

      const headers = [
        self.$t("Code"),
        self.$t("Name"),
        self.$t("Phone"),
        self.$t("City"),
        "Points",
        self.$t("Total_Sale_Due"),
        self.$t("Total_Sell_Return_Due")
      ];

      const body = (self.clients || []).map(client => ([
        client.code,
        client.name,
        client.phone,
        client.city,
        client.points,
        client.due,
        client.return_Due
      ]));

      const marginX = 40;
      const rtl =
        (self.$i18n && ['ar','fa','ur','he'].includes(self.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      autoTable(pdf, {
        head: [headers],
        body: body,
        startY: 110,
        theme: 'striped',
        margin: { left: marginX, right: marginX },
        styles: { font: 'Vazirmatn', fontSize: 9, cellPadding: 4, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [63,81,181], textColor: 255 },
        alternateRowStyles: { fillColor: [245,247,250] },
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
          const title = self.$t('CustomersList') || 'Customer List';
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

      pdf.save("Customer_List.pdf");
    },


    //--------------------------------------- Get All Clients -------------------------------\\
    Get_Clients(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "clients?page=" +
            page +
            "&name=" +
            this.Filter_Name +
            "&code=" +
            this.Filter_Code +
            "&phone=" +
            this.Filter_Phone +
            "&email=" +
            this.Filter_Email +
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
          this.clients = response.data.clients;
          this.company_info = response.data.company_info;
          this.totalRows = response.data.totalRows;
          this.clients_without_ecommerce = response.data.clients_without_ecommerce;
          this.module_name = response.data.module_name;
          this.accounts = response.data.accounts;
          this.payment_methods = response.data.payment_methods;
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

    //----------------------------------- Show import Client -------------------------------\\
    Show_import_clients() {
      this.$bvModal.show("importClients");
    },

    //------------------------------ Event Import clients -------------------------------\\
    onFileSelected(e) {
      this.import_clients = "";
      let file = e.target.files[0];
      let errorFilesize;

      if (file["size"] < 1048576) {
        // 1 mega = 1,048,576 Bytes
        errorFilesize = false;
      } else {
        this.makeToast(
          "danger",
          this.$t("file_size_must_be_less_than_1_mega"),
          this.$t("Failed")
        );
      }

      if (errorFilesize === false) {
        this.import_clients = file;
      }
    },

   //----------------------------------------Submit  import clients-----------------\\
    Submit_import() {
      NProgress.start();
      NProgress.set(0.1);

      this.ImportProcessing = true;
      this.data.append("clients", this.import_clients);

      axios
        .post("clients/import/csv", this.data)
        .then(response => {
          this.ImportProcessing = false;
          NProgress.done();

          if (response.data.status === true) {
            this.makeToast("success", this.$t("Successfully_Imported"), this.$t("Success"));
            Fire.$emit("Event_import");
          } else {
            // Show message returned from backend
            this.makeToast("danger", response.data.message || this.$t("Import_failed"), this.$t("Failed"));
          }
        })
        .catch(error => {
          this.ImportProcessing = false;
          NProgress.done();

          if (error.response) {
            // Show Laravel validation error
            if (error.response.status === 422 && error.response.data.errors) {
              const firstError = Object.values(error.response.data.errors)[0][0];
              this.makeToast("danger", firstError, this.$t("Failed"));
            } else {
              // Other backend exceptions
              const message = error.response.data.message || this.$t("Please_follow_the_import_instructions");
              this.makeToast("danger", message, this.$t("Failed"));
            }
          } else {
            // Network error or no response
            this.makeToast("danger", this.$t("Network_or_server_error"), this.$t("Failed"));
          }
        });
    },

    //----------------------------------- Show Details Client -------------------------------\\
    showDetails(client) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.client = client;
      
      // Load custom fields and their values
      Promise.all([
        axios.get("custom-fields?entity_type=client"),
        axios.get("custom-field-values", {
          params: {
            entity_type: "App\\Models\\Client",
            entity_id: client.id
          }
        })
      ])
        .then(([fieldsResponse, valuesResponse]) => {
          const allFields = fieldsResponse.data.custom_fields || [];
          const fieldValues = valuesResponse.data.success && valuesResponse.data.values 
            ? valuesResponse.data.values 
            : {};

          // Map all custom fields with their values (or empty if no value)
          this.clientCustomFields = allFields.map(field => {
            const fieldValue = fieldValues[field.id];
            return {
              id: field.id,
              name: field.name,
              field_type: field.field_type,
              value: fieldValue ? fieldValue.value : null
            };
          });

          NProgress.done();
          Fire.$emit("Get_Details_customers");
        })
        .catch(error => {
          console.error('Error loading custom fields:', error);
          this.clientCustomFields = [];
          NProgress.done();
          Fire.$emit("Get_Details_customers");
        });
    },

    //----------------------------------- Get Custom Field Display Value -------------------------------\\
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
    },

    //------------------------------ Navigate to Create Customer Page -------------------------------\\
    New_Client() {
      this.$router.push({ name: 'Create_Customer' });
    },

    //------------------------------ Navigate to Edit Customer Page -------------------------------\\
    Edit_Client(client) {
      this.$router.push({ name: 'Edit_Customer', params: { id: client.id } });
    },

   
    //---------------------------------------- Create new Client -------------------------------\\
    Create_Client() {
      this.SubmitProcessing = true;
      axios
        .post("clients", {
          name: this.client.name,
          email: this.client.email,
          phone: this.client.phone,
          tax_number: this.client.tax_number,
          country: this.client.country,
          city: this.client.city,
          adresse: this.client.adresse,
          is_royalty_eligible: this.client.is_royalty_eligible
        })
        .then(response => {
          Fire.$emit("Event_Customer");

          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
          this.SubmitProcessing = false;
        })
        .catch(error => {
          
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    //----------------------------------- Update Client -------------------------------\\
    Update_Client() {
      this.SubmitProcessing = true;
      axios
        .put("clients/" + this.client.id, {
          name: this.client.name,
          email: this.client.email,
          phone: this.client.phone,
          tax_number: this.client.tax_number,
          country: this.client.country,
          city: this.client.city,
          adresse: this.client.adresse,
          is_royalty_eligible: this.client.is_royalty_eligible
        })
        .then(response => {
          Fire.$emit("Event_Customer");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          this.SubmitProcessing = false;
        })
        .catch(error => {
         
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    //-------------------------------- Reset Form -------------------------------\\
    reset_Form() {
      this.client = {
        id: "",
        name: "",
        email: "",
        phone: "",
        country: "",
        tax_number: "",
        city: "",
        adresse: "",
        is_royalty_eligible: "",
      };
    },

    //------------------------------- Remove Client -------------------------------\\
    Remove_Client(id) {
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
          axios
            .delete("clients/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("Delete_Customer");
            })
            .catch(() => {
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete.ClientError"),
                "warning"
              );
            });
        }
      });
    },

    //---- Delete Clients by selection

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
            .post("clients/delete/by_selection", {
              selectedIds: this.selectedIds
            })
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("Delete_Customer");
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
    },


    //------ Validate Form Submit_Payment_sell_due
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
        }
       else {
            this.Submit_Pay_due();
        }

      });
    },

      //---------- keyup paid Amount

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

      //-------------------------------- reset_Form_payment-------------------------------\\
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

    //------------------------------ Show Modal Pay_due-------------------------------\\
    Pay_due(row) {
      this.reset_Form_payment();
      this.payment.client_id = row.id;
      this.payment.client_name = row.name;
      this.payment.account_id = null;
      this.payment.payment_method_id = null;
      this.payment.due = row.due || 0;
      this.payment.opening_balance = row.opening_balance || 0;
      this.payment.date = new Date().toISOString().slice(0, 10);
      setTimeout(() => {
        this.$bvModal.show("modal_Pay_due");
      }, 500);
      
    },

     //------------------------------ Print Customer_Invoice -------------------------\\
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

     //---------------------------------------- Submit_Pay_due-------------------------------\\
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
          
          // Refresh clients data without affecting receipt
          this.Get_Clients(this.serverParams.page);

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

    //-------------------------------Pay sell return due -----------------------------------\\

     //------ Validate Form Submit_Payment_sell_return_due
    Submit_Payment_sell_return_due() {
      this.$refs.ref_pay_return_due.validate().then(success => {
        if (!success) {
           this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else if (this.payment_return.amount > this.payment_return.return_Due) {
          this.makeToast(
            "warning",
            this.$t("Paying_amount_is_greater_than_Total_Due"),
            this.$t("Warning")
          );
          this.payment_return.amount = 0;
        }
       else {
            this.Submit_Pay_return_due();
        }

      });
    },

      //---------- keyup paid Amount

    Verified_return_paidAmount() {
      if (isNaN(this.payment_return.amount)) {
        this.payment_return.amount = 0;
      } else if (this.payment_return.amount > this.payment_return.return_Due) {
        this.makeToast(
          "warning",
          this.$t("Paying_amount_is_greater_than_Total_Due"),
          this.$t("Warning")
        );
        this.payment_return.amount = 0;
      } 
    },

      //-------------------------------- reset_Form_payment-------------------------------\\
    reset_Form_payment_return_due() {
      this.payment_return = {
        client_id: "",
        client_name: "",
        account_id: "",
        date: "",
        return_Due: "",
        amount: "",
        notes: "",
        payment_method_id: "",
      };
    },

    //------------------------------ Show Modal Pay_return_due-------------------------------\\
    Pay_return_due(row) {
      this.reset_Form_payment_return_due();
      this.payment_return.client_id = row.id;
      this.payment_return.client_name = row.name;
      this.payment_return.return_Due = row.return_Due;
      this.payment_return.account_id = null;
      this.payment_return.payment_method_id = null;
      this.payment_return.date = new Date().toISOString().slice(0, 10);
      setTimeout(() => {
        this.$bvModal.show("modal_Pay_return_due");
      }, 500);
      
    },

     //------------------------------ Print Customer_Invoice -------------------------\\
    print_return_due() {
      var divContents = document.getElementById("invoice-POS-return").innerHTML;
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

     //---------------------------------------- Submit_Pay_due-------------------------------\\
    Submit_Pay_return_due() {
      this.payment_return_Processing = true;
      axios
        .post("clients_pay_return_due", {
          client_id: this.payment_return.client_id,
          amount: this.payment_return.amount,
          notes: this.payment_return.notes,
          payment_method_id: this.payment_return.payment_method_id,
          account_id: this.payment_return.account_id,
        })
        .then(response => {
          Fire.$emit("Event_pay_return_due");

          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
          this.payment_return_Processing = false;
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.payment_return_Processing = false;
        });
    },

    //------------------------------ Price Formatting -------------------------\\
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
          maximumFractionDigits: dec || 2
        });
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },



  }, // END METHODS

  //----------------------------- Created function-------------------

  created: function() {
    this.Get_Clients(1);

    Fire.$on("get_credit_card_details", () => {
      setTimeout(() => NProgress.done(), 500);
      this.$bvModal.show("show_credit_card_details");
    });

    Fire.$on("Get_Details_customers", () => {
      setTimeout(() => NProgress.done(), 500);
      this.$bvModal.show("showDetails");
    });

    // Event_pay_due is now handled directly in Submit_Pay_due method
    // This listener is kept for backwards compatibility but is no longer needed
    Fire.$on("Event_pay_due", () => {
      // Handled in Submit_Pay_due method now
    });

    Fire.$on("Event_pay_return_due", () => {
      setTimeout(() => {
        this.Get_Clients(this.serverParams.page);
        this.$bvModal.hide("modal_Pay_return_due");
      }, 500);
       this.$bvModal.show("Show_invoice_return");
    });

    Fire.$on("Event_edit_store_account", () => {
      setTimeout(() => {
        this.Get_Clients(this.serverParams.page);
        this.$bvModal.hide("form_Edit_Online_Store_Account");
      }, 500);
    });


    Fire.$on("Event_Customer", () => {
      setTimeout(() => {
        this.Get_Clients(this.serverParams.page);
      }, 500);
    });

    Fire.$on("Delete_Customer", () => {
      setTimeout(() => {
        this.Get_Clients(this.serverParams.page);
      }, 500);
    });

    Fire.$on("Event_import", () => {
      setTimeout(() => {
        this.Get_Clients(this.serverParams.page);
        this.$bvModal.hide("importClients");
      }, 500);
    });
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

/* Portal Client Modal */
.portal-client-modal .modal-header {
  border-bottom: 1px solid #e9ecef;
  padding: 1rem 1.25rem;
}
.portal-client-modal .modal-body {
  padding: 1.25rem;
}
.portal-client-header {
  padding: 0.5rem 0;
}
.portal-client-avatar {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #5b6bf6 0%, #3f51b5 100%);
  box-shadow: 0 4px 12px rgba(91, 107, 246, 0.35);
}
.portal-status-card {
  padding: 1rem 1.25rem;
  border-radius: 12px;
  border: 1px solid #e9ecef;
  transition: border-color 0.2s, background 0.2s;
}
.portal-status-card.portal-enabled {
  background: linear-gradient(135deg, rgba(40, 167, 69, 0.08) 0%, rgba(40, 167, 69, 0.04) 100%);
  border-color: rgba(40, 167, 69, 0.35);
}
.portal-status-card.portal-disabled {
  background: #f8f9fa;
  border-color: #e9ecef;
}
.portal-status-icon {
  width: 40px;
  height: 40px;
}
.portal-enabled .portal-status-icon {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}
.portal-disabled .portal-status-icon {
  background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
}
.portal-password-toggle {
  right: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  padding: 0.25rem;
  color: #6c757d;
  text-decoration: none;
}
.portal-password-toggle:hover {
  color: #495057;
}
.font-weight-semibold {
  font-weight: 600;
}
</style>


