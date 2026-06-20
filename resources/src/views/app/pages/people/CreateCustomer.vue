<template>
  <div class="main-content">
    <breadcumb :page="$t('Add')" :folder="$t('Customers')"/>
    
    <validation-observer ref="Create_Customer">
      <b-card>
        <b-form @submit.prevent="Submit_Customer">
          <b-row>
            <!-- First name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Firstname"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Firstname') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="firstname-feedback"
                    label="Firstname"
                    :placeholder="$t('Firstname')"
                    v-model="client.firstname"
                  ></b-form-input>
                  <b-form-invalid-feedback id="firstname-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Last name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="lastname"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('lastname') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="lastname-feedback"
                    label="lastname"
                    :placeholder="$t('lastname')"
                    v-model="client.lastname"
                  ></b-form-input>
                  <b-form-invalid-feedback id="lastname-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Username -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Username"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="'Username' + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="name-feedback"
                    label="name"
                    :placeholder="'Username'"
                    v-model="client.name"
                  ></b-form-input>
                  <b-form-invalid-feedback id="name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
            
             <!-- Customer Email -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Email"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Email') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="email-feedback"
                    label="email"
                    v-model="client.email"
                    :placeholder="$t('Email')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="email-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Customer Phone -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Phone')">
                  <b-form-input
                    label="Phone"
                    v-model="client.phone"
                    :placeholder="$t('Phone')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Customer Country -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Country')">
                  <b-form-input
                    label="Country"
                    v-model="client.country"
                    :placeholder="$t('Country')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Customer City -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('City')">
                  <b-form-input
                    label="City"
                    v-model="client.city"
                    :placeholder="$t('City')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Customer State -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('State')">
                  <b-form-input
                    label="State"
                    v-model="client.state"
                    :placeholder="$t('State')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Customer Zip -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Zip')">
                  <b-form-input
                    label="Zip"
                    v-model="client.zip"
                    :placeholder="$t('Zip')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

             <!-- Customer Tax Number -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Tax_Number')">
                  <b-form-input
                    label="Tax Number"
                    v-model="client.tax_number"
                    :placeholder="$t('Tax_Number')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <!-- Opening Balance -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Opening_Balance_Previous_Dues')">
                  <b-form-input
                    type="number"
                    step="0.01"
                    :label="$t('Opening_Balance')"
                    v-model="client.opening_balance"
                    placeholder="0.00"
                  ></b-form-input>
                  <small class="text-muted">Enter the customer's previous outstanding balance from before system start</small>
                </b-form-group>
            </b-col>

            <!-- Credit Limit -->
            <b-col md="6" sm="12">
                <b-form-group :label="$t('Credit_Limit')">
                  <b-form-input
                    type="number"
                    step="0.01"
                    :label="$t('Credit_Limit')"
                    v-model="client.credit_limit"
                    placeholder="0.00"
                  ></b-form-input>
                  <small class="text-muted">{{ $t('Maximum_credit_amount_allowed_for_this_customer_0_means_No_limit') }}</small>
                </b-form-group>
            </b-col>

            <!-- Customer Adress -->
            <b-col md="12" sm="12">
                <b-form-group :label="$t('Adress')">
                  <b-form-input
                    label="Adress"
                    v-model="client.adresse"
                    :placeholder="$t('Adress')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

             <b-col md="6" sm="12" class="mt-4 mb-4">
              <div class="psx-form-check">
                <input type="checkbox" v-model="client.is_royalty_eligible" class="psx-checkbox psx-form-check-input" id="is_royalty_eligible">
                <label class="psx-form-check-label" for="is_royalty_eligible">
                  <h5>{{ $t('Is_Royalty_Eligible') }}</h5>
                </label>
              </div>
            </b-col>

            <!-- Custom Fields -->
            <b-col md="12" sm="12" class="mt-4">
              <CustomFieldsForm
                entity-type="client"
                v-model="customFieldValues"
              />
            </b-col>

            <b-col md="12" class="mt-3">
                <b-button variant="primary" type="submit" :disabled="SubmitProcessing">{{$t('submit')}}</b-button>
                <b-button variant="secondary" class="ml-2" @click="$router.push({ name: 'Customers' })">{{$t('Cancel')}}</b-button>
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
import CustomFieldsForm from "../../../../components/CustomFieldsForm.vue";

export default {
  components: {
    CustomFieldsForm
  },
  metaInfo: {
    title: "Create Customer"
  },
  data() {
    return {
      SubmitProcessing: false,
      customFieldValues: {},
      client: {
        id: "",
        firstname: "",
        lastname: "",
        name: "",
        email: "",
        phone: "",
        country: "",
        tax_number: "",
        city: "",
        state: "",
        zip: "",
        adresse: "",
        is_royalty_eligible: "",
        opening_balance: 0,
        credit_limit: 0,
      },
    };
  },

  methods: {
    //------------- Submit Validation Create Customer
    Submit_Customer() {
      // Prefer using firstname/lastname to build name when empty
      const fullName = [this.client.firstname, this.client.lastname]
        .map(v => (v || "").trim())
        .filter(Boolean)
        .join(" ")
        .trim();
      if (!this.client.name && fullName) {
        this.client.name = fullName;
      }

      this.$refs.Create_Customer.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Client();
        }
      });
    },

    //---------------------------------------- Create new Client -------------------------------\\
    Create_Client() {
      this.SubmitProcessing = true;
      axios
        .post("clients", {
          firstname: this.client.firstname,
          lastname: this.client.lastname,
          name: this.client.name,
          email: this.client.email,
          phone: this.client.phone,
          tax_number: this.client.tax_number,
          country: this.client.country,
          city: this.client.city,
          state: this.client.state,
          zip: this.client.zip,
          adresse: this.client.adresse,
          is_royalty_eligible: this.client.is_royalty_eligible,
          opening_balance: parseFloat(this.client.opening_balance) || 0,
          credit_limit: parseFloat(this.client.credit_limit) || 0
        })
        .then(response => {
          const clientId = response.data.id || response.data.client?.id;
          
          // Save custom field values if any
          if (clientId && Object.keys(this.customFieldValues).length > 0) {
            return axios.post("custom-field-values", {
              entity_type: "App\\Models\\Client",
              entity_id: clientId,
              values: this.customFieldValues
            }).then(() => {
              this.makeToast(
                "success",
                this.$t("Successfully_Created"),
                this.$t("Success")
              );
              this.SubmitProcessing = false;
              this.$router.push({ name: 'Customers' });
            });
          } else {
            this.makeToast(
              "success",
              this.$t("Successfully_Created"),
              this.$t("Success")
            );
            this.SubmitProcessing = false;
            this.$router.push({ name: 'Customers' });
          }
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
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

  //----------------------------- Created function-------------------
  created: function() {
    // Reset form on component creation
    this.client = {
      id: "",
      firstname: "",
      lastname: "",
      name: "",
      email: "",
      phone: "",
      country: "",
      tax_number: "",
      city: "",
      state: "",
      zip: "",
      adresse: "",
      is_royalty_eligible: "",
      opening_balance: 0,
      credit_limit: 0,
    };
  }
};
</script>
