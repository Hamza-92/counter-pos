<template>
  <div class="main-content due-assets-page">
    <breadcumb :page="$t('Due_Assets')" :folder="$t('Assets')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <!-- Header & quick actions -->
      <div class="due-assets-header mb-4">
        <div class="row align-items-center">
          <div class="col-md-6">
            <h2 class="due-assets-title mb-1">{{ $t('Due_Assets') }}</h2>
            <p class="due-assets-subtitle text-muted mb-0">
              {{ $t('Due_assets_subtitle') || 'Assets due for validation within the next 5 working days or overdue' }}
            </p>
          </div>
          <div class="col-md-6 text-md-right mt-3 mt-md-0">
            <router-link to="/app/assets/list" class="btn btn-outline-primary btn-sm">
              <lucide-icon class="mr-1" name="files" />{{ $t('Assets_List') }}
            </router-link>
          </div>
        </div>
      </div>

      <!-- Summary stat cards -->
      <b-row class="due-assets-stats mb-4">
        <b-col md="4" sm="6" class="mb-3">
          <div class="due-stat-card due-stat-total">
            <div class="due-stat-icon"><lucide-icon name="clock" /></div>
            <div class="due-stat-body">
              <span class="due-stat-value">{{ assets.length }}</span>
              <span class="due-stat-label">{{ $t('Total_Due') || 'Total due' }}</span>
            </div>
          </div>
        </b-col>
        <b-col md="4" sm="6" class="mb-3">
          <div class="due-stat-card due-stat-overdue">
            <div class="due-stat-icon"><lucide-icon name="x" /></div>
            <div class="due-stat-body">
              <span class="due-stat-value">{{ overdueCount }}</span>
              <span class="due-stat-label">{{ $t('Overdue') || 'Overdue' }}</span>
            </div>
          </div>
        </b-col>
        <b-col md="4" sm="6" class="mb-3">
          <div class="due-stat-card due-stat-soon">
            <div class="due-stat-icon"><lucide-icon name="calendar-days" /></div>
            <div class="due-stat-body">
              <span class="due-stat-value">{{ dueSoonCount }}</span>
              <span class="due-stat-label">{{ $t('Due_soon') || 'Due soon' }}</span>
            </div>
          </div>
        </b-col>
      </b-row>

      <!-- Cron / Schedule configuration -->
      <div class="card due-cron-card mb-4 shadow-sm">
        <div class="card-header due-cron-header">
          <lucide-icon class="due-cron-header-icon" name="clock" />
          <div>
            <h5 class="mb-0">{{ $t('Cron_Schedule_Config') || 'Cron / Schedule configuration' }}</h5>
            <small class="text-white-50">{{ $t('Automatic_notifications_setup') || 'Set up automatic notifications' }}</small>
          </div>
        </div>
        <div class="card-body">
          <p class="due-cron-desc text-muted mb-3">
            {{ $t('Asset_validation_cron_description') || 'To send automatic notifications when assets are due for validation, the Laravel scheduler must run every minute. Add this line to your server crontab:' }}
          </p>
          <div class="form-group mb-3">
            <label class="due-label">{{ $t('Crontab_line') || 'Crontab line' }}</label>
            <div class="input-group due-cron-input-group">
              <input
                type="text"
                readonly
                class="form-control due-cron-input font-monospace"
                :value="scheduleInfo.cron_line"
              >
              <div class="input-group-append">
                <b-button variant="primary" size="sm" @click="copyCronLine" class="due-cron-copy-btn">
                  <lucide-icon class="mr-1" name="files" />{{ $t('Copy') || 'Copy' }}
                </b-button>
              </div>
            </div>
          </div>
          <p class="small text-muted mb-3">
            {{ $t('Asset_validation_schedule_detail') || 'The command "assets:check-validation-due" runs daily. It finds assets whose next validation is within 5 working days (or overdue) and sends email and in-app notifications to users with Assets permission.' }}
          </p>
          <b-button
            variant="outline-primary"
            size="sm"
            :disabled="runCheckLoading"
            @click="runValidationDueNow"
            class="due-run-btn"
          >
            <span v-if="runCheckLoading" class="spinner-border spinner-border-sm mr-1" role="status"></span>
            <lucide-icon class="mr-1" name="music" v-else />
            {{ $t('Run_check_now') || 'Run check now' }}
          </b-button>
        </div>
      </div>

      <!-- Due assets table -->
      <div class="card due-table-card shadow-sm">
        <div class="card-header due-table-header">
          <h5 class="mb-0">
            <lucide-icon class="mr-2" name="files" />{{ $t('Due_Assets') }} ({{ assets.length }})
          </h5>
        </div>
        <div class="card-body p-0">
          <!-- Empty state -->
          <div v-if="assets.length === 0" class="due-empty-state">
            <div class="due-empty-icon"><lucide-icon name="check" /></div>
            <h5 class="due-empty-title">{{ $t('All_caught_up') || 'All caught up' }}</h5>
            <p class="due-empty-text text-muted">
              {{ $t('No_assets_due_for_validation') || 'No assets due for validation within the next 5 working days.' }}
            </p>
            <router-link to="/app/assets/list" class="btn btn-outline-primary btn-sm mt-2">
              {{ $t('View_all_assets') || 'View all assets' }}
            </router-link>
          </div>

          <vue-good-table
            v-else
            :columns="columns"
            :rows="assets"
            :pagination-options="{ enabled: true, perPage: 10, perPageDropdown: [10, 20, 50] }"
            styleClass="tableOne vgt-table due-assets-table">

            <template slot="table-row" slot-scope="props">
              <span v-if="props.column.field == 'next_validation'">
                <span :class="getValidationBadgeClass(props)">
                  {{ getValidationBadgeLabel(props) }}
                </span>
                <span class="due-date-text">{{ props.formattedRow[props.column.field] || '—' }}</span>
              </span>
              <span v-else-if="props.column.field == 'actions'">
                <router-link :to="'/app/assets/edit/' + props.row.id" class="btn btn-sm btn-primary due-edit-btn">
                  <lucide-icon name="pen" /> {{ $t('Edit') }}
                </router-link>
              </span>
              <span v-else>{{ props.formattedRow[props.column.field] }}</span>
            </template>

          </vue-good-table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DueAssets',
  data() {
    return {
      isLoading: true,
      assets: [],
      scheduleInfo: {
        cron_line: '',
        base_path: '',
        command: '',
        schedule: '',
        description: ''
      },
      runCheckLoading: false,
      columns: [
        { label: this.$t('Tag'), field: 'tag' },
        { label: this.$t('Name'), field: 'name' },
        { label: this.$t('Category'), field: 'asset_category_name' },
        { label: this.$t('Serial'), field: 'serial_number' },
        { label: this.$t('Status'), field: 'status' },
        { label: this.$t('Warehouse'), field: 'warehouse_name' },
        { label: this.$t('Last_Verification'), field: 'last_verification' },
        { label: this.$t('Next_Validation'), field: 'next_validation' },
        { label: this.$t('Actions'), field: 'actions', sortable: false }
      ]
    }
  },
  computed: {
    overdueCount() {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      return this.assets.filter(a => {
        if (!a.next_validation) return false;
        const d = new Date(a.next_validation);
        d.setHours(0, 0, 0, 0);
        return d < today;
      }).length;
    },
    dueSoonCount() {
      return this.assets.length - this.overdueCount;
    }
  },
  mounted() {
    this.getDueAssets();
    this.getScheduleInfo();
  },
  methods: {
    async getScheduleInfo() {
      try {
        const { data } = await axios.get('assets/schedule-info');
        this.scheduleInfo = data;
      } catch (e) {
        this.scheduleInfo.cron_line = '* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1';
      }
    },
    copyCronLine() {
      const line = this.scheduleInfo.cron_line || '';
      if (!line) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(line).then(() => {
          this.makeToast('success', this.$t('Copied') || 'Copied to clipboard', this.$t('Success') || 'Success');
        }).catch(() => this.fallbackCopy(line));
      } else {
        this.fallbackCopy(line);
      }
    },
    fallbackCopy(text) {
      const el = document.createElement('textarea');
      el.value = text;
      document.body.appendChild(el);
      el.select();
      document.execCommand('copy');
      document.body.removeChild(el);
      this.makeToast('success', this.$t('Copied') || 'Copied to clipboard', this.$t('Success') || 'Success');
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast && this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    },
    async runValidationDueNow() {
      this.runCheckLoading = true;
      try {
        const { data } = await axios.post('assets/run-validation-due');
        this.makeToast('success', data.message || 'Check completed.', this.$t('Success') || 'Success');
      } catch (e) {
        const msg = (e.response && e.response.data && e.response.data.message) || e.message || 'Request failed';
        this.makeToast('danger', msg, this.$t('Failed') || 'Failed');
      } finally {
        this.runCheckLoading = false;
      }
    },
    async getDueAssets() {
      this.isLoading = true;
      try {
        const { data } = await axios.get('assets/due');
        this.assets = data.assets || [];
      } finally {
        this.isLoading = false;
      }
    },
    getValidationBadgeClass(props) {
      if (props.column.field !== 'next_validation' || !props.row.next_validation) return '';
      const d = new Date(props.row.next_validation);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      d.setHours(0, 0, 0, 0);
      if (d < today) return 'due-badge due-badge-overdue';
      return 'due-badge due-badge-soon';
    },
    getValidationBadgeLabel(props) {
      if (props.column.field !== 'next_validation' || !props.row.next_validation) return '';
      const d = new Date(props.row.next_validation);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      d.setHours(0, 0, 0, 0);
      if (d < today) return this.$t('Overdue') || 'Overdue';
      return this.$t('Due_soon') || 'Due soon';
    }
  }
}
</script>

