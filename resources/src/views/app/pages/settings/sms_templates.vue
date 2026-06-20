<template>
  <div class="main-content">
    <breadcumb :page="$t('sms_templates')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div id="section_notifications_template" v-else>

   <!-- Language selector for templates -->
   <div class="row mt-3">
     <div class="col-md-12">
       <div class="form-group">
         <label class="font-weight-bold">{{ $t('Template_Language') || 'Template language' }}</label>
         <b-form-select
           v-model="selectedLocale"
           :options="languageOptions"
           value-field="locale"
           text-field="name"
           class="form-control w-auto d-inline-block"
           @change="onLocaleChange"
         />
         <p class="text-muted small mt-1 mb-0">{{ $t('Edit_templates_per_language') || 'Templates are saved per language. When sending SMS, the system default language is used.' }}</p>
       </div>
     </div>
   </div>

   <!-- Notification Client -->
  <div class="row mt-5">
    <div class="col-md-12">

      <div class="card">
        <div class="card-header">
          <h4>{{$t('Notification_Client')}}</h4>
        </div>
        <!--begin::form-->
        <div class="card-body">

          <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">

            <!-- Sell -->
            <b-tab :title="$t('Sale')">

              <form @submit.prevent="update_sms_body('sale')">
                  <div class="row">
                    <div class=" col-md-12">
                      <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                      <p>
                        {contact_name},{business_name},{invoice_number},{invoice_url},{total_amount},{paid_amount},{due_amount}
                      </p>
                    </div>
                    <hr>
                    <div class="form-group col-md-12">
                      <label for="sms_body_sale">{{$t('sms_body')}} </label>
                      <textarea type="text" v-model="sms_body_sale" class="form-control" style=" height: 200px!important;"
                        name="sms_body_sale" id="sms_body_sale" :placeholder="$t('sms_body')"></textarea>
                    </div>

                  </div>

                  <div class="row mt-3">
                    <div class="col-md-6">
                      <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                        <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                          aria-hidden="true"></span> <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                      </button>
                    </div>
                  </div>
              </form>

            </b-tab>

            <!-- Quotation -->
            <b-tab :title="$t('Quote')">

              <form @submit.prevent="update_sms_body('quotation')">
                  <div class="row">
                    <div class=" col-md-12">
                      <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                      <p>
                        {contact_name},{business_name},{quotation_number},{quotation_url},{total_amount}
                      </p>
                    </div>
                    <hr>
                    <div class="form-group col-md-12">
                      <label for="sms_body_quotation">{{$t('sms_body')}} </label>
                      <textarea type="text" v-model="sms_body_quotation" class="form-control"
                        style=" height: 200px!important;" name="sms_body_quotation" id="sms_body_quotation"
                        :placeholder="$t('sms_body')"></textarea>
                    </div>

                  </div>

                  <div class="row mt-3">
                    <div class="col-md-6">
                      <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                        <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                          aria-hidden="true"></span> <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                      </button>
                    </div>
                  </div>
              </form>

            </b-tab>

            <!-- Payment Received -->
            <b-tab :title="$t('PaiementsReceived')">

              <form @submit.prevent="update_sms_body('payment_received')">
                  <div class="row">
                    <div class=" col-md-12">
                      <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                      <p>
                        {contact_name},{business_name},{payment_number},{paid_amount}
                      </p>
                    </div>
                    <hr>
                    <div class="form-group col-md-12">
                      <label for="sms_body_payment_received">{{$t('sms_body')}} </label>
                      <textarea type="text" v-model="sms_body_payment_received" class="form-control"
                        style=" height: 200px!important;" name="sms_body_payment_received" id="sms_body_payment_received"
                        :placeholder="$t('sms_body')"></textarea>
                    </div>

                  </div>

                  <div class="row mt-3">
                    <div class="col-md-6">
                      <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                        <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                          aria-hidden="true"></span> <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                      </button>
                    </div>
                  </div>
              </form>

            </b-tab>

            <!-- Payment Received -->
            <b-tab title="Subscription Reminder">
              <form @submit.prevent="update_sms_body('subscription_reminder')">
                <div class="row">
                  <div class="col-md-12">
                    <span><strong>{{$t('Available_Tags')}}: </strong></span>
                    <p>
                      {client_name}, {business_name}, {next_billing_date}
                    </p>
                  </div>
                  <hr>
                  <div class="form-group col-md-12">
                    <label for="sms_body_subscription_reminder">{{$t('sms_body')}}</label>
                    <textarea type="text" v-model="sms_body_subscription_reminder" class="form-control"
                      style="height: 200px!important;" name="sms_body_subscription_reminder" id="sms_body_subscription_reminder"
                      :placeholder="$t('sms_body')"></textarea>
                  </div>
                </div>
                <div class="row mt-3">
                  <div class="col-md-6">
                    <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                      <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                        aria-hidden="true"></span>
                      <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                    </button>
                  </div>
                </div>
              </form>
            </b-tab>

            <!-- Asset validation due -->
            <b-tab :title="$t('Asset_Validation_Due') || 'Asset validation due'">
              <form @submit.prevent="update_sms_body('asset_validation_due')">
                <div class="row">
                  <div class="col-md-12">
                    <span><strong>{{$t('Available_Tags')}}: </strong></span>
                    <p>
                      {asset_name},{asset_tag},{next_validation},{business_name}
                    </p>
                  </div>
                  <hr>
                  <div class="form-group col-md-12">
                    <label for="sms_body_asset_validation_due">{{$t('sms_body')}}</label>
                    <textarea type="text" v-model="sms_body_asset_validation_due" class="form-control"
                      style="height: 200px!important;" name="sms_body_asset_validation_due" id="sms_body_asset_validation_due"
                      :placeholder="$t('sms_body')"></textarea>
                  </div>
                </div>
                <div class="row mt-3">
                  <div class="col-md-6">
                    <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                      <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                        aria-hidden="true"></span>
                      <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                    </button>
                  </div>
                </div>
              </form>
            </b-tab>


          </b-tabs>


        </div>
      </div>
    </div>
  </div>


  <!-- {{-- Notification Supplier --}} -->
  <div class="row mt-5">
    <div class="col-md-12">

      <div class="card">
        <div class="card-header">
          <h4>{{$t('Notification_Supplier')}}</h4>
        </div>
        <!--begin::form-->
        <div class="card-body">

          <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">

            <!-- Purchase -->
            <b-tab :title="$t('Purchase')">

              <form @submit.prevent="update_sms_body('purchase')">
                <div class="row">
                  <div class=" col-md-12">
                    <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                    <p>
                      {contact_name},{business_name},{invoice_number},{invoice_url},{total_amount},{paid_amount},{due_amount}
                    </p>
                  </div>
                  <hr>
                  <div class="form-group col-md-12">
                    <label for="sms_body_purchase">{{$t('sms_body')}} </label>
                    <textarea type="text" v-model="sms_body_purchase" class="form-control"
                      style=" height: 200px!important;" name="sms_body_purchase" id="sms_body_purchase"
                      :placeholder="$t('sms_body')"></textarea>
                  </div>

                </div>

                <div class="row mt-3">
                  <div class="col-md-6">
                    <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                      <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                        aria-hidden="true"></span> <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                    </button>
                  </div>
                </div>
              </form>

            </b-tab>

            <!-- Payment Sent -->
            <b-tab :title="$t('PaiementsSent')">

              <form @submit.prevent="update_sms_body('payment_sent')">
                <div class="row">
                  <div class=" col-md-12">
                    <span> <strong>{{$t('Available_Tags')}} : </strong></span>
                    <p>
                      {contact_name},{business_name},{payment_number},{paid_amount}
                    </p>
                  </div>
                  <hr>
                  <div class="form-group col-md-12">
                    <label for="sms_body_payment_sent">{{$t('sms_body')}}</label>
                    <textarea type="text" v-model="sms_body_payment_sent" class="form-control"
                      style=" height: 200px!important;" name="sms_body_payment_sent" id="sms_body_payment_sent"
                      :placeholder="$t('sms_body')"></textarea>
                  </div>

                </div>

                <div class="row mt-3">
                  <div class="col-md-6">
                    <button type="submit" :disabled="Submit_Processing" class="btn btn-primary">
                      <span v-if="Submit_Processing" class="spinner-border spinner-border-sm" role="status"
                        aria-hidden="true"></span> <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
                    </button>
                  </div>
                </div>
              </form>

            </b-tab>

          </b-tabs>

        </div>
      </div>
    </div>
  </div>

