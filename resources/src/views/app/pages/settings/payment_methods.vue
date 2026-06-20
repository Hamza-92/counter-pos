<template>
  <div class="main-content">
    <breadcumb :page="$t('Payment_Methods')" :folder="$t('Settings')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <b-card class="wrapper" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="methods"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          enabled: true,
          placeholder: $t('Search_this_table'),  
        }"
        @on-selected-rows-change="selectionChanged"
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
            @click="New_Method()"
            class="btn-rounded"
            variant="btn btn-primary btn-icon m-1"
          >
            <lucide-icon name="plus" />
            {{$t('Add')}}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
        <span v-if="props.column.field == 'actions'">
          <template v-if="[1, 2, 3].includes(props.row.id)">
              <span class="text-warning">{{$t('You_cant_edit_or_remove_default_payment_choices')}}</span>
          </template>
          <template v-else>
              <a @click="Edit_Method(props.row)" title="Edit" v-b-tooltip.hover>
                  <lucide-icon class="text-25 text-success" name="pencil" />
              </a>
              <a title="Delete" v-b-tooltip.hover @click="Remove_Method(props.row.id)">
                  <lucide-icon class="text-25 text-danger" name="x" />
              </a>
          </template>
      </span>
    </template>
      </vue-good-table>
    </b-card>

    <validation-observer ref="ref_create_method">
      <b-modal hide-footer size="md" id="New_Method" :title="editmode?$t('Edit'):$t('Add')">
        <b-form @submit.prevent="Submit_method">
          <b-row>
         
            <!-- Name -->
            <b-col md="12">
              <validation-provider
                name="Name"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Name') + ' ' + '*'">
                  <b-form-input
                    :placeholder="$t('Enter_Payment_Method')"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Name-feedback"
                    label="Name"
                    v-model="method.name"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

             <b-col md="12" class="mt-3">
                <b-button variant="primary" type="submit"  :disabled="SubmitProcessing"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
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
    title: "Payment Methods"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing:false,
      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      totalRows: "",
      search: "",
      limit: "10",
      methods: [],
      editmode: false,

      method: {
        id: "",
        name: "",
      }
    };
  },
  computed: {
    columns() {
      return [
       
        {
          label: "Payment Method",
          field: "name",
          tdClass: "text-left",
          thClass: "text-left"
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
    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.get_methods(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.get_methods(1);
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
      this.get_methods(this.serverParams.page);
    },

    //---- Event on Search

    onSearch(value) {
      this.search = value.searchTerm;
      this.get_methods(this.serverParams.page);
    },

    //---- Validation State Form

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------------- Submit Validation Create & Edit Category
    Submit_method() {
      this.$refs.ref_create_method.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          if (!this.editmode) {
            this.Store_method();
          } else {
            this.Update_method();
          }
        }
      });
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //------------------------------ Modal  (create method) -------------------------------\\
    New_Method() {
      this.reset_Form();
      this.editmode = false;
      this.$bvModal.show("New_Method");
    },

    //------------------------------ Modal (Update method) -------------------------------\\
    Edit_Method(method) {
      this.get_methods(this.serverParams.page);
      this.reset_Form();
      this.method = method;
      this.editmode = true;
      this.$bvModal.show("New_Method");
    },

    //--------------------------Get ALL methods ---------------------------\\

    get_methods(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "payment_methods?page=" +
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
          this.methods = response.data.methods;
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

    //----------------------------------Create new methods ----------------\\
    Store_method() {
      this.SubmitProcessing = true;
      axios
        .post("payment_methods", {
          name: this.method.name,
        })
        .then(response => {
          this.SubmitProcessing = false;
          Fire.$emit("event_method");
          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
        })
        .catch(error => {
          this.SubmitProcessing = false;
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //---------------------------------- Update method ----------------\\
    Update_method() {
      this.SubmitProcessing = true;
      axios
        .put("payment_methods/" + this.method.id, {
          name: this.method.name,
        })
        .then(response => {
          this.SubmitProcessing = false;
          Fire.$emit("event_method");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
        })
        .catch(error => {
          this.SubmitProcessing = false;
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //--------------------------- reset Form ----------------\\

    reset_Form() {
      this.method = {
        id: "",
        name: "",
      };
    },

    //--------------------------- Remove method----------------\\
    Remove_Method(id) {
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
          axios
            .delete("payment_methods/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("event_delete_method");
            })
            .catch(() => {
              this.$swal(
                this.$t("Delete_Failed"),
                this.$t("Delete_Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    },

  }, //end Methods

  //----------------------------- Created function-------------------

  created: function() {
    this.get_methods(1);

    Fire.$on("event_method", () => {
      setTimeout(() => {
        this.get_methods(this.serverParams.page);
        this.$bvModal.hide("New_Method");
      }, 500);
    });

    Fire.$on("event_delete_method", () => {
      setTimeout(() => {
        this.get_methods(this.serverParams.page);
      }, 500);
    });
  }
};
</script>