<style scoped>
.due-assets-page .page-wrapper {
  max-width: 100%;
}

.due-assets-header {
  padding: 0;
}

.due-assets-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1a1a2e;
}

.due-assets-subtitle {
  font-size: 0.9rem;
}

/* Stat cards */
.due-assets-stats {
  margin-left: -0.5rem;
  margin-right: -0.5rem;
}

.due-stat-card {
  display: flex;
  align-items: center;
  padding: 1.25rem;
  border-radius: 12px;
  border: 1px solid #e9ecef;
  background: #fff;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.due-stat-card:hover {
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

.due-stat-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 1rem;
  flex-shrink: 0;
}

.due-stat-icon i {
  font-size: 1.5rem;
  color: #fff;
}

.due-stat-total .due-stat-icon { background: linear-gradient(135deg, #5b6bf0 0%, #7c8aff 100%); }
.due-stat-overdue .due-stat-icon { background: linear-gradient(135deg, #e74c3c 0%, #ff6b6b 100%); }
.due-stat-soon .due-stat-icon { background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%); }

.due-stat-body {
  display: flex;
  flex-direction: column;
}

.due-stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1a1a2e;
  line-height: 1.2;
}

.due-stat-label {
  font-size: 0.8rem;
  color: #6c757d;
  margin-top: 2px;
}

/* Cron card */
.due-cron-card {
  border-radius: 12px;
  border: 1px solid #e9ecef;
  overflow: hidden;
}

.due-cron-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
  color: #fff;
  padding: 1rem 1.25rem;
  border: none;
}

.due-cron-header-icon {
  font-size: 1.5rem;
  opacity: 0.9;
}

.due-cron-header small {
  font-size: 0.8rem;
}

.due-cron-card .card-body {
  padding: 1.25rem;
}

.due-cron-desc {
  font-size: 0.9rem;
  line-height: 1.5;
}

.due-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #495057;
  margin-bottom: 0.35rem;
}

