<template>
  <div class="main-content">
    <breadcumb :page="$t('Expense_List')" :folder="$t('Expenses')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="expenses"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          enabled: true,
          placeholder: $t('Search_this_table'),  
        }"
        
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="tableOne table-hover vgt-table"
      >
        <div slot="selected-row-actions" v-if="currentUserPermissions && currentUserPermissions.includes('expense_delete')">
          <button class="btn btn-danger btn-sm" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Expense_PDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
           <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="expenses"
              :columns="columns"
              :file-name="'Expenses'"
              :file-type="'xlsx'"
              :sheet-name="'Expenses'"
              >
              <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
          <router-link
            class="btn-sm btn btn-primary ripple btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('expense_add')"
            to="/app/expenses/store"
          >
            <span class="ul-btn__icon">
              <lucide-icon name="plus" />
            </span>
            <span class="ul-btn__text ml-1">{{$t('Add')}}</span>
          </router-link>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'date'">
            {{ formatDisplayDate(props.row.date) }}
          </span>
          <span v-else-if="props.column.field == 'documents'">
            <span v-if="props.row.documents_count > 0" class="badge badge-info">
              <lucide-icon name="file" /> {{ props.row.documents_count }}
            </span>
            <span v-else class="text-muted">-</span>
          </span>
          <span v-else-if="props.column.field == 'actions'">
            <a
              title="Attach Documents"
              class="cursor-pointer mr-2"
              v-b-tooltip.hover
              @click="Manage_Documents(props.row.id)"
            >
              <lucide-icon class="text-20 text-info" name="file" />
            </a>
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('expense_edit')"
              title="Edit"
              v-b-tooltip.hover
              :to="'/app/expenses/edit/'+props.row.id"
            >
              <lucide-icon class="text-20 text-success" name="pencil" />
            </router-link>
            <a
              title="Delete"
              class="cursor-pointer ml-2"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('expense_delete')"
              @click="Remove_Expense(props.row.id)"
            >
              <lucide-icon class="text-20 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </div>

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

    <!-- Multiple Filters -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <!-- date  -->
          <b-col md="12">
            <b-form-group :label="$t('date')">
              <b-form-input type="date" v-model="Filter_date"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Reference  -->
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input label="Reference" :placeholder="$t('Reference')" v-model="Filter_Ref"></b-form-input>
            </b-form-group>
          </b-col>

           <!-- Payment choice -->
            <b-col md="12">
            <b-form-group :label="$t('Paymentchoice')">
              <v-select
                v-model="Filter_Reg"
                :reduce="label => label.value"
                :placeholder="$t('PleaseSelect')"
                :options="payment_methods.map(payment_methods => ({label: payment_methods.name, value: payment_methods.id}))"
              ></v-select>
            </b-form-group>
          </b-col>

            <!-- Account  -->
            <b-col md="12">
            <b-form-group :label="$t('Account')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Account')"
                v-model="Filter_account"
                :options="accounts.map(accounts => ({label: accounts.account_name, value: accounts.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- warehouse  -->
          <b-col md="12">
            <b-form-group :label="$t('warehouse')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                v-model="Filter_warehouse"
                :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Expense_Category  -->
          <b-col md="12">
            <b-form-group :label="$t('Expense_Category')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Category')"
                v-model="Filter_category"
                :options="expense_Category.map(expense_Category => ({label: expense_Category.name, value: expense_Category.id}))"
              />
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Get_Expenses(serverParams.page)"
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
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import Util from '../../../../utils';

