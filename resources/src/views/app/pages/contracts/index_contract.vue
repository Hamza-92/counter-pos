<template>
  <div class="main-content contracts-page">
    <breadcumb :page="$t('Contract_List') || 'Contract List'" :folder="$t('Contracts') || 'Contracts'"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <!-- Dashboard counters -->
      <div class="stats-dashboard mb-4">
        <div class="stat-card active">
          <div class="stat-icon-wrapper"><lucide-icon class="stat-icon" name="check" /></div>
          <div class="stat-content">
            <div class="stat-value">{{ count_active }}</div>
            <div class="stat-label">Active</div>
          </div>
        </div>
        <div class="stat-card expired">
          <div class="stat-icon-wrapper"><lucide-icon class="stat-icon" name="x" /></div>
          <div class="stat-content">
            <div class="stat-value">{{ count_expired }}</div>
            <div class="stat-label">Expired</div>
          </div>
        </div>
        <div class="stat-card about-to-expire">
          <div class="stat-icon-wrapper"><lucide-icon class="stat-icon" name="alert-triangle" /></div>
          <div class="stat-content">
            <div class="stat-value">{{ count_about_to_expire }}</div>
            <div class="stat-label">About to Expire</div>
          </div>
        </div>
        <div class="stat-card recently-added">
          <div class="stat-icon-wrapper"><lucide-icon class="stat-icon" name="plus" /></div>
          <div class="stat-content">
            <div class="stat-value">{{ count_recently_added }}</div>
            <div class="stat-label">Recently Added</div>
          </div>
        </div>
        <div class="stat-card trash">
          <div class="stat-icon-wrapper"><lucide-icon class="stat-icon" name="trash-2" /></div>
          <div class="stat-content">
            <div class="stat-value">{{ count_trash }}</div>
            <div class="stat-label">Trash</div>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <b-row class="mb-4">
        <b-col md="6" class="mb-3">
          <b-card title="Contracts by Type" class="chart-card">
            <apexchart v-if="chartByType.series.length" type="donut" height="280" :options="chartByType.options" :series="chartByType.series"></apexchart>
            <p v-else class="text-muted mb-0">No data</p>
          </b-card>
        </b-col>
        <b-col md="6" class="mb-3">
          <b-card title="Contract Value by Type (USD)" class="chart-card">
            <apexchart v-if="chartValueByType.series.length" type="bar" height="280" :options="chartValueByType.options" :series="chartValueByType.series"></apexchart>
            <p v-else class="text-muted mb-0">No data</p>
          </b-card>
        </b-col>
      </b-row>

      <!-- Table -->
      <div class="control-bar mb-3">
        <router-link to="/app/contracts/store" class="btn btn-primary">
          <lucide-icon name="plus" /> {{ $t('Add') || 'Add' }} Contract
        </router-link>
      </div>

      <div class="table-card">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="contracts"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
          :search-options="{ enabled: true, placeholder: $t('Search_this_table') || 'Search' }"
          :pagination-options="{ enabled: true, mode: 'records' }"
          styleClass="vgt-table table-hover"
        >
          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field === 'actions'">
              <router-link :to="'/app/contracts/view/' + props.row.id" class="btn btn-sm btn-info mr-1" title="View"><lucide-icon name="eye" /></router-link>
              <router-link :to="'/app/contracts/edit/' + props.row.id" class="btn btn-sm btn-primary mr-1" title="Edit"><lucide-icon name="pencil" /></router-link>
              <b-button size="sm" variant="danger" @click="Remove_Contract(props.row.id)" title="Delete"><lucide-icon name="x" /></b-button>
            </span>
            <span v-else-if="props.column.field === 'status'">
              <span :class="['badge', 'status-' + props.row.status]">{{ props.row.status }}</span>
            </span>
            <span v-else-if="props.column.field === 'party_type'">
              <span :class="['badge', 'party-' + (props.row.party_type || 'customer')]">
                {{ (props.row.party_type || 'customer') === 'employee' ? ($t('Employee') || 'Employee') : ($t('Customer') || 'Customer') }}
              </span>
            </span>
            <span v-else-if="props.column.field === 'value'">
              ${{ Number(props.row.value).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
            </span>
            <span v-else>{{ props.formattedRow[props.column.field] }}</span>
          </template>
        </vue-good-table>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import NProgress from "nprogress";
import VueApexCharts from "vue-apexcharts";

export default {
  metaInfo: { title: "Contracts" },
  components: { apexchart: VueApexCharts },
  data() {
    return {
      isLoading: true,
      serverParams: { sort: { field: "id", type: "desc" }, page: 1, perPage: 10 },
      totalRows: 0,
      search: "",
      limit: "10",
      contracts: [],
      count_active: 0,
      count_expired: 0,
      count_about_to_expire: 0,
      count_recently_added: 0,
      count_trash: 0,
      chartByType: { series: [], options: { labels: [], legend: { position: 'bottom' } } },
      chartValueByType: { series: [{ name: 'Value (USD)', data: [] }], options: { chart: { type: 'bar' }, xaxis: { categories: [] }, plotOptions: { bar: { horizontal: true } } } },
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),
    columns() {
      return [
        { label: this.$t("Contract_Number") || "Contract #", field: "contract_number", thClass: "text-left" },
        { label: this.$t("Subject") || "Subject", field: "subject", thClass: "text-left" },
        { label: this.$t("Party_Type") || "Party", field: "party_type", thClass: "text-left" },
        { label: this.$t("Name") || "Name", field: "party_name", thClass: "text-left" },
        { label: this.$t("Value") || "Value", field: "value", thClass: "text-right" },
        { label: this.$t("Type") || "Type", field: "type", thClass: "text-left" },
        { label: this.$t("start_date") || "Start", field: "start_date", thClass: "text-left" },
        { label: this.$t("Finish_Date") || "End", field: "end_date", thClass: "text-left" },
        { label: this.$t("Status") || "Status", field: "status", thClass: "text-left" },
        { label: this.$t("Action") || "Actions", field: "actions", sortable: false, thClass: "text-left" },
      ];
    },
  },
  methods: {
    makeToast(variant, msg, title) {
      this.$bvToast.toast(msg, { title: title || this.$t("Notice") || "Notice", variant: variant, solid: true });
    },
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },
    onPageChange({ currentPage }) {
      this.updateParams({ page: currentPage });
      this.Get_Contracts(currentPage);
    },
    onPerPageChange({ currentPerPage }) {
      this.limit = currentPerPage;
      this.updateParams({ page: 1, perPage: currentPerPage });
      this.Get_Contracts(1);
    },
    onSortChange(params) {
      let field = params[0].field;
      if (field === "client_name" || field === "party_name") field = "client_id";
      this.updateParams({ sort: { type: params[0].type, field } });
      this.Get_Contracts(this.serverParams.page);
    },
    onSearch(value) {
      this.search = value.searchTerm || "";
      this.Get_Contracts(1);
    },
    Get_Contracts(page) {
      NProgress.start();
      const dashboardReq = axios.get("contracts/dashboard").then(r => {
        this.count_active = r.data.count_active;
        this.count_expired = r.data.count_expired;
        this.count_about_to_expire = r.data.count_about_to_expire;
        this.count_recently_added = r.data.count_recently_added;
        this.count_trash = r.data.count_trash;
        const charts = r.data.charts || {};
        this.chartByType.series = (charts.by_type || []).map(x => x.count);
        this.chartByType.options.labels = (charts.by_type || []).map(x => x.type);
        this.chartValueByType.series[0].data = (charts.value_by_type || []).map(x => x.value);
        this.chartValueByType.options.xaxis.categories = (charts.value_by_type || []).map(x => x.type);
      }).catch(() => {});
      const listReq = axios.get(
        "contracts?page=" + page +
        "&SortField=" + this.serverParams.sort.field +
        "&SortType=" + this.serverParams.sort.type +
        "&search=" + encodeURIComponent(this.search) +
        "&limit=" + this.limit
      ).then(response => {
        this.contracts = response.data.contracts;
        this.totalRows = response.data.totalRows;
      }).catch(() => {});
      Promise.all([dashboardReq, listReq]).finally(() => {
        this.isLoading = false;
        NProgress.done();
      });
    },
    Remove_Contract(id) {
      this.$bvModal.msgBoxConfirm(this.$t("Confirm_delete") || "Are you sure?", {
        title: this.$t("Confirm"),
        size: "sm",
        buttonSize: "sm",
        okVariant: "danger",
        okTitle: this.$t("yes"),
        cancelTitle: this.$t("no"),
        footerClass: "p-2",
        hideHeaderClose: false,
      }).then(ok => {
        if (ok) {
          axios.delete("contracts/" + id).then(() => {
            this.makeToast("success", this.$t("Deleted_in_successfully") || "Deleted successfully", this.$t("Success") || "Success");
            this.Get_Contracts(this.serverParams.page);
          }).catch(() => this.makeToast("danger", this.$t("Something_went_wrong") || "Something went wrong", this.$t("Failed") || "Failed"));
        }
      });
    },
  },
  mounted() {
    this.Get_Contracts(this.serverParams.page);
  },
};
</script>

<style scoped>
.stats-dashboard { display: flex; flex-wrap: wrap; gap: 1rem; }
.stat-card { flex: 1; min-width: 120px; padding: 1rem; border-radius: 8px; display: flex; align-items: center; gap: 0.75rem; }
.stat-card.active { background: #d1fae5; color: #065f46; }
.stat-card.expired { background: #fee2e2; color: #991b1b; }
.stat-card.about-to-expire { background: #fef3c7; color: #92400e; }
.stat-card.recently-added { background: #dbeafe; color: #1e40af; }
.stat-card.trash { background: #e5e7eb; color: #374151; }
.stat-value { font-size: 1.5rem; font-weight: 700; }
.badge.status-active { background: #10b981; }
.badge.status-expired { background: #ef4444; }
.badge.status-draft { background: #6b7280; }
.badge.status-cancelled { background: #f59e0b; }
.badge.party-customer { background: #3b82f6; color: #fff; }
.badge.party-employee { background: #8b5cf6; color: #fff; }
</style>
