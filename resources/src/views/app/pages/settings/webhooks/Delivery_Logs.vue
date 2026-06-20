<template>
  <div class="main-content">
    <breadcumb :page="$t('Delivery_Logs') || 'Delivery Logs'" :folder="$t('Webhooks')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <div class="mb-3 d-flex flex-wrap" style="gap: 8px;">
        <router-link
          class="btn btn-outline-secondary btn-rounded btn-sm"
          to="/app/settings/webhooks/list"
        >
          <lucide-icon name="arrow-left" /> {{ $t("Back") || "Back to Webhooks" }}
        </router-link>
        <b-form-select
          v-model="statusFilter"
          :options="statusOptions"
          size="sm"
          @change="Get_Logs(1)"
          style="max-width: 180px;"
        ></b-form-select>
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="table-hover tableOne vgt-table"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'status'">
            <b-badge :variant="statusVariant(props.row.status)">{{ props.row.status }}</b-badge>
          </span>
          <span v-else-if="props.column.field == 'webhook'">
            {{ props.row.webhook ? props.row.webhook.name : "—" }}
          </span>
          <span v-else-if="props.column.field == 'response_code'">
            <b-badge v-if="props.row.response_code" :variant="httpVariant(props.row.response_code)">
              {{ props.row.response_code }}
            </b-badge>
            <span v-else>—</span>
          </span>
          <span v-else-if="props.column.field == 'actions'">
            <a @click="Show_Log(props.row)" title="View" v-b-tooltip.hover>
              <lucide-icon class="text-25 text-info" name="eye" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </b-card>

    <b-modal hide-footer size="lg" id="Log_Modal" :title="$t('Delivery_Details') || 'Delivery Details'">
      <div v-if="active">
        <p><strong>Event:</strong> {{ active.event }}</p>
        <p><strong>Webhook:</strong> {{ active.webhook && active.webhook.name }}</p>
        <p><strong>URL:</strong> {{ active.webhook && active.webhook.url }}</p>
        <p><strong>Status:</strong> <b-badge :variant="statusVariant(active.status)">{{ active.status }}</b-badge></p>
        <p><strong>Response code:</strong> {{ active.response_code || "—" }}</p>
        <p><strong>Attempt:</strong> {{ active.attempt }}</p>
        <p><strong>Duration:</strong> {{ active.duration_ms }} ms</p>
        <p v-if="active.error_message"><strong>Error:</strong> {{ active.error_message }}</p>
        <h6 class="mt-3">Payload</h6>
        <pre style="max-height: 260px; overflow: auto; background:#f8f9fa; padding:8px; border-radius:4px;">{{ formatJson(active.payload) }}</pre>
        <h6 class="mt-3">Response body</h6>
        <pre style="max-height: 220px; overflow: auto; background:#f8f9fa; padding:8px; border-radius:4px;">{{ active.response_body || "—" }}</pre>
      </div>
    </b-modal>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: { title: "Webhook Delivery Logs" },
  data() {
    return {
      isLoading: true,
      serverParams: {
        columnFilters: {},
        sort: { field: "id", type: "desc" },
        page: 1,
        perPage: 15,
      },
      totalRows: 0,
      search: "",
      limit: "15",
      rows: [],
      active: null,
      statusFilter: "",
      statusOptions: [
        { value: "", text: "All statuses" },
        { value: "pending", text: "Pending" },
        { value: "retrying", text: "Retrying" },
        { value: "success", text: "Success" },
        { value: "failed", text: "Failed" },
      ],
    };
  },
  computed: {
    columns() {
      return [
        { label: "ID", field: "id", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Event") || "Event", field: "event", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Webhook") || "Webhook", field: "webhook", sortable: false, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Status"), field: "status", tdClass: "text-center", thClass: "text-center" },
        { label: "HTTP", field: "response_code", tdClass: "text-center", thClass: "text-center" },
        { label: this.$t("Attempt") || "Attempt", field: "attempt", tdClass: "text-center", thClass: "text-center" },
        { label: this.$t("Created_At") || "Created", field: "created_at", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Action"), field: "actions", sortable: false, tdClass: "text-left", thClass: "text-left" },
      ];
    },
  },
  methods: {
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Logs(currentPage);
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Logs(1);
      }
    },
    onSortChange(params) {
      this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
      this.Get_Logs(this.serverParams.page);
    },
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Logs(this.serverParams.page);
    },
    statusVariant(s) {
      return {
        success: "success",
        failed: "danger",
        pending: "secondary",
        retrying: "warning",
      }[s] || "secondary";
    },
    httpVariant(code) {
      if (code >= 200 && code < 300) return "success";
      if (code >= 400 && code < 500) return "warning";
      if (code >= 500) return "danger";
      return "secondary";
    },
    formatJson(value) {
      if (!value) return "—";
      try {
        return JSON.stringify(JSON.parse(value), null, 2);
      } catch (_) {
        return value;
      }
    },
    Show_Log(row) {
      this.active = row;
      this.$bvModal.show("Log_Modal");
    },
    Get_Logs(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("webhooks/deliveries", {
          params: {
            page,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.limit,
            status: this.statusFilter,
          },
        })
        .then((response) => {
          this.rows = response.data.deliveries;
          this.totalRows = response.data.totalRows;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => (this.isLoading = false), 500);
        });
    },
  },
  created() {
    this.Get_Logs(1);
  },
};
</script>
