<template>
  <div class="main-content">
    <breadcumb :page="$t('SubCategory')" :folder="$t('Products')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :rows="rows"
        :totalRows="totalRows"
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :select-options="{ enabled: true, clearSelectionText: '' }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
        styleClass="table-hover tableOne vgt-table"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        @on-selected-rows-change="selectionChanged"
      >
        <div slot="selected-row-actions">
          <button class="btn btn-danger btn-sm" :disabled="!selectedIds.length" @click="deleteBySelected">
            {{$t('Del')}}
          </button>
        </div>

        <div slot="table-actions" class="mt-2 mb-3">
          <b-button class="btn-rounded" variant="btn btn-primary btn-icon m-1" @click="openCreate">
            <lucide-icon name="plus" /> {{$t('Add')}}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <!-- Actions -->
          <span v-if="props.column.field === 'actions'">
            <a v-b-tooltip.hover :title="$t('Edit')" @click="openEdit(props.row)">
              <lucide-icon class="text-25 text-success" name="pencil" />
            </a>
            <a v-b-tooltip.hover :title="$t('Delete')" class="ml-2" @click="removeOne(props.row.id)">
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>

          <!-- Default -->
          <span v-else>{{ props.formattedRow[props.column.field] }}</span>
        </template>
      </vue-good-table>
    </b-card>

    <!-- Create/Edit Modal -->
    <validation-observer ref="CategoryForm">
      <b-modal id="New_SubCategory" hide-footer size="md" :title="editmode ? $t('Edit') : $t('Add')">
        <b-form @submit.prevent="submitCategory">
          <b-row>
            <!-- Parent Category -->
            <b-col md="12">
              <validation-provider name="Category" :rules="{ required: true }" v-slot="v">
                <b-form-group :label="$t('Categorie') + ' *'">
                  <v-select
                    :class="{'is-invalid': !!v.errors.length}"
                    :state="v.errors[0] ? false : (v.valid ? true : null)"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Category')"
                    v-model="form.category_id"
                    :options="categories.map(c => ({ label: c.name, value: c.id }))"
                  />
                  <b-form-invalid-feedback>{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Name -->
            <b-col md="12">
              <validation-provider name="Name subcategory" :rules="{ required: true }" v-slot="v">
                <b-form-group :label="$t('SubCategoryName') + ' *'">
                  <b-form-input
                    v-model="form.name"
                    :placeholder="$t('Enter_name_category')"
                    :state="getState(v)"
                    aria-describedby="Name-feedback"
                  />
                  <b-form-invalid-feedback id="Name-feedback">{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Description -->
            <b-col md="12">
              <b-form-group :label="$t('Description')">
                <b-form-textarea
                  v-model="form.description"
                  :placeholder="$t('Afewwords')"
                  rows="3"
                />
              </b-form-group>
            </b-col>

            <!-- Status -->
            <b-col md="12">
              <b-form-group :label="$t('Status')">
                <b-form-checkbox v-model="form.status" switch>
                  {{ form.status ? $t('Active') : $t('Inactive') }}
                </b-form-checkbox>
              </b-form-group>
            </b-col>

            <!-- Submit -->
            <b-col md="12" class="mt-3">
              <b-button variant="primary" type="submit" :disabled="submitProcessing">
                <lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}
              </b-button>
              <div v-once class="typo__p" v-if="submitProcessing">
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
import NProgress from 'nprogress'

const API = 'subcategories'

export default {
  metaInfo: { title: 'SubCategory' },

  data() {
    return {
      isLoading: true,
      submitProcessing: false,

      serverParams: {
        sort: { field: 'id', type: 'desc' },
        page: 1,
        perPage: 10
      },

      selectedIds: [],
      totalRows: 0,
      search: '',
      limit: '10',

      rows: [],
      categories: [],
      editmode: false,

      form: { id: '', category_id: '', name: '', description: '', status: true },
    }
  },

  computed: {
    columns() {
      return [
        { label: this.$t('Categorie'), field: 'category_name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('SubCategoryName'), field: 'name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Status'), field: 'status_label', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Action'), field: 'actions', sortable: false, tdClass: 'text-left', thClass: 'text-left' }
      ]
    }
  },

  methods: {
    getState({ dirty, validated, valid = null }) { return dirty || validated ? valid : null },
    toast(variant, msg, title) { this.$root.$bvToast.toast(msg, { title, variant, solid: true }) },
    updateParams(patch) { this.serverParams = { ...this.serverParams, ...patch } },

    // Table events
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage })
        this.fetchRows()
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage
        this.updateParams({ page: 1, perPage: currentPerPage })
        this.fetchRows()
      }
    },
    onSortChange(params) {
      const s = params[0] || { field: 'id', type: 'desc' }
      this.updateParams({ sort: { field: s.field, type: s.type } })
      this.fetchRows()
    },
    onSearch({ searchTerm }) {
      this.search = searchTerm
      this.updateParams({ page: 1 })
      this.fetchRows()
    },
    selectionChanged({ selectedRows }) {
      this.selectedIds = selectedRows.map(r => r.id)
    },

    // CRUD
    openCreate() {
      this.resetForm()
      this.editmode = false
      this.$bvModal.show('New_SubCategory')
    },

    async openEdit(row) {
      this.resetForm()
      this.editmode = true
      try {
        NProgress.start(); NProgress.set(0.1)
        const { data } = await axios.get(`${API}/${row.id}`)
        this.form = {
          id: data.id,
          category_id: data.category_id || '',
          name: data.name || '',
          description: data.description || '',
          status: !!data.status,
        }
      } catch (e) {
        this.toast('danger', this.$t('InvalidData'), this.$t('Failed'))
        return
      } finally {
        NProgress.done()
      }
      this.$bvModal.show('New_SubCategory')
    },

    async fetchRows() {
      NProgress.start(); NProgress.set(0.1)
      this.isLoading = true
      const { page, perPage, sort } = this.serverParams
      try {
        const { data } = await axios.get(
          `${API}?page=${page}&SortField=${sort.field}&SortType=${sort.type}&search=${encodeURIComponent(this.search)}&limit=${this.limit}`
        )
        // map API payload into table rows with category name + status label
        const subs = data.subcategories || []
        this.rows = subs.map(sc => ({
          id: sc.id,
          name: sc.name,
          category_id: sc.category_id,
          category_name: sc.category ? sc.category.name : '',
          status: !!sc.status,
          status_label: sc.status ? this.$t('Active') : this.$t('Inactive'),
        }))
        this.totalRows = data.totalRows || 0
      } catch (e) {
        this.toast('danger', this.$t('InvalidData'), this.$t('Failed'))
      } finally {
        this.isLoading = false
        NProgress.done()
      }
    },

    async submitCategory() {
      const valid = await this.$refs.CategoryForm.validate()
      if (!valid) return

      this.submitProcessing = true
      const payload = {
        category_id: this.form.category_id,
        name: this.form.name,
        description: this.form.description,
        status: this.form.status ? 1 : 0,
      }

      try {
        NProgress.start(); NProgress.set(0.1)
        if (this.editmode) {
          await axios.put(`${API}/${this.form.id}`, payload)
        } else {
          await axios.post(API, payload)
        }
        this.toast('success', this.$t('Successfully_Updated'), this.$t('Success'))
        this.$bvModal.hide('New_SubCategory')
        this.fetchRows()
      } catch (e) {
        this.toast('danger', this.$t('InvalidData'), this.$t('Failed'))
      } finally {
        this.submitProcessing = false
        NProgress.done()
      }
    },

    async removeOne(id) {
      const res = await this.$swal({
        title: this.$t('Delete_Title'),
        text: this.$t('Delete_Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: this.$t('Delete_cancelButtonText'),
        confirmButtonText: this.$t('Delete_confirmButtonText'),
      })
      if (!res.value) return

      try {
        NProgress.start(); NProgress.set(0.1)
        await axios.delete(`${API}/${id}`)
        await this.$swal(this.$t('Delete_Deleted'), this.$t('Deleted_in_successfully'), 'success')
        this.fetchRows()
      } catch (e) {
        this.$swal(this.$t('Delete_Failed'), this.$t('Delete_Therewassomethingwronge'), 'warning')
      } finally {
        NProgress.done()
      }
    },

    async deleteBySelected() {
      if (!this.selectedIds.length) return

      const res = await this.$swal({
        title: this.$t('Delete_Title'),
        text: this.$t('Delete_Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: this.$t('Delete_cancelButtonText'),
        confirmButtonText: this.$t('Delete_confirmButtonText'),
      })
      if (!res.value) return

      NProgress.start(); NProgress.set(0.1)
      try {
        await axios.post(`${API}/delete/by_selection`, { selectedIds: this.selectedIds })
        await this.$swal(this.$t('Delete_Deleted'), this.$t('Deleted_in_successfully'), 'success')
        this.selectedIds = []
        this.fetchRows()
      } catch (e) {
        this.$swal(this.$t('Delete_Failed'), this.$t('Delete_Therewassomethingwronge'), 'warning')
      } finally {
        NProgress.done()
      }
    },

    resetForm() {
      this.form = { id: '', category_id: '', name: '', description: '', status: true }
      if (this.$refs.CategoryForm) {
        this.$refs.CategoryForm.reset()
      }
    },
  },

  created() {
    // Load all parent categories for the select
    axios.get('categories')
      .then(({ data }) => {
        this.categories = data.categories || []
      })
      .catch(() => {})

    this.fetchRows()
  },
}
</script>

