<template>
  <div class="main-content">
    <breadcumb :page="$t('Create_Susbscription')" :folder="$t('Subscriptions')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="ref_for_subscription" v-if="!isLoading">
      <b-form @submit.prevent="Submit_subscription">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>

                <!-- Date -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Date" :rules="{ required: true}" v-slot="{ errors }">
                    <b-form-group :label="$t('date') + ' ' + '*'">
                      <b-form-input v-model="form.date" type="date" :state="!errors.length" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                 <!-- client -->
                 <b-col lg="4" md="6" sm="12">
                  <validation-provider name="client" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Customer') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="form.client_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Customer')"
                        :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- warehouse -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('warehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="form.warehouse_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                 <!-- product -->
                 <b-col lg="4" md="6" sm="12">
                  <validation-provider name="product" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('ProductName') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="form.product_id"
                        :reduce="label => label.value"
                        :placeholder="$t('SelectProduct')"
                        :options="products.map(products => ({label: products.name, value: products.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

               <!-- Total Cycles Input -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="total_cycles" :rules="{ required: true, min: 1 }" v-slot="validationContext">
                    <b-form-group  :label="$t('total_cycles') + ' ' + '*'">
                      <template #label>
                        Total Cycles *  
                      <lucide-icon class="text-info font-weight-bold" name="help-circle" v-b-tooltip.hover.bottom title="How long the subscription lasts (e.g., 12 months, 52 weeks, 3 years)" />
                     </template>
                      <b-input-group>
                        <b-form-input
                          v-model="form.total_cycles"
                          type="text"
                          min="1"
                          :state="!validationContext.errors.length"
                          aria-describedby="totalCycles-feedback"
                        ></b-form-input>
                        <b-input-group-append>
                          <!-- Cycle Type Dropdown -->
                          <b-form-select v-model="form.cycle_type">
                            <option value="monthly">Months</option>
                            <option value="weekly">Weeks</option>
                            <option value="yearly">Years</option>
                          </b-form-select>
                        </b-input-group-append>
                      </b-input-group>
                      <b-form-invalid-feedback id="totalCycles-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Billing Cycle -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Billing Cycle" :rules="{ required: true}" v-slot="{ errors }">
                    <b-form-group  :label="$t('Billing_Cycle') + ' ' + '*'">
                      <template #label>
                        Billing Cycle *  
                      <lucide-icon class="text-info font-weight-bold" name="help-circle" v-b-tooltip.hover.bottom title="How often the user pays (e.g., monthly, weekly, yearly)" />
                     </template>
                      <b-form-select v-model="form.billing_cycle" :state="!errors.length">
                        <option value="monthly">Monthly</option>
                        <option value="weekly">Weekly</option>
                        <option value="yearly">Yearly</option>
                      </b-form-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Price Per Cycle -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Price per Cycle" :rules="{ required: true}" v-slot="{ errors }">
                    <b-form-group :label="$t('Price_Per_Cycle') + ' ' + '*'">
                      <b-form-input v-model="form.price_per_cycle" type="text" step="0.01" :state="!errors.length" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Quantity -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Quantity" :rules="{ required: true}" v-slot="{ errors }">
                    <b-form-group :label="$t('Quantity') + ' ' + '*'">
                      <b-form-input v-model="form.quantity" type="text" :state="!errors.length" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Price Per Unit -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Price per Unit" :rules="{ required: true}" v-slot="{ errors }">
                    <b-form-group  :label="$t('Price_Per_Unit') + ' ' + '*'">
                      <b-form-input v-model="form.price_per_unit" type="text" step="0.01" :state="!errors.length" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Next Billing Date -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Next Billing Date" :rules="{ required: true}" v-slot="{ errors }">
                    <b-form-group :label="$t('next_billing_date') + ' ' + '*'">
                      <b-form-input v-model="form.next_billing_date" type="date" :state="!errors.length" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Status -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Status" :rules="{ required: true}" v-slot="{ errors }">
                    <b-form-group  :label="$t('Status') + ' ' + '*'">
                      <b-form-select v-model="form.status" :state="!errors.length">
                        <option value="active">{{ $t('Active') }}</option>
                        <option value="canceled">{{ $t('Canceled') }}</option>
                        <option value="completed">{{ $t('Completed') }}</option>
                      </b-form-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
      
                <b-col md="12">
                  <b-form-group>
                    <b-button variant="primary" type="submit"  :disabled="SubmitProcessing"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                      <div v-once class="typo__p" v-if="SubmitProcessing">
                        <div class="spinner sm spinner-primary mt-3"></div>
                      </div>
                  </b-form-group>
                </b-col>

              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>
  </div>
</template>

<script>
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Create Subscription"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing:false,
      accounts: [],
      deposit_category: [],
      form: {
        date: new Date().toISOString().slice(0, 10), // Default to today's date
        user_id: null,
        client_id: null,
        product_id: null,
        warehouse_id: null,
        total_cycles: 12, // Default value
        cycle_type: "monthly", // Default selection
        billing_cycle: "monthly", // Default selection
        remaining_cycles: 12, // Matches total_cycles initially
        price_per_cycle: 0,
        price_per_unit: 0,
        quantity: 1,
        next_billing_date: new Date().toISOString().slice(0, 10), // Default to today
        status: "active", // Default status
      },
      clients: [], // Clients list (loaded from API)
      products: [], // Products list (loaded from API)
      warehouses: [], // Warehouses list (loaded from API)
    }
  },

  methods: {
    //------------- Submit Validation
    Submit_subscription() {
      this.$refs.ref_for_subscription.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Store_subscription();
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

    //------ Validation State
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    // ---------------------------Create deposit
    Store_subscription() {
      this.SubmitProcessing = true;
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("subscriptions", {
          subscription: this.form
        })
        .then(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.SubmitProcessing = false;
          this.$router.push({
            name: "subscription_product"
          });
          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    //---------------------------------------Get deposit Elements ------------------------------\\
    GetElements() {
      axios
        .get("subscriptions/create")
        .then(response => {
          this.clients = response.data.clients;
          this.products = response.data.products;
          this.warehouses = response.data.warehouses;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  //----------------------------- Created function-------------------
  created() {
    this.GetElements();
  }
};
</script>
