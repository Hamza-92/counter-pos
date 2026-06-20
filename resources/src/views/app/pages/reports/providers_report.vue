<template>
  <div class="main-content">
    <breadcumb :page="$t('SuppliersReport')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper print-table-only" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        :group-options="{
          enabled: true,
          headerPosition: 'bottom',
        }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
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
        styleClass="tableOne table-hover vgt-table mt-4"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>
       <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <a title="PDF" class="cursor-pointer" v-b-tooltip.hover @click="Download_PDF(props.row , props.row.id)">
              <lucide-icon class="text-25 text-success" name="copy" />
            </a>
            <router-link title="Report" :to="'/app/reports/detail_supplier/'+props.row.id">
             <lucide-icon class="text-25 text-info" name="eye" />
            </router-link>
          </span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>


<script>
import NProgress from "nprogress";
import { mapActions, mapGetters } from "vuex";

export default {
  metaInfo: {
    title: "Report Providers"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      limit: "10",
      search: "",
      totalRows: "",
      providers: [],
      provider: {},
       rows: [{
          total_purchase: 'Total',
         
          children: [
             
          ],
      },],
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns() {
      return [
        {
          label: this.$t("SupplierName"),
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
          label: this.$t("Purchases"),
          field: "total_purchase",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("TotalAmount"),
          field: "total_amount",
          type: "decimal",
          headerField: this.sumCount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Paid"),
          field: "total_paid",
          type: "decimal",
          headerField: this.sumCount2,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Total_Purchase_Due"),
          field: "due",
          type: "decimal",
          headerField: this.sumCount3,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Total_Purchase_Return_Due"),
          field: "return_Due",
          type: "decimal",
          headerField: this.sumCount4,
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

    sumCount(rowObj) {
        if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
            console.error('Invalid input for sumCount');
            return 0; // or whatever default value is appropriate
        }

        let sum = 0;
        for (let i = 0; i < rowObj.children.length; i++) {
            if (typeof rowObj.children[i].total_amount === 'number') {
                sum += rowObj.children[i].total_amount;
            } else {
                console.error('Invalid total_amount at index', i);
            }
        }
        return sum;
    },
    sumCount2(rowObj) {
        if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
            console.error('Invalid input for sumCount2');
            return 0; // or whatever default value is appropriate
        }

        let sum = 0;
        for (let i = 0; i < rowObj.children.length; i++) {
            if (typeof rowObj.children[i].total_paid === 'number') {
                sum += rowObj.children[i].total_paid;
            } else {
                console.error('Invalid total_paid at index', i);
            }
        }
        return sum;
    },
    sumCount3(rowObj) {
        if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
            console.error('Invalid input for sumCount3');
            return 0; // or whatever default value is appropriate
        }

        let sum = 0;
        for (let i = 0; i < rowObj.children.length; i++) {
            if (typeof rowObj.children[i].due === 'number') {
                sum += rowObj.children[i].due;
            } else {
                console.error('Invalid due at index', i);
            }
        }
        return sum;
    },
    sumCount4(rowObj) {
        if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
            console.error('Invalid input for sumCount4');
            return 0; // or whatever default value is appropriate
        }

        let sum = 0;
        for (let i = 0; i < rowObj.children.length; i++) {
            if (typeof rowObj.children[i].return_Due === 'number') {
                sum += rowObj.children[i].return_Due;
            } else {
                console.error('Invalid return_Due at index', i);
            }
        }
        return sum;
    },


    //--------------------------- Download_PDF -------------------------------\\
    Download_PDF(provider , id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
     
       axios
        .get("report/provider_pdf/" + id, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "report-" + provider.name + ".pdf");
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

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Provider_Report(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Provider_Report(1);
      }
    },

    //---- Event on Sort Change

    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Provider_Report(this.serverParams.page);
    },

    //---- Event on Search

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Provider_Report(this.serverParams.page);
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

    //------ Print Table Only
    printTableOnly() {
      const root = this.$el;
      if (!root) {
        window.print();
        return;
      }

      const tableCard = root.querySelector(".print-table-only");
      if (!tableCard) {
        window.print();
        return;
      }

      // Get providers data from rows[0].children or this.providers
      const providersData = Array.isArray(this.rows[0]?.children) && this.rows[0].children.length > 0 
        ? this.rows[0].children 
        : (this.providers || []);

      // Manually construct the table HTML from providers data
      let tableHtml = `<table class="vgt-table table table-hover tableOne">`;

      // Table Header
      tableHtml += `<thead><tr>`;
      this.columns.filter(col => col.field !== 'actions').forEach(col => {
        tableHtml += `<th class="text-left">${col.label}</th>`;
      });
      tableHtml += `</tr></thead>`;

      // Table Body
      tableHtml += `<tbody>`;
      providersData.forEach(row => {
        tableHtml += `<tr>`;
        this.columns.filter(col => col.field !== 'actions').forEach(col => {
          let cellContent = row[col.field];
          if (['total_amount', 'total_paid', 'due', 'return_Due'].includes(col.field)) {
            cellContent = this.formatNumber(row[col.field], 2);
          }
          tableHtml += `<td class="text-left">${cellContent || ''}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      // Table Footer (Totals)
      const totalAmount = this.sumCount(this.rows[0]);
      const totalPaid = this.sumCount2(this.rows[0]);
      const totalDue = this.sumCount3(this.rows[0]);
      const totalReturnDue = this.sumCount4(this.rows[0]);

      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Total')}</td>`;
      tableHtml += `<td colspan="2"></td>`; // Span for SupplierName, Phone
      tableHtml += `<td class="text-left font-weight-bold">${this.formatNumber(totalAmount, 2)}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatNumber(totalPaid, 2)}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatNumber(totalDue, 2)}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatNumber(totalReturnDue, 2)}</td>`;
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("SuppliersReport")}`;
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
        .join("\n");

      const inlineStyles = Array.from(document.querySelectorAll("style"))
        .filter(s => !((s.textContent || "").includes("@media print")))
        .map(s => s.outerHTML)
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
    ${inlineStyles}
    <style>
      @media print { body, body * { visibility: visible !important; } }
      body { margin: 0.3cm; }
      .print-header { font-weight: 600; margin-bottom: 8px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f2f2f2; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    ${tableHtml}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    },

    //--------------------------- Get Customer Report -------------\\

    Get_Provider_Report(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "report/provider?page=" +
            page +
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
          this.providers = response.data.report;
          this.totalRows = response.data.totalRows;
          this.rows[0].children = this.providers;
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
    }
  }, //end Methods

  //----------------------------- Created function------------------- \\

  created: function() {
    this.Get_Provider_Report(1);
  }
};
</script>