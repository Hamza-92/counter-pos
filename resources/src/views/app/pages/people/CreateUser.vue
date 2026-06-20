<template>
  <div class="main-content">
    <breadcumb :page="$t('Add')" :folder="$t('Users')"/>
    
    <validation-observer ref="Create_User">
      <b-card>
        <b-form @submit.prevent="Submit_User" enctype="multipart/form-data">
          <b-row>
            <!-- First name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Firstname"
                :rules="{ required: true , min:3 , max:30}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Firstname') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Firstname-feedback"
                    label="Firstname"
                    v-model="user.firstname"
                    :placeholder="$t('Firstname')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Firstname-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Last name -->
            <b-col md="6" sm="12">
              <validation-provider
                name="lastname"
                :rules="{ required: true , min:3 , max:30}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('lastname') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="lastname-feedback"
                    label="lastname"
                    v-model="user.lastname"
                    :placeholder="$t('lastname')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="lastname-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Username -->
            <b-col md="6" sm="12">
              <validation-provider
                name="username"
                :rules="{ required: true , min:3 , max:30}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('username') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="username-feedback"
                    label="username"
                    v-model="user.username"
                    :placeholder="$t('username')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="username-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Phone -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Phone"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Phone') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Phone-feedback"
                    label="Phone"
                    v-model="user.phone"
                    :placeholder="$t('Phone')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Phone-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Email -->
            <b-col md="6" sm="12">
              <validation-provider
                name="Email"
                :rules="{ required: true}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Email') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Email-feedback"
                    label="Email"
                    v-model="user.email"
                    :placeholder="$t('Email')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Email-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                  <b-alert
                    show
                    variant="danger"
                    class="error mt-1"
                    v-if="email_exist !=''"
                  >{{email_exist}}</b-alert>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- password -->
            <b-col md="6" sm="12">
              <validation-provider
                name="password"
                :rules="{ required: true , min:6}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('password') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="password-feedback"
                    label="password"
                    type="password"
                    v-model="user.password"
                    :placeholder="$t('password')"
                  ></b-form-input>
                  <b-form-invalid-feedback id="password-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- role -->
            <b-col md="6" sm="12" class="mb-3">
              <validation-provider name="role" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('RoleName') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="user.role_id"
                    :reduce="label => label.value"
                    :placeholder="$t('PleaseSelect')"
                    :options="roles.map(roles => ({label: roles.name, value: roles.id}))"
                  />
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Avatar -->
            <b-col md="6" sm="12" class="mb-3">
              <validation-provider name="Avatar" ref="Avatar" rules="mimes:image/*|size:200">
                <b-form-group slot-scope="{validate, valid, errors }" :label="$t('UserImage')">
                  <input
                    :state="errors[0] ? false : (valid ? true : null)"
                    :class="{'is-invalid': !!errors.length}"
                    @change="onFileSelected"
                    label="Choose Avatar"
                    type="file"
                  >
                  <b-form-invalid-feedback id="Avatar-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Record View -->
            <b-col md="6" sm="12" class="mb-3">
              <b-card class="h-100">
                <b-card-header class="pb-2">
                  <h6 class="mb-0">{{$t('ShowAll')}}</h6>
                </b-card-header>
                <b-card-body class="pt-3">
                  <div class="psx-form-check">
                    <input type="checkbox" v-model="user.record_view" class="psx-checkbox psx-form-check-input" id="record_view">
                    <label class="psx-form-check-label" for="record_view">
                      <span class="font-weight-normal">{{$t('ShowAll')}} <lucide-icon class="text-info font-weight-bold" name="help-circle" v-b-tooltip.hover.bottom title="Allow user to view all records, not just their own" /></span>
                    </label>
                  </div>
                  <small class="text-muted d-block mt-2">Allow user to view all records, not just their own</small>
                </b-card-body>
              </b-card>
            </b-col>

            <!-- Access Warehouses -->
            <b-col md="6" sm="12" class="mb-3">
              <b-card class="h-100">
                <b-card-header class="pb-2">
                  <h6 class="mb-0">{{$t('Assigned_warehouses')}}</h6>
                </b-card-header>
                <b-card-body class="pt-3">
                  <div class="psx-form-check mb-3">
                    <input type="checkbox" v-model="user.is_all_warehouses" class="psx-checkbox psx-form-check-input" id="is_all_warehouses">
                    <label class="psx-form-check-label" for="is_all_warehouses">
                      <span class="font-weight-normal">{{$t('All_Warehouses')}} <lucide-icon class="text-info font-weight-bold" name="help-circle" v-b-tooltip.hover.bottom title="If 'All Warehouses' Selected , User Can access all data for the selected Warehouses" /></span>
                    </label>
                  </div>
                  
                  <b-form-group :label="$t('Some_warehouses')" class="mb-0" v-if="!user.is_all_warehouses">
                    <v-select
                      multiple
                      v-model="assigned_warehouses"
                      @input="Selected_Warehouse"
                      :reduce="label => label.value"
                      :placeholder="$t('PleaseSelect')"
                      :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                    />
                  </b-form-group>
                </b-card-body>
              </b-card>
            </b-col>

            <b-col md="12" class="mt-3">
                <b-button variant="primary" type="submit" :disabled="SubmitProcessing"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                <b-button variant="secondary" class="ml-2" @click="$router.push({ name: 'Users' })">{{$t('Cancel')}}</b-button>
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

