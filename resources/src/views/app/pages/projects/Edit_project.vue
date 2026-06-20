<template>
  <div class="main-content">
    <breadcumb :page="$t('Edit_Project')" :folder="$t('Projects')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="ref_edit_project" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Project">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>

                 <!-- Title -->
                 <b-col lg="4" md="6" sm="12">
                  <validation-provider
                    name="Title"
                    :rules="{ required: true}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('title') + ' ' + '*'">
                      <b-form-input
                        :placeholder="$t('Enter_Project_Title')"
                        :state="getValidationState(validationContext)"
                        aria-describedby="title-feedback"
                        label="Project Title"
                        v-model="project.title"
                      ></b-form-input>
                      <b-form-invalid-feedback id="title-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- start_date  -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider
                    name="start_date"
                    :rules="{ required: true}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('start_date') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="start_date-feedback"
                        type="date"
                        v-model="project.start_date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="start_date-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                 <!-- end_date  -->
                 <b-col lg="4" md="6" sm="12">
                  <validation-provider
                    name="end_date"
                    :rules="{ required: true}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Finish_Date') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="end_date-feedback"
                        type="date"
                        v-model="project.end_date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="end_date-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Client -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Client">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Customer') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="project.client_id"
                        @input="Selected_Client"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Customer')"
                        :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                 <!-- Company -->
                 <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Company">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Company') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="project.company_id"
                        @input="Selected_Company"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Company')"
                        :options="companies.map(companies => ({label: companies.name, value: companies.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Assigned_Employees -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Assigned_Employees">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Assigned_Employees')">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="assigned_employees"
                        @input="Selected_Team"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Team')"
                        :options="employees.map(employees => ({label: employees.username, value: employees.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                  <!-- Status  -->
                  <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Status" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Status') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="project.status"
                        @input="Selected_Status"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Status')"
                        :options="
                            [
                            {label: 'Not Started', value: 'not_started'},
                            {label: 'In Progress', value: 'progress'},
                            {label: 'Cancelled', value: 'cancelled'},
                            {label: 'On Hold', value: 'hold'},
                            {label: 'Completed', value: 'completed'},
                            ]"
                      ></v-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>


                <!-- Details -->
                <b-col lg="8" md="8" sm="12">
                  <validation-provider name="Description">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Details')">
                      <textarea
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="project.description"
                        rows="4"
                        class="form-control"
                        :placeholder="$t('Afewwords')"
                      ></textarea>
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
    title: "Edit Project"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing:false,
      clients: [],
      companies: [],
      employees: [],
      assigned_employees: [],
      project: {
          title: "",
          description:"",
          client_id:"",
          company_id:"",
          start_date: "",
          end_date:"",
          status:"",
      }, 
    };
  },

  methods: {
    //------------- Submit Validation Update Project
    Submit_Project() {
      this.$refs.ref_edit_project.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Update_Project();
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

    Selected_Client(value) {
            if (value === null) {
                this.project.client_id = "";
            }
        }, 

        Selected_Team(value) {
            if (value === null) {
                this.assigned_employees = [];
            }
        },

        Selected_Status(value) {
            if (value === null) {
                this.project.status = "";
            }
        },

        Selected_Company(value) {
            if (value === null) {
                this.project.company_id = "";
            }
            this.employees = [];
            this.assigned_employees = [];
            this.Get_employees_by_company(value);
        },

        //---------------------- Get_employees_by_company ------------------------------\\
        
        Get_employees_by_company(value) {
            axios
            .get("/Get_employees_by_company?id=" + value)
            .then(({ data }) => (this.employees = data));
        },

        

    //--------------------------------- Update_Project -------------------------\\
    Update_Project() {
      this.SubmitProcessing = true;
      var self = this;
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .put(`projects/${id}`, {
          title: self.project.title,
          description: self.project.description,
          client: self.project.client_id,
          company_id: self.project.company_id,
          assigned_to: self.assigned_employees,
          start_date: self.project.start_date,
          end_date: self.project.end_date,
          status: self.project.status,
        })
        .then(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          this.SubmitProcessing = false;
          this.$router.push({ name: "index_project" });
        })
        .catch(error => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    //---------------------------------------Get Expense Elements ------------------------------\\
    GetElements() {
      let id = this.$route.params.id;
      axios
        .get(`projects/${id}/edit`)
        .then(response => {
          this.project = response.data.project;
          this.clients = response.data.clients;
          this.companies = response.data.companies;
          this.employees = response.data.employees;
          this.assigned_employees = response.data.assigned_employees;

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