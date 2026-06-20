<template>
  <div class="main-content">
    <breadcumb :page="$t('Edit_Contract') || 'Edit Contract'" :folder="$t('Contracts') || 'Contracts'"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="ref_edit_contract" v-if="!isLoading && contract">
      <b-form @submit.prevent="Submit_Contract">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>
                <b-col lg="12" md="12" sm="12">
                  <b-form-group :label="($t('Party_Type') || 'Party Type') + ' *'">
                    <b-form-radio-group
                      v-model="contract.party_type"
                      :options="partyTypeOptions"
                      buttons
                      button-variant="outline-primary"
                    />
                  </b-form-group>
                </b-col>

                <b-col lg="4" md="6" sm="12" v-if="contract.party_type === 'customer'">
                  <validation-provider name="Customer" rules="required" v-slot="{ errors }">
                    <b-form-group :label="$t('Customer') + ' *'">
                      <v-select
                        v-model="contract.client_id"
                        :reduce="label => label.value"
                        :options="clients.map(c => ({ label: c.name, value: c.id }))"
                        :placeholder="$t('Choose_Customer')"
                        :class="{ 'is-invalid': errors[0] }"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col lg="4" md="6" sm="12" v-else>
                  <validation-provider name="Employee" rules="required" v-slot="{ errors }">
                    <b-form-group :label="($t('Employee') || 'Employee') + ' *'">
                      <v-select
                        v-model="contract.employee_id"
                        :reduce="label => label.value"
                        :options="employees.map(e => ({ label: ((e.firstname || '') + ' ' + (e.lastname || '')).trim(), value: e.id }))"
                        :placeholder="$t('Choose_Employee') || 'Choose Employee'"
                        :class="{ 'is-invalid': errors[0] }"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col lg="4" md="6" sm="12">
                  <b-form-group :label="$t('Project') || 'Project'">
                    <v-select
                      v-model="contract.project_id"
                      :reduce="label => label.value"
                      :options="projects.map(p => ({ label: p.title, value: p.id }))"
                      :placeholder="$t('Optional')"
                      clearable
                    />
                  </b-form-group>
                </b-col>
                <b-col lg="4" md="6" sm="12">
                  <b-form-group label="Contract Number">
                    <b-form-input v-model="contract.contract_number" disabled />
                  </b-form-group>
                </b-col>

                <b-col lg="6" md="6" sm="12">
                  <validation-provider name="Subject" rules="required" v-slot="{ errors }">
                    <b-form-group :label="$t('Subject') + ' *'">
                      <b-form-input v-model="contract.subject" :state="errors[0] ? false : null" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                <b-col lg="3" md="6" sm="12">
                  <validation-provider name="Value" rules="required" v-slot="{ errors }">
                    <b-form-group label="Value (USD) *">
                      <b-form-input v-model.number="contract.value" type="number" step="0.01" min="0" :state="errors[0] ? false : null" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                <b-col lg="3" md="6" sm="12">
                  <b-form-group :label="$t('Type') || 'Type'">
                    <v-select
                      v-model="contract.type"
                      :reduce="label => label.value"
                      :options="typeOptions"
                      clearable
                    />
                  </b-form-group>
                </b-col>

                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Start date" rules="required" v-slot="{ errors }">
                    <b-form-group :label="$t('start_date') + ' *'">
                      <b-form-input v-model="contract.start_date" type="date" :state="errors[0] ? false : null" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="End date" rules="required" v-slot="{ errors }">
                    <b-form-group :label="$t('Finish_Date') + ' *'">
                      <b-form-input v-model="contract.end_date" type="date" :state="errors[0] ? false : null" />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Status" rules="required" v-slot="{ errors }">
                    <b-form-group :label="$t('Status') + ' *'">
                      <v-select
                        v-model="contract.status"
                        :reduce="label => label.value"
                        :options="statusOptions"
                        :state="errors[0] ? false : null"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col lg="12">
                  <b-form-group :label="$t('Details') || 'Description'">
                    <RichTextEditor v-model="contract.description" editor-id="contract-edit-details-editor" />
                  </b-form-group>
                </b-col>
                <b-col lg="12" v-if="contract.party_type === 'customer'">
                  <b-form-checkbox v-model="contract.hide_from_customer">Hide from customer</b-form-checkbox>
                </b-col>
                <b-col md="12">
                  <b-button variant="primary" type="submit" :disabled="SubmitProcessing">
                    <lucide-icon class="me-2" name="check" /> {{ $t('submit') }}
                  </b-button>
                  <router-link :to="'/app/contracts/view/' + id" class="btn btn-secondary ml-2">View</router-link>
                  <div v-if="SubmitProcessing" class="spinner sm spinner-primary mt-3"></div>
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
import RichTextEditor from "@/components/RichTextEditor.vue";