export default {
  metaInfo: {
    title: "Expense"
  },
  data() {
    return {
      isLoading: true,
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
      Filter_date: "",
      Filter_Ref: "",
      Filter_warehouse: "",
      Filter_category: "",
      Filter_account: "",
      Filter_Reg: "",
      expenses: [],
      warehouses: [],
      payment_methods: [],
      accounts: [],
      expense_Category: [],
      documents: [],
      selectedFiles: [],
      currentExpenseId: null,
      uploadProcessing: false
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    columns() {
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
          label: this.$t("ModePaiement"),
          field: "payment_method",
          tdClass: "text-left",
          thClass: "text-left"
        },


        {
          label: this.$t("Account"),
          field: "account_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
       
        {
          label: this.$t("Amount"),
          field: "amount",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Categorie"),
          field: "category_name",
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
          label: this.$t("Details"),
          field: "details",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Documents"),
          field: "documents",
          tdClass: "text-center",
          thClass: "text-center"
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
    //---------------------- Expenses PDF -------------------------------\\
    Expense_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try { pdf.addFont(fontPath, "Vazirmatn", "normal"); pdf.addFont(fontPath, "Vazirmatn", "bold"); } catch(e){}
      pdf.setFont("Vazirmatn", "normal");

      const headers = [
        self.$t("date"),
        self.$t("Reference"),
        self.$t("Account"),
        self.$t("Categorie"),
        self.$t("warehouse"),
        self.$t("ModePaiement"),
        self.$t("Amount")
      ];

      const body = (self.expenses || []).map(expense => ([
        expense.date,
        expense.Ref,
        expense.account_name,
        expense.category_name,
        expense.warehouse_name,
        expense.payment_method,
        expense.amount
      ]));

      // Calculate totals
      let totalGrandTotal = self.expenses.reduce((sum, expense) => sum + parseFloat(expense.amount || 0), 0);
     
      const footer = [[
        self.$t("Total"),
        '',
        '',
        '',
        '',
        '',
        totalGrandTotal.toFixed(2)
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
          const title = self.$t('Expense_List') || 'Expense List';
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

      pdf.save("Expense_List.pdf");

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
      const date = new Date(value);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      return `${year}-${month}-${day} ${hours}:${minutes}`;
    },
    //----------------------------------------- Format Display Date (for tables) -------------------------------\\
    formatDisplayDate(value) {
      if (!value) return '';
      // Get date format from Vuex store (loaded from database) or fallback
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
      return Util.formatDisplayDate(value, dateFormat);
    },

    //------ update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Expenses(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Expenses(1);
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
      let field = "";
      if (params[0].field == "warehouse_name") {
        field = "warehouse_id";
      } else if (params[0].field == "category_name") {
        field = "expense_category_id";
      } else if (params[0].field == "account_name") {
        field = "account_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Get_Expenses(this.serverParams.page);
    },

    //------ Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Expenses(this.serverParams.page);
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_date = "";
      this.Filter_Ref = "";
      this.Filter_warehouse = "";
      this.Filter_category = "";
      this.Filter_account = "";
      this.Filter_Reg = "";
      this.Get_Expenses(this.serverParams.page);
    },

    // Simply replaces null values with strings=''
    setToStrings() {
      if (this.Filter_warehouse === null) {
        this.Filter_warehouse = "";
      } else if (this.Filter_category === null) {
        this.Filter_category = "";
      } else if (this.Filter_account === null) {
        this.Filter_account = "";
       } else if (this.Filter_Reg === null) {
        this.Filter_Reg = "";
      }
    },

    //------------------------------------------------ Get All Expense -------------------------------\\
    Get_Expenses(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "expenses?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&account_id=" +
            this.Filter_account +
            "&payment_method_id=" +
            this.Filter_Reg +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&date=" +
            this.Filter_date +
            "&expense_category_id=" +
            this.Filter_category +
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
          this.expenses = response.data.expenses;
          this.expense_Category = response.data.Expenses_category;
          this.warehouses = response.data.warehouses;
          this.accounts = response.data.accounts;
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

    //---------------------- Manage Expense Documents -------------------------------\\
    Manage_Documents(expenseId) {
      this.currentExpenseId = expenseId;
      this.selectedFiles = [];
      NProgress.start();
      NProgress.set(0.1);
      this.Get_Documents(expenseId);
    },

    //----------------------------------------- Get Documents -------------------------------\\
    Get_Documents(expenseId) {
      axios
        .get("expenses/" + expenseId + "/documents")
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
      formData.append('expense_id', this.currentExpenseId);

      axios
        .post("expenses/" + this.currentExpenseId + "/documents", formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        })
        .then(response => {
          this.uploadProcessing = false;
          this.selectedFiles = [];
          this.Get_Documents(this.currentExpenseId);
          this.Get_Expenses(this.serverParams.page);
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
        .get("expenses/documents/" + doc.id + "/download", {
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
            .delete("expenses/documents/" + documentId)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              this.Get_Documents(this.currentExpenseId);
              this.Get_Expenses(this.serverParams.page);
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

    //------------------------------- Remove Expense -------------------------\\

    Remove_Expense(id) {
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
            .delete("expenses/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );
              Fire.$emit("Delete_Expense");
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

  },

  //----------------------------- Created function-------------------
  created: function() {
    this.Get_Expenses(1);

    Fire.$on("Delete_Expense", () => {
      setTimeout(() => {
        // Complete the animation of theprogress bar.
        NProgress.done();
        this.Get_Expenses(this.serverParams.page);
      }, 500);
    });
  }
};
</script>