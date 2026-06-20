<template>
  <div class="main-content">
    <breadcumb :page="$t('Webhooks')" :folder="$t('Settings')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <div class="mb-3 d-flex flex-wrap" style="gap: 8px;">
        <router-link
          class="btn btn-outline-primary btn-rounded btn-sm"
          to="/app/settings/webhooks/delivery_logs"
        >
          <lucide-icon name="file-text" />
          {{ $t("Delivery_Logs") || "Delivery Logs" }}
        </router-link>
        <router-link
          class="btn btn-outline-secondary btn-rounded btn-sm"
          to="/app/settings/webhooks/incoming_logs"
        >
          <lucide-icon name="file-text" />
          {{ $t("Incoming_Logs") || "Incoming Logs" }}
        </router-link>
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="webhooks"
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
          <b-button
            v-if="canAdd"
            @click="New_Webhook()"
            class="btn-rounded"
            variant="btn btn-primary btn-icon m-1"
          >
            <lucide-icon name="plus" /> {{ $t("Add") }}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'is_active'">
            <b-badge v-if="props.row.is_active" variant="success">{{ $t("Active") || "Active" }}</b-badge>
            <b-badge v-else variant="secondary">{{ $t("Inactive") || "Inactive" }}</b-badge>
          </span>

          <span v-else-if="props.column.field == 'events'">
            <b-badge
              v-for="e in (props.row.events || []).slice(0, 3)"
              :key="e"
              variant="info"
              class="mr-1"
              >{{ e }}</b-badge
            >
            <span v-if="(props.row.events || []).length > 3">
              +{{ props.row.events.length - 3 }}
            </span>
          </span>

          <span v-else-if="props.column.field == 'actions'">
            <a
              v-if="canEdit"
              @click="Test_Webhook(props.row.id)"
              title="Send Test"
              v-b-tooltip.hover
            >
              <lucide-icon class="text-25 text-info" name="send" />
            </a>
            <a
              v-if="canEdit"
              @click="Toggle_Webhook(props.row)"
              :title="props.row.is_active ? 'Disable' : 'Enable'"
              v-b-tooltip.hover
            >
              <lucide-icon
                name="power"
                :class="props.row.is_active ? 'text-warning' : 'text-success'"
              />
            </a>
            <a v-if="canEdit" @click="Edit_Webhook(props.row)" title="Edit" v-b-tooltip.hover>
              <lucide-icon class="text-25 text-success" name="pencil" />
            </a>
            <a
              v-if="canDelete"
              title="Delete"
              v-b-tooltip.hover
              @click="Remove_Webhook(props.row.id)"
            >
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </b-card>

    <validation-observer ref="ref_create_webhook">
      <b-modal hide-footer size="lg" id="Webhook_Modal" :title="editmode ? $t('Edit') : $t('Add')">
        <b-form @submit.prevent="Submit_webhook">
          <b-row>
            <b-col md="6">
              <validation-provider name="Name" :rules="{ required: true }" v-slot="v">
                <b-form-group :label="$t('Name') + ' *'">
                  <b-form-input
                    v-model="webhook.name"
                    :state="getValidationState(v)"
                  ></b-form-input>
                  <b-form-invalid-feedback>{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="6">
              <b-form-group :label="$t('Status')">
                <b-form-checkbox v-model="webhook.is_active" switch>
                  {{ webhook.is_active ? $t("Active") || "Active" : $t("Inactive") || "Inactive" }}
                </b-form-checkbox>
              </b-form-group>
            </b-col>

            <b-col md="12">
              <validation-provider name="URL" :rules="{ required: true, url: true }" v-slot="v">
                <b-form-group :label="$t('URL') + ' *'">
                  <b-form-input
                    v-model="webhook.url"
                    :state="getValidationState(v)"
                    placeholder="https://example.com/webhook"
                  ></b-form-input>
                  <b-form-invalid-feedback>{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Events') + ' *'">
                <div class="mb-2">
                  <b-form-checkbox v-model="subscribeAll">
                    {{ $t("Subscribe_to_all_events") || "Subscribe to all events (*)" }}
                  </b-form-checkbox>
                </div>
                <b-form-checkbox-group
                  v-if="!subscribeAll"
                  v-model="webhook.events"
                  :options="availableEvents"
                  stacked
                  class="webhook-events-grid"
                ></b-form-checkbox-group>
              </b-form-group>
            </b-col>

            <b-col md="6">
              <b-form-group :label="$t('Timeout_seconds') || 'Timeout (seconds)'">
                <b-form-input
                  type="number"
                  min="1"
                  max="60"
                  v-model.number="webhook.timeout_seconds"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <b-col md="6" v-if="editmode">
              <b-form-group :label="$t('Secret') || 'Secret'">
                <b-input-group>
                  <b-form-input readonly :value="webhook.secret"></b-form-input>
                  <b-input-group-append>
                    <b-button variant="warning" @click="Regenerate_Secret">
                      {{ $t("Regenerate") || "Regenerate" }}
                    </b-button>
                  </b-input-group-append>
                </b-input-group>
                <small class="text-muted">
                  {{ $t("Use_this_secret_to_verify_HMAC_SHA256_signatures_sent_in_X-Webhook-Signature") || "Use this secret to verify HMAC-SHA256 signatures (X-Webhook-Signature)." }}
                </small>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing">
                <lucide-icon class="me-2 font-weight-bold" name="check" /> {{ $t("submit") }}
              </b-button>
              <div v-once class="typo__p" v-if="SubmitProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: { title: "Webhooks" },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      serverParams: {
        columnFilters: {},
        sort: { field: "id", type: "desc" },
        page: 1,
        perPage: 10,
      },
      totalRows: 0,
      search: "",
      limit: "10",
      webhooks: [],
      availableEvents: [],
      editmode: false,
      webhook: this.emptyWebhook(),
      subscribeAll: false,
    };
  },
  watch: {
    subscribeAll(val) {
      if (val) {
        this.webhook.events = ["*"];
      } else if (this.webhook.events.length === 1 && this.webhook.events[0] === "*") {
        this.webhook.events = [];
      }
    },
  },
  computed: {
    ...mapGetters(["currentUserPermissions"]),
    canAdd() {
      return this.currentUserPermissions && this.currentUserPermissions.includes("webhooks_add");
    },
    canEdit() {
      return this.currentUserPermissions && this.currentUserPermissions.includes("webhooks_edit");
    },
    canDelete() {
      return this.currentUserPermissions && this.currentUserPermissions.includes("webhooks_delete");
    },
    columns() {
      return [
        { label: this.$t("Name"), field: "name", tdClass: "text-left", thClass: "text-left" },
        { label: "URL", field: "url", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Events") || "Events", field: "events", sortable: false, tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Status"), field: "is_active", tdClass: "text-center", thClass: "text-center" },
        { label: this.$t("Action"), field: "actions", sortable: false, tdClass: "text-left", thClass: "text-left" },
      ];
    },
  },
  methods: {
    emptyWebhook() {
      return {
        id: "",
        name: "",
        url: "",
        events: [],
        is_active: true,
        timeout_seconds: 15,
        secret: "",
      };
    },
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Webhooks(currentPage);
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Webhooks(1);
      }
    },
    onSortChange(params) {
      this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
      this.Get_Webhooks(this.serverParams.page);
    },
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Webhooks(this.serverParams.page);
    },
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    },
    New_Webhook() {
      this.webhook = this.emptyWebhook();
      this.subscribeAll = false;
      this.editmode = false;
      this.$bvModal.show("Webhook_Modal");
    },
    Edit_Webhook(row) {
      const events = Array.isArray(row.events) ? [...row.events] : [];
      this.webhook = Object.assign(this.emptyWebhook(), row, { events });
      this.subscribeAll = events.length === 1 && events[0] === "*";
      this.editmode = true;
      this.$bvModal.show("Webhook_Modal");
    },
    Submit_webhook() {
      this.$refs.ref_create_webhook.validate().then((success) => {
        if (!success) {
          this.makeToast("danger", this.$t("Please_fill_the_form_correctly"), this.$t("Failed"));
          return;
        }
        this.editmode ? this.Update_Webhook() : this.Store_Webhook();
      });
    },
    Store_Webhook() {
      this.SubmitProcessing = true;
      axios
        .post("webhooks", {
          name: this.webhook.name,
          url: this.webhook.url,
          events: this.webhook.events,
          is_active: this.webhook.is_active ? 1 : 0,
          timeout_seconds: this.webhook.timeout_seconds,
        })
        .then(() => {
          this.SubmitProcessing = false;
          this.$bvModal.hide("Webhook_Modal");
          this.makeToast("success", this.$t("Created_in_successfully") || "Created", this.$t("Success"));
          this.Get_Webhooks(this.serverParams.page);
        })
        .catch((err) => {
          this.SubmitProcessing = false;
          this.makeToast(
            "danger",
            (err.response && err.response.data && err.response.data.message) || "Error",
            this.$t("Failed")
          );
        });
    },
    Update_Webhook() {
      this.SubmitProcessing = true;
      axios
        .put("webhooks/" + this.webhook.id, {
          name: this.webhook.name,
          url: this.webhook.url,
          events: this.webhook.events,
          is_active: this.webhook.is_active ? 1 : 0,
          timeout_seconds: this.webhook.timeout_seconds,
        })
        .then(() => {
          this.SubmitProcessing = false;
          this.$bvModal.hide("Webhook_Modal");
          this.makeToast("success", this.$t("Updated_in_successfully") || "Updated", this.$t("Success"));
          this.Get_Webhooks(this.serverParams.page);
        })
        .catch((err) => {
          this.SubmitProcessing = false;
          this.makeToast(
            "danger",
            (err.response && err.response.data && err.response.data.message) || "Error",
            this.$t("Failed")
          );
        });
    },
    Remove_Webhook(id) {
      this.$swal({
        title: this.$t("Delete_Title"),
        text: this.$t("Delete_Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete_cancelButtonText"),
        confirmButtonText: this.$t("Delete_confirmButtonText"),
      }).then((result) => {
        if (result.value) {
          axios
            .delete("webhooks/" + id)
            .then(() => {
              this.makeToast("success", this.$t("Deleted_in_successfully"), this.$t("Delete_Deleted"));
              this.Get_Webhooks(this.serverParams.page);
            })
            .catch(() =>
              this.makeToast("warning", this.$t("Delete_Therewassomethingwronge"), this.$t("Delete_Failed"))
            );
        }
      });
    },
    Toggle_Webhook(row) {
      axios
        .post("webhooks/" + row.id + "/toggle")
        .then((res) => {
          row.is_active = res.data.is_active;
          this.makeToast("success", "Webhook " + (row.is_active ? "enabled" : "disabled"), this.$t("Success"));
        })
        .catch(() => this.makeToast("danger", "Toggle failed", this.$t("Failed")));
    },
    Test_Webhook(id) {
      axios
        .post("webhooks/" + id + "/test")
        .then(() =>
          this.makeToast("info", "Test webhook queued — check Delivery Logs.", this.$t("Success"))
        )
        .catch(() => this.makeToast("danger", "Test dispatch failed", this.$t("Failed")));
    },
    Regenerate_Secret() {
      axios
        .post("webhooks/" + this.webhook.id + "/regenerate-secret")
        .then((res) => {
          this.webhook.secret = res.data.secret;
          this.makeToast("success", "Secret regenerated", this.$t("Success"));
        })
        .catch(() => this.makeToast("danger", "Regenerate failed", this.$t("Failed")));
    },
    Get_Webhooks(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("webhooks", {
          params: {
            page,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.limit,
          },
        })
        .then((response) => {
          this.webhooks = response.data.webhooks;
          this.totalRows = response.data.totalRows;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => (this.isLoading = false), 500);
        });
    },
    Get_Available_Events() {
      axios
        .get("webhooks/available-events")
        .then((res) => {
          this.availableEvents = res.data.events || [];
        })
        .catch(() => {});
    },
  },
  created() {
    this.Get_Webhooks(1);
    this.Get_Available_Events();
  },
};
</script>

<style scoped>
.webhook-events-grid {
  max-height: 240px;
  overflow-y: auto;
  padding: 8px 12px;
  border: 1px solid #eee;
  border-radius: 4px;
  background: #fafbfc;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 16px;
}
</style>
