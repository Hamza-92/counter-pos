<template>
  <div class="main-content">
    <breadcumb :page="$t('Languages')" :folder="$t('Settings')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

  
     <!-- Language Form -->
    <b-card class="mb-4" v-if="!isLoading">
      <b-form @submit.prevent="submitLang">
        <b-row>
          <b-col md="4">
            <b-form-group :label="$t('Name')">
              <b-form-input v-model="lang.name" required />
            </b-form-group>
          </b-col>

          <b-col md="4">
            <b-form-group :label="$t('Locale')">
              <b-form-input v-model="lang.locale" required />
            </b-form-group>
          </b-col>

          <b-col md="4">
            <b-form-group :label="$t('Flag')">
              <b-form-file v-model="lang.flag" accept="image/*" />
            </b-form-group>
          </b-col>
        </b-row>

        <b-button type="submit" variant="primary" :disabled="SubmitProcessing">
          {{ $t(lang.id ? 'Update Language' : 'Add Language') }} {{$t('Language')}}
          
        </b-button>
         <div v-once class="typo__p" v-if="SubmitProcessing">
          <div class="spinner sm spinner-primary mt-3"></div>
        </div>
        <b-button type="reset" variant="secondary" class="ml-2" @click="resetForm">
          {{$t('Reset')}}
        </b-button>
      </b-form>
    </b-card>


    <!-- Table of Languages -->
    <b-table :items="languages" :fields="['flag', 'name', 'locale','is_active','is_default', 'actions']" v-if="!isLoading">
      <template #cell(flag)="data">
        <img :src="`/flags/${data.item.flag}`" width="30" v-if="data.item.flag" />
      </template>

       <template #cell(is_active)="data">
        <b-form-checkbox
          :checked="data.item.is_active == true || data.item.is_active == 1 || data.item.is_active === '1'"
          @change="() => onToggleActive(data.item)"
        ></b-form-checkbox>
      </template>

      <template #cell(is_default)="data">
        <b-form-checkbox
          :checked="data.item.is_default == true || data.item.is_default == 1 || data.item.is_default === '1'"
          @change="() => onToggleDefault(data.item)"
        ></b-form-checkbox>
      </template>

      <template #cell(actions)="data">
        <b-button size="sm" @click="editLang(data.item)" v-if="data.item.locale !='en'">{{$t('Edit')}}</b-button>
        <b-button size="sm" variant="danger" @click="deleteLang(data.item.id)" v-if="data.item.locale !='en'">{{$t('Delete')}}</b-button>
        <b-button
          size="sm"
          variant="info"
          @click="$router.push({ name: 'translations_view', params: { locale: data.item.locale } })"
        >
          {{$t('Translations')}}
        </b-button>
      </template>
    </b-table>



  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Languages"
  },

  data() {
  return {
     isLoading: true,
     SubmitProcessing:false,
    languages: [],
    lang: {
      id: null,
      name: '',
      locale: '',
      flag: null,
    },
  };
},
methods: {

   async onToggleDefault(item) {
    try {
      await axios.post(`/languages_setting/${item.id}/set-default`);

      // Update state locally to reflect changes
      this.languages = this.languages.map(lang => ({
        ...lang,
        is_default: lang.id === item.id
      }));

      this.SetLocal(item.locale); // ✅ call it here after success

      this.makeToast(
        "success",
        this.$t("Successfully_Updated"),
        this.$t("Success")
      );
    } catch (error) {
      this.makeToast(
        "danger",
        this.$t("InvalidData"),
        this.$t("Failed")
      );
    }
  },

  async onToggleActive(item) {

   // Check if the item is the default language
  if (item.is_default) {
    this.makeToast(
        "danger",
        'Cannot change default language',
        this.$t("Failed")
    );

    return;
  }

    try {
      await axios.post(`/languages_setting/${item.id}/set-active`);

      this.makeToast(
        "success",
        this.$t("Successfully_Updated"),
        this.$t("Success")
      );
      window.location.reload();
    } catch (error) {
      this.makeToast(
        "danger",
        this.$t("InvalidData"),
        this.$t("Failed")
      );
    }
  },


  async fetchLanguages() {
    this.isLoading = true;
    const { data } = await axios.get('/languages_setting');
    this.languages = data;
    this.isLoading = false;
  },

  async submitLang() {
  this.SubmitProcessing = true;
  const formData = new FormData();
  formData.append('name', this.lang.name);
  formData.append('locale', this.lang.locale);
  if (this.lang.flag) formData.append('flag', this.lang.flag);

  try {
    if (this.lang.id) {
      await axios.post(`/languages_setting/${this.lang.id}?_method=PUT`, formData);
    } else {
      await axios.post('/languages_setting', formData);
    }

    this.makeToast(
      'success',
      this.$t('Successfully_Created'),
      this.$t('Success')
    );

    this.resetForm();
    this.fetchLanguages();
    this.SubmitProcessing = false;
  } catch (e) {
    this.makeToast(
      'danger',
      this.$t('InvalidData'),
      this.$t('Failed')
    );
    this.SubmitProcessing = false;
  }
  },

  async deleteLang(id) {
  const lang = this.languages.find(l => l.id === id);
  if (!lang) return;

  if (lang.is_default) {
    this.$swal(
      this.$t("Action_Blocked"),
      this.$t("You_cannot_delete_the_default_language"),
      "error"
    );
    return;
  }

  this.$swal({
    title: this.$t("Delete_Title"),
    text: this.$t("Delete_Text"),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    cancelButtonText: this.$t("Delete_cancelButtonText"),
    confirmButtonText: this.$t("Delete_confirmButtonText"),
  }).then(async (result) => {
    if (result.value) {
      NProgress.start();
      NProgress.set(0.1);
      try {
        await axios.delete("languages_setting/" + id);
        this.$swal(
          this.$t("Delete_Deleted"),
          this.$t("Deleted_in_successfully"),
          "success"
        );
        this.fetchLanguages();
        setTimeout(() => NProgress.done(), 500);
      } catch (error) {
        setTimeout(() => NProgress.done(), 500);
        this.$swal(
          this.$t("Delete_Failed"),
          this.$t("Delete_Therewassomethingwronge"),
          "warning"
        );
      }
    }
  });
  },

  SetLocal(locale) {
    this.$i18n.locale = locale;
    this.$store.dispatch("setLanguage", locale);
    Fire.$emit("ChangeLanguage");
    window.location.reload();
  },



  editLang(lang) {
    this.lang = { ...lang, flag: null };
  },
   resetForm() {
    this.lang = { id: null, name: '', locale: '', flag: null };
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
created() {
  this.fetchLanguages();
}

};
</script>