</div>



  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "SMS Templates"
  },
  data() {
    return {
      
      isLoading: true,
      Submit_Processing :false,
      sms_body_sale: '',
      sms_body_quotation: '',
      sms_body_payment_received: '',
      sms_body_subscription_reminder: '',

      sms_body_purchase: '',
      sms_body_payment_sent:'',
      sms_body_asset_validation_due: '',

      sms_body:'',

      selectedLocale: 'en',
      languages: [],
    };
  },

  computed: {
    languageOptions() {
      const list = (this.languages && this.languages.length) ? this.languages : [{ name: 'English', locale: 'en' }];
      return list.filter(l => l.is_active == true || l.is_active === 1 || l.is_active === '1').map(l => ({ name: l.name, locale: l.locale }));
    },
  },

  methods: {
    ...mapActions(["refreshUserPermissions"]),

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

     //---------------------------------- update_sms_body_sale ----------------\\
    update_sms_body(sms_body_type) {
        this.Submit_Processing = true;
        NProgress.start();
        NProgress.set(0.1);

        if(sms_body_type == 'sale'){
          this.sms_body = this.sms_body_sale;
        }else if(sms_body_type == 'quotation'){
          this.sms_body = this.sms_body_quotation;
        }else if(sms_body_type == 'payment_received'){
          this.sms_body = this.sms_body_payment_received;
        }else if(sms_body_type == 'purchase'){
          this.sms_body = this.sms_body_purchase;
        }else if(sms_body_type == 'payment_sent'){
          this.sms_body = this.sms_body_payment_sent;
        }else if(sms_body_type == 'subscription_reminder'){
          this.sms_body = this.sms_body_subscription_reminder;
        } else if (sms_body_type == 'asset_validation_due') {
          this.sms_body = this.sms_body_asset_validation_due;
        }

        axios
          .put("/update_sms_body", {
            sms_body: this.sms_body,
            sms_body_type: sms_body_type,
            locale: this.selectedLocale,
          })
          .then(response => {
            Fire.$emit("Event_sms");
            this.makeToast(
              "success",
              this.$t("Successfully_Updated"),
              this.$t("Success")
            );
            NProgress.done();
            this.Submit_Processing = false;
          })
          .catch(error => {
            NProgress.done();
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            this.Submit_Processing = false;
          });
    },


     //---------------------------------- get_sms_template ----------------\\
    get_sms_template() {
      const locale = this.selectedLocale || 'en';
      axios
        .get("get_sms_template?locale=" + encodeURIComponent(locale))
        .then(response => {
          this.sms_body_sale = response.data.sms_body_sale;
          this.sms_body_quotation = response.data.sms_body_quotation;
          this.sms_body_payment_received = response.data.sms_body_payment_received;
          this.sms_body_purchase = response.data.sms_body_purchase;
          this.sms_body_payment_sent = response.data.sms_body_payment_sent;
          this.sms_body_subscription_reminder = response.data.sms_body_subscription_reminder;
          this.sms_body_asset_validation_due = response.data.sms_body_asset_validation_due || '';

          this.isLoading = false;
        })
        .catch(error => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    fetchLanguages() {
      axios.get("/languages_setting").then(response => {
        this.languages = response.data || [];
        if (!this.selectedLocale && this.languages.length) {
          const defaultLang = this.languages.find(l => l.is_default) || this.languages[0];
          if (defaultLang) this.selectedLocale = defaultLang.locale;
        }
      }).catch(() => {
        this.languages = [];
      });
    },

    onLocaleChange() {
      this.isLoading = true;
      this.get_sms_template();
    },   


   
  }, //end Methods

  //----------------------------- Created function-------------------

  created: function() {
    this.fetchLanguages();
    this.get_sms_template();

    Fire.$on("Event_sms", () => {
      this.get_sms_template();
    });
  }
};
</script>