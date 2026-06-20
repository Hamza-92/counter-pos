<template>
  <div class="main-content">
    <breadcumb :page="$t('ListPurchases')" :folder="$t('Purchases')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="purchases"
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
        <div slot="selected-row-actions" v-if="currentUserPermissions.includes('Purchases_delete')">
          <button class="btn btn-danger btn-sm" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Purchase_PDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="purchases"
              :columns="columns"
              :file-name="'purchases'"
              :file-type="'xlsx'"
              :sheet-name="'purchases'"
              >
              <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
          <router-link
            class="btn-sm btn btn-primary ripple btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_add')"
            to="/app/purchases/store"
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
                id="dropdown-left"
                variant="link"
                text="Left align"
                toggle-class="text-decoration-none"
                size="lg"
                no-caret
              >
                <template v-slot:button-content class="_r_btn border-0">
                  <span class="_dot _r_block-dot bg-dark"></span>
                  <span class="_dot _r_block-dot bg-dark"></span>
                  <span class="_dot _r_block-dot bg-dark"></span>
                </template>
                  <b-dropdown-item title="Show" :to="'/app/purchases/detail/'+props.row.id">
                    <lucide-icon class="nav-icon font-weight-bold mr-2" name="eye" />
                    {{$t('PurchaseDetail')}}
                  </b-dropdown-item>

                <b-dropdown-item
                  title="Edit"
                  v-if="currentUserPermissions.includes('Purchases_edit') && props.row.purchase_has_return == 'no'"
                  :to="'/app/purchases/edit/'+props.row.id"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="pen" />
                  {{$t('EditPurchase')}}
                </b-dropdown-item>

                 <b-dropdown-item
                  title="Purchase Return"
                  v-if="currentUserPermissions.includes('Purchase_Returns_add') && props.row.purchase_has_return == 'no' && props.row.statut == 'received'"
                  :to="'/app/purchases/purchase_return/'+props.row.id"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="arrow-left" />
                  {{$t('Purchase_Return')}}
                </b-dropdown-item>

                <b-dropdown-item
                  title="Purchase Return"
                  v-if="currentUserPermissions.includes('Purchase_Returns_add') && props.row.purchase_has_return == 'yes'"
                  :to="'/app/purchase_return/edit/'+props.row.purchasereturn_id+'/'+props.row.id"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="arrow-left" />
                  {{$t('Purchase_Return')}}
                </b-dropdown-item>

                <b-dropdown-item
                  v-if="currentUserPermissions.includes('payment_purchases_view')"
                  @click="Show_Payments(props.row.id , props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="wallet" />
                  {{$t('ShowPayment')}}
                </b-dropdown-item>

                <b-dropdown-item
                  v-if="currentUserPermissions.includes('payment_purchases_add') && props.row.statut == 'received'"
                  @click="New_Payment(props.row)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="plus" />
                  {{$t('AddPayment')}}
                </b-dropdown-item>

                <b-dropdown-item title="PDF" @click="Invoice_PDF(props.row , props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="file-text" />
                  {{$t('DownloadPdf')}}
                </b-dropdown-item>

                <b-dropdown-item
                  title="Print Barcode"
                  @click="Print_Purchase_Barcode(props.row.id)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="barcode" />
                  {{$t('Printbarcode')}}
                </b-dropdown-item>

                <b-dropdown-item title=" WhatsApp Notification" @click="Send_WhatsApp(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="mail" />
                  WhatsApp Notification
                </b-dropdown-item>

                <b-dropdown-item title="Email" @click="Send_Email(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="mail" />
                  {{$t('email_notification')}}
                </b-dropdown-item>

                <b-dropdown-item title="SMS" @click="Purchase_SMS(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="message-square" />
                  {{$t('sms_notification')}}
                </b-dropdown-item>

                <b-dropdown-item title="Attach Documents" @click="Manage_Documents(props.row.id)">
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="file" />
                  {{$t('Attach_Documents')}}
                </b-dropdown-item>

                <b-dropdown-item
                  title="Delete"
                  v-if="currentUserPermissions.includes('Purchases_delete')"
                  @click="Remove_Purchase(props.row.id , props.row.purchase_has_return)"
                >
                  <lucide-icon class="nav-icon font-weight-bold mr-2" name="x" />
                  {{$t('DeletePurchase')}}
                </b-dropdown-item>
              </b-dropdown>
            </div>
          </span>
          <span v-else-if="props.column.field == 'date'">
            {{ formatDisplayDate(props.row.date) }}
          </span>
          <div v-else-if="props.column.field == 'statut'">
            <span
              v-if="props.row.statut == 'received'"
              class="badge badge-outline-success"
            >{{$t('Received')}}</span>
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
           <div v-else-if="props.column.field == 'Ref'">
              <router-link
                :to="'/app/purchases/detail/'+props.row.id"
              >
                <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
              </router-link> <br>
              <small v-if="props.row.purchase_has_return == 'yes'"><lucide-icon class="text-15 text-danger" name="arrow-left" /></small>
            </div>
            <div v-else-if="props.column.field == 'documents'">
              <span v-if="props.row.documents_count > 0" class="badge badge-info">
                <lucide-icon name="file" /> {{props.row.documents_count}}
              </span>
              <span v-else class="text-muted">-</span>
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

          <!-- Supplier  -->
          <b-col md="12">
            <b-form-group :label="$t('Supplier')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Supplier')"
                v-model="Filter_Supplier"
                :options="suppliers.map(suppliers => ({label: suppliers.name, value: suppliers.id}))"
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
                        {label: 'Received', value: 'received'},
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

          <b-col md="6" sm="12">
            <b-button
              @click="Get_Purchases(serverParams.page)"
              variant="primary ripple m-1"
              size="sm"
              block
            >
              <lucide-icon name="filter" />
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple m-1" size="sm" block>
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
                <tr v-if="factures.length <= 0">
                  <td colspan="5">{{$t('NodataAvailable')}}</td>
                </tr>
                <tr v-for="facture in factures">
                  <td>{{facture.date}}</td>
                  <td>{{facture.Ref}}</td>
                  <td>{{ formatPriceWithSymbol(currentUser.currency, facture.montant, 2) }}</td>
                   <td>{{facture.payment_method?facture.payment_method.name:'---'}}</td>
                  <td>
                    <div role="group" aria-label="Basic example" class="btn-group">
                      <span
                        title="Print"
                        class="btn btn-icon btn-info btn-sm"
                        @click="Payment_Purchase_PDF(facture,facture.id)"
                      >
                        <lucide-icon name="receipt" />
                      </span>
                      <span
                        v-if="currentUserPermissions.includes('payment_purchases_edit')"
                        title="Edit"
                        class="btn btn-icon btn-success btn-sm"
                        @click="Edit_Payment(facture)"
                      >
                        <lucide-icon name="pen" />
                      </span>
                      <span
                        title="Email"
                        class="btn btn-icon btn-primary btn-sm"
                        @click="Send_Email_Payment(facture.id)"
                      >
                        <lucide-icon name="mail" />
                      </span>
                      <span
                        title="SMS"
                        class="btn btn-icon btn-secondary btn-sm"
                        @click="Payment_Purchase_SMS(facture.id)"
                      >
                        <lucide-icon name="message-square" />
                      </span>
                      <span
                        v-if="currentUserPermissions.includes('payment_purchases_delete')"
                        title="Delete"
                        class="btn btn-icon btn-danger btn-sm"
                        @click="Remove_Payment(facture.id)"
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
                    v-model="facture.date"
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
                  v-model="facture.Ref"
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
                    v-model="facture.payment_method_id"
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
                  @keyup="Verified_Received_Amount(facture.received_amount)"
                  label="Received_Amount"
                  :placeholder="$t('Received_Amount')"
                  v-model.number="facture.received_amount"
                  :state="getValidationState(validationContext)"
                  aria-describedby="Received_Amount-feedback"
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
                   @keyup="Verified_paidAmount(facture.montant)"
                    label="Amount"
                    :placeholder="$t('Paying_Amount')"
                    v-model.number="facture.montant"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Amount-feedback"
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
              >{{parseFloat(facture.received_amount - facture.montant).toFixed(2)}}</p>
            </b-col>

             <!-- Account -->
             <b-col lg="6" md="6" sm="12">
              <validation-provider name="Account">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Account')">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="facture.account_id"
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
                <b-form-textarea id="textarea" v-model="facture.notes" rows="3" max-rows="6"></b-form-textarea>
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
import Util from "../../../../utils";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: {
    title: "Purchases"
  },

  data() {
    return {
      paymentProcessing: false,
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
      showDropdown: false,
      EditPaiementMode: false,
      Filter_Supplier: "",
      Filter_status: "",
      Filter_Payment: "",
      Filter_warehouse: "",
      Filter_Ref: "",
      Filter_date: "",
      Purchase_id: "",
      suppliers: [],
      warehouses: [],
      payment_methods: [],
      details: [],
      purchases: [],
      purchase: {},
      factures: [],
      accounts: [],
      purchase_due:'',
      due:0,
      facture: {
        montant: "",
        received_amount: "",
        payment_method_id: "",
        notes: ""
      },
      limit: "10",
      email: {
        to: "",
        subject: "",
        message: "",
        client_name: "",
        purchase_Ref: ""
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
      currentPurchaseId: null,
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
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
          label: this.$t("Supplier"),
          field: "provider_name",
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
          // type: "decimal",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Paid"),
          field: "paid_amount",
          // type: "decimal",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Due"),
          field: "due",
          // type: "decimal",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("PaymentStatus"),
          field: "payment_status",
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

  methods: {

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Purchases(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Purchases(1);
      }
    },

    //---- Event Select Rows
    selectionChanged({ selectedRows }) {
      this.selectedIds = [];
      selectedRows.forEach((row, index) => {
        this.selectedIds.push(row.id);
      });
    },

    //---- Event on Sort Change
    onSortChange(params) {
      let field = "";
      if (params[0].field == "provider_name") {
        field = "provider_id";
      } else if (params[0].field == "warehouse_name") {
        field = "warehouse_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Get_Purchases(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Purchases(this.serverParams.page);
    },

    //------ Validate Form Submit_Payment
    Submit_Payment() {
      this.$refs.Add_payment.validate().then(success => {
        if (!success) {
          return;
        } else if (this.facture.montant > this.facture.received_amount) {
          this.makeToast(
            "warning",
            this.$t("Paying_amount_is_greater_than_Received_amount"),
            this.$t("Warning")
          );
          this.facture.received_amount = 0;
        }
        else if (this.facture.montant > this.due) {
          this.makeToast(
            "warning",
            this.$t("Paying_amount_is_greater_than_Grand_Total"),
            this.$t("Warning")
          );
          this.facture.montant = 0;

        }else if (!this.EditPaiementMode) {
            this.Create_Payment();
        } else {
            this.Update_Payment();
        }

      });
    },

      //---------- keyup paid Amount

    Verified_paidAmount() {
      if (isNaN(this.facture.montant)) {
        this.facture.montant = 0;
      } else if (this.facture.montant > this.facture.received_amount) {
        this.makeToast(
          "warning",
          this.$t("Paying_amount_is_greater_than_Received_amount"),
          this.$t("Warning")
        );
        this.facture.montant = 0;
      } 
      else if (this.facture.montant > this.due) {
        this.makeToast(
          "warning",
          this.$t("Paying_amount_is_greater_than_Grand_Total"),
          this.$t("Warning")
        );
        this.facture.montant = 0;
      }
    },

    //---------- keyup Received Amount

    Verified_Received_Amount() {
      if (isNaN(this.facture.received_amount)) {
        this.facture.received_amount = 0;
      } 
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
      this.Filter_Supplier = "";
      this.Filter_status = "";
      this.Filter_Payment = "";
      this.Filter_Ref = "";
      this.Filter_date = "";
      this.Filter_warehouse = "";
      this.Get_Purchases(this.serverParams.page);
    },

    //---------------------- Purchases PDF -------------------------------\\
    Purchase_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try { pdf.addFont(fontPath,'Vazirmatn','normal'); pdf.addFont(fontPath,'Vazirmatn','bold'); } catch(e){}
      pdf.setFont('Vazirmatn','normal');

      const headers = [
        self.$t("Reference"),
        self.$t("Supplier"),
        self.$t("warehouse"),
        self.$t("Status"),
        self.$t("Total"),
        self.$t("Paid"),
        self.$t("Due"),
        self.$t("PaymentStatus")
      ];

      const body = (self.purchases || []).map(purchase => ([
        purchase.Ref,
        purchase.provider_name,
        purchase.warehouse_name,
        purchase.statut,
        purchase.GrandTotal,
        purchase.paid_amount,
        purchase.due,
        purchase.payment_status
      ]));

      // Calculate totals
      let totalGrandTotal = self.purchases.reduce((sum, purchase) => sum + parseFloat(purchase.GrandTotal || 0), 0);
      let totalPaidAmount = self.purchases.reduce((sum, purchase) => sum + parseFloat(purchase.paid_amount || 0), 0);
      let totalDue = self.purchases.reduce((sum, purchase) => sum + parseFloat(purchase.due || 0), 0);

      const footer = [[
        self.$t("Total"),
        '',
        '',
        '',
        totalGrandTotal.toFixed(2),
        totalPaidAmount.toFixed(2),
        totalDue.toFixed(2),
        ''
      ]];

      const marginX = 40;
      const rtl =
        (self.$i18n && ['ar','fa','ur','he'].includes(self.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      autoTable(pdf, {
        head: [headers],
        body: body,
        foot: footer,
        startY: 110,
        theme: 'striped',
        margin: { left: marginX, right: marginX },
        styles: { font: 'Vazirmatn', fontSize: 9, cellPadding: 4, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [63,81,181], textColor: 255 },
        alternateRowStyles: { fillColor: [245,247,250] },
        footStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [63,81,181], textColor: 255 },
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
          const title = self.$t('PurchasesList') || 'Purchases List';
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

      pdf.save("Purchases_List.pdf");
    },

    Send_WhatsApp(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("purchase_send_whatsapp", {
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

    //--------------------------- Invoice Purchase -------------------------------\\
    Invoice_PDF(purchase, id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
     
       axios
        .get("purchase_pdf/" + id, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Purchase-" + purchase.Ref + ".pdf");
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

    //------------------------------ Payment Purchase -------------------------------\\
    Payment_Purchase_PDF(facture, id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
     
       axios
        .get("payment_purchase_pdf/" + id, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Payment-" + facture.Ref + ".pdf");
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
      if (this.Filter_Supplier === null) {
        this.Filter_Supplier = "";
      } else if (this.Filter_warehouse === null) {
        this.Filter_warehouse = "";
      } else if (this.Filter_status === null) {
        this.Filter_status = "";
      } else if (this.Filter_Payment === null) {
        this.Filter_Payment = "";
      }
    },

    //------------------------------------------------ Get All Purchases -------------------------------\\
    Get_Purchases(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "purchases?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&date=" +
            this.Filter_date +
            "&provider_id=" +
            this.Filter_Supplier +
            "&statut=" +
            this.Filter_status +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&payment_statut=" +
            this.Filter_Payment +
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
          this.purchases = response.data.purchases;
          this.suppliers = response.data.suppliers;
          this.warehouses = response.data.warehouses;
          this.accounts = response.data.accounts;
          this.totalRows = response.data.totalRows;
          this.payment_methods =  response.data.payment_methods;

          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
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

    //------------------------------- Remove Purchase -------------------------\\

    Remove_Purchase(id , purchase_has_return) {
      if(purchase_has_return == 'yes'){
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
              .delete("purchases/" + id)
              .then(() => {
                this.$swal(
                  this.$t("Delete_Deleted"),
                  this.$t("Deleted_in_successfully"),
                  "success"
                );
                Fire.$emit("Delete_Purchase");
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

    //---- Delete purchases by selection

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
            .post("purchases_delete_by_selection", {
              selectedIds: this.selectedIds
            })
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("Delete_Purchase");
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

      //---------SMS notification
     Payment_Purchase_SMS(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("payment_purchase_send_sms", {
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
        .post("payment_purchase_send_email", {
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

    //--------------------------------------------- Send Purchase to Email -------------------------------\\
    Send_Email(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("purchase_send_email", {
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
     
     Purchase_SMS(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("purchase_send_sms", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("Send_SMS"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("sms_config_invalid"), this.$t("Failed"));
        });
    },

    //----------------------------------------------------- Add Payment to Purchase -------------------------------\\
    New_Payment(purchase) {
      if (purchase.payment_status == "paid") {
        this.makeToast(
          "warning",
          this.$t("PaymentComplete"),
          this.$t("Warning")
        );
      } else {
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        this.reset_form_payment();
        this.EditPaiementMode = false;
        this.purchase = purchase;
        this.facture.date = new Date().toISOString().slice(0, 10);
        this.Number_Order_Payment();
        this.facture.montant = purchase.due;
        this.facture.payment_method_id = 2;
        this.facture.received_amount = purchase.due;
        this.due = parseFloat(this.purchase.due);
        setTimeout(() => {
          // Complete the animation of the  progress bar.
          NProgress.done();
          this.$bvModal.show("Add_Payment");
        }, 500);
      }
    },

    Number_Order_Payment() {
      axios
        .get("payment_purchase_get_number")
        .then(({ data }) => (this.facture.Ref = data));
    },

    //------------------------------------------------ Edit Pyament -------------------------------\\
    Edit_Payment(facture) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.reset_form_payment();

      this.facture.id        = facture.id;
      this.facture.Ref       = facture.Ref;
      this.facture.payment_method_id = facture.payment_method_id;
      this.facture.account_id = facture.account_id;
      this.facture.date    = facture.date;
      this.facture.change  = facture.change;
      this.facture.montant = facture.montant;
      this.facture.received_amount = parseFloat(facture.montant + facture.change).toFixed(2);
      this.facture.notes   = facture.notes;
      this.due = parseFloat(this.purchase_due) + facture.montant;
      this.EditPaiementMode = true;
      setTimeout(() => {
        // Complete the animation of the  progress bar.
        NProgress.done();
        this.$bvModal.show("Add_Payment");
      }, 500);
    },

    //--------------------------------- Show All Payments by Purchase -------------------------------\\
    Show_Payments(id, purchase) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.reset_form_payment();
      this.Purchase_id = id;
      this.purchase = purchase;
      this.Get_Payments(id);
    },

    reset_form_payment() {
      this.facture = {
        id: "",
        purchase_id: "",
        account_id: "",
        date: "",
        Ref: "",
        montant: "",
        received_amount: "",
        payment_method_id: "",
        notes: ""
      };
    },

    //---------------------------------------- Create Payment --------------------------------------\\
    Create_Payment() {
      this.paymentProcessing = true;
      NProgress.start();
      NProgress.set(0.1);
        axios
          .post("payment_purchase", {
            purchase_id: this.purchase.id,
            date: this.facture.date,
            montant: parseFloat(this.facture.montant).toFixed(2),
            received_amount: parseFloat(this.facture.received_amount).toFixed(2),
            payment_method_id: this.facture.payment_method_id,
            account_id: this.facture.account_id,
            change: parseFloat(this.facture.received_amount - this.facture.montant).toFixed(2),
            notes: this.facture.notes
          })
          .then(response => {
            this.paymentProcessing = false;
            Fire.$emit("Create_Facture_purchase");
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

    //------------------------------------------- Update Payment -------------------------------\\
    Update_Payment() {
      this.paymentProcessing = true;
      NProgress.start();
      NProgress.set(0.1);
     
        axios
          .put("payment_purchase/" + this.facture.id, {
            purchase_id: this.purchase.id,
            date: this.facture.date,
            montant: parseFloat(this.facture.montant).toFixed(2),
            received_amount: parseFloat(this.facture.received_amount).toFixed(2),
            payment_method_id: this.facture.payment_method_id,
            account_id: this.facture.account_id,
            change: parseFloat(this.facture.received_amount - this.facture.montant).toFixed(2),
            notes: this.facture.notes
          })
          .then(response => {
            this.paymentProcessing = false;
            Fire.$emit("Update_Facture_purchase");
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

  

    //------------------------------------ Remove Payment -------------------------------\\
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
            .delete("payment_purchase/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("Delete_Facture_purchase");
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

    //----------------------------------------- Get All Payments  -------------------------------\\
    Get_Payments(id) {
      axios
        .get("get_payments_by_purchase/" + id)
        .then(response => {
          this.factures = response.data.payments;
          this.purchase_due = response.data.due;
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

    //----------------------------------------- Manage Documents -------------------------------\\
    Manage_Documents(purchaseId) {
      this.currentPurchaseId = purchaseId;
      this.selectedFiles = [];
      NProgress.start();
      NProgress.set(0.1);
      this.Get_Documents(purchaseId);
    },

    //----------------------------------------- Get Documents -------------------------------\\
    Get_Documents(purchaseId) {
      axios
        .get("purchases/" + purchaseId + "/documents")
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
      formData.append('purchase_id', this.currentPurchaseId);

      axios
        .post("purchases/" + this.currentPurchaseId + "/documents", formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        .then(response => {
          this.uploadProcessing = false;
          this.selectedFiles = [];
          this.Get_Documents(this.currentPurchaseId);
          this.Get_Purchases(this.serverParams.page);
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
        .get("purchases/documents/" + doc.id + "/download", {
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
            .delete("purchases/documents/" + documentId)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.Get_Documents(this.currentPurchaseId);
              this.Get_Purchases(this.serverParams.page);
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

    //----------------------------------------- Format File Size -------------------------------\\
    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    },

    //----------------------------------------- Format Date Time -------------------------------\\
    formatDateTime(value) {
      if (!value) return '';
      const d = new Date(value);
      if (isNaN(d.getTime())) return value; // fallback to raw if invalid

      const pad = n => (n < 10 ? '0' + n : n);
      const year = d.getFullYear();
      const month = pad(d.getMonth() + 1);
      const day = pad(d.getDate());
      const hours = pad(d.getHours());
      const minutes = pad(d.getMinutes());

      // Result: YYYY-MM-DD HH:MM
      return `${year}-${month}-${day} ${hours}:${minutes}`;
    },
    //----------------------------------------- Format Display Date (for tables) -------------------------------\\
    formatDisplayDate(value) {
      if (!value) return '';
      // Get date format from Vuex store (loaded from database) or fallback
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
      return Util.formatDisplayDate(value, dateFormat);
    },

    // ------------------------- Print Barcodes for Purchase -------------------------\\
    Print_Purchase_Barcode(id) {
      this.$router.push({
        name: "barcode",
        query: { purchase_id: id }
      });
    }
  },

  //-----------------------------Created function-------------------
  created: function() {
    this.Get_Purchases(1);

    Fire.$on("Delete_Purchase", () => {
      setTimeout(() => {
        this.Get_Purchases(this.serverParams.page);
        // Complete the animation of the  progress bar.
        NProgress.done();
      }, 800);
    });

    Fire.$on("Create_Facture_purchase", () => {
      setTimeout(() => {
        this.Get_Purchases(this.serverParams.page);
        // Complete the animation of the  progress bar.
        NProgress.done();
        this.$bvModal.hide("Add_Payment");
      }, 800);
    });

    Fire.$on("Update_Facture_purchase", () => {
      
      setTimeout(() => {
        NProgress.done();
        this.$bvModal.hide("Add_Payment");
        this.$bvModal.hide("Show_payment");
        this.Get_Purchases(this.serverParams.page);
      }, 800);
    });

    Fire.$on("Delete_Facture_purchase", () => {
      setTimeout(() => {
        NProgress.done();
        this.$bvModal.hide("Show_payment");
        this.Get_Purchases(this.serverParams.page);
      }, 800);
    });
  }
};
</script>