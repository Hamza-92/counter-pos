<template>
  <div class="main-content">
    <breadcumb :page="$t('stock_report')" :folder="$t('Reports')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-row v-if="!isLoading">
        <b-col lg="12" class="mb-4">
            <h3 class="text-center">{{product.name}}</h3>
        </b-col>
      <!-- Warehouse Quantity -->
          <b-col md="5" v-if="product.type == 'is_single'">
          
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>{{$t('warehouse')}}</th>
                  <th>{{$t('Quantity')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="PROD_W in product.CountQTY">
                  <td>{{PROD_W.mag}}</td>
                  <td>{{formatNumber(PROD_W.qte ,2)}} {{product.unit}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>
          <!-- Warehouse Variants Quantity -->
          <b-col md="7" v-if="product.is_variant == 'yes'" class="mt-4">
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>{{$t('warehouse')}}</th>
                  <th>{{$t('Variant')}}</th>
                  <th>{{$t('Quantity')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="PROD_V in product.CountQTY_variants">
                  <td>{{PROD_V.mag}}</td>
                  <td>{{PROD_V.variant}}</td>
                  <td>{{formatNumber(PROD_V.qte ,2)}} {{product.unit}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>

      <b-col md="12">
        <b-card class="card mb-30" header-bg-variant="transparent ">
          <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">
           

            <!-- Sales Table -->
            <b-tab :title="$t('Sales')">
              <vue-good-table
                mode="remote"
                :columns="columns_sales"
                :totalRows="totalRows_sales"
                :rows="rows_sales"
                :group-options="{
                  enabled: true,
                  headerPosition: 'bottom',
                }"
                @on-page-change="PageChangeSales"
                @on-per-page-change="onPerPageChangeSales"
                @on-search="onSearch_sales"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
              <div slot="table-actions" class="mt-2 mb-3">
                <b-button @click="printTableOnly('sales')" size="sm" variant="outline-secondary ripple m-1">
                  <lucide-icon name="printer" /> {{ $t("print") }}
                </b-button>
                <b-button @click="Sales_PDF()" size="sm" variant="outline-success ripple m-1">
                  <lucide-icon name="copy" /> PDF
                </b-button>

                <vue-excel-xlsx
                    class="btn btn-sm btn-outline-danger ripple m-1"
                    :data="sales"
                    :columns="columns_sales"
                    :file-name="'sales_report'"
                    :file-type="'xlsx'"
                    :sheet-name="'sales_report'"
                    >
                    <lucide-icon name="file-spreadsheet" /> EXCEL
                </vue-excel-xlsx>

              </div>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'Ref'">
                    <router-link
                      :to="'/app/sales/detail/'+props.row.sale_id"
                    >
                      <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                    </router-link>
                  </div>
                  <span v-else-if="props.column.field == 'total'">
                    {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.total, 2) }}
                  </span>
                  <span v-else>
                    {{ props.formattedRow[props.column.field] }}
                  </span>
                </template>
              </vue-good-table>
            </b-tab>

             <!-- Quotations Table -->
            <b-tab :title="$t('Quotations')">
              <vue-good-table
                mode="remote"
                :columns="columns_quotations"
                :totalRows="totalRows_quotations"
                :rows="rows_quotations"
                :group-options="{
                  enabled: true,
                  headerPosition: 'bottom',
                }"
                @on-page-change="PageChangeQuotation"
                @on-per-page-change="onPerPageChangeQuotation"
                @on-search="onSearch_quotations"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
              <div slot="table-actions" class="mt-2 mb-3">
                <b-button @click="printTableOnly('quotations')" size="sm" variant="outline-secondary ripple m-1">
                  <lucide-icon name="printer" /> {{ $t("print") }}
                </b-button>
                <b-button @click="Quotation_PDF()" size="sm" variant="outline-success ripple m-1">
                  <lucide-icon name="copy" /> PDF
                </b-button>

                <vue-excel-xlsx
                    class="btn btn-sm btn-outline-danger ripple m-1"
                    :data="quotations"
                    :columns="columns_quotations"
                    :file-name="'Quotation_report'"
                    :file-type="'xlsx'"
                    :sheet-name="'Quotation_report'"
                    >
                    <lucide-icon name="file-spreadsheet" /> EXCEL
                </vue-excel-xlsx>
              </div>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'Ref'">
                    <router-link
                      :to="'/app/quotations/detail/'+props.row.quotation_id"
                    >
                      <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                    </router-link>
                  </div>
                  <span v-else-if="props.column.field == 'total'">
                    {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.total, 2) }}
                  </span>
                  <span v-else>
                    {{ props.formattedRow[props.column.field] }}
                  </span>
                </template>
              </vue-good-table>
            </b-tab>

            <!-- Purchases Table -->
            <b-tab :title="$t('Purchases')">
              <vue-good-table
                mode="remote"
                :columns="columns_purchases"
                :totalRows="totalRows_purchases"
                :rows="rows_purchases"
                :group-options="{
                  enabled: true,
                  headerPosition: 'bottom',
                }"
                @on-page-change="PageChangePurchases"
                @on-per-page-change="onPerPageChangePurchases"
                @on-search="onSearch_purchases"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
              <div slot="table-actions" class="mt-2 mb-3">
                <b-button @click="printTableOnly('purchases')" size="sm" variant="outline-secondary ripple m-1">
                  <lucide-icon name="printer" /> {{ $t("print") }}
                </b-button>
                <b-button @click="Purchase_PDF()" size="sm" variant="outline-success ripple m-1">
                  <lucide-icon name="copy" /> PDF
                </b-button>

                <vue-excel-xlsx
                    class="btn btn-sm btn-outline-danger ripple m-1"
                    :data="purchases"
                    :columns="columns_purchases"
                    :file-name="'purchases_report'"
                    :file-type="'xlsx'"
                    :sheet-name="'purchases_report'"
                    >
                    <lucide-icon name="file-spreadsheet" /> EXCEL
                </vue-excel-xlsx>
              </div>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'Ref'">
                    <router-link
                      :to="'/app/purchases/detail/'+props.row.purchase_id"
                    >
                      <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                    </router-link>
                  </div>
                  <span v-else-if="props.column.field == 'total'">
                    {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.total, 2) }}
                  </span>
                  <span v-else>
                    {{ props.formattedRow[props.column.field] }}
                  </span>
                </template>
              </vue-good-table>
            </b-tab>

            <!-- Sales Return Table -->
            <b-tab :title="$t('SalesReturn')">
              <vue-good-table
                mode="remote"
                :columns="columns_sales_return"
                :totalRows="totalRows_sales_return"
                :rows="rows_sales_return"
                :group-options="{
                  enabled: true,
                  headerPosition: 'bottom',
                }"
                @on-page-change="Page_Change_sales_Return"
                @on-per-page-change="onPerPage_Change_sales_Return"
                @on-search="onSearch_return_sales"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
              <div slot="table-actions" class="mt-2 mb-3">
                <b-button @click="printTableOnly('sales_return')" size="sm" variant="outline-secondary ripple m-1">
                  <lucide-icon name="printer" /> {{ $t("print") }}
                </b-button>
                <b-button @click="Sale_Return_PDF()" size="sm" variant="outline-success ripple m-1">
                  <lucide-icon name="copy" /> PDF
                </b-button>

                <vue-excel-xlsx
                    class="btn btn-sm btn-outline-danger ripple m-1"
                    :data="sales_return"
                    :columns="columns_sales_return"
                    :file-name="'sales_return_report'"
                    :file-type="'xlsx'"
                    :sheet-name="'sales_return_report'"
                    >
                    <lucide-icon name="file-spreadsheet" /> EXCEL
                </vue-excel-xlsx>
              </div>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'Ref'">
                    <router-link
                      :to="'/app/sale_return/detail/'+props.row.return_sale_id"
                    >
                      <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                    </router-link>
                  </div>
                  <span v-else-if="props.column.field == 'total'">
                    {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.total, 2) }}
                  </span>
                  <span v-else>
                    {{ props.formattedRow[props.column.field] }}
                  </span>
                </template>
              </vue-good-table>
            </b-tab>

             <!-- Purchase Return Table -->
            <b-tab :title="$t('PurchasesReturn')">
              <vue-good-table
                mode="remote"
                :columns="columns_purchase_return"
                :totalRows="totalRows_purchases_return"
                :rows="rows_purchases_return"
                :group-options="{
                  enabled: true,
                  headerPosition: 'bottom',
                }"
                @on-page-change="Page_Change_purchases_Return"
                @on-per-page-change="onPerPage_Change_purchases_Return"
                @on-search="onSearch_return_purchases"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
               <div slot="table-actions" class="mt-2 mb-3">
                <b-button @click="printTableOnly('purchases_return')" size="sm" variant="outline-secondary ripple m-1">
                  <lucide-icon name="printer" /> {{ $t("print") }}
                </b-button>
                <b-button @click="Returns_Purchase_PDF()" size="sm" variant="outline-success ripple m-1">
                  <lucide-icon name="copy" /> PDF
                </b-button>

                <vue-excel-xlsx
                    class="btn btn-sm btn-outline-danger ripple m-1"
                    :data="purchases_return"
                    :columns="columns_purchase_return"
                    :file-name="'purchases_return_report'"
                    :file-type="'xlsx'"
                    :sheet-name="'purchases_return_report'"
                    >
                    <lucide-icon name="file-spreadsheet" /> EXCEL
                </vue-excel-xlsx>
              </div>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'Ref'">
                    <router-link
                      :to="'/app/purchase_return/detail/'+props.row.return_purchase_id"
                    >
                      <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                    </router-link>
                  </div>
                  <span v-else-if="props.column.field == 'total'">
                    {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.total, 2) }}
                  </span>
                  <span v-else>
                    {{ props.formattedRow[props.column.field] }}
                  </span>
                </template>
              </vue-good-table>
            </b-tab>

             <!-- Transfers Table -->
            <b-tab :title="$t('StockTransfers')">
              <vue-good-table
                mode="remote"
                :columns="columns_transfers"
                :totalRows="totalRows_transfers"
                :rows="transfers"
                @on-page-change="PageChangeTransfer"
                @on-per-page-change="onPerPageChangeTransfer"
                @on-search="onSearch_transfers"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
              <div slot="table-actions" class="mt-2 mb-3">
                <b-button @click="printTableOnly('transfers')" size="sm" variant="outline-secondary ripple m-1">
                  <lucide-icon name="printer" /> {{ $t("print") }}
                </b-button>
                <b-button @click="Transfer_PDF()" size="sm" variant="outline-success ripple m-1">
                  <lucide-icon name="copy" /> PDF
                </b-button>
              </div>
               
              </vue-good-table>
            </b-tab>

             <!-- Adjustment Table -->
            <b-tab :title="$t('Adjustment')">
              <vue-good-table
                mode="remote"
                :columns="columns_adjustments"
                :totalRows="totalRows_adjustments"
                :rows="adjustments"
                @on-page-change="PageChangeAdjustment"
                @on-per-page-change="onPerPageChangeAdjustment"
                @on-search="onSearch_adjustments"
                :search-options="{
                  placeholder: $t('Search_this_table'),
                  enabled: true,
                }"
                :pagination-options="{
                  enabled: true,
                  mode: 'records',
                  nextLabel: 'next',
                  prevLabel: 'prev',
                }"
                styleClass="tableOne table-hover vgt-table"
              >
               <div slot="table-actions" class="mt-2 mb-3">
                <b-button @click="printTableOnly('adjustments')" size="sm" variant="outline-secondary ripple m-1">
                  <lucide-icon name="printer" /> {{ $t("print") }}
                </b-button>
                <b-button @click="Adjustment_PDF()" size="sm" variant="outline-success ripple m-1">
                  <lucide-icon name="copy" /> PDF
                </b-button>
              </div>
              </vue-good-table>
            </b-tab>

             

          </b-tabs>
        </b-card>
      </b-col>
    </b-row>
  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  data() {
    return {
      totalRows_quotations: "",
      totalRows_sales: "",
      totalRows_sales_return: "",
      totalRows_purchases_return: "",
      totalRows_purchases: "",
      totalRows_transfers: "",
      totalRows_adjustments: "",

      limit_quotations: "10",
      limit_sales_return: "10",
      limit_purchases_return: "10",
      limit_sales: "10",
      limit_purchases: "10",
      limit_transfers: "10",
      limit_adjustments: "10",

      sales_page: 1,
      quotations_page: 1,
      Return_sale_page: 1,
      Return_purchase_page: 1,
      purchases_page: 1,
      transfers_page: 1,
      adjustments_page: 1,

      search_sales:"",
      search_purchases:"",
      search_quotations:"",
      search_return_sales:"",
      search_return_purchases:"",
      search_transfers:"",
      search_adjustments:"",

      isLoading: true,
      product:{},
      purchases: [],
      rows_purchases: [{ statut: '', children: [] }],
      sales: [],
      rows_sales: [{ statut: '', children: [] }],
      quotations: [],
      rows_quotations: [{ statut: '', children: [] }],
      sales_return: [],
      rows_sales_return: [{ statut: '', children: [] }],
      purchases_return: [],
      rows_purchases_return: [{ statut: '', children: [] }],
      transfers: [],
      adjustments: [],
      // Optional price format key for frontend display (loaded from system settings/Vuex store)
      price_format_key: null
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns_quotations() {
      return [
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
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumQuotationsTotal,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
      ];
    },
    columns_sales() {
      return [
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
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumSalesTotal,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        
      ];
    },
    columns_sales_return() {
      return [
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
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumSalesReturnTotal,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
      ];
    },
    columns_purchases() {
      return [
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
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Supplier"),
          field: "provider_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumPurchasesTotal,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
      ];
    },
    columns_purchase_return() {
      return [
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
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Supplier"),
          field: "provider_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumPurchaseReturnTotal,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
      ];
    },
    columns_transfers() {
      return [
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
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("FromWarehouse"),
          field: "from_warehouse",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("ToWarehouse"),
          field: "to_warehouse",
          tdClass: "text-left",
          thClass: "text-left"
        },
       
      ];
    },
    columns_adjustments() {
      return [
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
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
       
      ];
    }
  },

  methods: {

     //----------------------------------- Sales PDF ------------------------------\\
    Sales_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
      pdf.setFont("VazirmatnBold"); 

      let columns = [
        { header: self.$t("date"), dataKey: "date" },
        { header: self.$t("Reference"), dataKey: "Ref" },
        { header: self.$t("product_name"), dataKey: "product_name" },
        { header: self.$t("Customer"), dataKey: "client_name" },
        { header: self.$t("warehouse"), dataKey: "warehouse_name" },
        { header: self.$t("Quantity"), dataKey: "quantity" },
        { header: self.$t("SubTotal"), dataKey: "total" },
      ];

      autoTable(pdf, {
             columns: columns,
             body: self.sales,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Sales List", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
      });
      pdf.save("Sale_List.pdf");
    },

      //------------------------------------- Quotations PDF -------------------------\\
    Quotation_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
      pdf.setFont("VazirmatnBold"); 

      let columns = [
        { header: self.$t("date"), dataKey: "date" },
        { header: self.$t("Reference"), dataKey: "Ref" },
        { header: self.$t("product_name"), dataKey: "product_name" },
        { header: self.$t("Customer"), dataKey: "client_name" },
        { header: self.$t("warehouse"), dataKey: "warehouse_name" },
        { header: self.$t("Quantity"), dataKey: "quantity" },
        { header: self.$t("SubTotal"), dataKey: "total" },
      ];

      autoTable(pdf, {
             columns: columns,
             body: self.quotations,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Quotation List", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
      });

      pdf.save("Quotation_List.pdf");
    },

     //---------------------- Purchases PDF -------------------------------\\
    Purchase_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
      pdf.setFont("VazirmatnBold"); 

      let columns = [
        { header: self.$t("date"), dataKey: "date" },
        { header: self.$t("Reference"), dataKey: "Ref" },
        { header: self.$t("product_name"), dataKey: "product_name" },
        { header: self.$t("Supplier"), dataKey: "provider_name" },
        { header: self.$t("warehouse"), dataKey: "warehouse_name" },
        { header: self.$t("Quantity"), dataKey: "quantity" },
        { header: self.$t("SubTotal"), dataKey: "total" },
      ];

      autoTable(pdf, {
             columns: columns,
             body: self.purchases,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Purchase List", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
      });

      pdf.save("Purchase_List.pdf");
    },

     //----------------------------------------- Sales Return PDF -----------------------\\
    Sale_Return_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
      pdf.setFont("VazirmatnBold"); 

      let columns = [
        { header: self.$t("date"), dataKey: "date" },
        { header: self.$t("Reference"), dataKey: "Ref" },
        { header: self.$t("product_name"), dataKey: "product_name" },
        { header: self.$t("Customer"), dataKey: "client_name" },
        { header: self.$t("warehouse"), dataKey: "warehouse_name" },
        { header: self.$t("Quantity"), dataKey: "quantity" },
        { header: self.$t("SubTotal"), dataKey: "total" },
      ];

      autoTable(pdf, {
             columns: columns,
             body: self.sales_return,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Sales Return List", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
      });

      pdf.save("Sales Return.pdf");
    },

      //----------------------------------------- Returns Purchase PDF -----------------------\\
    Returns_Purchase_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
      pdf.setFont("VazirmatnBold"); 

      let columns = [
        { header: self.$t("date"), dataKey: "date" },
        { header: self.$t("Reference"), dataKey: "Ref" },
        { header: self.$t("product_name"), dataKey: "product_name" },
        { header: self.$t("Supplier"), dataKey: "provider_name" },
        { header: self.$t("warehouse"), dataKey: "warehouse_name" },
        { header: self.$t("Quantity"), dataKey: "quantity" },
        { header: self.$t("SubTotal"), dataKey: "total" },
      ];

      autoTable(pdf, {
             columns: columns,
             body: self.purchases_return,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Purchase Return List", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
      });

      pdf.save("purchase_returns.pdf");
    },

     //-------------------------------------- Transfer PDF ------------------------------\\
    Transfer_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
      pdf.setFont("VazirmatnBold"); 

      let columns = [
        { header: self.$t("date"), dataKey: "date" },
        { header: self.$t("Reference"), dataKey: "Ref" },
        { header: self.$t("product_name"), dataKey: "product_name" },
        { header: self.$t("FromWarehouse"), dataKey: "from_warehouse" },
        { header: self.$t("ToWarehouse"), dataKey: "to_warehouse" },
      ];

      autoTable(pdf, {
             columns: columns,
             body: self.transfers,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Transfer List", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
      });

      pdf.save("Transfer_List.pdf");
    },

     //-------------------------------------- Adjustement PDF ------------------------------\\
    Adjustment_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold"); 
      pdf.setFont("VazirmatnBold"); 

      let columns = [
        { header: self.$t("date"), dataKey: "date" },
        { header: self.$t("Reference"), dataKey: "Ref" },
        { header: self.$t("product_name"), dataKey: "product_name" },
        { header: self.$t("warehouse"), dataKey: "warehouse_name" },
      ];

      autoTable(pdf, {
             columns: columns,
             body: self.adjustments,
             startY: 70,
             theme: "grid", 
             didDrawPage: (data) => {
               pdf.setFont("VazirmatnBold");
               pdf.setFontSize(18);
               pdf.text("Adjustment List", 40, 25);   
             },
             styles: {
               font: "VazirmatnBold", 
               halign: "center", // 
             },
             headStyles: {
               fillColor: [26, 86, 219], 
               textColor: 255, 
               fontStyle: "bold", 
             },
      });

      pdf.save("Adjustment_List.pdf");
    },

      //----------------------------------- Get Details Product ------------------------------\\
    showDetails() {
      let id = this.$route.params.id;
      axios
        .get(`get_product_detail/${id}`)
        .then(response => {
          this.product = response.data;
        })
        .catch(response => {
         
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
    // to the existing toLocaleString behavior to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 2;
        const n = Number(number || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, decimals, effectiveKey);
      } catch (e) {
        const n = Number(number || 0);
        return n.toLocaleString(undefined, { maximumFractionDigits: dec || 2 });
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      try {
        const safeSymbol = symbol || (this.currentUser && this.currentUser.currency) || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      } catch (e) {
        const safeSymbol = symbol || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      }
    },

    //------ Print Table Only - Print data with all columns based on table type
    printTableOnly(tableType) {
      let title, rows, columns;
      
      if (tableType === 'sales') {
        title = `${this.$t("Reports")} / ${this.$t("stock_report")} / ${this.$t("Sales")}`;
        rows = Array.isArray(this.sales) ? this.sales : [];
        columns = this.columns_sales;
      } else if (tableType === 'quotations') {
        title = `${this.$t("Reports")} / ${this.$t("stock_report")} / ${this.$t("Quotations")}`;
        rows = Array.isArray(this.quotations) ? this.quotations : [];
        columns = this.columns_quotations;
      } else if (tableType === 'purchases') {
        title = `${this.$t("Reports")} / ${this.$t("stock_report")} / ${this.$t("Purchases")}`;
        rows = Array.isArray(this.purchases) ? this.purchases : [];
        columns = this.columns_purchases;
      } else if (tableType === 'sales_return') {
        title = `${this.$t("Reports")} / ${this.$t("stock_report")} / ${this.$t("SalesReturn")}`;
        rows = Array.isArray(this.sales_return) ? this.sales_return : [];
        columns = this.columns_sales_return;
      } else if (tableType === 'purchases_return') {
        title = `${this.$t("Reports")} / ${this.$t("stock_report")} / ${this.$t("PurchasesReturn")}`;
        rows = Array.isArray(this.purchases_return) ? this.purchases_return : [];
        columns = this.columns_purchase_return;
      } else if (tableType === 'transfers') {
        title = `${this.$t("Reports")} / ${this.$t("stock_report")} / ${this.$t("StockTransfers")}`;
        rows = Array.isArray(this.transfers) ? this.transfers : [];
        columns = this.columns_transfers;
      } else if (tableType === 'adjustments') {
        title = `${this.$t("Reports")} / ${this.$t("stock_report")} / ${this.$t("Adjustment")}`;
        rows = Array.isArray(this.adjustments) ? this.adjustments : [];
        columns = this.columns_adjustments;
      } else {
        return;
      }
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      rows.forEach(row => {
        tableHTML += '<tr>';
        columns.forEach(col => {
          let cellValue = '';
          
          if (col.field === 'total') {
            // Format monetary values
            cellValue = this.formatPriceWithSymbol(this.currentUser?.currency, row.total, 2);
          } else {
            // Default: get value directly from row object
            cellValue = row[col.field] || '';
          }
          
          tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; text-align: left;">${cellValue}</td>`;
        });
        tableHTML += '</tr>';
      });
      
      tableHTML += '</tbody></table>';

      const w = window.open("", "_blank");
      if (!w) {
        alert("Please allow popups to print");
        return;
      }

      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
        .join("\n");

      const doc = w.document;
      doc.open();
      doc.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <base href="${window.location.origin}/" />
    <title>${title}</title>
    ${links}
    <style>
      /* Force visibility in print (some global POS print CSS hides body) */
      @media print { 
        body, body * { visibility: visible !important; }
        @page { size: A4 landscape; margin: 0.3cm; }
      }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 10px; font-size: 14px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 10px; }
      th { background-color: #f5f5f5; font-weight: bold; }
      tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    ${tableHTML}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    },

    // Group footer helpers for vue-good-table
    sumSalesTotal(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, 0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },

    sumQuotationsTotal(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, 0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },

    sumPurchasesTotal(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, 0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },

    sumSalesReturnTotal(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, 0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },

    sumPurchaseReturnTotal(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, 0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].total) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },


    //--------------------------- Event Page Change -------------\\
    PageChangeSales({ currentPage }) {
      if (this.sales_page !== currentPage) {
        this.Get_Sales(currentPage);
      }
    },

    //--------------------------- Limit Page Sales -------------\\
    onPerPageChangeSales({ currentPerPage }) {
      if (this.limit_sales !== currentPerPage) {
        this.limit_sales = currentPerPage;
        this.Get_Sales(1);
      }
    },

    onSearch_sales(value) {
      this.search_sales = value.searchTerm;
      this.Get_Sales(1);
    },

    //--------------------------- get_sales_by_product -------------\\
    Get_Sales(page) {
      axios
        .get(
          "/report/get_sales_by_product?page=" +
            page +
            "&limit=" +
            this.limit_sales +
            "&search=" +
            this.search_sales +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.sales = response.data.sales;
          this.totalRows_sales = response.data.totalRows;
          this.rows_sales[0].children = this.sales;
        })
        .catch(response => {});
    },

    //--------------------------- Event Page Change -------------\\
    PageChangePurchases({ currentPage }) {
      if (this.purchases_page !== currentPage) {
        this.Get_Sales(currentPage);
      }
    },

    //--------------------------- Limit Page Purchases -------------\\
    onPerPageChangePurchases({ currentPerPage }) {
      if (this.limit_purchases !== currentPerPage) {
        this.limit_purchases = currentPerPage;
        this.Get_Purchases(1);
      }
    },

    onSearch_purchases(value) {
      this.search_purchases = value.searchTerm;
      this.Get_Purchases(1);
    },

    //--------------------------- Get Purchases By product -------------\\
    Get_Purchases(page) {
      axios
        .get(
          "report/get_purchases_by_product?page=" +
            page +
            "&limit=" +
            this.limit_purchases +
            "&search=" +
            this.search_purchases +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.purchases = response.data.purchases;
          this.totalRows_purchases = response.data.totalRows;
          this.rows_purchases[0].children = this.purchases;
          this.isLoading = false;
        })
        .catch(response => {
           setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //--------------------------- Event Page Change -------------\\
    PageChangeQuotation({ currentPage }) {
      if (this.quotations_page !== currentPage) {
        this.Get_Quotations(currentPage);
      }
    },

    //--------------------------- Limit Page Quotations -------------\\
    onPerPageChangeQuotation({ currentPerPage }) {
      if (this.limit_quotations !== currentPerPage) {
        this.limit_quotations = currentPerPage;
        this.Get_Quotations(1);
      }
    },

    onSearch_quotations(value) {
      this.search_quotations = value.searchTerm;
      this.Get_Quotations(1);
    },

    //--------------------------- Get Quotations By product -------------\\
    Get_Quotations(page) {
      axios
        .get(
          "report/get_quotations_by_product?page=" +
            page +
            "&limit=" +
            this.limit_quotations +
            "&search=" +
            this.search_quotations +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.quotations = response.data.quotations;
          this.totalRows_quotations = response.data.totalRows;
          this.rows_quotations[0].children = this.quotations;
        })
        .catch(response => {
         
        });
    },

     //--------------------------- Event Page Change -------------\\
    PageChangeTransfer({ currentPage }) {
      if (this.transfers_page !== currentPage) {
        this.Get_Transfers(currentPage);
      }
    },

    //--------------------------- Limit Page transfers -------------\\
    onPerPageChangeTransfer({ currentPerPage }) {
      if (this.limit_transfers !== currentPerPage) {
        this.limit_transfers = currentPerPage;
        this.Get_Transfers(1);
      }
    },

    onSearch_transfers(value) {
      this.search_transfers = value.searchTerm;
      this.Get_Transfers(1);
    },

    //--------------------------- Get Transfers By product -------------\\
    Get_Transfers(page) {
      axios
        .get(
          "report/get_transfer_by_product?page=" +
            page +
            "&limit=" +
            this.limit_transfers +
             "&search=" +
            this.search_transfers +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.transfers = response.data.transfers;
          this.totalRows_transfers = response.data.totalRows;
         
        })
        .catch(response => {
         
        });
    },

      //--------------------------- Event Page Change -------------\\
    PageChangeAdjustment({ currentPage }) {
      if (this.adjustments_page !== currentPage) {
        this.Get_adjustments(currentPage);
      }
    },

    //--------------------------- Limit Page adjustments -------------\\
    onPerPageChangeAdjustment({ currentPerPage }) {
      if (this.limit_adjustments !== currentPerPage) {
        this.limit_adjustments = currentPerPage;
        this.Get_adjustments(1);
      }
    },

    onSearch_adjustments(value) {
      this.search_adjustments = value.searchTerm;
      this.Get_adjustments(1);
    },

    //--------------------------- Get adjustment By product -------------\\
    Get_adjustments(page) {
      axios
        .get(
          "report/get_adjustment_by_product?page=" +
            page +
            "&limit=" +
            this.limit_adjustments +
            "&search=" +
            this.search_adjustments +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.adjustments = response.data.adjustments;
          this.totalRows_adjustments = response.data.totalRows;
         
        })
        .catch(response => {
         
        });
    },

    //--------------------------- Event Page Change -------------\\
    Page_Change_sales_Return({ currentPage }) {
      if (this.Return_sale_page !== currentPage) {
        this.Get_Sales_Return(currentPage);
      }
    },

    //--------------------------- Limit Page sales Returns -------------\\
    onPerPage_Change_sales_Return({ currentPerPage }) {
      if (this.limit_sales_return !== currentPerPage) {
        this.limit_sales_return = currentPerPage;
        this.Get_Sales_Return(1);
      }
    },

    onSearch_return_sales(value) {
      this.search_return_sales = value.searchTerm;
      this.Get_Sales_Return(1);
    },

    //--------------------------- Get sales Returns By product -------------\\
    Get_Sales_Return(page) {
      axios
        .get(
          "/report/get_sales_return_by_product?page=" +
            page +
            "&limit=" +
            this.limit_sales_return +
            "&search=" +
            this.search_return_sales +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.sales_return = response.data.sales_return;
          this.totalRows_sales_return = response.data.totalRows;
          this.rows_sales_return[0].children = this.sales_return;
        })
        .catch(response => {});
    },

    //--------------------------- Event Page Change -------------\\
    Page_Change_purchases_Return({ currentPage }) {
      if (this.Return_purchase_page !== currentPage) {
        this.Get_Purchases_Return(currentPage);
      }
    },

    //--------------------------- Limit Page sales Returns -------------\\
    onPerPage_Change_purchases_Return({ currentPerPage }) {
      if (this.limit_purchases_return !== currentPerPage) {
        this.limit_purchases_return = currentPerPage;
        this.Get_Purchases_Return(1);
      }
    },

     onSearch_return_purchases(value) {
      this.search_return_purchases = value.searchTerm;
      this.Get_Purchases_Return(1);
    },

    //--------------------------- Get purchases Returns By product -------------\\
    Get_Purchases_Return(page) {
      axios
        .get(
          "/report/get_purchase_return_by_product?page=" +
            page +
            "&limit=" +
            this.limit_purchases_return +
            "&search=" +
            this.search_return_purchases +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.purchases_return = response.data.purchases_return;
          this.totalRows_purchases_return = response.data.totalRows;
          this.rows_purchases_return[0].children = this.purchases_return;
        })
        .catch(response => {});
    }
  }, //end Methods

  //----------------------------- Created function------------------- \\

  created: function() {
    this.showDetails();
    this.Get_Sales(1);
    this.Get_Purchases(1);
    this.Get_Quotations(1);
    this.Get_Sales_Return(1);
    this.Get_Purchases_Return(1);
    this.Get_Transfers(1);
    this.Get_adjustments(1);
  }
};
</script>