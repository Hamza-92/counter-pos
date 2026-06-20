<template>
  <div class="main-content">
    <breadcumb :page="$t('Checklist_Items')" :folder="$t('Service_Maintenance')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <b-card :title="$t('Checklist_Items')">
            <b-row class="mb-3">
              <b-col md="12" class="d-flex justify-content-end">
                <b-button size="sm" variant="primary" @click="openModal">
                  <lucide-icon name="plus" /> {{ $t('Add') }}
                </b-button>
              </b-col>
            </b-row>

            <vue-good-table
              :rows="items"
              :columns="itemColumns"
              :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
              :pagination-options="{ enabled: true, mode: 'records' }"
              styleClass="tableOne vgt-table mt-3"
            >
              <template slot="table-row" slot-scope="props">
                <span v-if="props.column.field === 'actions'">
                  <b-button
                    size="sm"
                    variant="outline-primary"
                    class="mr-2"
                    @click.stop="editItem(props.row)"
                  >
                    <lucide-icon name="pencil" />
                  </b-button>
                  <b-button
                    size="sm"
                    variant="outline-danger"
                    @click.stop="removeItem(props.row)"
                  >
                    <lucide-icon name="x" />
                  </b-button>
                </span>
              </template>
            </vue-good-table>
          </b-card>
    </div>

    <!-- Modal for Create/Edit Item -->
    <validation-observer ref="Create_Item">
      <b-modal
        hide-footer
        size="md"
        id="modal_Item"
        :title="editmode ? $t('Edit') : $t('Add')"
        @hidden="resetModal"
      >
        <b-form @submit.prevent="saveItem">
          <b-row>
            <b-col md="12">
              <validation-provider
                name="Category"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Category') + ' ' + '*'">
                  <b-form-select
                    v-model="itemForm.category_id"
                    :options="categoryOptions"
                    :disabled="categories.length === 0"
                    :state="getValidationState(validationContext)"
                    aria-describedby="category-feedback"
                  >
                    <template #first>
                      <b-form-select-option :value="null" disabled>
                        {{ $t('Select') }} {{ $t('Category') }}
                      </b-form-select-option>
                    </template>
                  </b-form-select>
                  <b-form-invalid-feedback id="category-feedback">
                    {{ validationContext.errors[0] }}
                  </b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
            <b-col md="12">
              <validation-provider
                name="Item Name"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Item_Name') + ' ' + '*'">
                  <b-form-input
                    :placeholder="$t('Item_Name')"
                    :state="getValidationState(validationContext)"
                    aria-describedby="item-name-feedback"
                    v-model="itemForm.name"
                  ></b-form-input>
                  <b-form-invalid-feedback id="item-name-feedback">
                    {{ validationContext.errors[0] }}
                  </b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
          </b-row>
          <div class="text-right mt-3">
            <b-button variant="secondary" @click="$bvModal.hide('modal_Item')" class="mr-2">
              {{ $t('Cancel') }}
            </b-button>
            <b-button type="submit" variant="primary" :disabled="SubmitProcessing">
              <span v-if="SubmitProcessing">
                <i class="fa fa-spinner fa-spin"></i> {{ $t('Processing') }}...
              </span>
              <span v-else>
                {{ editmode ? $t('Update') : $t('Add') }}
              </span>
            </b-button>
          </div>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
</template>

<script>
export default {
  name: 'ServiceChecklists',
  data() {
    return {
      isLoading: true,
      editmode: false,
      SubmitProcessing: false,
      categories: [],
      items: [],
      itemForm: {
        id: null,
        category_id: null,
        name: ''
      },
      itemColumns: [
        { label: this.$t('Category'), field: 'category_name' },
        { label: this.$t('Name'), field: 'name' },
        { label: this.$t('Actions'), field: 'actions', sortable: false }
      ]
    };
  },
  async mounted() {
    await this.loadCategories();
    await this.loadItems();
    this.isLoading = false;
  },
  computed: {
    categoryOptions() {
      return this.categories.map(cat => ({
        value: cat.id,
        text: cat.name
      }));
    }
  },
  methods: {
    async loadCategories() {
      const { data } = await axios.get('service_checklist/categories');
      this.categories = (data.categories || []).map(cat => ({
        ...cat,
        items_count: cat.items_count || 0
      }));
    },
    async loadItems() {
      const { data } = await axios.get('service_checklist/items');
      // Map items to include category name
      this.items = (data.items || []).map(item => {
        const category = this.categories.find(cat => cat.id === item.category_id);
        return {
          ...item,
          category_name: category ? category.name : '-'
        };
      });
    },
    openModal() {
      this.editmode = false;
      this.itemForm = { id: null, category_id: null, name: '' };
      this.$bvModal.show('modal_Item');
    },
    editItem(row) {
      this.editmode = true;
      this.itemForm = {
        id: row.id,
        category_id: row.category_id,
        name: row.name
      };
      this.$bvModal.show('modal_Item');
    },
    resetModal() {
      this.editmode = false;
      this.itemForm = { id: null, category_id: null, name: '' };
      if (this.$refs.Create_Item) {
        this.$refs.Create_Item.reset();
      }
    },
    async saveItem() {
      const valid = await this.$refs.Create_Item.validate();
      if (!valid) {
        return;
      }
      
      this.SubmitProcessing = true;
      
      try {
        const payload = {
          category_id: this.itemForm.category_id,
          name: this.itemForm.name.trim(),
          sort_order: 0
        };
        
        let response;
        if (this.editmode) {
          response = await axios.put(`service_checklist/items/${this.itemForm.id}`, payload);
          this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'));
        } else {
          response = await axios.post('service_checklist/items', payload);
          this.makeToast('success', this.$t('Successfully_Created'), this.$t('Success'));
        }
        
        if (response.data && response.data.success) {
          this.$bvModal.hide('modal_Item');
          await this.loadItems();
          await this.loadCategories();
        } else {
          this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'));
        }
      } catch (error) {
        console.error('Error saving item:', error);
        const errorMsg = error.response?.data?.message || error.message || this.$t('InvalidData');
        this.makeToast('danger', errorMsg, this.$t('Failed'));
      } finally {
        this.SubmitProcessing = false;
      }
    },
    async removeItem(row) {
      const ok = await this.$bvModal.msgBoxConfirm(this.$t('AreYouSure'), {
        size: 'sm'
      });
      if (!ok) return;
      
      try {
        await axios.delete(`service_checklist/items/${row.id}`);
        this.makeToast('success', this.$t('Deleted_in_successfully'), this.$t('Success'));
        await this.loadItems();
        await this.loadCategories();
      } catch (error) {
        console.error('Error removing item:', error);
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
    },
    
    //------ Validation State
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    }
  }
};
</script>

