export default {
  metaInfo: {
    title: "Create User"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      email_exist: "",
      roles: [],
      warehouses: [],
      data: new FormData(),
      user: {
        firstname: "",
        lastname: "",
        username: "",
        password: "",
        email: "",
        phone: "",
        role_id: "",
        avatar: "",
        is_all_warehouses: 1,
      },
      assigned_warehouses: [],
    };
  },

  methods: {
    //------------- Submit Validation Create User
    Submit_User() {
      this.$refs.Create_User.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_User();
        }
      });
    },

    //------------------------ Create User ---------------------------\\
    Create_User() {
      var self = this;
      self.SubmitProcessing = true;
      self.data = new FormData();
      self.data.append("firstname", self.user.firstname);
      self.data.append("lastname", self.user.lastname);
      self.data.append("username", self.user.username);
      self.data.append("email", self.user.email);
      self.data.append("password", self.user.password);
      self.data.append("phone", self.user.phone);
      self.data.append("role", self.user.role_id);
      self.data.append("is_all_warehouses", self.user.is_all_warehouses);
      self.data.append("record_view", self.user.record_view ? 1 : 0);
      self.data.append("avatar", self.user.avatar);

      // append array assigned_warehouses
      if (self.assigned_warehouses.length) {
        for (var i = 0; i < self.assigned_warehouses.length; i++) {
          self.data.append("assigned_to[" + i + "]", self.assigned_warehouses[i]);
        }
      } else {
        self.data.append("assigned_to", []);
      }

      axios
        .post("users", self.data)
        .then(response => {
          self.SubmitProcessing = false;
          this.makeToast(
            "success",
            this.$t("Successfully_Created"),
            this.$t("Success")
          );
          // Redirect to users list after successful creation
          this.$router.push({ name: 'Users' });
        })
        .catch(error => {
          self.SubmitProcessing = false;
          if (error.response && error.response.data && error.response.data.errors && error.response.data.errors.email) {
            self.email_exist = error.response.data.errors.email[0];
          }
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    Selected_Warehouse(value) {
      if (!value.length) {
        this.assigned_warehouses = [];
      }
    },

    //------------------------------ Event Upload Avatar -------------------------------\\
    async onFileSelected(e) {
      const { valid } = await this.$refs.Avatar.validate(e);

      if (valid) {
        this.user.avatar = e.target.files[0];
      } else {
        this.user.avatar = "";
      }
    },

    //----------------------------------- Get Roles and Warehouses ---------------------------\\
    Get_Data() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        // Reuse the same query shape as the Users listing so the backend
        // receives valid SortField/SortType values and can return roles
        // metadata without throwing "Order direction must be \"asc\" or \"desc\"."
        .get(
          "users?page=1" +
          "&name=" +
          "" +
          "&statut=" +
          "" +
          "&phone=" +
          "" +
          "&email=" +
          "" +
          "&SortField=" +
          "id" +
          "&SortType=" +
          "desc" +
          "&search=" +
          "" +
          "&limit=" +
          1
        )
        .then(response => {
          this.roles = response.data.roles;
          this.warehouses = response.data.warehouses;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(error => {
          NProgress.done();
          this.isLoading = false;
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
    this.Get_Data();
  }
};
</script>
