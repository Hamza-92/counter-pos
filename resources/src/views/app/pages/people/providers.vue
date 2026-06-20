<template>
  <div class="main-content">
    <breadcumb :page="$t('SuppliersManagement')" :folder="$t('Suppliers')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="providers"
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
        <div slot="selected-row-actions" v-if="currentUserPermissions.includes('Suppliers_delete')">
          <button class="btn btn-danger btn-sm" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info m-1" size="sm" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Providers_PDF()" size="sm" variant="outline-success m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="providers"
              :columns="columns"
              :file-name="'providers'"
              :file-type="'xlsx'"
              :sheet-name="'providers'"
              >
              <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
         
          <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_import')"
            :to="{ name: 'Import_Suppliers' }"
            class="btn btn-info btn-sm m-1"
          >
            <lucide-icon name="download" />
            {{ $t("Import_Suppliers") }}
          </router-link>

          <b-button
            @click="New_Provider()"
            size="sm"
            variant="btn btn-primary btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_add')"
          >
            <lucide-icon name="plus" />
            {{$t('Add')}}
          </b-button>
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

                <b-dropdown-item
                  v-if="props.row.due > 0 && currentUserPermissions && currentUserPermissions.includes('pay_supplier_due')"
                  @click="Pay_due(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="dollar-sign" />
                  {{$t('pay_all_purchase_due_at_a_time')}}
                </b-dropdown-item>

                 <b-dropdown-item
                  v-if="props.row.return_Due > 0 && currentUserPermissions && currentUserPermissions.includes('pay_purchase_return_due')"
                  @click="Pay_return_due(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="dollar-sign" />
                  {{$t('pay_all_purchase_return_due_at_a_time')}}
                </b-dropdown-item>

                <b-dropdown-item
                  @click="showDetails(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="eye" />
                  {{$t('Provider_details')}}
                </b-dropdown-item>

                <b-dropdown-item
                 v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_edit')"
                  @click="Edit_Provider(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="pencil" />
                  {{$t('Edit_Provider')}}
                </b-dropdown-item>

                <b-dropdown-item
                  title="Delete"
                  v-if="currentUserPermissions.includes('Suppliers_delete')"
                  @click="Remove_Provider(props.row.id)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="x" />
                  {{$t('Delete_Provider')}}
                </b-dropdown-item>
                </b-dropdown>
            </div>
          </span>

        </template>
      </vue-good-table>
    </div>

    <!-- Multiple Filters  -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- Code Provider   -->
          <b-col md="12">
            <b-form-group :label="$t('SupplierCode')">
              <b-form-input label="Code" :placeholder="$t('SearchByCode')" v-model="Filter_Code"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Name Provider   -->
          <b-col md="12">
            <b-form-group :label="$t('SupplierName')">
              <b-form-input label="Name" :placeholder="$t('SearchByName')" v-model="Filter_Name"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Phone Provider   -->
          <b-col md="12">
            <b-form-group :label="$t('Phone')">
              <b-form-input label="Phone" :placeholder="$t('SearchByPhone')" v-model="Filter_Phone"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Email Provider   -->
          <b-col md="12">
            <b-form-group :label="$t('Email')">
              <b-form-input label="Email" :placeholder="$t('SearchByEmail')" v-model="Filter_Email"></b-form-input>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Get_Providers(serverParams.page)"
              variant="primary m-1"
              size="sm"
              block
            >
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


    <!-- Modal Pay_due-->
    <validation-observer ref="ref_pay_due">
      <b-modal
        hide-footer
        size="md"
        id="modal_Pay_due"
        title="Pay Due"
      >
        <b-form @submit.prevent="Submit_Payment_Purchase_due">
          <b-row>
          
            <!-- Paying Amount  -->
            <b-col lg="12" md="12" sm="12">
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
                  <span class="badge badge-danger">{{$t('Due')}} : {{currentUser.currency}} {{payment.due}}</span>
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

    <!-- Modal Pay_return_Due-->
    <validation-observer ref="ref_pay_return_due">
      <b-modal
        hide-footer
        size="md"
        id="modal_Pay_return_due"
        title="Pay Purchase Return Due"
      >
        <b-form @submit.prevent="Submit_Payment_purchase_return_due">
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
    <b-modal hide-footer size="sm" scrollable id="Show_invoice" :title="$t('Provider_Credit_Note')">
        <div id="invoice-POS">
          <div style="max-width:400px;margin:0px auto">
          <div class="info" >
            <h2 class="text-center">{{company_info.CompanyName}}</h2>

            <p>
                <span>{{$t('date')}} : {{payment.date}} <br></span>
                <span >{{$t('Adress')}} : {{company_info.CompanyAdress}} <br></span>
                <span >{{$t('Phone')}} : {{company_info.CompanyPhone}} <br></span>
                <span >{{$t('Customer')}} : {{payment.provider_name}} <br></span>
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
                    <!-- <td style="text-align: left;" colspan="1">{{payment.Reglement}}</td> -->
                    <td
                      style="text-align: left;"
                      colspan="2"
                    >{{formatNumber(payment.amount ,2)}}</td>
                    <td
                      style="text-align: right;"
                      colspan="2"
                    >{{formatNumber(payment.due - payment.amount ,2)}}</td>
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
    <b-modal hide-footer size="sm" scrollable id="Show_invoice_return" :title="$t('Purchase_return_due')">
         <div id="invoice-POS-return">
          <div style="max-width:400px;margin:0px auto">
          <div class="info" >
            <h2 class="text-center">{{company_info.CompanyName}}</h2>

            <p>
                <span>{{$t('date')}} : {{payment_return.date}} <br></span>
                <span >{{$t('Adress')}} : {{company_info.CompanyAdress}} <br></span>
                <span >{{$t('Phone')}} : {{company_info.CompanyPhone}} <br></span>
                <span >{{$t('Customer')}} : {{payment_return.provider_name}} <br></span>
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

    <!-- Show details Provider -->
    <b-modal ok-only size="md" id="showDetails" :title="$t('SupplierDetails')">
      <b-row>
        <b-col lg="12" md="12" sm="12" class="mt-3">
          <table class="table table-striped table-md">
            <tbody>
              <tr>
                <!-- Provider Code -->
                <td>{{$t('SupplierCode')}}</td>
                <th>{{provider.code}}</th>
              </tr>
              <tr>
                <!-- Provider Name -->
                <td>{{$t('SupplierName')}}</td>
                <th>{{provider.name}}</th>
              </tr>
              <tr>
                <!-- Provider Phone -->
                <td>{{$t('Phone')}}</td>
                <th>{{provider.phone}}</th>
              </tr>
              <tr>
                <!-- Provider Email -->
                <td>{{$t('Email')}}</td>
                <th>{{provider.email}}</th>
              </tr>
              <tr>
                <!-- Provider country -->
                <td>{{$t('Country')}}</td>
                <th>{{provider.country}}</th>
              </tr>
              <tr>
                <!-- Provider City -->
                <td>{{$t('City')}}</td>
                <th>{{provider.city}}</th>
              </tr>
              <tr>
                <!-- Provider Adress -->
                <td>{{$t('Adress')}}</td>
                <th>{{provider.adresse}}</th>
              </tr>
              <tr>
                <!-- Provider Tax_Number -->
                <td>{{$t('Tax_Number')}}</td>
                <th>{{provider.tax_number}}</th>
              </tr>
               <tr>
                <!-- Total_Purchase_Due -->
                <td>{{$t('Total_Purchase_Due')}}</td>
                <th>{{currentUser.currency}} {{provider.due}}</th>
              </tr>

               <tr>
                <!-- Total_Purchase_Return_Due -->
                <td>{{$t('Total_Purchase_Return_Due')}}</td>
                <th>{{currentUser.currency}} {{provider.return_Due}}</th>
              </tr>
            </tbody>
          </table>
          
          <!-- Custom Fields Section -->
          <div v-if="providerCustomFields && providerCustomFields.length > 0" class="mt-4">
            <h6 class="text-primary mb-3">
              <lucide-icon class="mr-2" name="database-zap" />
              {{ $t('CustomFields') }}
            </h6>
            <table class="table table-striped table-md">
              <tbody>
                <tr v-for="field in providerCustomFields" :key="field.id">
                  <td>{{ field.name }}</td>
                  <th>{{ getCustomFieldDisplayValue(field) }}</th>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else-if="providerCustomFields && providerCustomFields.length === 0" class="mt-4">
            <p class="text-muted">{{ $t('NoCustomFields') || 'No custom fields defined for suppliers.' }}</p>
          </div>
        </b-col>
      </b-row>
    </b-modal>

    <!-- Modal Show Import Providers -->
    <b-modal
      ok-only
      ok-title="Cancel"
      size="md"
      id="importProviders"
      :title="$t('Import_Suppliers')"
     >
      <b-form @submit.prevent="Submit_import" enctype="multipart/form-data">
        <b-row>
          <!-- File -->
          <b-col md="12" sm="12" class="mb-3">
            <b-form-group>
              <input type="file" @change="onFileSelected" label="Choose File">
              <b-form-invalid-feedback
                id="File-feedback"
                class="d-block"
              >File must be in xlsx format</b-form-invalid-feedback>
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
              :href="'/import/exemples/import_providers.xlsx'"
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


  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default {
  metaInfo: {
    title: "Provider"
  },
  data() {
    return {
      editmode: false,
      isLoading: true,
      SubmitProcessing:false,
      ImportProcessing:false,
      paymentProcessing:false,
      payment_return_Processing:false,
      showDropdown: false,
      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      totalRows: "",
      search: "",
      limit: "10",
      Filter_Name: "",
      Filter_Code: "",
      Filter_Phone: "",
      Filter_Email: "",
      import_providers: "",
      data: new FormData(),
      company_info:{},
      providers: [],
      payment_methods: [],
      accounts: [],
      provider: {
        id: "",
        name: "",
        code: "",
        phone: "",
        email: "",
        tax_number: "",
        country: "",
        city: "",
        adresse: ""
      },
      payment: {
        provider_id: "",
        provider_name: "",
        account_id: "",
        date:"",
        due: "",
        amount: "",
        notes: "",
        payment_method_id: "",
      },
       payment_return: {
        provider_id: "",
        provider_name: "",
        account_id: "",
        date:"",
        return_Due: "",
        amount: "",
        notes: "",
        payment_method_id: "",
      },
      providerCustomFields: [],
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
    columns() {
      return [
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
          label: this.$t("City"),
          field: "city",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Tax_Number"),
          field: "tax_number",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Total_Purchase_Due"),
          field: "due",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
         {
          label: this.$t("Total_Purchase_Return_Due"),
          field: "return_Due",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },

        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }
  },

  methods: {

    //----------------------------------- Show import providers -------------------------------\\
    Show_import_providers() {
      this.$bvModal.show("importProviders");
    },

    //------------------------------ Event Import providers -------------------------------\\
    onFileSelected(e) {
      this.import_providers = "";
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
        this.import_providers = file;
      }
    },

     //----------------------------------------Submit import providers-----------------\\
    Submit_import() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      var self = this;
      self.ImportProcessing = true;
      self.data.append("providers", self.import_providers);

      axios
        .post("providers/import/csv", this.data)
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


    //------ update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Providers(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Providers(1);
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
      this.Get_Providers(this.serverParams.page);
    },

    //------ Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Providers(this.serverParams.page);
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
      this.Get_Providers(this.serverParams.page);
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //------------ Providers PDF -----------------------\\
    Providers_PDF() {
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
        self.$t("Country"),
        self.$t("City"),
        self.$t("Total_Purchase_Due"),
        self.$t("Total_Purchase_Return_Due")
      ];

      const body = (self.providers || []).map(provider => ([
        provider.code,
        provider.name,
        provider.phone,
        provider.country,
        provider.city,
        provider.due,
        provider.return_Due
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
          const title = self.$t('ProvidersList') || 'Provider List';
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

      pdf.save("Provider_List.pdf");
    },

    //------------------------------ Navigate to Create Supplier Page -------------------------------\\
    New_Provider() {
      this.$router.push({ name: 'Create_Supplier' });
    },

    //------------------------------ Navigate to Edit Supplier Page -------------------------------\\
    Edit_Provider(provider) {
      this.$router.push({ name: 'Edit_Supplier', params: { id: provider.id } });
    },

    //----------------------------  Get all Providers  -----------------------\\
    Get_Providers(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "providers?page=" +
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
          this.providers = response.data.providers;
          this.totalRows = response.data.totalRows;
          this.company_info = response.data.company_info;
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


    //--------------------------- Update Provider -----------------------\\
    Update_provider() {
      this.SubmitProcessing = true;
      axios
        .put("providers/" + this.provider.id, {
          name: this.provider.name,
          email: this.provider.email,
          tax_number: this.provider.tax_number,
          phone: this.provider.phone,
          country: this.provider.country,
          city: this.provider.city,
          adresse: this.provider.adresse
        })
        .then(response => {
          Fire.$emit("Event_Provider");

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

    //----------------------------------- Show Details provider -------------------------------\\
    showDetails(provider) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.provider = provider;
      
      // Load custom fields and their values
      Promise.all([
        axios.get("custom-fields?entity_type=provider"),
        axios.get("custom-field-values", {
          params: {
            entity_type: "App\\Models\\Provider",
            entity_id: provider.id
          }
        })
      ])
        .then(([fieldsResponse, valuesResponse]) => {
          const allFields = fieldsResponse.data.custom_fields || [];
          const fieldValues = valuesResponse.data.success && valuesResponse.data.values 
            ? valuesResponse.data.values 
            : {};

          // Filter to only show active custom fields (matching the form behavior)
          const activeFields = allFields.filter(field => field.is_active !== false);

          // Map active custom fields with their values (or empty if no value)
          this.providerCustomFields = activeFields.map(field => {
            const fieldValue = fieldValues[field.id];
            return {
              id: field.id,
              name: field.name,
              field_type: field.field_type,
              value: fieldValue ? fieldValue.value : null
            };
          });

          NProgress.done();
          Fire.$emit("Get_Details_Provider");
        })
        .catch(error => {
          console.error('Error loading custom fields:', error);
          this.providerCustomFields = [];
          NProgress.done();
          Fire.$emit("Get_Details_Provider");
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

    //--------------------------------- Reset Form -----------------------\\
    reset_Form() {
      this.provider = {
        id: "",
        name: "",
        phone: "",
        email: "",
        country: "",
        tax_number: "",
        city: "",
        adresse: ""
      };
    },

    //---------------------------- DELETE Provider -----------------------\\

    Remove_Provider(id) {
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
            .delete("providers/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("Delete_Provider");
            })
            .catch(() => {
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete.ProviderError"),
                "warning"
              );
            });
        }
      });
    },

    //---- Delete providers by selection

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
            .post("providers/delete/by_selection", {
              selectedIds: this.selectedIds
            })
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("Delete_Provider");
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



     //------ Validate Form Submit_Payment_Purchase_due
    Submit_Payment_Purchase_due() {
      this.$refs.ref_pay_due.validate().then(success => {
        if (!success) {
           this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else if (this.payment.amount > this.payment.due) {
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
      } else if (this.payment.amount > this.payment.due) {
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
        provider_id: "",
        provider_name: "",
        account_id: "",
        date: "",
        due: "",
        amount: "",
        notes: "",
        payment_method_id: "",
      };
    },

    //------------------------------ Show Modal Pay_due-------------------------------\\
    Pay_due(row) {
      this.reset_Form_payment();
      this.payment.provider_id = row.id;
      this.payment.provider_name = row.name;
      this.payment.due = row.due;
      this.payment.account_id = null;
      this.payment.payment_method_id = null;
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
      axios
        .post("pay_supplier_due", {
          provider_id: this.payment.provider_id,
          amount: this.payment.amount,
          notes: this.payment.notes,
          payment_method_id: this.payment.payment_method_id,
          account_id: this.payment.account_id,
        })
        .then(response => {
          Fire.$emit("Event_pay_due");

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

    
    //-------------------------------Pay Purchase return due -----------------------------------\\

     //------ Validate Form Submit_Payment_purchase_return_due

    Submit_Payment_purchase_return_due() {
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
        provider_id: "",
        provider_name: "",
        account_id: "",
        date:"",
        return_Due: "",
        amount: "",
        notes: "",
        payment_method_id: "",
      };
    },

    //------------------------------ Show Modal Pay_return_due-------------------------------\\
    Pay_return_due(row) {
      this.reset_Form_payment_return_due();
      this.payment_return.provider_id = row.id;
      this.payment_return.provider_name = row.name;
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
        .post("pay_purchase_return_due", {
          provider_id: this.payment_return.provider_id,
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


    
  },

  //----------------------------- Created function-------------------\\

  created: function() {
    this.Get_Providers(1);

     Fire.$on("Event_pay_due", () => {
      setTimeout(() => {
        this.Get_Providers(this.serverParams.page);
        this.$bvModal.hide("modal_Pay_due");
      }, 500);
       this.$bvModal.show("Show_invoice");
      //  setTimeout(() => this.print_it(), 1000);
    });

    Fire.$on("Event_pay_return_due", () => {
      setTimeout(() => {
        this.Get_Providers(this.serverParams.page);
        this.$bvModal.hide("modal_Pay_return_due");
      }, 500);
       this.$bvModal.show("Show_invoice_return");
      //  setTimeout(() => this.print_return_due(), 1000);
    });

    Fire.$on("Get_Details_Provider", () => {
      // Complete the animation of theprogress bar.
      setTimeout(() => NProgress.done(), 500);
      this.$bvModal.show("showDetails");
    });

    Fire.$on("Event_Provider", () => {
      setTimeout(() => {
        this.Get_Providers(this.serverParams.page);
      }, 500);
    });

    Fire.$on("Delete_Provider", () => {
      setTimeout(() => {
        this.Get_Providers(this.serverParams.page);
      }, 500);
    });

    Fire.$on("Event_import", () => {
      setTimeout(() => {
        this.Get_Providers(this.serverParams.page);
        this.$bvModal.hide("importProviders");
      }, 500);
    });
  }
};
</script>
