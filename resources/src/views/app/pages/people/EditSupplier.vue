<template>
  <div class="main-content">
    <breadcumb :page="$t('Edit')" :folder="$t('Suppliers')"/>
    
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    
    <validation-observer ref="Create_Provider" v-if="!isLoading && provider">
      <b-card>
        <b-form @submit.prevent="Submit_Provider">
          <b-row>
            <!-- Provider Name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Name Provider"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('SupplierName') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="name-feedback"
                    label="name"
                    v-model="provider.name"
                    :placeholder="$t('SupplierName')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

             <!-- Provider Email -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Email')">
                  <b-form-input
                    label="email"
                    v-model="provider.email"
                    :placeholder="$t('Email')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Provider Phone -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Phone')">
                  <b-form-input
                    label="Phone"
                    v-model="provider.phone"
                    :placeholder="$t('Phone')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Provider Country -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Country')">
                  <b-form-input
                    label="Country"
                    v-model="provider.country"
                    :placeholder="$t('Country')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Provider City -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('City')">
                  <b-form-input
                    label="City"
                    v-model="provider.city"
                    :placeholder="$t('City')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Provider Tax_Number -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Tax_Number')">
                  <b-form-input
                    label="Tax_Number"
                    v-model="provider.tax_number"
                    :placeholder="$t('Tax_Number')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Credit Limit -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Credit_Limit')">
                  <b-form-input
                    type="number"
                    step="0.01"
                    :label="$t('Credit_Limit')"
                    v-model="provider.credit_limit"
                    placeholder="0.00"
                  ></b-form-input>
                  <small class="text-muted">{{$t('Maximum_credit_amount_allowed_for_this_supplier_0_means_No_limit')}}</small>
                </b-form-group>
            </b-col>

            <!-- Provider Adress -->
            <b-col md="12" sm="12">
                <b-form-group :label="$t('Adress')">
                  <textarea
                    label="Adress"
                    class="form-control"
                    rows="4"
                    v-model="provider.adresse"
                    :placeholder="$t('Adress')"
                 ></textarea>
                </b-form-group>
            </b-col>

            <!-- Custom Fields -->
            <b-col md="12" sm="12" class="mt-4">
              <CustomFieldsForm
                v-if="provider && provider.id"
                entity-type="provider"
                :entity-id="provider.id"
                v-model="customFieldValues"
              />
            </b-col>

            <b-col md="12" class="mt-3">
                <b-button variant="primary" type="submit" :disabled="SubmitProcessing"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                <b-button variant="secondary" class="ml-2" @click="$router.push({ name: 'Suppliers' })">{{$t('Cancel')}}</b-button>
                  <div v-once class="typo__p" v-if="SubmitProcessing">
                    <div class="spinner sm spinner-primary mt-3"></div>
                  </div>
            </b-col>

          </b-row>
        </b-form>
      </b-card>
    </validation-observer>
  </div>
</template>

<script>
import NProgress from "nprogress";
import CustomFieldsForm from "../../../../components/CustomFieldsForm.vue";

export default {
  components: {
    CustomFieldsForm
  },
  metaInfo: {
    title: "Edit Supplier"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      customFieldValues: {},
      provider: {
        id: "",
        name: "",
        phone: "",
        email: "",
        tax_number: "",
        country: "",
        city: "",
        adresse: "",
        credit_limit: 0
      },
    };
  },

  methods: {
    //------------- Submit Validation Edit Provider
    Submit_Provider() {
      this.$refs.Create_Provider.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Update_provider();
        }
      });
    },

    //--------------------------- Update Provider -----------------------\\
    Update_provider() {
      this.SubmitProcessing = true;
      axios
        .put("providers/" + this.provider.id, {
          name: this.provider.name,
          email: this.provider.email,
          tax_number: this.provider.tax_number,
          phone: this.provider.phone,
          country: this.provider.country,
          city: this.provider.city,
          adresse: this.provider.adresse,
          credit_limit: parseFloat(this.provider.credit_limit) || 0
        })
        .then(response => {
          // Save custom field values if any
          if (Object.keys(this.customFieldValues).length > 0) {
            return axios.post("custom-field-values", {
              entity_type: "App\\Models\\Provider",
              entity_id: this.provider.id,
              values: this.customFieldValues
            }).then(() => {
              this.makeToast(
                "success",
                this.$t("Successfully_Updated"),
                this.$t("Success")
              );
              this.SubmitProcessing = false;
              this.$router.push({ name: 'Suppliers' });
            });
          } else {
            this.makeToast(
              "success",
              this.$t("Successfully_Updated"),
              this.$t("Success")
            );
            this.SubmitProcessing = false;
            this.$router.push({ name: 'Suppliers' });
          }
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    //----------------------------------- Get Provider Data -------------------------------\\
    Get_Provider() {
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .get("providers/" + id)
        .then(response => {
          // Ensure all properties exist, even if API doesn't return them
          this.provider = {
            id: response.data.provider?.id || "",
            name: response.data.provider?.name || "",
            email: response.data.provider?.email || "",
            phone: response.data.provider?.phone || "",
            tax_number: response.data.provider?.tax_number || "",
            country: response.data.provider?.country || "",
            city: response.data.provider?.city || "",
            adresse: response.data.provider?.adresse || "",
            credit_limit: response.data.provider?.credit_limit || 0
          };
          // CustomFieldsForm component will handle loading values
          NProgress.done();
          this.isLoading = false;
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("Failed_to_load_supplier"), this.$t("Failed"));
          setTimeout(() => {
            this.isLoading = false;
            this.$router.push({ name: 'Suppliers' });
          }, 500);
        });
    },

    //------ Event Validation State
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
  },

  watch: {
    'provider.id': {
      handler(newId) {
        if (newId) {
          // Ensure custom fields component can load when provider.id is available
          this.$nextTick(() => {
            // Component will handle loading automatically via entityId prop
          });
        }
      },
      immediate: false
    }
  },

  //----------------------------- Created function-------------------
  created: function() {
    this.Get_Provider();
  }
};
</script>
