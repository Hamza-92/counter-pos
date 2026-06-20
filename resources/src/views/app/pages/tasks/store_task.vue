<template>
  <div class="main-content">
    <breadcumb :page="$t('Create_Task')" :folder="$t('Tasks')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <validation-observer ref="ref_create_task" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Task">
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
                        :placeholder="$t('Enter_Task_Title')"
                        :state="getValidationState(validationContext)"
                        aria-describedby="title-feedback"
                        label="Task Title"
                        v-model="task.title"
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
                        v-model="task.start_date"
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
                        v-model="task.end_date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="end_date-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Project -->
                <b-col lg="4" md="6" sm="12">
                  <validation-provider name="Project">
                      <b-form-group slot-scope="{ valid, errors }"  :label="$t('Project') + ' ' + '*'">

                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="task.project_id"
                        @input="Selected_Project"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Project')"
                        :options="projects.map(projects => ({label: projects.title, value: projects.id}))"
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
                        v-model="task.company_id"
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
                        v-model="task.assigned_to"
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
                        v-model="task.status"
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
                        v-model="task.description"
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
    title: "Create Task"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing:false,
      projects: [],
      companies: [],
      employees: [],
      task: {
          title: "",
          description:"",
          project_id:"",
          company_id:"",
          assigned_to:[],
          start_date: new Date().toISOString().slice(0, 10),
          end_date:"",
          status:"",
      }, 
    };
  },

  methods: {
    //------------- Submit Validation Create Task
    Submit_Task() {
      this.$refs.ref_create_task.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Task();
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

    // ---------------------------Create Task
    Create_Task() {
      this.SubmitProcessing = true;
      var self = this;
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("tasks", {
          title: self.task.title,
          description: self.task.description,
          company_id: self.task.company_id,
          project_id: self.task.project_id,
          assigned_to: self.task.assigned_to,
          start_date: self.task.start_date,
          end_date: self.task.end_date,
          status: self.task.status,
        })
        .then(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          this.SubmitProcessing = false;
          this.$router.push({
            name: "index_task"
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

    Selected_Project(value) {
        if (value === null) {
            this.task.project_id = "";
        }
    },


    Selected_Team(value) {
        if (value === null) {
            this.task.assigned_to = [];
        }
    },

    Selected_Status(value) {
        if (value === null) {
            this.task.status = "";
        }
    },

    
    Selected_Company(value) {
          if (value === null) {
              this.task.company_id = "";
          }
          this.employees = [];
          this.task.assigned_to = [];
          this.Get_employees_by_company(value);
      },

      //---------------------- Get_employees_by_company ------------------------------\\
      
      Get_employees_by_company(value) {
          axios
          .get("/Get_employees_by_company?id=" + value)
          .then(({ data }) => (this.employees = data));
      },

    //---------------------------------------Get deposit Elements ------------------------------\\
    GetElements() {
      axios
        .get("tasks/create")
        .then(response => {
          this.projects = response.data.projects;
          this.companies = response.data.companies;
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
