<template>
  <div class="main-content">
    <breadcumb :page="$t('Checklist_Categories')" :folder="$t('Service_Maintenance')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <b-row>
        <b-col md="6">
          <b-card :title="$t('Checklist_Categories')">
            <b-form @submit.prevent="saveCategory">
              <b-form-group :label="$t('Name')">
                <b-form-input v-model="categoryForm.name" required />
              </b-form-group>
              <b-form-group :label="$t('Description')">
                <b-form-textarea v-model="categoryForm.description" rows="2" />
              </b-form-group>
              <div class="text-right">
                <b-button size="sm" variant="secondary" class="mr-2" @click="resetCategoryForm">
                  {{ $t('Reset') }}
                </b-button>
                <b-button size="sm" type="submit" variant="primary">
                  {{ $t('Save') }}
                </b-button>
              </div>
            </b-form>
          </b-card>
        </b-col>

        <b-col md="6">
          <b-card :title="$t('List')">
            <vue-good-table
              :rows="categories"
              :columns="categoryColumns"
              :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
              :pagination-options="{ enabled: true, mode: 'records' }"
              styleClass="tableOne vgt-table"
            >
              <template slot="table-row" slot-scope="props">
                <span v-if="props.column.field === 'actions'">
                  <b-button
                    size="sm"
                    variant="outline-primary"
                    class="mr-2"
                    @click.stop="editCategory(props.row)"
                  >
                    <lucide-icon name="pencil" />
                  </b-button>
                  <b-button
                    size="sm"
                    variant="outline-danger"
                    @click.stop="removeCategory(props.row)"
                  >
                    <lucide-icon name="x" />
                  </b-button>
                </span>
              </template>
            </vue-good-table>
          </b-card>
        </b-col>
      </b-row>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ServiceChecklistCategories',
  data() {
    return {
      isLoading: true,
      categories: [],
      categoryForm: {
        id: null,
        name: '',
        description: ''
      },
      categoryColumns: [
        { label: this.$t('Name'), field: 'name' },
        { label: this.$t('Description'), field: 'description' },
        { label: this.$t('Items') + ' ' + this.$t('Count'), field: 'items_count' },
        { label: this.$t('Actions'), field: 'actions', sortable: false }
      ]
    };
  },
  async mounted() {
    await this.loadCategories();
    this.isLoading = false;
  },
  methods: {
    async loadCategories() {
      const { data } = await axios.get('service_checklist/categories');
      this.categories = (data.categories || []).map(cat => ({
        ...cat,
        items_count: cat.items_count || 0
      }));
    },
    editCategory(cat) {
      this.categoryForm = {
        id: cat.id,
        name: cat.name,
        description: cat.description || ''
      };
    },
    resetCategoryForm() {
      this.categoryForm = { id: null, name: '', description: '' };
    },
    async saveCategory() {
      if (!this.categoryForm.name || !this.categoryForm.name.trim()) {
        this.makeToast('warning', this.$t('Name_is_required'), this.$t('Warning'));
        return;
      }
      
      try {
        const payload = {
          name: this.categoryForm.name.trim(),
          description: this.categoryForm.description || ''
        };
        
        if (this.categoryForm.id) {
          await axios.put(
            `service_checklist/categories/${this.categoryForm.id}`,
            payload
          );
          this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'));
        } else {
          await axios.post('service_checklist/categories', payload);
          this.makeToast('success', this.$t('Successfully_Created'), this.$t('Success'));
        }
        await this.loadCategories();
        this.resetCategoryForm();
      } catch (error) {
        console.error('Error saving category:', error);
        const errorMsg = error.response?.data?.message || error.message || this.$t('InvalidData');
        this.makeToast('danger', errorMsg, this.$t('Failed'));
      }
    },
    async removeCategory(row) {
      const ok = await this.$bvModal.msgBoxConfirm(this.$t('AreYouSure'), {
        size: 'sm'
      });
      if (!ok) return;
      
      try {
        await axios.delete(`service_checklist/categories/${row.id}`);
        this.makeToast('success', this.$t('Deleted_in_successfully'), this.$t('Success'));
        await this.loadCategories();
        this.resetCategoryForm();
      } catch (error) {
        console.error('Error removing category:', error);
        const errorMsg = error.response?.data?.message || error.message || this.$t('InvalidData');
        this.makeToast('danger', errorMsg, this.$t('Failed'));
      }
    },
    
    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    }
  }
};
</script>

