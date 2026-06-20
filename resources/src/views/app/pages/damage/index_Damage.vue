<template>
  <div class="main-content">
    <breadcumb :page="$t('Damages')" :folder="$t('Adjustment')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="damages"
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
        styleClass="table-hover tableOne vgt-table"
      >
      
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info m-1" size="sm" v-b-toggle.sidebar-right>
            <lucide-icon name="filter" />
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Damage_PDF()" size="sm" variant="outline-success m-1">
            <lucide-icon name="copy" /> {{$t('Export_PDF')}}
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="damages"
              :columns="columns"
              :file-name="'Damages'"
              :file-type="'xlsx'"
              :sheet-name="'Damages'"
              >
              <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
          <router-link
            class="btn-sm btn btn-primary btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('damage_view')"
            to="/app/damages/store"
          >
            <span class="ul-btn__icon">
              <lucide-icon name="plus" />
            </span>
            <span class="ul-btn__text ml-1">{{$t('Add')}}</span>
          </router-link>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <a :title="$t('Download_PDF')" v-b-tooltip.hover @click="download_damage_pdf(props.row , props.row.id)">
              <lucide-icon class="text-25 text-primary cursor-pointer" name="file-text" />
            </a>
            <a v-b-tooltip.hover :title="$t('View')" class="cursor-pointer" @click="showDetails(props.row.id)">
              <lucide-icon class="text-25 text-info" name="eye" />
            </a>
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('adjustment_edit')"
              v-b-tooltip.hover
              :title="$t('Edit')"
              :to="'/app/damages/edit/'+props.row.id"
            >
              <lucide-icon class="text-25 text-success" name="pencil" />
            </router-link>
            <a
              v-b-tooltip.hover
              :title="$t('Delete')"
              class="cursor-pointer"
              v-if="currentUserPermissions && currentUserPermissions.includes('adjustment_delete')"
              @click="Remove_Damage(props.row.id)"
            >
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </div>

    <!-- Multiple Filters -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
          <b-col md="12">
            <b-form-group :label="$t('date')">
              <b-form-input type="date" v-model="Filter_date"></b-form-input>
            </b-form-group>
          </b-col>
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input label="Reference" :placeholder="$t('Reference')" v-model="Filter_Ref"></b-form-input>
            </b-form-group>
          </b-col>
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
          <b-col md="6" sm="12">
            <b-button
              @click="Get_Damages(serverParams.page)"
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

    <!-- Show details -->
    <b-modal ok-only size="lg" id="showDetails" :title="$t('Damage_Detail')">
      <b-row>
        <b-col lg="5" md="12" sm="12" class="mt-3">
          <table class="table table-hover table-bordered table-sm">
            <tbody>
              <tr>
                <td>{{$t('date')}}</td>
                <th>{{damage.date}}</th>
              </tr>
              <tr>
                <td>{{$t('Reference')}}</td>
                <th>{{damage.Ref}}</th>
              </tr>
              <tr>
                <td>{{$t('warehouse')}}</td>
                <th>{{damage.warehouse}}</th>
              </tr>
            </tbody>
          </table>
        </b-col>
        <b-col lg="7" md="12" sm="12" class="mt-3">
          <div class="table-responsive">
            <table class="table table-hover table-bordered table-sm">
              <thead>
                <tr>
                  <th scope="col">{{$t('ProductName')}}</th>
                  <th scope="col">{{$t('CodeProduct')}}</th>
                  <th scope="col">{{$t('Quantity')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="detail in details" :key="detail.code + detail.name">
                  <td>{{detail.name}}</td>
                  <td>{{detail.code}}</td>
                  <td>{{formatNumber(detail.quantity ,2)}} {{detail.unit}}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </b-col>
      </b-row>
       <hr v-show="damage.note">
          <b-row class="mt-4">
           <b-col md="12">
             <p>{{damage.note}}</p>
           </b-col>
        </b-row>
    </b-modal>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

export default {
  metaInfo: { title: "Damage" },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: { field: "id", type: "desc" },
        page: 1,
        perPage: 10
      },
      search: "",
      totalRows: "",
      limit: "10",
      Filter_date: "",
      Filter_Ref: "",
      Filter_warehouse: "",
      warehouses: [],
      damages: [],
      details: [],
      damage: {}
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions"]),
    columns() {
      return [
        { label: this.$t("date"), field: "date", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Reference"), field: "Ref", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("warehouse"), field: "warehouse_name", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("TotalProducts"), field: "items", type: "decimal", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Action"), field: "actions", tdClass: "text-left", thClass: "text-left", sortable: false }
      ];
    }
  },
  methods: {
    download_damage_pdf(damage, id) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("damage_pdf/" + id, { responseType: "blob", headers: { "Content-Type": "application/json" } })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Damage-" + damage.Ref + ".pdf");
          document.body.appendChild(link);
          link.click();
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => setTimeout(() => NProgress.done(), 500));
    },
    Damage_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try { pdf.addFont(fontPath, "Vazirmatn", "normal"); pdf.addFont(fontPath, "Vazirmatn", "bold"); } catch(e) {}
      pdf.setFont("Vazirmatn", "normal");
      const headers = [ self.$t("date"), self.$t("Reference"), self.$t("warehouse"), self.$t("TotalProducts") ];
      const body = (self.damages || []).map(d => ([ d.date, d.Ref, d.warehouse_name, d.items ]));
      const marginX = 40;
      const rtl = (self.$i18n && ['ar','fa','ur','he'].includes(self.$i18n.locale)) || (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');
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
          pdf.setFillColor(63,81,181); pdf.rect(0, 0, pageW, 60, 'F');
          pdf.setTextColor(255); pdf.setFont('Vazirmatn', 'bold'); pdf.setFontSize(16);
          const title = self.$t('Damages');
          rtl ? pdf.text(title, pageW - marginX, 38, { align: 'right' }) : pdf.text(title, marginX, 38);
          pdf.setTextColor(33); pdf.setFontSize(8);
          const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
          rtl ? pdf.text(pn, marginX, pageH - 14, { align: 'left' }) : pdf.text(pn, pageW - marginX, pageH - 14, { align: 'right' });
        }
      });
      pdf.save("Damage_List.pdf");
    },
    showDetails(id) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("damages/detail/" + id)
        .then(response => {
          this.damage = response.data.damage;
          this.details = response.data.details;
          Fire.$emit("Get_Details_Damage");
        })
        .catch(() => { Fire.$emit("Get_Details_Damage"); });
    },
    formatNumber(number, dec) {
      const value = (typeof number === "string" ? number : number.toString()).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec) return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },
    updateParams(newProps) { this.serverParams = Object.assign({}, this.serverParams, newProps); },
    onPageChange({ currentPage }) { if (this.serverParams.page !== currentPage) { this.updateParams({ page: currentPage }); this.Get_Damages(currentPage); } },
    onPerPageChange({ currentPerPage }) { if (this.limit !== currentPerPage) { this.limit = currentPerPage; this.updateParams({ page: 1, perPage: currentPerPage }); this.Get_Damages(1); } },
    onSortChange(params) {
      let field = params[0].field == "warehouse_name" ? "warehouse_id" : params[0].field;
      this.updateParams({ sort: { type: params[0].type, field } });
      this.Get_Damages(this.serverParams.page);
    },
    onSearch(value) { this.search = value.searchTerm; this.Get_Damages(this.serverParams.page); },
    Reset_Filter() { this.search = ""; this.Filter_date = ""; this.Filter_Ref = ""; this.Filter_warehouse = ""; this.Get_Damages(this.serverParams.page); },
    setToStrings() { if (this.Filter_warehouse === null) { this.Filter_warehouse = ""; } },
    Get_Damages(page) {
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "damages?page=" + page +
          "&Ref=" + this.Filter_Ref +
          "&warehouse_id=" + this.Filter_warehouse +
          "&date=" + this.Filter_date +
          "&SortField=" + this.serverParams.sort.field +
          "&SortType=" + this.serverParams.sort.type +
          "&search=" + this.search +
          "&limit=" + this.limit
        )
        .then(response => {
          this.damages = response.data.damages;
          this.warehouses = response.data.warehouses;
          this.totalRows = response.data.totalRows;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => { NProgress.done(); setTimeout(() => { this.isLoading = false; }, 500); });
    },
    Remove_Damage(id) {
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
            .delete("damages/" + id)
            .then(() => {
              this.$swal(this.$t("Delete_Deleted"), this.$t("Deleted_in_successfully"), "success");
              Fire.$emit("Delete_Damage");
            })
            .catch(() => {
              setTimeout(() => NProgress.done(), 500);
              this.$swal(this.$t("Delete_Failed"), this.$t("Delete_Therewassomethingwronge"), "warning");
            });
        }
      });
    },
  },
  created() {
    this.Get_Damages(1);
    Fire.$on("Get_Details_Damage", () => { setTimeout(() => NProgress.done(), 500); this.$bvModal.show("showDetails"); });
    Fire.$on("Delete_Damage", () => { setTimeout(() => { NProgress.done(); this.Get_Damages(this.serverParams.page); }, 500); });
  }
};
</script>