export default {
  metaInfo: { title: "Edit Contract" },
  components: {
    RichTextEditor,
  },
  data() {
    return {
      id: this.$route.params.id,
      isLoading: true,
      SubmitProcessing: false,
      contract: null,
      clients: [],
      employees: [],
      projects: [],
      partyTypeOptions: [
        { text: "Customer", value: "customer" },
        { text: "Employee", value: "employee" },
      ],
      typeOptions: [
        { label: "Service", value: "service" },
        { label: "Lease", value: "lease" },
        { label: "Sales", value: "sales" },
        { label: "NDA", value: "nda" },
        { label: "Employment", value: "employment" },
        { label: "Other", value: "other" },
      ],
      statusOptions: [
        { label: "Draft", value: "draft" },
        { label: "Active", value: "active" },
        { label: "Expired", value: "expired" },
        { label: "Cancelled", value: "cancelled" },
      ],
    };
  },
  watch: {
    "contract.party_type"(val, old) {
      if (!val || !old || val === old) return;
      if (val === "customer") {
        this.contract.employee_id = null;
      } else {
        this.contract.client_id = null;
        this.contract.hide_from_customer = false;
      }
      this.$nextTick(() => {
        if (this.$refs.ref_edit_contract) this.$refs.ref_edit_contract.reset();
      });
    },
  },
  mounted() {
    axios.get("contracts/" + this.id + "/edit").then(r => {
      const c = r.data.contract || {};
      if (!c.party_type) c.party_type = c.employee_id ? "employee" : "customer";
      this.contract = c;
      this.clients = r.data.clients || [];
      this.employees = r.data.employees || [];
      this.projects = r.data.projects || [];
      this.isLoading = false;
    }).catch(() => { this.isLoading = false; });
  },
  methods: {
    makeToast(variant, msg, title) {
      this.$bvToast.toast(msg, { title: title || this.$t("Notice") || "Notice", variant: variant, solid: true });
    },
    Submit_Contract() {
      this.$refs.ref_edit_contract.validate().then(success => {
        if (!success) return;
        this.SubmitProcessing = true;
        axios.put("contracts/" + this.id, this.contract).then(() => {
          this.makeToast("success", this.$t("Updated_in_successfully") || "Updated successfully", this.$t("Success") || "Success");
          this.$router.push("/app/contracts/view/" + this.id);
        }).catch(error => {
          if (error.response && error.response.status === 422 && error.response.data && error.response.data.errors) {
            const errors = error.response.data.errors;
            Object.keys(errors).forEach(key => {
              (errors[key] || []).forEach(msg => this.makeToast("danger", msg, this.$t("Validation_Error") || "Validation error"));
            });
          } else if (error.response && error.response.data && error.response.data.message) {
            this.makeToast("danger", error.response.data.message, this.$t("Failed") || "Failed");
          } else {
            this.makeToast("danger", this.$t("Something_went_wrong") || "Something went wrong", this.$t("Failed") || "Failed");
          }
        }).finally(() => { this.SubmitProcessing = false; });
      });
    },
  },
};
</script>
