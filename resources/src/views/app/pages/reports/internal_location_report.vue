<template>
  <div class="main-content">
    <breadcumb :page="$t('Internal_Location_Report')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-row class="justify-content-center mb-4" v-if="!isLoading">
      <b-col lg="4" md="6" sm="12">
        <b-form-group :label="$t('warehouse')">
          <v-select
            @input="Selected_Warehouse"
            v-model="warehouse_id"
            :reduce="label => label.value"
            :placeholder="$t('All_Warehouses')"
            :options="warehouses.map(w => ({ label: w.name, value: w.id }))"
          />
        </b-form-group>
      </b-col>
    </b-row>

    <vue-good-table
      v-if="!isLoading"
      mode="remote"
      :columns="columns"
      :totalRows="totalRows"
      :rows="rows"
      @on-page-change="onPageChange"
      @on-per-page-change="onPerPageChange"
      @on-sort-change="onSortChange"
      @on-search="onSearch"
      :search-options="{ placeholder: $t('Search_this_table'), enabled: true }"
      :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
      styleClass="table-hover tableOne vgt-table"
    >
      <template slot="table-row" slot-scope="props">
        <span v-if="props.column.field === 'location'">
          <span v-if="props.row.location_code">
            {{ props.row.location_code }}<span v-if="props.row.location_name"> - {{ props.row.location_name }}</span>
          </span>
          <span v-else>-</span>
        </span>
        <span v-else>
          {{ props.formattedRow[props.column.field] }}
        </span>
      </template>
    </vue-good-table>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Internal Location Report"
  },
  data() {
    return {
      isLoading: true,
      warehouses: [],
      warehouse_id: "",
      rows: [],
      totalRows: 0,
      searchTerm: "",
      limit: "10",
      serverParams: {
        sort: { field: "name", type: "asc" },
        page: 1,
        perPage: 10
      }
    };
  },
  computed: {
    columns() {
      return [
        { label: this.$t("warehouse"), field: "warehouse", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("CodeProduct"), field: "code", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("ProductName"), field: "name", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Internal_Location_Rack_Shelf"), field: "location", tdClass: "text-left", thClass: "text-left", sortable: false },
      ];
    }
  },
  methods: {
    Selected_Warehouse(val) {
      if (val === null) this.warehouse_id = "";
      this.getReport(1);
    },
    onPageChange(params) {
      this.serverParams.page = params.currentPage;
      this.getReport(this.serverParams.page);
    },
    onPerPageChange(params) {
      this.serverParams.perPage = params.currentPerPage;
      this.limit = String(params.currentPerPage);
      this.getReport(1);
    },
    onSortChange(params) {
      if (params && params.length) {
        this.serverParams.sort.field = params[0].field;
        this.serverParams.sort.type = params[0].type;
      }
      this.getReport(1);
    },
    onSearch(params) {
      this.searchTerm = params.searchTerm;
      this.getReport(1);
    },
    getReport(page) {
      NProgress.start();
      NProgress.set(0.1);
      this.isLoading = true;

      axios
        .get(
          "report/internal_location_report?page=" +
            page +
            "&limit=" +
            this.limit +
            "&warehouse_id=" +
            (this.warehouse_id || "") +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            (this.searchTerm || "")
        )
        .then(response => {
          this.rows = response.data.rows || [];
          this.totalRows = response.data.totalRows || 0;
          this.warehouses = response.data.warehouses || [];
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 400);
        });
    }
  },
  created() {
    this.getReport(1);
  }
};
</script>

