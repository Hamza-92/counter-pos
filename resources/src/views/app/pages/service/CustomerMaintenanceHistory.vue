<template>
  <div class="main-content">
    <breadcumb :page="$t('Customer_Maintenance_History')" :folder="$t('Service_Maintenance')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <b-row class="mb-3">
        <b-col md="4">
          <b-form-group :label="$t('Customer')">
            <v-select
              :reduce="c => c.id"
              v-model="filters.client_id"
              :options="clients"
              label="name"
              :placeholder="$t('Choose_Customer')"
              @input="fetchHistory"
            />
          </b-form-group>
        </b-col>
        <b-col md="3">
          <b-form-group :label="$t('From')">
            <b-form-input v-model="filters.from" type="date" @change="fetchHistory" />
          </b-form-group>
        </b-col>
        <b-col md="3">
          <b-form-group :label="$t('To')">
            <b-form-input v-model="filters.to" type="date" @change="fetchHistory" />
          </b-form-group>
        </b-col>
      </b-row>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        :pagination-options="{ enabled: true, mode: 'records' }"
        styleClass="tableOne vgt-table"
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'CustomerMaintenanceHistory',
  data() {
    return {
      isLoading: true,
      rows: [],
      totalRows: 0,
      clients: [],
      serverParams: {
        page: 1,
        perPage: 10
      },
      filters: {
        client_id: null,
        from: '',
        to: ''
      },
      columns: [
        { label: this.$t('date'), field: 'scheduled_date' },
        { label: this.$t('Customer'), field: 'client_name' },
        { label: this.$t('Technician'), field: 'technician_name' },
        { label: this.$t('Service_Item'), field: 'service_item' },
        { label: this.$t('Job_Type'), field: 'job_type' },
        { label: this.$t('Status'), field: 'status' }
      ]
    };
  },
  async mounted() {
    await this.fetchHistory();
    this.isLoading = false;
  },
  methods: {
    async fetchHistory() {
      const params = {
        page: this.serverParams.page,
        limit: this.serverParams.perPage,
        client_id: this.filters.client_id,
        from: this.filters.from,
        to: this.filters.to
      };
      const { data } = await axios.get('report/customer_maintenance_history', {
        params
      });
      this.rows = data.jobs || [];
      this.totalRows = data.totalRows || 0;
      this.clients = (data.clients || []).map(c => ({ id: c.id, name: c.name }));
    },
    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.fetchHistory();
    },
    onPerPageChange({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage;
      this.fetchHistory();
    }
  }
};
</script>

















