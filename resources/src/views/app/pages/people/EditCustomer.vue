<template>
  <div class="main-content">
    <breadcumb :page="$t('Edit')" :folder="$t('Customers')"/>
    
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    
    <validation-observer ref="Create_Customer" v-if="!isLoading">
      <b-card>
        <b-form @submit.prevent="Submit_Customer">
          <b-row>
            <!-- First name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Firstname"
                :rules="{ required: false }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Firstname')">
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
                :rules="{ required: false }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('lastname')">
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
            <b-col md="12" sm="12" class="mt-4" v-if="client.id">
              <CustomFieldsForm
                entity-type="client"
                :entity-id="client.id"
                v-model="customFieldValues"
              />
            </b-col>

            <!-- Client Portal -->
            <b-col md="12" sm="12" class="mt-4" v-if="client.id">
              <b-card :title="$t('Client_Portal') || 'Client Portal'">
                <p class="text-muted small">{{ $t('Client_Portal_Description') || 'Let this customer access the portal to view invoices, payments, and statements.' }}</p>
                <div v-if="portalLoading" class="text-muted">Loading...</div>
                <div v-else>
                  <div class="mb-2" v-if="portalStatus.portal_email">
                    <small><strong>{{ $t('Portal_Email') || 'Portal email' }}:</strong> {{ portalStatus.portal_email }}</small>
                    <span v-if="portalStatus.portal_enabled" class="badge badge-success ml-2">{{ $t('Enabled') || 'Enabled' }}</span>
                    <span v-else class="badge badge-secondary ml-2">{{ $t('Disabled') || 'Disabled' }}</span>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <b-button size="sm" variant="primary" @click="openEnablePortalModal" v-if="!portalStatus.portal_enabled">
                      {{ $t('Portal_Enable') || 'Enable portal' }}
                    </b-button>
                    <b-button size="sm" variant="outline-primary" @click="openEnablePortalModal" v-else-if="!portalStatus.has_password">
                      {{ $t('Portal_Set_Password') || 'Set password' }}
                    </b-button>
                    <b-button size="sm" variant="outline-danger" @click="disablePortal" v-if="portalStatus.portal_enabled" :disabled="portalSending">
                      {{ $t('Portal_Disable') || 'Disable portal' }}
                    </b-button>
                  </div>
                </div>
              </b-card>
            </b-col>

            <!-- Enable Portal Modal -->
            <b-modal ref="enablePortalModal" v-model="showEnablePortalModal" :title="$t('Portal_Enable') || 'Enable Client Portal'" @ok="enablePortal" :ok-disabled="!portalEmail.trim()" ok-title="Save">
              <b-alert v-if="portalErrors.length" variant="danger" dismissible show class="small">{{ portalErrors.join(' ') }}</b-alert>
              <b-form-group :label="$t('Portal_Email') || 'Portal login email'">
                <b-form-input v-model="portalEmail" type="email" :placeholder="client.email"></b-form-input>
                <small class="text-muted">{{ $t('Portal_Email_Help') || 'Email the client will use to log in.' }}</small>
              </b-form-group>
              <b-form-group :label="$t('Portal_Password') || 'Portal password'">
                <b-form-input v-model="portalPassword" type="password" autocomplete="new-password" :placeholder="$t('Portal_Password_Optional') || 'Leave blank to keep current password'"></b-form-input>
                <small class="text-muted">{{ $t('Portal_Password_Help') || 'Set a new password or leave blank to keep the current one.' }}</small>
              </b-form-group>
            </b-modal>

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
import NProgress from "nprogress";
import CustomFieldsForm from "../../../../components/CustomFieldsForm.vue";

