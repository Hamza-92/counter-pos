<template>
  <div class="main-content">
    <breadcumb :page="$t('Customers_with_Login')" :folder="$t('Customers')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else>
      <div class="d-flex justify-content-end mb-3">
        <b-button
          variant="outline-primary"
          class="mr-2"
          href="/online_store"
          target="_blank"
        >
          <lucide-icon class="mr-1" name="shopping-bag" />
          <span>Online Store</span>
        </b-button>
       
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="accounts"
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
        :styleClass="showDropdown ? 'tableOne table-hover vgt-table full-height' : 'tableOne table-hover vgt-table non-height'"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'actions'">
            <b-button size="sm" variant="outline-primary" class="mr-2" @click="editAccount(props.row)">
              <lucide-icon class="me-1" name="pencil" /> {{ $t('Edit') }}
            </b-button>
            <b-button size="sm" variant="outline-danger" @click="confirmDelete(props.row)">
              <lucide-icon class="me-1" name="x" /> {{ $t('Delete') }}
            </b-button>
          </span>
        </template>
      </vue-good-table>
    </div>

    <!-- Edit Ecommerce Account Modal -->
    <validation-observer ref="Edit_Account">
      <b-modal hide-footer size="md" id="Edit_Ecommerce_Account" :title="$t('Edit') + ' - ' + (form.client_name || '')">
        <b-form @submit.prevent="submitAccount">
          <b-row>
            <!-- Client (read-only) -->
            <b-col md="12" sm="12">
              <b-form-group :label="$t('CustomerName')">
                <b-form-input v-model="form.client_name" readonly />
              </b-form-group>
            </b-col>

            <!-- Email -->
            <b-col md="12" sm="12">
              <validation-provider
                name="Email"
                rules="required|email"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Email') + ' *'">
                  <b-form-input
                    v-model="form.email"
                    :state="getValidationState(validationContext)"
                    aria-describedby="email-feedback"
                    @input="email_exist = ''"
                  />
                  <b-form-invalid-feedback id="email-feedback">
                    {{ validationContext.errors[0] }}
                  </b-form-invalid-feedback>

                  <b-alert
                    v-if="email_exist"
                    show
                    variant="danger"
                    class="mt-1 py-1 px-2"
                  >
                    {{ email_exist }}
                  </b-alert>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Password -->
            <b-col md="12" sm="12">
              <validation-provider
                name="password"
                :rules="{ min: 6, max: 32 }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('password')">
                  <b-form-input
                    v-model="form.password"
                    :state="getValidationState(validationContext)"
                    :type="showPassword ? 'text' : 'password'"
                    :placeholder="$t('password')"
                  />
                  <b-form-invalid-feedback>
                    {{ validationContext.errors[0] }}
                  </b-form-invalid-feedback>

                  <b-button
                    variant="link"
                    class="position-absolute"
                    style="top: 56%; right: 0px; transform: translateY(-50%);"
                    @click="showPassword = !showPassword"
                  >
                    {{ showPassword ? 'Hide' : 'Show' }}
                  </b-button>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Status -->
            <b-col md="12" sm="12">
              <b-form-group :label="$t('Status')">
                <b-form-checkbox v-model="form.status" switch>
                  {{ form.status ? $t('Active') : $t('Inactive') }}
                </b-form-checkbox>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing">
                {{ $t('submit') }}
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
  metaInfo: {
    title: "Online Store Accounts",
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      email_exist: "",
      showPassword: false,

      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc",
        },
        page: 1,
        perPage: 10,
      },

      showDropdown: false,
      totalRows: "",
      search: "",
      limit: "10",

      accounts: [],
      form: {
        id: "",
        client_id: "",
        client_name: "",
        email: "",
        password: "",
        status: true,
      },
    };
  },

  mounted() {
    this.$root.$on("bv::dropdown::show", () => {
      this.showDropdown = true;
    });
    this.$root.$on("bv::dropdown::hide", () => {
      this.showDropdown = false;
    });
  },

  computed: {
    ...mapGetters(["currentUser"]),

    columns() {
      return [
        {
          label: this.$t("Code"),
          field: "client_code",
          tdClass: "text-left",
          thClass: "text-left",
        },
        {
          label: this.$t("Name"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
        },
        {
          label: this.$t("Phone"),
          field: "phone",
          tdClass: "text-left",
          thClass: "text-left",
        },
        {
          label: this.$t("Email"),
          field: "email",
          tdClass: "text-left",
          thClass: "text-left",
        },
        {
          label: this.$t("Status"),
          field: "status",
          tdClass: "text-left",
          thClass: "text-left",
          formatFn: (val) => (val ? this.$t("Active") : this.$t("Inactive")),
        },
        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
      ];
    },
  },

  methods: {
    // Validation state helper
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true,
      });
    },

    // Remote table handlers
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.getAccounts(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.getAccounts(1);
      }
    },

    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field,
        },
      });
      this.getAccounts(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.getAccounts(this.serverParams.page);
    },

    // Fetch accounts from API
    getAccounts(page) {
      NProgress.start();
      NProgress.set(0.1);

      axios
        .get(
          "ecommerce_clients?page=" +
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
        .then((response) => {
          this.accounts = response.data.accounts || [];
          this.totalRows = response.data.totalRows || 0;
          this.isLoading = false;
          NProgress.done();
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    // Edit account
    editAccount(row) {
      this.resetForm();
      this.form.id = row.id;
      this.form.client_id = row.client_id;
      this.form.client_name = row.client_name;
      this.form.email = row.email;
      this.form.status = !!row.status;
      this.$bvModal.show("Edit_Ecommerce_Account");
    },

    // Confirm delete
    confirmDelete(row) {
      this.$bvModal
        .msgBoxConfirm(this.$t("AreYouSure"), {
          title: this.$t("Confirm"),
          size: "sm",
          okVariant: "danger",
          okTitle: this.$t("Yes"),
          cancelTitle: this.$t("No"),
          footerClass: "p-2",
          centered: true,
        })
        .then((value) => {
          if (value) {
            this.deleteAccount(row);
          }
        });
    },

    deleteAccount(row) {
      this.SubmitProcessing = true;
      NProgress.start();
      NProgress.set(0.1);

      axios
        .delete("ecommerce_clients/" + row.id)
        .then(() => {
          this.makeToast("success", this.$t("Deleted_in_successfully"), this.$t("Success"));
          this.getAccounts(this.serverParams.page);
        })
        .catch(() => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        })
        .finally(() => {
          this.SubmitProcessing = false;
          NProgress.done();
        });
    },

    // Submit edited account
    submitAccount() {
      this.$refs.Edit_Account.validate().then((success) => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
          return;
        }

        this.SubmitProcessing = true;
        this.email_exist = "";
        NProgress.start();
        NProgress.set(0.1);

        axios
          .put("ecommerce_clients/" + this.form.id, {
            email: this.form.email,
            password: this.form.password || null,
            status: this.form.status ? 1 : 0,
          })
          .then(() => {
            this.makeToast(
              "success",
              this.$t("Updated_in_successfully"),
              this.$t("Success")
            );
            this.$bvModal.hide("Edit_Ecommerce_Account");
            this.getAccounts(this.serverParams.page);
          })
          .catch((error) => {
            // Backend validation errors (same style as Add_product.vue)
            if (error && error.errors) {
              if (error.errors.email && error.errors.email.length > 0) {
                this.email_exist = error.errors.email[0];
                this.makeToast("danger", this.email_exist, this.$t("Failed"));
              } else {
                const firstKey = Object.keys(error.errors)[0];
                const firstVal = error.errors[firstKey];
                const msg = Array.isArray(firstVal) ? firstVal[0] : firstVal;
                this.makeToast("danger", msg || this.$t("InvalidData"), this.$t("Failed"));
              }
            } else {
              this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            }
          })
          .finally(() => {
            this.SubmitProcessing = false;
            NProgress.done();
          });
      });
    },

    resetForm() {
      this.form = {
        id: "",
        client_id: "",
        client_name: "",
        email: "",
        password: "",
        status: true,
      };
      this.email_exist = "";
    },
  },

  created() {
    this.getAccounts(1);
  },
};
</script>


