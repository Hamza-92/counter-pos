<template>
  <div class="main-content">

  <breadcumb :page="$t('Translations')" :folder="$t('Settings')"/>
  <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

  <div v-if="!isLoading">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <b-button
          variant="secondary"
          @click="$router.push('/app/settings/translations_settings')"
        >
          ← {{$t('Back')}}
        </b-button>
      </div>
      <h4 class="mb-0">{{$t('Translations for')}} " {{language}} "</h4>
      <div></div> <!-- Spacer -->
    </div>

    <!-- Reload Alert -->
    <b-alert
      variant="info"
      show
      class="w-100 mb-3 text-center"
    >
      🔄 {{$t('Please reload the page after saving translations to apply the changes.')}}
    </b-alert>

   <!-- Responsive Search and Bulk Save -->
  <div class="mb-3">
    <b-row class="gy-2 align-items-center">
      <!-- Search & Buttons Section -->
      <b-col cols="12" md="8" class="mt-2">
        <b-input-group>
          <b-form-input
            v-model="searchInput"
            placeholder="Search by key or value..."
          ></b-form-input>

          <b-input-group-append>
            <b-button variant="primary" @click="applySearch">
              🔍
            </b-button>
            <b-button variant="warning" @click="resetSearch">
              🔄
            </b-button>
          </b-input-group-append>
        </b-input-group>
      </b-col>

      <!-- Save All Button -->
      <b-col cols="12" md="4" class="mt-2">
        <b-button variant="info" class="mr-2" @click="showAddModal = true">
          ➕ {{$t('Add New')}}
        </b-button>

        <b-button variant="success" @click="bulkSave">
          💾 {{$t('Save All Changes')}}
        </b-button>
      
      </b-col>
    </b-row>
  </div>


<b-modal v-model="showAddModal" :title="$t('Add New Translation')" @ok="submitNewTranslation" ok-title="Add" cancel-title="Cancel">
  <b-form @submit.stop.prevent>
    <b-form-group label="Key">
      <b-form-input v-model="newTranslation.key" required></b-form-input>
    </b-form-group>
    <b-form-group label="Value">
      <b-form-input v-model="newTranslation.value"></b-form-input>
    </b-form-group>
  </b-form>
</b-modal>

<!-- Translations Table -->
<b-table
  :items="filteredTranslations"
  :fields="['key', 'value', 'actions']"
  :busy="isLoading"
  responsive
  bordered
  striped
  small
>
  <template #cell(key)="data">
    <b-form-input :value="data.item.key" disabled></b-form-input>
  </template>

  <template #cell(value)="data">
    <b-form-input v-model="data.item.value" />
  </template>

  <template #cell(actions)="data">
    <b-button
      size="sm"
      variant="success"
      @click="saveTranslation(data.item)"
    >
      {{$t('Save')}}
    </b-button>
      <b-button
      v-if="data.item.locale !='en'"
      size="sm"
      variant="danger"
      @click="deleteTranslation(data.item.key)"
    >{{$t('Delete')}}
    </b-button>
  </template>
</b-table>

<!-- Pagination -->
<b-pagination
  v-model="currentPage"
  :total-rows="totalRows"
  :per-page="perPage"
  align="center"
  class="mt-3"
></b-pagination>

  <div class="text-muted text-right mb-2">
  {{$t('Showing')}} {{ translations.length }} of {{ totalRows }} {{$t('Translations')}}
</div>

</div>


  </div>
</template>