export default {
  components: {
    CustomFieldsForm
  },
  metaInfo: {
    title: "Edit Customer"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      customFieldValues: {},
      portalStatus: {},
      portalLoading: false,
      portalSending: false,
      showEnablePortalModal: false,
      portalEmail: "",
      portalPassword: "",
      portalErrors: [],
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
        credit_limit: 0,
      },
    };
  },

  methods: {
    //------------- Submit Validation Edit Customer
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
          this.Update_Client();
        }
      });
    },

    //----------------------------------- Update Client -------------------------------\\
    Update_Client() {
      this.SubmitProcessing = true;
      axios
        .put("clients/" + this.client.id, {
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
          credit_limit: parseFloat(this.client.credit_limit) || 0
        })
        .then(response => {
          // Save custom field values if any
          if (Object.keys(this.customFieldValues).length > 0) {
            return axios.post("custom-field-values", {
              entity_type: "App\\Models\\Client",
              entity_id: this.client.id,
              values: this.customFieldValues
            }).then(() => {
              this.makeToast(
                "success",
                this.$t("Successfully_Updated"),
                this.$t("Success")
              );
              this.SubmitProcessing = false;
              this.$router.push({ name: 'Customers' });
            });
          } else {
            this.makeToast(
              "success",
              this.$t("Successfully_Updated"),
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

    //----------------------------------- Get Customer Data -------------------------------\\
    Get_Customer() {
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .get("clients/" + id)
        .then(response => {
          // Merge to keep default keys even if API omits them
          this.client = { ...this.client, ...(response.data.client || {}) };
          // CustomFieldsForm component will handle loading values
          NProgress.done();
          this.isLoading = false;
          this.Get_Portal_Status();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("Failed_to_load_customer"), this.$t("Failed"));
          setTimeout(() => {
            this.isLoading = false;
            this.$router.push({ name: 'Customers' });
          }, 500);
        });
    },

    Get_Portal_Status() {
      if (!this.client.id) return;
      this.portalLoading = true;
      axios.get("clients/" + this.client.id + "/portal-status")
        .then(({ data }) => { this.portalStatus = data; })
        .catch(() => { this.portalStatus = {}; })
        .finally(() => { this.portalLoading = false; });
    },

    openEnablePortalModal() {
      this.portalEmail = this.client.email || "";
      this.portalPassword = "";
      this.portalErrors = [];
      this.showEnablePortalModal = true;
    },

    enablePortal(bvModalEvt) {
      bvModalEvt.preventDefault();
      this.portalErrors = [];
      this.portalSending = true;
      axios.post("clients/" + this.client.id + "/portal-enable", {
        email: this.portalEmail.trim(),
        password: this.portalPassword ? this.portalPassword : undefined
      }).then(({ data }) => {
        this.makeToast("success", data.message || "Portal updated", this.$t("Success"));
        this.showEnablePortalModal = false;
        this.Get_Portal_Status();
      }).catch((e) => {
        // Main.js axios interceptor rejects with response.data, so e may be { message, errors } directly
        const data = e?.response?.data || e;
        if (data?.errors) {
          const messages = [];
          Object.keys(data.errors).forEach(function(field) {
            (data.errors[field] || []).forEach(function(msg) { messages.push(msg); });
          });
          this.portalErrors = messages.length ? messages : [data.message || "Validation failed."];
          this.makeToast("danger", this.portalErrors.join(" "), this.$t("Validation_Error") || "Validation Error");
        } else {
          const msg = data?.message || "Failed";
          this.portalErrors = [msg];
          this.makeToast("danger", msg, this.$t("Failed"));
        }
      }).finally(() => { this.portalSending = false; });
    },

    disablePortal() {
      if (!confirm(this.$t("Portal_Disable_Confirm") || "Disable portal access for this customer?")) return;
      this.portalSending = true;
      axios.post("clients/" + this.client.id + "/portal-disable")
        .then(({ data }) => {
          this.makeToast("success", data.message || "Portal disabled", this.$t("Success"));
          this.Get_Portal_Status();
        }).catch(() => this.makeToast("danger", this.$t("Failed"), this.$t("Failed")))
        .finally(() => { this.portalSending = false; });
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
    this.Get_Customer();
  }
};
</script>
