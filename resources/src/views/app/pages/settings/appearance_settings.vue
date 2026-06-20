<template>
  <div class="main-content">
    <breadcumb :page="$t('Appearance_Settings')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <!-- System Settings -->
    <validation-observer ref="form_setting" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Setting">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card no-body :header="$t('Appearance_Settings')">
              <b-card-body>
                <b-row>
               

                <!-- App Name -->
                <b-col lg="4" md="4" sm="12">
                  <validation-provider
                    name="App Name"
                    :rules="{ required: true }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('app_name') + ' *'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="app-name-feedback"
                        v-model="setting.app_name"
                        class="form-control"
                      ></b-form-input>
                      <b-form-invalid-feedback id="app-name-feedback">
                        {{ validationContext.errors[0] }}
                      </b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

              <!-- Page Title Suffix -->
              <b-col lg="4" md="4" sm="12">
                <validation-provider
                  name="Page Title Suffix"
                  :rules="{ required: true }"
                  v-slot="validationContext"
                >
                  <b-form-group :label="$t('page_title_suffix') + ' *'">
                    <b-form-input
                      :state="getValidationState(validationContext)"
                      aria-describedby="page-title-feedback"
                      v-model="setting.page_title_suffix"
                      class="form-control"
                    ></b-form-input>
                    <b-form-invalid-feedback id="page-title-feedback">
                      {{ validationContext.errors[0] }}
                    </b-form-invalid-feedback>
                  </b-form-group>
                </validation-provider>
              </b-col>

               <!-- - Logo -->
                <b-col lg="4" md="4" sm="12">
                  <validation-provider name="Logo" ref="Logo" rules="mimes:image/*|size:200">
                    <b-form-group
                      slot-scope="{validate, valid, errors }"
                      :label="$t('ChangeLogo')"
                    >
                      <input
                        :state="errors[0] ? false : (valid ? true : null)"
                        :class="{'is-invalid': !!errors.length}"
                        @change="onFileSelected"
                        label="Choose Logo"
                        type="file"
                      >
                      <b-form-invalid-feedback id="Logo-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Favicon Upload -->
                <b-col lg="4" md="4" sm="12">
                  <validation-provider name="Favicon" ref="Favicon" rules="mimes:image/*|size:100">
                    <b-form-group
                      slot-scope="{ validate, valid, errors }"
                      :label="$t('ChangeFavicon')"
                    >
                      <input
                        :state="errors[0] ? false : (valid ? true : null)"
                        :class="{'is-invalid': !!errors.length}"
                        @change="onFaviconSelected"
                        type="file"
                      >
                      <b-form-invalid-feedback id="favicon-feedback">{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                  <!-- developed by -->
                  <b-col lg="4" md="4" sm="12">
                    <validation-provider
                      name="developed by"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('developed_by') + ' ' + '*'">
                         <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="developed_by-feedback"
                          v-model="setting.developed_by"
                          class="form-control"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="developed_by-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                   <!-- Footer -->
                  <b-col lg="4" md="4" sm="12">
                    <validation-provider
                      name="footer"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('footer') + ' ' + '*'">
                         <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="footer-feedback"
                          v-model="setting.footer"
                          class="form-control"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="footer-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- Customize Button Visibility -->
                  <b-col lg="12" md="12" sm="12" class="mb-3">
                    <div class="d-flex align-items-center justify-content-between customize-toggle-row">
                      <div>
                        <div class="customize-toggle-title">Show the floating Customize button</div>
                        <div class="customize-toggle-hint">
                          When enabled, a Customize button appears at the bottom-right of every page so users can quickly change theme, layout, primary color and language.
                        </div>
                      </div>
                      <label class="switch switch-primary ml-3 mb-0">
                        <input
                          type="checkbox"
                          v-model="setting.customize_button_visible"
                        />
                        <span class="slider"></span>
                      </label>
                    </div>
                  </b-col>

                  <!-- Hide Site Name in Sidebar -->
                  <b-col lg="12" md="12" sm="12" class="mb-3">
                    <div class="d-flex align-items-center justify-content-between customize-toggle-row">
                      <div>
                        <div class="customize-toggle-title">{{ $t('hide_site_name') }}</div>
                        <div class="customize-toggle-hint">
                          {{ $t('hide_site_name_hint') }}
                        </div>
                      </div>
                      <label class="switch switch-primary ml-3 mb-0">
                        <input
                          type="checkbox"
                          v-model="setting.hide_site_name"
                        />
                        <span class="slider"></span>
                      </label>
                    </div>
                  </b-col>



                  <b-col md="12">
                    <b-form-group>
                      <b-button variant="primary" type="submit"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card-body>
            </b-card>
          </b-col>

          <!-- Login Page Appearance -->
          <b-col lg="12" md="12" sm="12" class="mt-3">
            <b-card no-body>
              <b-card-header class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{$t('Appearance_Settings')}} - Login Page</h6>
              </b-card-header>
              <b-card-body>
                <b-row>
                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Login hero title">
                      <b-form-input
                        v-model="setting.login_hero_title"
                        class="form-control"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Login hero subtitle">
                      <b-form-input
                        v-model="setting.login_hero_subtitle"
                        class="form-control"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Login panel title">
                      <b-form-input
                        v-model="setting.login_panel_title"
                        class="form-control"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Login panel subtitle">
                      <b-form-input
                        v-model="setting.login_panel_subtitle"
                        class="form-control"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Hero badge text">
                      <b-form-input
                        v-model="setting.login_hero_badge"
                        class="form-control"
                        placeholder="Secure & Reliable"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Hero feature 1">
                      <b-form-input
                        v-model="setting.login_hero_feature_1"
                        class="form-control"
                        placeholder="Real-time inventory tracking"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Hero feature 2">
                      <b-form-input
                        v-model="setting.login_hero_feature_2"
                        class="form-control"
                        placeholder="Multi-location POS support"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Hero feature 3">
                      <b-form-input
                        v-model="setting.login_hero_feature_3"
                        class="form-control"
                        placeholder="Advanced reporting & analytics"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Sign in button text">
                      <b-form-input
                        v-model="setting.login_btn_text"
                        class="form-control"
                        placeholder="Sign in"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col lg="6" md="6" sm="12">
                    <b-form-group label="Login footer text">
                      <b-form-input
                        v-model="setting.login_footer_text"
                        class="form-control"
                        placeholder="Leave empty to use app name"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <b-col md="12">
                    <b-form-group>
                      <b-button variant="primary" type="submit">
                        <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                      </b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </b-card-body>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>

  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Appearance Settings"
  },
  data() {
    return {
      
      isLoading: true,
      data: new FormData(),
      setting: {
        logo: "",
        favicon:"",
        footer:"",
        app_name:"",
        page_title_suffix:"",
        developed_by:"",
        customize_button_visible: true,
        hide_site_name: false,
        // Login page appearance
        login_hero_title: "",
        login_hero_subtitle: "",
        login_panel_title: "",
        login_panel_subtitle: "",
        login_hero_badge: "",
        login_hero_feature_1: "",
        login_hero_feature_2: "",
        login_hero_feature_3: "",
        login_btn_text: "",
        login_footer_text: "",
      },

    };
  },

  methods: {
    ...mapActions(["refreshUserPermissions"]),

    //------------- Submit Validation Setting
    Submit_Setting() {
      this.$refs.form_setting.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Update_Settings();
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

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------------------------------ Event Upload Logo -------------------------------\\
    async onFileSelected(e) {
      const { valid } = await this.$refs.Logo.validate(e);

      if (valid) {
        this.setting.logo = e.target.files[0];
      } else {
        this.setting.logo = "";
      }
    },

     async onFaviconSelected(e) {
      const { valid } = await this.$refs.Favicon.validate(e);

      if (valid) {
        this.setting.favicon = e.target.files[0];
      } else {
        this.setting.favicon = "";
      }
    },

  
    //---------------------------------- Update Settings ----------------\\
    Update_Settings() {
      NProgress.start();
      NProgress.set(0.1);
      var self = this;
      self.data.append("favicon", self.setting.favicon);
      self.data.append("logo", self.setting.logo);
      self.data.append("app_name", self.setting.app_name);
      self.data.append("page_title_suffix", self.setting.page_title_suffix);
      self.data.append("developed_by", self.setting.developed_by);
      self.data.append("footer", self.setting.footer);
      self.data.append("customize_button_visible", self.setting.customize_button_visible ? "1" : "0");
      self.data.append("hide_site_name", self.setting.hide_site_name ? "1" : "0");
      // Login page appearance
      self.data.append("login_hero_title", self.setting.login_hero_title || "");
      self.data.append("login_hero_subtitle", self.setting.login_hero_subtitle || "");
      self.data.append("login_panel_title", self.setting.login_panel_title || "");
      self.data.append("login_panel_subtitle", self.setting.login_panel_subtitle || "");
      self.data.append("login_hero_badge", self.setting.login_hero_badge || "");
      self.data.append("login_hero_feature_1", self.setting.login_hero_feature_1 || "");
      self.data.append("login_hero_feature_2", self.setting.login_hero_feature_2 || "");
      self.data.append("login_hero_feature_3", self.setting.login_hero_feature_3 || "");
      self.data.append("login_btn_text", self.setting.login_btn_text || "");
      self.data.append("login_footer_text", self.setting.login_footer_text || "");
     
      self.data.append("_method", "put");

      axios
        .post("update_appearance_settings/" + self.setting.id, self.data)
        .then(response => {
          Fire.$emit("Event_Setting");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
          // 🔄 Reload the page
          setTimeout(() => {
            window.location.reload();
          }, 500); // optional short delay to show toast

        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          NProgress.done();
        });
    }, 


    //---------------------------------- Get SETTINGS ----------------\\
    Get_Settings() {
      axios
        .get("get_appearance_settings")
        .then(response => {
          this.setting         = response.data.settings;
          this.isLoading = false;
        })
        .catch(error => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

  }, //end Methods

  //----------------------------- Created function-------------------

  created: function() {
    this.Get_Settings();

    Fire.$on("Event_Setting", () => {
      this.Get_Settings();
    });

  }
};
</script>