<script>
export default {
  data() {
    return {
      showAddModal: false,
      newTranslation: {
        key: '',
        value: '',
      },
      locale: this.$route.params.locale,
      language:'',
      translations: [],
      originalTranslations: [],
      totalRows: 0,
      currentPage: 1,
      perPage: 100,
      isLoading: false,
      searchQuery: '',     // sent to backend
      searchInput: '',     // input field
    };
  },
  computed: {
    filteredTranslations() {
      if (!this.searchQuery) return this.translations;
      return this.translations.filter(item =>
        item.key.toLowerCase().includes(this.searchQuery.toLowerCase())
      );
    }
  },
  methods: {

    async submitNewTranslation() {
    if (!this.newTranslation.key) return;

    try {
      await axios.put(`/translations_setting/${this.locale}`, {
        key: this.newTranslation.key,
        value: this.newTranslation.value,
      });

      this.$bvToast.toast(this.$t("Translation added"), {
        title: this.$t("Success"),
        variant: 'success',
        solid: true,
      });

      this.showAddModal = false;
      this.newTranslation.key = '';
      this.newTranslation.value = '';

      // Reload current page
      this.fetchTranslations(this.currentPage);
    } catch (err) {
      this.$bvToast.toast(this.$t("Failed to add translation"), {
        title: this.$t("Failed"),
        variant: 'danger',
        solid: true,
      });
    }
  },

    applySearch() {
      this.searchQuery = this.searchInput;
      this.currentPage = 1;
      this.fetchTranslations(1);
    },

    resetSearch() {
      this.searchInput = '';
      this.searchQuery = '';
      this.currentPage = 1;
      this.fetchTranslations(1);
    },

    async fetchTranslations(page = 1) {
      this.isLoading = true;
      try {
        const res = await axios.get(`/translations_setting/${this.locale}`, {
          params: {
            page: page,
            per_page: this.perPage,
            search: this.searchQuery,
          }
        });
        this.translations = res.data.data;
        this.originalTranslations = JSON.parse(JSON.stringify(res.data.data));
        this.totalRows = res.data.total;
        this.currentPage = res.data.current_page;
        this.language = res.data.language;
      } catch (err) {
      } finally {
        this.isLoading = false;
      }
    },

    async saveTranslation(entry) {
      try {
        await axios.put(`/translations_setting/${this.locale}`, {
          key: entry.key,
          value: entry.value,
        });
        this.$bvToast.toast(this.$t("Translation updated"), {
          title: 'Success',
          variant: 'success',
          solid: true,
        });
      } catch (err) {
        this.$bvToast.toast(this.$t("Failed to update"), {
          title: this.$t("Failed"),
          variant: 'danger',
          solid: true,
        });
      }
    },

    async bulkSave() {
      const changed = this.translations.filter((t, i) =>
        t.value !== this.originalTranslations[i]?.value
      );

      if (!changed.length) {
        this.$bvToast.toast(this.$t("No changes to save"), {
          title: this.$t("Notice"),
          variant: 'info',
          solid: true,
        });
        return;
      }

      this.isLoading = true;
      try {
        await Promise.all(changed.map(entry =>
          axios.put(`/translations_setting/${this.locale}`, {
            key: entry.key,
            value: entry.value,
          })
        ));
        this.$bvToast.toast(this.$t("All changes saved successfully"), {
          title: this.$t("Success"),
          variant: 'success',
          solid: true,
        });
        this.fetchTranslations(this.currentPage);
      } catch (err) {
        this.$bvToast.toast(this.$t("Bulk save failed"), {
          title: this.$t("Failed"),
          variant: 'danger',
          solid: true,
        });
      } finally {
        this.isLoading = false;
      }
    },

    async deleteTranslation(key) {
      const translation = this.translations.find(t => t.key === key);
      if (!translation) return;

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
         
          try {
            await axios.delete(`/translations_setting/${this.locale}/${key}`);
            this.$swal(
              this.$t("Delete_Deleted"),
              this.$t("Deleted_in_successfully"),
              "success"
            );
            this.fetchTranslations(this.currentPage);
          } catch (error) {
            this.$swal(
              this.$t("Delete_Failed"),
              this.$t("Delete_Therewassomethingwronge"),
              "warning"
            );
          } finally {
          }
        }
      });
    }

  },
  watch: {
    currentPage(newPage) {
      this.fetchTranslations(newPage);
    },
    searchQuery() {
      this.currentPage = 1;
      this.fetchTranslations(1);
    }
  },
  created() {
    this.fetchTranslations();
  }
};
</script>
