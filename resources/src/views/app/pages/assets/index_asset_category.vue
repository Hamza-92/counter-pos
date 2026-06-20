<template>
  <div class="main-content">
    <breadcumb :page="$t('Asset_Category')" :folder="$t('Assets')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="categories"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :pagination-options="{ enabled: true, mode: 'records' }"
        styleClass="tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button
            v-if="currentUserPermissions && currentUserPermissions.includes('assets')"
            @click="New_Category()"
            size="sm"
            variant="primary ripple m-1"
          >
            <lucide-icon name="plus" />
            {{$t('Add')}}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <a
              @click="Edit_Category(props.row)"
              v-if="currentUserPermissions && currentUserPermissions.includes('assets')"
              title="Edit"
              class="cursor-pointer"
              v-b-tooltip.hover
            >
              <lucide-icon class="text-25 text-success" name="pencil" />
            </a>
            <a
              title="Delete"
              class="cursor-pointer"
              v-b-tooltip.hover
              v-if="currentUserPermissions && currentUserPermissions.includes('assets')"
              @click="Delete_Category(props.row.id)"
            >
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </div>

    <validation-observer ref="Create_Category">
      <b-modal hide-footer size="lg" id="New_Asset_Category" :title="editmode?$t('Edit'):$t('Add')">
        <b-form @submit.prevent="Submit_Category">
          <b-row>
            <b-col md="12">
              <validation-provider name="Name" :rules="{ required: true }" v-slot="validationContext">
                <b-form-group :label="$t('Name') + ' *'">
                  <b-form-input :state="getValidationState(validationContext)" aria-describedby="name-feedback" v-model="category.name"></b-form-input>
                  <b-form-invalid-feedback id="name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Description')">
                <textarea v-model="category.description" rows="4" class="form-control" :placeholder="$t('Afewwords')"></textarea>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing"><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
              <div v-once class="typo__p" v-if="SubmitProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
  
</template>

<script>
import { mapGetters } from 'vuex';
import NProgress from 'nprogress';

export default {
  name: 'AssetCategoryIndex',
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      serverParams: {
        sort: { field: 'id', type: 'desc' },
        page: 1,
        perPage: 10
      },
      search: '',
      limit: '10',
      totalRows: 0,
      categories: [],
      editmode: false,
      category: { id: '', name: '', description: '' },
    };
  },
  computed: {
    ...mapGetters(['currentUserPermissions']),
    columns() {
      return [
        { label: this.$t('Name'), field: 'name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Description'), field: 'description', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Action'), field: 'actions', tdClass: 'text-left', thClass: 'text-left', sortable: false },
      ];
    }
  },
  created() {
    this.Get_Categories(1);
    Fire.$on('Create_Asset_Category', () => {
      this.Get_Categories(this.serverParams.page);
      this.$bvModal.hide('New_Asset_Category');
    });
    Fire.$on('Delete_Asset_Category', () => {
      this.Get_Categories(this.serverParams.page);
    });
  },
  methods: {
    Submit_Category() {
      this.$refs.Create_Category.validate().then(success => {
        if (!success) {
          this.makeToast('danger', this.$t('Please_fill_the_form_correctly'), this.$t('Failed'));
        } else {
          if (!this.editmode) this.Create_Category(); else this.Update_Category();
        }
      });
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    },
    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.Get_Categories(currentPage);
    },
    onPerPageChange({ currentPerPage }) {
      this.limit = currentPerPage;
      this.serverParams.page = 1;
      this.serverParams.perPage = currentPerPage;
      this.Get_Categories(1);
    },
    onSortChange(params) {
      this.serverParams.sort.type = params[0].type;
      this.serverParams.sort.field = params[0].field;
      this.Get_Categories(this.serverParams.page);
    },
    onSearch(params) {
      this.search = params.searchTerm;
      this.Get_Categories(this.serverParams.page);
    },
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },
    New_Category() {
      this.reset_Form();
      this.editmode = false;
      this.$bvModal.show('New_Asset_Category');
    },
    Edit_Category(cat) {
      this.reset_Form();
      this.category = { id: cat.id, name: cat.name, description: cat.description };
      this.editmode = true;
      this.$bvModal.show('New_Asset_Category');
    },
    reset_Form() {
      this.category = { id: '', name: '', description: '' };
    },
    Get_Categories(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          'assets_category?page=' + page +
          '&SortField=' + this.serverParams.sort.field +
          '&SortType=' + this.serverParams.sort.type +
          '&search=' + this.search +
          '&limit=' + this.limit
        )
        .then(response => {
          const payload = response.data || {};
          this.categories = payload.data || payload.asset_categories || [];
          this.totalRows = payload.totalRows || this.categories.length;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => { this.isLoading = false; }, 300);
        });
    },
    Create_Category() {
      this.SubmitProcessing = true;
      axios.post('assets_category', { name: this.category.name, description: this.category.description })
        .then(() => {
          Fire.$emit('Create_Asset_Category');
          this.makeToast('success', this.$t('Successfully_Created'), this.$t('Success'));
          this.SubmitProcessing = false;
        })
        .catch(() => {
          this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'));
          this.SubmitProcessing = false;
        });
    },
    Update_Category() {
      this.SubmitProcessing = true;
      axios.put('assets_category/' + this.category.id, { name: this.category.name, description: this.category.description })
        .then(() => {
          Fire.$emit('Create_Asset_Category');
          this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'));
          this.SubmitProcessing = false;
        })
        .catch(() => {
          this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'));
          this.SubmitProcessing = false;
        });
    },
    Delete_Category(id) {
      this.$swal({
        title: this.$t('Delete_Title'),
        text: this.$t('Delete_Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: this.$t('Delete_cancelButtonText'),
        confirmButtonText: this.$t('Delete_confirmButtonText')
      }).then(result => {
        if (result.value) {
          axios.delete('assets_category/' + id)
            .then(() => {
              this.$swal(this.$t('Delete_Deleted'), this.$t('Deleted_in_successfully'), 'success');
              Fire.$emit('Delete_Asset_Category');
            })
            .catch(() => {
              this.$swal(this.$t('Delete_Failed'), this.$t('Delete_Therewassomethingwronge'), 'warning');
            });
        }
      });
    }
  }
}
</script>


