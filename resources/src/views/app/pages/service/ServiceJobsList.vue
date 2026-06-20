<template>
  <div class="main-content">
    <breadcumb :page="$t('Service_Jobs')" :folder="$t('Service_Maintenance')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <!-- Filters -->
      <div class="filter-bar mb-3">
        <b-row>
          <b-col md="3">
            <b-form-group :label="$t('Status')" class="mb-0">
              <b-form-select v-model="filters.status" :options="statusFilterOptions" size="sm" @change="applyFilters" />
            </b-form-group>
          </b-col>
          <b-col md="3">
            <b-form-group :label="$t('Payment_Status') || 'Payment'" class="mb-0">
              <b-form-select v-model="filters.payment_status" :options="paymentFilterOptions" size="sm" @change="applyFilters" />
            </b-form-group>
          </b-col>
          <b-col md="3">
            <b-form-group :label="$t('From') || 'From'" class="mb-0">
              <b-form-input v-model="filters.from" type="date" size="sm" @change="applyFilters" />
            </b-form-group>
          </b-col>
          <b-col md="3">
            <b-form-group :label="$t('To') || 'To'" class="mb-0">
              <b-form-input v-model="filters.to" type="date" size="sm" @change="applyFilters" />
            </b-form-group>
          </b-col>
        </b-row>
      </div>

      <div class="control-bar mb-3 d-flex justify-content-between">
        <div>
          <b-button v-if="hasActiveFilters" variant="outline-secondary" size="sm" @click="clearFilters">
            <lucide-icon class="mr-1" name="x" /> {{ $t('Clear_Filters') || 'Clear filters' }}
          </b-button>
        </div>
        <div>
          <router-link to="/app/service/jobs/create" class="btn btn-primary btn-sm">
            <lucide-icon class="mr-1" name="plus" />{{ $t('Add') }}
          </router-link>
        </div>
      </div>

      <div class="table-card">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="jobs"
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
              <router-link
                :to="`/app/service/jobs/details/${props.row.id}`"
                class="btn btn-sm btn-outline-info mr-2"
                :title="$t('View_Details') || 'View Details'"
              >
                <lucide-icon name="eye" />
              </router-link>
              <router-link
                :to="`/app/service/jobs/edit/${props.row.id}`"
                class="btn btn-sm btn-outline-primary mr-2"
                :title="$t('Edit') || 'Edit'"
              >
                <lucide-icon name="pencil" />
              </router-link>
              <b-button
                size="sm"
                variant="outline-danger"
                @click.stop="deleteJob(props.row)"
                :title="$t('Delete') || 'Delete'"
              >
                <lucide-icon name="x" />
              </b-button>
            </span>
            <span v-else-if="props.column.field === 'device'">
              <span v-if="props.row.device_brand || props.row.device_model">
                {{ props.row.device_brand }} {{ props.row.device_model }}
              </span>
              <span v-else class="text-muted">{{ props.row.service_item || '-' }}</span>
            </span>
            <span v-else-if="props.column.field === 'status'">
              <span class="badge" :class="statusBadgeClass(props.row.status)">
                {{ statusLabel(props.row.status) }}
              </span>
            </span>
            <span v-else-if="props.column.field === 'payment_status'">
              <span class="badge" :class="paymentBadgeClass(props.row.payment_status)">
                {{ $t(props.row.payment_status || 'unpaid') }}
              </span>
            </span>
            <span v-else-if="props.column.field === 'total_amount'" class="text-right">
              {{ formatNumber(props.row.total_amount) }}
            </span>
            <span v-else-if="props.column.field === 'balance_due'" class="text-right">
              <span :class="props.row.balance_due > 0 ? 'text-danger' : 'text-success'">
                {{ formatNumber(props.row.balance_due) }}
              </span>
            </span>
          </template>
        </vue-good-table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ServiceJobsList',
  data() {
    return {
      isLoading: true,
      jobs: [],
      totalRows: 0,
      serverParams: {
        sort: { field: 'Ref', type: 'desc' },
        page: 1,
        perPage: 10,
        searchTerm: ''
      },
      filters: {
        status: '',
        payment_status: '',
        from: '',
        to: ''
      },
      statusFilterOptions: [
        { value: '', text: 'All' },
        { value: 'pending', text: 'Pending' },
        { value: 'intake', text: 'Intake' },
        { value: 'diagnostic', text: 'Diagnostic' },
        { value: 'quoted', text: 'Quoted' },
        { value: 'approved', text: 'Approved' },
        { value: 'in_progress', text: 'In Progress' },
        { value: 'ready', text: 'Ready for Pickup' },
        { value: 'delivered', text: 'Delivered' },
        { value: 'declined', text: 'Declined' },
        { value: 'completed', text: 'Completed' },
        { value: 'cancelled', text: 'Cancelled' }
      ],
      paymentFilterOptions: [
        { value: '', text: 'All' },
        { value: 'unpaid', text: 'Unpaid' },
        { value: 'partial', text: 'Partial' },
        { value: 'paid', text: 'Paid' }
      ],
      columns: [
        { label: this.$t('Reference') || 'Reference', field: 'Ref' },
        { label: this.$t('Customer'), field: 'client_name' },
        { label: this.$t('Device') || 'Device / Item', field: 'device', sortable: false },
        { label: this.$t('Technician'), field: 'technician_name' },
        { label: this.$t('Scheduled_Date'), field: 'scheduled_date' },
        { label: this.$t('Status'), field: 'status' },
        { label: this.$t('Payment') || 'Payment', field: 'payment_status' },
        { label: this.$t('Total'), field: 'total_amount', type: 'number' },
        { label: this.$t('Balance_Due') || 'Balance', field: 'balance_due', type: 'number', sortable: false },
        { label: this.$t('Actions'), field: 'actions', sortable: false }
      ]
    };
  },
  computed: {
    hasActiveFilters() {
      return !!(this.filters.status || this.filters.payment_status || this.filters.from || this.filters.to);
    }
  },
  watch: {
    '$route.query'() {
      this.applyQueryFilters();
    }
  },
  mounted() {
    this.applyQueryFilters();
  },
  methods: {
    async fetchJobs() {
      this.isLoading = true;
      const params = {
        page: this.serverParams.page,
        limit: this.serverParams.perPage,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.serverParams.searchTerm
      };
      if (this.filters.status) params.status = this.filters.status;
      if (this.filters.payment_status) params.payment_status = this.filters.payment_status;
      if (this.filters.from) params.from = this.filters.from;
      if (this.filters.to) params.to = this.filters.to;

      try {
        const { data } = await axios.get('service_jobs', { params });
        this.jobs = data.jobs;
        this.totalRows = data.totalRows;
      } finally {
        this.isLoading = false;
      }
    },
    applyFilters() {
      this.serverParams.page = 1;
      this.fetchJobs();
    },
    applyQueryFilters() {
      const q = this.$route.query || {};
      this.filters.status = q.status || '';
      this.filters.payment_status = q.payment_status || '';
      this.filters.from = q.from || '';
      this.filters.to = q.to || '';
      this.applyFilters();
    },
    clearFilters() {
      this.filters = { status: '', payment_status: '', from: '', to: '' };
      // strip query params from URL too
      if (Object.keys(this.$route.query).length > 0) {
        this.$router.replace({ path: this.$route.path });
      } else {
        this.applyFilters();
      }
    },
    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.fetchJobs();
    },
    onPerPageChange({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage;
      this.fetchJobs();
    },
    onSortChange(params) {
      this.serverParams.sort.field = params[0].field;
      this.serverParams.sort.type = params[0].type;
      this.fetchJobs();
    },
    onSearch(params) {
      this.serverParams.searchTerm = params.searchTerm;
      this.fetchJobs();
    },
    async deleteJob(row) {
      const ok = await this.$bvModal.msgBoxConfirm(this.$t('AreYouSure'), { size: 'sm' });
      if (!ok) return;
      try {
        await axios.delete(`service_jobs/${row.id}`);
        this.makeToast('success', this.$t('Deleted_in_successfully'), this.$t('Success'));
        await this.fetchJobs();
      } catch (error) {
        const errorMsg = error.response?.data?.message || error.message || this.$t('InvalidData');
        this.makeToast('danger', errorMsg, this.$t('Failed'));
      }
    },
    statusBadgeClass(s) {
      const map = {
        delivered: 'badge-outline-success',
        ready: 'badge-outline-success',
        completed: 'badge-outline-success',
        approved: 'badge-outline-primary',
        in_progress: 'badge-outline-primary',
        quoted: 'badge-outline-info',
        diagnostic: 'badge-outline-info',
        intake: 'badge-outline-info',
        pending: 'badge-outline-warning',
        declined: 'badge-outline-danger',
        cancelled: 'badge-outline-danger'
      };
      return map[s] || 'badge-outline-secondary';
    },
    statusLabel(s) {
      if (!s) return '-';
      const map = {
        pending: this.$t('Pending'),
        intake: this.$t('Intake') || 'Intake',
        diagnostic: this.$t('Diagnostic') || 'Diagnostic',
        quoted: this.$t('Quoted') || 'Quoted',
        approved: this.$t('Approved') || 'Approved',
        in_progress: this.$t('In_Progress'),
        ready: this.$t('Ready') || 'Ready for Pickup',
        delivered: this.$t('Delivered') || 'Delivered',
        declined: this.$t('Declined') || 'Declined',
        completed: this.$t('complete'),
        cancelled: this.$t('Cancelled')
      };
      return map[s] || s;
    },
    paymentBadgeClass(s) {
      if (s === 'paid') return 'badge-outline-success';
      if (s === 'partial') return 'badge-outline-warning';
      return 'badge-outline-danger';
    },
    formatNumber(n) {
      const v = Number(n) || 0;
      return v.toFixed(2);
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    }
  }
};
</script>

<style scoped>
.filter-bar {
  background: #f6f8fb;
  border-radius: 8px;
  padding: 12px 16px;
}
</style>

<!-- Non-scoped dark-mode override. The scoped block above pins the
     filter bar to a light gray bg with [data-v-xxxx] specificity that
     beats the global theme rules. Repaint here so dark mode reaches
     it. Other surfaces on this page (vgt-table, b-form-select,
     b-button, badge-outline-*, btn-outline-*) are already covered by
     the global _dark.scss rules. -->
<style>
.dark-theme .filter-bar {
  background: #232323;
}
</style>
