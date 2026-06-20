<template>
  <div class="main-content">
    <breadcumb :page="$t('Subscription_Product')" :folder="$t('Subscriptions')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <b-card class="wrapper" v-if="!isLoading">

       <b-alert show variant="info">
        ⏱️ <strong>Subscription Automation</strong><br><br>
        Once a subscription is created, the system uses scheduled commands (cron jobs) to handle billing automatically.<br><br>

        <strong>1️⃣ Generate Invoices Automatically</strong><br>
        <code>php artisan subscriptions:generate-invoices</code><br>
        This command will automatically create a new sale (invoice) for each active subscription based on its billing cycle (daily, weekly, monthly, yearly).<br><br>

        <strong>2️⃣ Send SMS Reminders</strong><br>
        <code>php artisan subscriptions:send-sms-reminders</code><br>
        This command sends reminder messages to clients for upcoming or due subscription payments via SMS.<br><br>

        📌 <strong>Make sure to schedule these commands in your Cron Job (e.g., cPanel) to run daily or as needed.</strong>
      </b-alert>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="subscriptions"
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
          <router-link
            class="btn-sm btn btn-primary ripple btn-icon m-1"
            to="/app/subscription_product/store"
          >
            <span class="ul-btn__icon">
              <lucide-icon name="plus" />
            </span>
            <span class="ul-btn__text ml-1">{{$t('Add')}}</span>
          </router-link>

        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
          
            <router-link
              title="Subscription details"
              v-b-tooltip.hover
              :to="'/app/subscription_product/detail/'+props.row.id"
            >
              <lucide-icon class="text-25 text-primary" name="eye" />
            </router-link>

            <a title="Delete" v-b-tooltip.hover @click="Remove_subscription(props.row.id)">
              <lucide-icon class="text-25 text-danger cursor-pointer" name="x" />
            </a>
          </span>

          <div v-else-if="props.column.field == 'status'">
            <label class="switch switch-primary mr-3">
              <input @change="isChecked(props.row)" type="checkbox" v-model="props.row.status">
              <span class="slider"></span>
            </label>
          </div>

        </template>
      </vue-good-table>
    </b-card>

  </div>
</template>


<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Subscriptions"
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
      subscriptions: [],
     
    };
  },
  computed: {
    columns() {
      return [
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
         label: this.$t("product_name"),
          field: "product_name",
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
          label: this.$t("Billing_Cycle"),
          field: "billing_cycle",
          tdClass: "text-left",
          thClass: "text-left"
        },

        {
          label: this.$t("total_cycles"),
          field: "total_cycles",
          tdClass: "text-left",
          thClass: "text-left"
        },

        {
          label: this.$t("remaining_cycles"),
          field: "remaining_cycles",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("next_billing_date"),
          field: "next_billing_date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Status"),
          field: "status",
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

    isChecked(subscription) {

      let newStatus = subscription.status ? 'active' : 'canceled'; // Adjust based on toggle logic

    axios
      .put(`/subscriptions/${subscription.id}/status`, {
        status: newStatus // Send the updated status
      })
      .then(response => {
        this.makeToast(
          "success",
          this.$t("Subscription_status_updated_successfully"),
          this.$t("Success")
        );
      })
      .catch(error => {
        this.makeToast(
          "warning",
          this.$t("Failed_to_update_subscription_status"),
          this.$t("Warning")
        );
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
        this.get_subscriptions(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.get_subscriptions(1);
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
      this.get_subscriptions(this.serverParams.page);
    },

    //---- Event on Search

    onSearch(value) {
      this.search = value.searchTerm;
      this.get_subscriptions(this.serverParams.page);
    },

    //---- Validation State Form

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },


    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

 

    //--------------------------Get ALL  asset ---------------------------\\

    get_subscriptions(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "subscriptions?page=" +
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
          this.subscriptions    = response.data.subscriptions;
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


    //--------------------------- Remove subscription----------------\\
    Remove_subscription(id) {
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
            .delete("subscriptions/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete_Deleted"),
                this.$t("Deleted_in_successfully"),
                "success"
              );

              Fire.$emit("Event_delete_subscription");
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
    this.get_subscriptions(1);


    Fire.$on("Event_delete_subscription", () => {
      setTimeout(() => {
        this.get_subscriptions(this.serverParams.page);
      }, 500);
    });
  }
};
</script>