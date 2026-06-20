<template>
  <div class="main-content">
    <breadcumb :page="$t('productsList')" :folder="$t('Products')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="products"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :select-options="{ enabled: true, clearSelectionText: '' }"
        @on-selected-rows-change="selectionChanged"
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
        styleClass="tableOne vgt-table"
      >
        <!-- selected actions -->
        <div slot="selected-row-actions" v-if="can('products_delete')">
          <button class="btn btn-sm btn-outline-danger" @click="delete_by_selected()">
            <lucide-icon name="trash-2" /> {{$t('Del')}}
          </button>
        </div>

        <!-- table actions -->
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info m-1" size="sm" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            {{ $t("Filter") }}
          </b-button>

          <b-button @click="Product_PDF()" size="sm" variant="outline-success m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>

          <vue-excel-xlsx
            class="btn btn-sm btn-outline-danger m-1"
            :data="products"
            :columns="excelColumns"
            :file-name="'products'"
            :file-type="'xlsx'"
            :sheet-name="'products'"
          >
            <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>

          <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('product_import')"
            :to="{ name: 'import_products' }"
            class="btn btn-sm btn-outline-info m-1"
          >
            <lucide-icon name="download" />
            {{ $t("import_products") }}
          </router-link>

          <router-link
            class="btn btn-sm btn-primary m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('products_add')"
            to="/app/products/store"
          >
            <lucide-icon name="plus" />
            {{$t('Add')}}
          </router-link>
        </div>

        <!-- SAFE rendering: never v-html for user text -->
        <template slot="table-row" slot-scope="props">
          <!-- actions -->
          <span v-if="props.column.field === 'actions'" class="action-cell">
            <router-link
              v-if="can('products_view')"
              v-b-tooltip.hover
              title="View"
              :to="{ name:'detail_product', params: { id: props.row.id} }"
              class="btn btn-sm btn-outline-info action-btn"
            >
              <lucide-icon class="text-info" name="eye" />
            </router-link>

            <router-link
              v-if="can('products_edit')"
              v-b-tooltip.hover
              title="Edit"
              :to="{ name:'edit_product', params: { id: props.row.id } }"
              class="btn btn-sm btn-outline-success action-btn"
            >
              <lucide-icon class="text-success" name="pencil" />
            </router-link>

            <a
              v-if="can('products_add')"
              @click="Duplicate_Product(props.row.id)"
              v-b-tooltip.hover
              title="Duplicate"
              class="btn btn-sm btn-outline-warning action-btn"
            >
              <lucide-icon class="text-warning" name="copy" />
            </a>

            <a
              v-if="can('products_delete')"
              @click="Remove_Product(props.row.id)"
              v-b-tooltip.hover
              title="Delete"
              class="btn btn-sm btn-outline-danger action-btn"
            >
              <lucide-icon class="text-danger" name="x" />
            </a>
          </span>

          <!-- image (own slot, no html column flag) -->
          <span v-else-if="props.column.field === 'image'">
            <b-img
              thumbnail
              height="50"
              width="50"
              fluid
              :src="'/images/products/' + props.row.image"
              alt="image"
            />
          </span>

          <!-- multi-line text rendered safely -->
          <span v-else-if="props.column.field === 'name'" class="pre">{{ props.row.name }}</span>
          <span v-else-if="props.column.field === 'category'" class="pre">{{ props.row.categories_display || props.row.category }}</span>
          <span
            v-else-if="props.column.field === 'cost'"
            :class="{'pre': props.row.type === 'Variable'}"
          >
            {{ props.row.type === 'Variable' 
              ? formatPriceWithSymbol(currentUser && currentUser.currency ? currentUser.currency : '', firstLine(props.row.cost), 2)
              : formatPriceWithSymbol(currentUser && currentUser.currency ? currentUser.currency : '', props.row.cost, 2) }}
          </span>
          <span
            v-else-if="props.column.field === 'price'"
            :class="{'pre': props.row.type === 'Variable'}"
          >
            {{ props.row.type === 'Variable' 
              ? formatPriceWithSymbol(currentUser && currentUser.currency ? currentUser.currency : '', firstLine(props.row.price), 2)
              : formatPriceWithSymbol(currentUser && currentUser.currency ? currentUser.currency : '', props.row.price, 2) }}
          </span>

          <!-- default -->
          <span v-else>
            {{ props.formattedRow[props.column.field] }}
          </span>
        </template>
      </vue-good-table>

      <!-- Filter sidebar -->
      <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
        <div class="px-3 py-2">
          <b-row>
            <b-col md="12">
              <b-form-group :label="$t('CodeProduct')">
                <b-form-input :placeholder="$t('SearchByCode')" v-model="Filter_code" />
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('ProductName')">
                <b-form-input :placeholder="$t('SearchByName')" v-model="Filter_name" />
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Categorie')">
                <v-select
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Category')"
                  v-model="Filter_category"
                  :options="categories.map(c => ({ label: c.name, value: c.id }))"
                />
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Brand')">
                <v-select
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Brand')"
                  v-model="Filter_brand"
                  :options="brands.map(b => ({ label: b.name, value: b.id }))"
                />
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('warehouse')">
                <v-select
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Warehouse')"
                  v-model="Filter_warehouse"
                  :options="warehouses.map(w => ({ label: w.name, value: w.id }))"
                />
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-button @click="Get_Products(serverParams.page)" variant="primary m-1" size="sm" block>
                <lucide-icon name="filter" /> {{ $t("Filter") }}
              </b-button>
            </b-col>

            <b-col md="6" sm="12">
              <b-button @click="Reset_Filter()" variant="danger m-1" size="sm" block>
                <lucide-icon name="power" /> {{ $t("Reset") }}
              </b-button>
            </b-col>
          </b-row>
        </div>
      </b-sidebar>

      <!-- Import modal (unchanged except safer handling) -->
      <b-modal ok-only ok-title="Cancel" size="md" id="importProducts" :title="$t('import_products')">
        <b-form @submit.prevent="Submit_import" enctype="multipart/form-data">
          <b-row>
            <b-col md="12" sm="12" class="mb-3">
              <b-form-group>
                <input type="file" @change="onFileSelected">
                <b-form-invalid-feedback id="File-feedback" class="d-block">
                  File must be in xlsx format
                </b-form-invalid-feedback>
              </b-form-group>
            </b-col>

            <b-col md="6" sm="12">
              <b-button type="submit" variant="primary" :disabled="ImportProcessing" size="sm" block>
                {{ $t("submit") }}
              </b-button>
              <div v-once class="typo__p" v-if="ImportProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>

            <b-col md="6" sm="12">
              <a :href="'/import/exemples/import_products.xlsx'" class="btn btn-info btn-sm btn-block">
                {{ $t("Download_exemple") }}
              </a>
            </b-col>

            <!-- import instructions table kept -->
            <!-- ... (your existing instructions table) ... -->
          </b-row>
        </b-form>
      </b-modal>
    </div>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: { title: "Products" },
  data() {
    return {
      serverParams: { sort: { field: "id", type: "desc" }, page: 1, perPage: 10 },
      selectedIds: [],
      ImportProcessing: false,
      data: new FormData(),
      import_products: "",
      search: "",
      totalRows: "",
      isLoading: true,
      limit: "10",
      Filter_brand: "",
      Filter_code: "",
      Filter_name: "",
      Filter_category: "",
      Filter_warehouse: "",
      categories: [],
      subcategories: [],
      brands: [],
      products: [],
      warehouses: [],
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    columns() {
      return [
        { label: this.$t("image"), field: "image", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("type"), field: "type", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Name_product"), field: "name", tdClass: "text-left pre", thClass: "text-left" },
        { label: this.$t("Code"), field: "code", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Brand"), field: "brand", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Categorie"), field: "category", tdClass: "text-left pre", thClass: "text-left" },
        { label: this.$t("Cost"), field: "cost", tdClass: "text-left pre", thClass: "text-left" },
        { label: this.$t("Price"), field: "price", tdClass: "text-left pre", thClass: "text-left" },
        { label: this.$t("Unit"), field: "unit", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Quantity"), field: "quantity", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Action"), field: "actions", tdClass: "text-left", thClass: "text-left", sortable: false }
      ];
    },
    excelColumns() {
      return [
        { label: this.$t("type"), field: "type" },
        { label: this.$t("Name_product"), field: "name" },
        { label: this.$t("Code"), field: "code" },
        { label: this.$t("Categorie"), field: "categories_display" },
        { label: this.$t("Cost"), field: "cost" },
        { label: this.$t("Price"), field: "price" },
        { label: this.$t("Unit"), field: "unit" },
        { label: this.$t("Quantity"), field: "quantity" }
      ];
    }
  },
  methods: {
    can(p) { return this.currentUserPermissions && this.currentUserPermissions.includes(p); },

    // Return first line of a possibly multi-line string
    firstLine(val) {
      if (val === null || val === undefined) return '';
      return String(val).split('\n')[0];
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      if (number === null || number === undefined || number === '') return '0.00';
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
        const decimals = Number.isInteger(dec) ? dec : 2;
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        return formatPriceDisplayHelper(number, decimals, key);
      } catch (e) {
        return this.formatNumber(number, dec);
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      const safeSymbol = symbol || "";
      const value = this.formatPriceDisplay(number, dec);
      return safeSymbol ? `${safeSymbol} ${value}` : value;
    },

    Product_PDF() {
      const pdf = new jsPDF("p", "pt");
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        pdf.addFont(fontPath, "Vazirmatn", "normal");
        pdf.addFont(fontPath, "Vazirmatn", "bold");
      } catch(e) { /* ignore if already added */ }
      pdf.setFont("Vazirmatn", "normal");

      const headers = [
        this.$t("type"),
        this.$t("Name_product"),
        this.$t("Code"),
        this.$t("Categorie"),
        this.$t("Cost"),
        this.$t("Price"),
        this.$t("Unit"),
        this.$t("Quantity")
      ];

      const products_pdf = JSON.parse(JSON.stringify(this.products));
      products_pdf.forEach(item => {
        item.name  = String(item.name || '').replace(/\r?\n/g, '\n');
        item.cost  = String(item.cost || '').replace(/\r?\n/g, '\n');
        item.price = String(item.price || '').replace(/\r?\n/g, '\n');
        item.categories_display = String(item.categories_display || '').replace(/\r?\n/g, '\n');
      });

      const body = products_pdf.map(p => ([
        p.type,
        p.name,
        p.code,
        p.categories_display || p.category,
        p.cost,
        p.price,
        p.unit,
        p.quantity
      ]));

      const marginX = 40;
      const rtl =
        (this.$i18n && ['ar','fa','ur','he'].includes(this.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      autoTable(pdf, {
        head: [headers],
        body,
        startY: 110,
        theme: 'striped',
        margin: { left: marginX, right: marginX },
        styles: { font: 'Vazirmatn', fontSize: 9, cellPadding: 4, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [63,81,181], textColor: 255 },
        alternateRowStyles: { fillColor: [245,247,250] },
        columnStyles: { 4: { halign: 'right' }, 5: { halign: 'right' }, 7: { halign: 'right' } },
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
          const title = this.$t('productsList') || 'Product List';
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

      pdf.save("Product_List.pdf");
    },

    Show_import_products() { this.$bvModal.show("importProducts"); },

    onFileSelected(e) {
      this.import_products = "";
      const file = e.target.files?.[0];
      if (!file) return;
      if (file.size >= 1048576) {
        this.makeToast("danger", this.$t("file_size_must_be_less_than_1_mega"), this.$t("Failed"));
        return;
      }
      this.import_products = file;
    },

    Submit_import() {
      NProgress.start(); NProgress.set(0.1);
      this.ImportProcessing = true;
      const fd = new FormData();
      fd.append("products", this.import_products);

      axios.post("products/import/csv", fd)
        .then(response => {
          this.ImportProcessing = false;
          NProgress.done();
          if (response.data.status === true) {
            this.makeToast("success", this.$t("Successfully_Imported"), this.$t("Success"));
            Fire.$emit("Event_import");
          } else {
            this.makeToast("danger", response.data.message || this.$t("Import_failed"), this.$t("Failed"));
          }
        })
        .catch(error => {
          this.ImportProcessing = false;
          NProgress.done();
          if (error.response && error.response.status === 422) {
            const firstError = Object.values(error.response.data.errors || { _: [this.$t('InvalidData')] })[0][0];
            this.makeToast("danger", firstError, this.$t("Failed"));
          } else {
            const message = error.response?.data?.message || this.$t("Please_follow_the_import_instructions");
            this.makeToast("danger", message, this.$t("Failed"));
          }
        });
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    },

    updateParams(newProps) { this.serverParams = Object.assign({}, this.serverParams, newProps); },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Products(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Products(1);
      }
    },

    selectionChanged({ selectedRows }) {
      this.selectedIds = selectedRows.map(r => r.id);
    },

    onSortChange(params) {
      const f = params[0]?.field;
      const field = (f === "brand") ? "brand_id" : (f === "category") ? "category_id" : f;
      this.updateParams({ sort: { type: params[0].type, field } });
      this.Get_Products(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Products(this.serverParams.page);
    },

    Reset_Filter() {
      this.search = "";
      this.Filter_brand = "";
      this.Filter_code = "";
      this.Filter_name = "";
      this.Filter_category = "";
      this.Filter_warehouse = "";
      this.Get_Products(this.serverParams.page);
    },

    setToStrings() {
      if (this.Filter_category === null) this.Filter_category = "";
      if (this.Filter_brand === null) this.Filter_brand = "";
      if (this.Filter_warehouse === null) this.Filter_warehouse = "";
    },

    Get_Products(page) {
      NProgress.start(); NProgress.set(0.1);
      this.setToStrings();

      axios.get(
        "products?page=" + page +
        "&code=" + encodeURIComponent(this.Filter_code || "") +
        "&name=" + encodeURIComponent(this.Filter_name || "") +
        "&category_id=" + encodeURIComponent(this.Filter_category || "") +
        "&brand_id=" + encodeURIComponent(this.Filter_brand || "") +
        "&warehouse_id=" + encodeURIComponent(this.Filter_warehouse || "") +
        "&SortField=" + encodeURIComponent(this.serverParams.sort.field) +
        "&SortType=" + encodeURIComponent(this.serverParams.sort.type) +
        "&search=" + encodeURIComponent(this.search || "") +
        "&limit=" + encodeURIComponent(this.limit)
      )
      .then(response => {
        this.products   = response.data.products;
        this.warehouses = response.data.warehouses;
        this.categories = response.data.categories;
        this.subcategories = response.data.subcategories || [];
        this.brands     = response.data.brands;
        this.totalRows  = response.data.totalRows;
        NProgress.done(); this.isLoading = false;
      })
      .catch(() => {
        NProgress.done();
        setTimeout(() => { this.isLoading = false; }, 500);
      });
    },

    Remove_Product(id) {
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
          NProgress.start(); NProgress.set(0.1);
          axios.delete("products/" + id)
            .then(() => {
              this.$swal(this.$t("Delete_Deleted"), this.$t("Deleted_in_successfully"), "success");
              Fire.$emit("Delete_Product");
            })
            .catch(() => {
              setTimeout(() => NProgress.done(), 500);
              this.$swal(this.$t("Delete_Failed"), this.$t("Delete.Therewassomethingwronge"), "warning");
            });
        }
      });
    },

    Duplicate_Product(id) {
      this.$swal({
        title: this.$t("Confirm"),
        text: this.$t("Are_you_sure_you_want_to_duplicate_this_product"),
        type: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Cancel"),
        confirmButtonText: this.$t("Yes")
      }).then(result => {
        if (result.value) {
          // Navigate to the create page with duplicate param to prefill data
          this.$router.push({ name: "store_product", query: { duplicate: id } });
        }
      });
    },

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
          NProgress.start(); NProgress.set(0.1);
          axios.post("products/delete/by_selection", { selectedIds: this.selectedIds })
            .then(() => {
              this.$swal(this.$t("Delete_Deleted"), this.$t("Deleted_in_successfully"), "success");
              Fire.$emit("Delete_Product");
            })
            .catch(() => {
              setTimeout(() => NProgress.done(), 500);
              this.$swal(this.$t("Delete_Failed"), this.$t("Delete_Therewassomethingwronge"), "warning");
            });
        }
      });
    }
  },

  created() {
    this.Get_Products(1);

    Fire.$on("Delete_Product", () => {
      this.Get_Products(this.serverParams.page);
      setTimeout(() => NProgress.done(), 500);
    });

    Fire.$on("Event_import", () => {
      setTimeout(() => {
        this.Get_Products(this.serverParams.page);
        this.$bvModal.hide("importProducts");
      }, 500);
    });
  }
};
</script>

<style scoped>
.pre { white-space: pre-line; }
</style>