.due-cron-input-group .form-control {
  font-size: 0.85rem;
  border-radius: 8px 0 0 8px;
}

.due-cron-copy-btn {
  border-radius: 0 8px 8px 0;
}

.due-run-btn {
  border-radius: 8px;
}

/* Table card */
.due-table-card {
  border-radius: 12px;
  border: 1px solid #e9ecef;
  overflow: hidden;
}

.due-table-header {
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  padding: 1rem 1.25rem;
  font-weight: 600;
}

.due-table-header h5 {
  font-size: 1rem;
  color: #1a1a2e;
}

/* Empty state */
.due-empty-state {
  text-align: center;
  padding: 3rem 2rem;
}

.due-empty-icon {
  width: 72px;
  height: 72px;
  margin: 0 auto 1rem;
  border-radius: 50%;
  background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.due-empty-icon i {
  font-size: 2rem;
  color: #155724;
}

.due-empty-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1a1a2e;
  margin-bottom: 0.5rem;
}

.due-empty-text {
  font-size: 0.9rem;
  max-width: 400px;
  margin: 0 auto;
}

/* Table badges & actions */
.due-date-text {
  display: block;
  font-size: 0.8rem;
  color: #6c757d;
  margin-top: 2px;
}

.due-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.due-badge-overdue {
  background: #fee2e2;
  color: #b91c1c;
}

.due-badge-soon {
  background: #fef3c7;
  color: #b45309;
}

.due-edit-btn {
  border-radius: 6px;
}

/* Table wrapper padding for vue-good-table */
.due-table-card >>> .vgt-wrap {
  padding: 0 1rem 1rem;
}

.due-table-card >>> .vgt-inner-wrap {
  box-shadow: none;
  border: none;
}
</style>
