<template>
  <div class="main-content">
    <breadcumb :page="$t('sms_settings')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

     <!-- default_form_sms -->
    <validation-observer ref="default_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Default_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body header="default sms gateway">
              <b-card-body>
                <b-row>
                    <!-- Default SMS Gateway -->
                  <b-col lg="4" md="4" sm="12">
                    <b-form-group :label="$t('Default_SMS_Gateway')">
                      <v-select
                        v-model="default_sms_gateway"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_SMS_Gateway')"
                        :options="sms_gateway.map(sms_gateway => ({label: sms_gateway.title, value: sms_gateway.id}))"
                      />
                    </b-form-group>
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
        </b-row>
      </b-form>
    </validation-observer>

        <!-- Termii SMS API -->
        <validation-observer ref="termi_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Termi_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body header="Termii">
              <b-card-body>
                <b-row>
                  
                   <!-- Termii_KEY  -->
                  <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="TERMI_KEY"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="Termii KEY *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="TERMI_KEY-feedback"
                          label="TERMI_KEY"
                          v-model="termi.TERMI_KEY"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="TERMI_KEY-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                    <!-- TERMI_SECRET  -->
                   <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="TERMI_SECRET"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="Termii SECRET *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="TERMI_SECRET-feedback"
                          label="TERMI_SECRET"
                          v-model="termi.TERMI_SECRET"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="TERMI_SECRET-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                    <!-- TERMI_SENDER  -->
                    <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="TERMI_SENDER"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="Termii Sender *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="TERMI_SENDER-feedback"
                          label="TERMI_SENDER"
                          v-model="termi.TERMI_SENDER"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="TERMI_SENDER-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
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
        </b-row>
      </b-form>
    </validation-observer>

    <!-- Twilio SMS API -->
    <validation-observer ref="twilio_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Twilio_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body header="TWILIO_SMS">
              <b-card-body>
                <b-row>
                  
                   <!-- TWILIO_SID  -->
                  <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="TWILIO_SID"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="TWILIO SID *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="TWILIO_SID-feedback"
                          label="TWILIO_SID"
                          v-model="twilio.TWILIO_SID"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="TWILIO_SID-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                   <!-- TWILIO_TOKEN  -->
                  <b-col lg="6" md="6" sm="12">
                      <b-form-group label="TWILIO TOKEN *">
                        <b-form-input
                          label="TWILIO_TOKEN"
                          v-model="twilio.TWILIO_TOKEN"
                          :placeholder="$t('LeaveBlank')"
                        ></b-form-input>
                      </b-form-group>
                  </b-col>

                    <!-- TWILIO_FROM  -->
                  <b-col lg="6" md="6" sm="12">
                    <validation-provider
                      name="TWILIO_FROM"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group label="TWILIO FROM *">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="TWILIO_FROM-feedback"
                          label="TWILIO_FROM"
                          v-model="twilio.TWILIO_FROM"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="TWILIO_FROM-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
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
        </b-row>
      </b-form>
    </validation-observer>

     <!-- Infobip SMS API -->
    <validation-observer ref="infobip_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_infobip_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body header="InfoBip">
              <b-card-body>
                <b-row>
                  
                   <!-- BASE_URL  -->
                  <b-col lg="6" md="6" sm="12">
                      <b-form-group label="BASE URL">
                        <b-form-input
                          label="BASE_URL"
                          v-model="infobip.base_url"
                        ></b-form-input>
                      </b-form-group>
                  </b-col>

                    <!-- API_KEY  -->
                  <b-col lg="6" md="6" sm="12">
                      <b-form-group  label="API KEY">
                        <b-form-input
                          label="API_KEY"
                          v-model="infobip.api_key"
                        ></b-form-input>
                      </b-form-group>
                  </b-col>

                    <!-- SMS Sender From  -->
                  <b-col lg="6" md="6" sm="12">
                      <b-form-group label="SMS sender number Or Name">
                        <b-form-input
                          label="SMS_From"
                          v-model="infobip.sender_from"
                        ></b-form-input>
                      </b-form-group>
                  </b-col>

                  <b-col md="12">
                    <b-form-group>
                      <b-button variant="primary" type="submit"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              
              <p class="mt-5">
                <strong>BASE_URL : </strong> The Infobip data center used for API traffic.<br>

                <strong>API_KEY :</strong> Authentication method. See API documentation <br>

                <strong>SMS sender number Or Name :</strong> displayed on recipient's device as message sender. <br>
                
                <strong>WhatsApp sender number :</strong> Registered WhatsApp sender number. Must be in international format.<br>

                <strong> ## Links</strong><br>

                <strong>[API Reference](https://www.infobip.com/docs/api)</strong><br>

                <strong>[PHP Client for Infobip API](https://github.com/infobip/infobip-api-php-client)</strong><br>
            </p>
            </b-card-body>
            </b-card>
          </b-col>
        </b-row>
      </b-form>


    </validation-observer>

    <!-- Custom SMS Gateway -->
    <validation-observer ref="custom_form_sms" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Custom_SMS">
        <b-row class="mt-5">
          <b-col lg="12" md="12" sm="12">
            <b-card no-body :header="$t('Custom_SMS_Gateway')">
              <b-card-body>
                <b-row>

                  <!-- API URL -->
                  <b-col lg="8" md="8" sm="12">
                    <validation-provider
                      name="api_url"
                      :rules="{ required: true, url: true }"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('Custom_SMS_Api_Url') + ' *'">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="custom_api_url-feedback"
                          v-model="custom.api_url"
                          placeholder="https://api.provider.com/sms/send"
                        ></b-form-input>
                        <b-form-invalid-feedback id="custom_api_url-feedback">
                          {{ validationContext.errors[0] }}
                        </b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>

                  <!-- HTTP Method -->
                  <b-col lg="4" md="4" sm="12">
                    <b-form-group :label="$t('Custom_SMS_Method')">
                      <v-select
                        v-model="custom.method"
                        :clearable="false"
                        :options="['POST','GET','PUT']"
                      />
                    </b-form-group>
                  </b-col>

                  <!-- Content Type -->
                  <b-col lg="4" md="4" sm="12">
                    <b-form-group :label="$t('Custom_SMS_Content_Type')">
                      <v-select
                        v-model="custom.content_type"
                        :clearable="false"
                        :options="['json','form']"
                      />
                    </b-form-group>
                  </b-col>

                  <!-- Sender -->
                  <b-col lg="4" md="4" sm="12">
                    <b-form-group :label="$t('Custom_SMS_Sender')">
                      <b-form-input
                        v-model="custom.sender"
                        placeholder="Sender ID or phone"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <!-- Success Keyword -->
                  <b-col lg="4" md="4" sm="12">
                    <b-form-group :label="$t('Custom_SMS_Success_Keyword')">
                      <b-form-input
                        v-model="custom.success_keyword"
                        placeholder="e.g. success"
                      ></b-form-input>
                    </b-form-group>
                  </b-col>

                  <!-- Headers (key/value rows) -->
                  <b-col md="12">
                    <label class="font-weight-bold mt-3">{{ $t('Custom_SMS_Headers') }}</label>
                    <b-row
                      v-for="(row, idx) in customHeaderRows"
                      :key="'h-'+idx"
                      class="align-items-center"
                    >
                      <b-col lg="5" md="5" sm="12">
                        <b-form-group>
                          <b-form-input
                            v-model="row.key"
                            placeholder="Header name (e.g. Authorization)"
                          ></b-form-input>
                        </b-form-group>
                      </b-col>
                      <b-col lg="6" md="6" sm="12">
                        <b-form-group>
                          <b-form-input
                            v-model="row.value"
                            placeholder="Header value (e.g. Bearer xxx)"
                          ></b-form-input>
                        </b-form-group>
                      </b-col>
                      <b-col lg="1" md="1" sm="12">
                        <b-button variant="outline-danger" size="sm" @click="removeHeaderRow(idx)">
                          <lucide-icon name="x" />
                        </b-button>
                      </b-col>
                    </b-row>
                    <b-button variant="outline-primary" size="sm" @click="addHeaderRow" class="mb-3">
                      <lucide-icon name="plus" /> {{ $t('Custom_SMS_Add_Header') }}
                    </b-button>
                  </b-col>

                  <!-- Payload (key/value rows) -->
                  <b-col md="12">
                    <label class="font-weight-bold mt-3">{{ $t('Custom_SMS_Payload') }}</label>
                    <p class="text-muted small">
                      {{ $t('Custom_SMS_Payload_Hint') }}
                    </p>
                    <b-row
                      v-for="(row, idx) in customPayloadRows"
                      :key="'p-'+idx"
                      class="align-items-center"
                    >
                      <b-col lg="5" md="5" sm="12">
                        <b-form-group>
                          <b-form-input
                            v-model="row.key"
                            placeholder="Field name (e.g. to)"
                          ></b-form-input>
                        </b-form-group>
                      </b-col>
                      <b-col lg="6" md="6" sm="12">
                        <b-form-group>
                          <b-form-input
                            v-model="row.value"
                            placeholder="Value (e.g. {phone})"
                          ></b-form-input>
                        </b-form-group>
                      </b-col>
                      <b-col lg="1" md="1" sm="12">
                        <b-button variant="outline-danger" size="sm" @click="removePayloadRow(idx)">
                          <lucide-icon name="x" />
                        </b-button>
                      </b-col>
                    </b-row>
                    <b-button variant="outline-primary" size="sm" @click="addPayloadRow" class="mb-3">
                      <lucide-icon name="plus" /> {{ $t('Custom_SMS_Add_Field') }}
                    </b-button>
                  </b-col>

                  <b-col md="12">
                    <b-form-group>
                      <b-button variant="primary" type="submit">
                        <lucide-icon class="me-2 font-weight-bold" name="check" /> {{ $t('submit') }}
                      </b-button>
                    </b-form-group>
                  </b-col>
                </b-row>

                <p class="mt-3 text-muted small">
                  <strong>{{ $t('Custom_SMS_Placeholders') }}:</strong>
                  <code>{phone}</code>, <code>{message}</code>, <code>{sender}</code>
                </p>
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
    title: "SMS Settings"
  },
  data() {
    return {
      
      isLoading: true,
      sms_gateway: [],
      default_sms_gateway:'',

      twilio:{
        TWILIO_SID:'',
        TWILIO_TOKEN:'',
        TWILIO_FROM:'',
      },

      termi:{
        TERMI_KEY:'',
        TERMI_SECRET:'',
        TERMI_SENDER:'',
      },

      infobip:{
        base_url:'',
        api_key:'',
        sender_from:'',
      },

      custom:{
        api_url:'',
        method:'POST',
        content_type:'json',
        sender:'',
        success_keyword:'',
        headers:{},
        payload:{},
      },
      customHeaderRows:[],
      customPayloadRows:[],

    };
  },

  methods: {
    ...mapActions(["refreshUserPermissions"]),

    
    //------------- Submit_Default_SMS
    Submit_Default_SMS() {
      this.$refs.default_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.update_Default_SMS();
        }
      });
    },


    //------------- Submit Validation SMS
    Submit_Twilio_SMS() {
      this.$refs.twilio_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.update_twilio_config();
        }
      });
    },

    //------------- Submit Validation SMS
    Submit_Termi_SMS() {
      this.$refs.termi_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.update_termi_config();
        }
      });
    },

    //------------- Submit Custom SMS
    Submit_Custom_SMS() {
      this.$refs.custom_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.update_custom_config();
        }
      });
    },

    addHeaderRow() {
      this.customHeaderRows.push({ key: '', value: '' });
    },
    removeHeaderRow(idx) {
      this.customHeaderRows.splice(idx, 1);
    },
    addPayloadRow() {
      this.customPayloadRows.push({ key: '', value: '' });
    },
    removePayloadRow(idx) {
      this.customPayloadRows.splice(idx, 1);
    },
    rowsToObject(rows) {
      const obj = {};
      rows.forEach(r => {
        const key = (r.key || '').trim();
        if (key !== '') {
          obj[key] = r.value || '';
        }
      });
      return obj;
    },
    objectToRows(obj) {
      if (!obj || typeof obj !== 'object') return [];
      return Object.keys(obj).map(k => ({ key: k, value: obj[k] }));
    },

     //------------- Submit Validation SMS
    Submit_infobip_SMS() {
      this.$refs.infobip_form_sms.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
            this.update_infobip_config();
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

      //---------------------------------- update_twilio_config ----------------\\
    update_Default_SMS() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .put("update_Default_SMS",{
          default_sms_gateway: this.default_sms_gateway,
        })
        .then(response => {
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },


  

     //---------------------------------- update_twilio_config ----------------\\
    update_twilio_config() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_twilio_config",{
          TWILIO_SID: this.twilio.TWILIO_SID,
          TWILIO_TOKEN: this.twilio.TWILIO_TOKEN,
          TWILIO_FROM: this.twilio.TWILIO_FROM,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

      //---------------------------------- update_termi_config ----------------\\
    update_termi_config() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_termi_config",{
          TERMI_KEY: this.termi.TERMI_KEY,
          TERMI_SECRET: this.termi.TERMI_SECRET,
          TERMI_SENDER: this.termi.TERMI_SENDER,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //---------------------------------- update_custom_config ----------------\\
    update_custom_config() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_custom_config",{
          api_url: this.custom.api_url,
          method: this.custom.method,
          content_type: this.custom.content_type,
          sender: this.custom.sender,
          success_keyword: this.custom.success_keyword,
          headers: this.rowsToObject(this.customHeaderRows),
          payload: this.rowsToObject(this.customPayloadRows),
        })
        .then(response => {
          Fire.$emit("Event_sms");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //---------------------------------- update_termi_config ----------------\\
    update_infobip_config() {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .post("update_infobip_config",{
          base_url: this.infobip.base_url,
          api_key: this.infobip.api_key,
          sender_from: this.infobip.sender_from,
        })
        .then(response => {
          Fire.$emit("Event_sms");
          this.makeToast(
            "success",
            this.$t("Successfully_Updated"),
            this.$t("Success")
          );
          NProgress.done();
        })
        .catch(error => {
          NProgress.done();
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },



     //---------------------------------- get_sms_config ----------------\\
    get_sms_config() {
      axios
        .get("get_sms_config")
        .then(response => {
          this.twilio = response.data.twilio;
          this.termi = response.data.termi;
          this.infobip = response.data.infobip;
          this.sms_gateway = response.data.sms_gateway;
          this.default_sms_gateway = response.data.default_sms_gateway;

          const c = response.data.custom || {};
          this.custom = {
            api_url: c.api_url || '',
            method: c.method || 'POST',
            content_type: c.content_type || 'json',
            sender: c.sender || '',
            success_keyword: c.success_keyword || '',
            headers: c.headers || {},
            payload: c.payload || {},
          };
          this.customHeaderRows = this.objectToRows(this.custom.headers);
          this.customPayloadRows = this.objectToRows(this.custom.payload);

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
    this.get_sms_config();


    Fire.$on("Event_sms", () => {
      this.get_sms_config();
    });
  }
};
</script>