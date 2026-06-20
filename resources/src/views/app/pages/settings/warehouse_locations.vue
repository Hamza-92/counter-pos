<template>
  <div class="main-content">
    <breadcumb :page="$t('Warehouse_Locations')" :folder="$t('Settings')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <b-row class="mb-3">
        <b-col md="6">
          <b-form-group :label="$t('Warehouses')">
            <b-form-select
              v-model="filters.warehouse_id"
              :options="warehouseOptions"
              @change="onWarehouseFilterChange"
            />
          </b-form-group>
        </b-col>
        <b-col md="6" class="d-flex align-items-end justify-content-end">
          <b-button
            @click="New_Location()"
            class="btn-rounded"
            variant="btn btn-primary btn-icon m-1"
          >
            <lucide-icon name="plus" />
            {{$t('Add')}}
          </b-button>
        </b-col>
      </b-row>

      <vue-good-table
        :key="tableKey"
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="locations"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev', perPage: serverParams.perPage, setCurrentPage: serverParams.page }"
        styleClass="table-hover tableOne vgt-table"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'is_active'">
            <span
              class="badge"
              :class="props.row.is_active ? 'badge-success' : 'badge-danger'"
            >
              {{ props.row.is_active ? $t('Active') : $t('Inactive') }}
            </span>
          </span>

          <span v-else-if="props.column.field === 'actions'">
            <a @click="Edit_Location(props.row)" title="Edit" v-b-tooltip.hover>
              <lucide-icon class="text-25 text-success" name="pencil" />
            </a>
            <a title="Delete" v-b-tooltip.hover @click="Remove_Location(props.row.id)">
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </b-card>

    <validation-observer ref="Create_Location">
      <b-modal hide-footer size="lg" id="New_Warehouse_Location" :title="editmode ? $t('Edit') : $t('Add')">
        <b-form @submit.prevent="Submit_Location">
          <b-row>
            <b-col md="6">
              <validation-provider name="Warehouse" :rules="{ required: true }" v-slot="validationContext">
                <b-form-group :label="$t('Warehouse') + ' ' + '*'">
                  <b-form-select
                    v-model="location.warehouse_id"
                    :options="warehouseOptionsNoAll"
                    :state="getValidationState(validationContext)"
                  />
                  <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="6">
              <validation-provider name="Code" :rules="{ required: true }" v-slot="validationContext">
                <b-form-group :label="$t('Rack_Location_Code') + ' ' + '*'">
                  <b-form-input
                    :placeholder="$t('Enter_Rack_Location_Code')"
                    v-model="location.code"
                    :state="getValidationState(validationContext)"
                  />
                  <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="6">
              <b-form-group :label="$t('Location_Name')">
                <b-form-input :placeholder="$t('Enter_Location_Name')" v-model="location.name" />
              </b-form-group>
            </b-col>

            <b-col md="6" class="d-flex align-items-center">
              <label class="switch switch-primary mr-3">
                <span>{{ $t('Active') }}</span>
              </label>
              <b-form-checkbox v-model="location.is_active" switch />
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing">
                <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
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
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Warehouse Locations"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      tableKey: 0,
      serverParams: {
        sort: { field: "id", type: "desc" },
        page: 1,
        perPage: 10
      },
      totalRows: 0,
      search: "",
      limit: "10",
      locations: [],
      warehouses: [],
      filters: {
        warehouse_id: ""
      },
      editmode: false,
      location: {
        id: "",
        warehouse_id: "",
        code: "",
        name: "",
        is_active: true
      }
    };
  },

  computed: {
    columns() {
      return [
        { label: this.$t("Warehouses"), field: "warehouse", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Rack_Location_Code"), field: "code", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Location_Name"), field: "name", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Status"), field: "is_active", tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Action"), field: "actions", html: true, sortable: false, tdClass: "text-left", thClass: "text-left" }
      ];
    },

    warehouseOptions() {
      const opts = [{ value: "", text: this.$t("All") }];
      return opts.concat(this.warehouses.map(w => ({ value: w.id, text: w.name })));
    },

    warehouseOptionsNoAll() {
      return this.warehouses.map(w => ({ value: w.id, text: w.name }));
    }
  },

  methods: {
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    },

    onWarehouseFilterChange() {
      this.serverParams.page = 1;
      this.getLocations();
    },

    // ------------------- VGT remote events -------------------\\
    onPageChange(params) {
      this.serverParams.page = params.currentPage;
      this.getLocations();
    },
    onPerPageChange(params) {
      this.serverParams.perPage = params.currentPerPage;
      this.getLocations();
    },
    onSortChange(params) {
      if (params && params.length) {
        this.serverParams.sort.field = params[0].field;
        this.serverParams.sort.type = params[0].type;
      }
      this.getLocations();
    },
    onSearch(params) {
      this.search = params.searchTerm;
      this.getLocations();
    },

    // ------------------- CRUD -------------------\\
    reset_Form() {
      this.location = {
        id: "",
        warehouse_id: this.filters.warehouse_id || (this.warehouses[0] ? this.warehouses[0].id : ""),
        code: "",
        name: "",
        is_active: true
      };
    },

    New_Location() {
      this.editmode = false;
      this.reset_Form();
      this.$bvModal.show("New_Warehouse_Location");
    },

    Edit_Location(row) {
      this.editmode = true;
      this.location = {
        id: row.id,
        warehouse_id: row.warehouse_id,
        code: row.code,
        name: row.name,
        is_active: !!row.is_active
      };
      this.$bvModal.show("New_Warehouse_Location");
    },

    Submit_Location() {
      this.$refs.Create_Location.validate().then(success => {
        if (!success) return;

        this.SubmitProcessing = true;
        NProgress.start();
        NProgress.set(0.1);

        const wasNew = !this.editmode;
        const newWarehouseId = this.location.warehouse_id;

        const payload = {
          warehouse_id: this.location.warehouse_id,
          code: this.location.code,
          name: this.location.name,
          is_active: this.location.is_active
        };

        const req = this.editmode
          ? axios.put("warehouse_locations/" + this.location.id, payload)
          : axios.post("warehouse_locations", payload);

        req
          .then(() => {
            NProgress.done();
            this.SubmitProcessing = false;
            this.$bvModal.hide("New_Warehouse_Location");
            this.makeToast(
              "success",
              wasNew ? this.$t("Successfully_Created") : this.$t("Successfully_Updated"),
              this.$t("Success")
            );
            if (wasNew) {
              // Jump to page 1 (new row sorts to top via id desc) and clear any
              // warehouse filter that would hide it, so the user sees their new
              // row immediately after saving.
              this.serverParams.page = 1;
              if (this.filters.warehouse_id && Number(this.filters.warehouse_id) !== Number(newWarehouseId)) {
                this.filters.warehouse_id = "";
              }
            }
            this.refreshTable();
          })
          .catch(() => {
            NProgress.done();
            this.SubmitProcessing = false;
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      });
    },

    Remove_Location(id) {
      this.$swal({
        title: this.$t("Are_you_sure"),
        text: this.$t("You_wont_be_able_to_revert_this"),
        type: "warning",
        showCancelButton: true,
        confirmButtonText: this.$t("Yes_delete_it"),
        cancelButtonText: this.$t("No_cancel"),
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33"
      }).then(result => {
        if (!result.value) return;

        NProgress.start();
        NProgress.set(0.1);
        axios
          .delete("warehouse_locations/" + id)
          .then(() => {
            NProgress.done();
            this.makeToast("success", this.$t("Deleted_in_successfully"), this.$t("Success"));
            this.refreshTable();
          })
          .catch(() => {
            NProgress.done();
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      });
    },

    // ------------------- Load list -------------------\\
    getLocations() {
      // Only show the full-page spinner on the very first load. Subsequent
      // refreshes keep the card mounted; we remount only the table itself
      // via tableKey so vue-good-table picks up the new rows reliably.
      const initial = this.isLoading;
      return axios
        .get("warehouse_locations", {
          params: {
            page: this.serverParams.page,
            limit: this.serverParams.perPage,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            warehouse_id: this.filters.warehouse_id || "",
            _t: Date.now()
          }
        })
        .then(response => {
          this.locations = response.data.locations || [];
          this.totalRows = response.data.totalRows || 0;
          this.warehouses = response.data.warehouses || [];
          if (initial) this.isLoading = false;
        })
        .catch(() => {
          if (initial) {
            setTimeout(() => { this.isLoading = false; }, 400);
          }
        });
    },

    /** Re-fetch and force vue-good-table to remount so new/changed rows appear. */
    refreshTable() {
      return this.getLocations().finally(() => {
        this.tableKey += 1;
      });
    }
  },

  created() {
    if (this.$route && this.$route.query && this.$route.query.warehouse_id) {
      this.filters.warehouse_id = this.$route.query.warehouse_id;
    }
    this.getLocations();
  }
};
</script>

