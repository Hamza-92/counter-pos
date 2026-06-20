<template>
  <div class="main-content">
    <breadcumb :page="$t('Categories')" :folder="$t('Products')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :rows="categories"
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
          <!-- Icon cell -->
          <span v-if="props.column.field === 'icon'">
            <i v-if="props.row.icon" :class="props.row.icon" class="text-20"></i>
            <span v-else class="text-muted">—</span>
          </span>

          <!-- Actions -->
          <span v-else-if="props.column.field === 'actions'">
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
      <b-modal id="New_Category" hide-footer size="md" :title="editmode ? $t('Edit') : $t('Add')">
        <b-form @submit.prevent="submitCategory">
          <b-row>
            <!-- Code -->
            <b-col md="12">
              <validation-provider name="Code category" :rules="{ required: true }" v-slot="v">
                <b-form-group :label="$t('Codecategorie') + ' *'">
                  <b-form-input
                    v-model="category.code"
                    :placeholder="$t('Enter_Code_category')"
                    :state="getState(v)"
                    aria-describedby="Code-feedback"
                  />
                  <b-form-invalid-feedback id="Code-feedback">{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Name -->
            <b-col md="12">
              <validation-provider name="Name category" :rules="{ required: true }" v-slot="v">
                <b-form-group :label="$t('Namecategorie') + ' *'">
                  <b-form-input
                    v-model="category.name"
                    :placeholder="$t('Enter_name_category')"
                    :state="getState(v)"
                    aria-describedby="Name-feedback"
                  />
                  <b-form-invalid-feedback id="Name-feedback">{{ v.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

           <!-- Icon (Bootstrap Icons) -->
            <b-col md="12">
              <b-form-group :label="$t('Icon')">
                <div class="d-flex align-items-center">
                  <b-form-select v-model="category.icon" :options="iconOptions" class="mr-3" />
                  <i v-if="category.icon" :class="category.icon" style="font-size:22px;"></i>
                  <span v-else class="text-muted">No icon selected</span>
                </div>
                <small class="text-muted d-block mt-1">Pick an icon for this category</small>
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
import 'bootstrap-icons/font/bootstrap-icons.css'

const API = 'categories' // base endpoint

// Curated Bootstrap Icons (add/remove as you like)
const biNames = [
  'bag','basket','cart','shop','shop-window','tag','ticket-perforated',
  'cash-coin','credit-card','qr-code','barcode',
  'box','boxes','box-seam','truck','truck-flatbed','airplane',
  'house','house-door','geo-alt','map','compass','pin-map','pin',
  'alarm','calendar','clock','hourglass','stopwatch',
  'funnel','sliders','filter','sort-alpha-down','sort-alpha-up','search','zoom-in','zoom-out',
  'upload','download','cloud-upload','cloud-download','link-45deg','unlock','lock',
  'shield','shield-check','flag','info-circle','question-circle','exclamation-circle',
  'star','heart','hand-thumbs-up','hand-thumbs-down','check-circle','x-circle','trash',
  'pencil-square','eraser','files','file-earmark','clipboard','copy','save','folder2-open','images',
  'camera','image','play','pause','stop','music-note','mic',
  'printer','display','laptop','tablet','phone','device-hdd','controller','watch'
]

// Build select options with full class names
const makeBiOptions = () => [
  { value: '', text: 'None' },
  ...biNames.map(n => ({ value: `bi bi-${n}`, text: n.replace(/-/g, ' ') }))
];

export default {
  metaInfo: { title: 'Category' },

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

      categories: [],
      editmode: false,

      category: { id: '', name: '', code: '', icon: '' },

      // Bootstrap Icons options
      iconOptions: makeBiOptions(),
    }
  },

  computed: {
    columns() {
      return [
        { label: this.$t('Codecategorie'), field: 'code', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Namecategorie'), field: 'name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Icon'), field: 'icon', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Action'), field: 'actions', sortable: false, tdClass: 'text-left', thClass: 'text-left' }
      ]
    }
  },

  methods: {
    // Helpers
    getState({ dirty, validated, valid = null }) { return dirty || validated ? valid : null },
    toast(variant, msg, title) { this.$root.$bvToast.toast(msg, { title, variant, solid: true }) },
    updateParams(patch) { this.serverParams = { ...this.serverParams, ...patch } },

    // Table events
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage })
        this.fetchCategories()
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage
        this.updateParams({ page: 1, perPage: currentPerPage })
        this.fetchCategories()
      }
    },
    onSortChange(params) {
      const s = params[0] || { field: 'id', type: 'desc' }
      this.updateParams({ sort: { field: s.field, type: s.type } })
      this.fetchCategories()
    },
    onSearch({ searchTerm }) {
      this.search = searchTerm
      this.updateParams({ page: 1 })
      this.fetchCategories()
    },
    selectionChanged({ selectedRows }) {
      this.selectedIds = selectedRows.map(r => r.id)
    },

    // CRUD
    openCreate() {
      this.resetForm()
      this.editmode = false
      this.$bvModal.show('New_Category')
    },

    async openEdit(row) {
      this.resetForm()
      this.editmode = true
      // Fetch full category (we need 'code' which is not in the list view)
      try {
        NProgress.start(); NProgress.set(0.1)
        const { data } = await axios.get(`${API}/${row.id}`)
        this.category = {
          id: data.id,
          name: data.name || '',
          code: data.code || '',
          icon: data.icon || ''
        }
      } catch (e) {
        this.toast('danger', this.$t('InvalidData'), this.$t('Failed'))
        return
      } finally {
        NProgress.done()
      }
      this.$bvModal.show('New_Category')
    },

    async fetchCategories() {
      NProgress.start(); NProgress.set(0.1)
      const { page, perPage, sort } = this.serverParams
      try {
        const { data } = await axios.get(
          `${API}?page=${page}&SortField=${sort.field}&SortType=${sort.type}&search=${encodeURIComponent(this.search)}&limit=${this.limit}`
        )
        this.categories = data.categories || []
        this.totalRows = data.totalRows || 0
      } catch (e) {
        // noop
      } finally {
        NProgress.done(); this.isLoading = false
      }
    },

    async submitCategory() {
      const ok = await this.$refs.CategoryForm.validate()
      if (!ok) {
        this.toast('danger', this.$t('Please_fill_the_form_correctly'), this.$t('Failed'))
        return
      }
      this.submitProcessing = true
      try {
        if (this.editmode) {
          await axios.put(`${API}/${this.category.id}`, {
            name: this.category.name,
            code: this.category.code,
            icon: this.category.icon || ''
          })
          this.toast('success', this.$t('Successfully_Updated'), this.$t('Success'))
        } else {
          await axios.post(API, {
            name: this.category.name,
            code: this.category.code,
            icon: this.category.icon || ''
          })
          this.toast('success', this.$t('Successfully_Created'), this.$t('Success'))
        }
        this.$bvModal.hide('New_Category')
        this.fetchCategories()
      } catch (e) {
        this.toast('danger', this.$t('InvalidData'), this.$t('Failed'))
      } finally {
        this.submitProcessing = false
      }
    },

    resetForm() {
      this.category = { id: '', name: '', code: '', icon: '' }
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
        confirmButtonText: this.$t('Delete_confirmButtonText')
      })
      if (!res.value) return
      try {
        await axios.delete(`${API}/${id}`)
        await this.$swal(this.$t('Delete_Deleted'), this.$t('Deleted_in_successfully'), 'success')
        this.fetchCategories()
      } catch (e) {
        this.$swal(this.$t('Delete_Failed'), this.$t('Delete_Therewassomethingwronge'), 'warning')
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
        confirmButtonText: this.$t('Delete_confirmButtonText')
      })
      if (!res.value) return

      NProgress.start(); NProgress.set(0.1)
      try {
        await axios.post(`${API}/delete/by_selection`, { selectedIds: this.selectedIds })
        await this.$swal(this.$t('Delete_Deleted'), this.$t('Deleted_in_successfully'), 'success')
        this.fetchCategories()
      } catch (e) {
        this.$swal(this.$t('Delete_Failed'), this.$t('Delete_Therewassomethingwronge'), 'warning')
      } finally {
        NProgress.done()
      }
    }
  },

  created() {
    this.fetchCategories()

    // Event bus hooks
    Fire.$on('Event_Category', () => {
      this.$bvModal.hide('New_Category')
      this.fetchCategories()
    })
    Fire.$on('Delete_Category', () => {
      this.fetchCategories()
    })
  }
}
</script>