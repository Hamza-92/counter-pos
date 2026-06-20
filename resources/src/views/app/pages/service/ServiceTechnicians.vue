<template>
  <div class="main-content">
    <breadcumb :page="$t('Service_Technicians')" :folder="$t('Service_Maintenance')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <b-row>
        <b-col md="4">
          <b-card :title="$t('Technician_Details')">
            <b-form @submit.prevent="saveTechnician">
              <b-form-group :label="$t('Name')">
                <b-form-input v-model="form.name" required />
              </b-form-group>
              <b-form-group :label="$t('Phone')">
                <b-form-input v-model="form.phone" />
              </b-form-group>
              <b-form-group :label="$t('Email')">
                <b-form-input v-model="form.email" type="email" />
              </b-form-group>
              <b-form-group :label="$t('Notes')">
                <b-form-textarea v-model="form.notes" rows="2" />
              </b-form-group>
              <b-form-group :label="$t('Status')">
                <b-form-checkbox v-model="form.is_active" switch>
                  {{ form.is_active ? $t('Actif') : $t('Inactif') }}
                </b-form-checkbox>
              </b-form-group>
              <div class="text-right">
                <b-button size="sm" variant="secondary" class="mr-2" @click="resetForm">
                  {{ $t('Reset') }}
                </b-button>
                <b-button size="sm" type="submit" variant="primary">
                  {{ $t('Save') }}
                </b-button>
              </div>
            </b-form>
          </b-card>
        </b-col>

        <b-col md="8">
          <b-card :title="$t('Service_Technicians')">
            <vue-good-table
              mode="remote"
              :columns="columns"
              :totalRows="totalRows"
              :rows="technicians"
              @on-page-change="onPageChange"
              @on-per-page-change="onPerPageChange"
              @on-sort-change="onSortChange"
              @on-search="onSearch"
              :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
              :pagination-options="{ enabled: true, mode: 'records' }"
              styleClass="tableOne vgt-table"
            >
              <template slot="table-row" slot-scope="props">
                <span v-if="props.column.field === 'actions'">
                  <b-button
                    size="sm"
                    variant="outline-primary"
                    class="mr-1"
                    @click="editTechnician(props.row)"
                  >
                    <lucide-icon name="pencil" />
                  </b-button>
                  <b-button
                    size="sm"
                    variant="outline-danger"
                    @click="removeTechnician(props.row)"
                  >
                    <lucide-icon name="x" />
                  </b-button>
                </span>
                <span v-else-if="props.column.field === 'is_active'">
                  <span
                    v-if="props.row.is_active"
                    class="badge badge-outline-success"
                  >
                    {{ $t('Actif') }}
                  </span>
                  <span
                    v-else
                    class="badge badge-outline-danger"
                  >
                    {{ $t('Inactif') }}
                  </span>
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
  name: 'ServiceTechnicians',
  data() {
    return {
      isLoading: true,
      technicians: [],
      totalRows: 0,
      serverParams: {
        sort: { field: 'id', type: 'desc' },
        page: 1,
        perPage: 10,
        searchTerm: ''
      },
      form: {
        id: null,
        name: '',
        phone: '',
        email: '',
        notes: '',
        is_active: true
      },
      columns: [
        { label: this.$t('Name'), field: 'name' },
        { label: this.$t('Phone'), field: 'phone' },
        { label: this.$t('Email'), field: 'email' },
        { label: this.$t('Status'), field: 'is_active' },
        { label: this.$t('Actions'), field: 'actions', sortable: false }
      ]
    };
  },
  async mounted() {
    await this.fetchTechnicians();
    this.isLoading = false;
  },
  methods: {
    async fetchTechnicians() {
      const params = {
        page: this.serverParams.page,
        limit: this.serverParams.perPage,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.serverParams.searchTerm
      };
      const { data } = await axios.get('service_technicians', { params });
      this.technicians = data.technicians || [];
      this.totalRows = data.totalRows || 0;
    },
    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.fetchTechnicians();
    },
    onPerPageChange({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage;
      this.fetchTechnicians();
    },
    onSortChange(params) {
      this.serverParams.sort.field = params[0].field;
      this.serverParams.sort.type = params[0].type;
      this.fetchTechnicians();
    },
    onSearch(params) {
      this.serverParams.searchTerm = params.searchTerm;
      this.fetchTechnicians();
    },
    resetForm() {
      this.form = {
        id: null,
        name: '',
        phone: '',
        email: '',
        notes: '',
        is_active: true
      };
    },
    editTechnician(row) {
      this.form = {
        id: row.id,
        name: row.name,
        phone: row.phone,
        email: row.email,
        notes: row.notes,
        is_active: !!row.is_active
      };
    },
    async saveTechnician() {
      const payload = {
        name: this.form.name,
        phone: this.form.phone,
        email: this.form.email,
        notes: this.form.notes,
        is_active: this.form.is_active
      };
      if (this.form.id) {
        await axios.put(`service_technicians/${this.form.id}`, payload);
      } else {
        await axios.post('service_technicians', payload);
      }
      this.resetForm();
      await this.fetchTechnicians();
    },
    async removeTechnician(row) {
      const ok = await this.$bvModal.msgBoxConfirm(this.$t('AreYouSure'), {
        size: 'sm'
      });
      if (!ok) return;
      await axios.delete(`service_technicians/${row.id}`);
      await this.fetchTechnicians();
    }
  }
};
</script>

















