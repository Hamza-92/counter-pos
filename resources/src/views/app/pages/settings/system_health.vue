<template>
  <div class="main-content">
    <breadcumb :page="$t('System_Health')" :folder="$t('Settings')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-if="!isLoading" class="system-health-card">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ $t('System_Health') }}</h4>
        <div class="d-flex flex-wrap gap-2">
          <b-button variant="outline-primary" @click="refresh" :disabled="refreshing">
            <lucide-icon name="refresh-cw" />
            {{ refreshing ? $t('Refreshing') + '...' : $t('Refresh') }}
          </b-button>
          <b-button variant="primary" @click="downloadPdf" :disabled="pdfLoading">
            <lucide-icon name="download" />
            {{ pdfLoading ? $t('Loading') + '...' : $t('Download_Report_PDF') }}
          </b-button>
        </div>
      </div>

      <b-row>
        <b-col md="6" lg="4" class="mb-3">
          <b-card class="metric-card h-100">
            <div class="metric-icon-wrap text-primary">
              <lucide-icon name="code" />
            </div>
            <h6 class="text-muted text-uppercase mb-2">{{ $t('PHP_Version') }}</h6>
            <p class="metric-value mb-0">{{ metrics.php_version || '—' }}</p>
          </b-card>
        </b-col>
        <b-col md="6" lg="4" class="mb-3">
          <b-card class="metric-card h-100">
            <div class="metric-icon-wrap text-danger">
              <lucide-icon name="server" />
            </div>
            <h6 class="text-muted text-uppercase mb-2">{{ $t('Laravel_Version') }}</h6>
            <p class="metric-value mb-0">{{ metrics.laravel_version || '—' }}</p>
          </b-card>
        </b-col>
        <b-col md="6" lg="4" class="mb-3">
          <b-card class="metric-card h-100">
            <div class="metric-icon-wrap text-success">
              <lucide-icon name="activity" />
            </div>
            <h6 class="text-muted text-uppercase mb-2">{{ $t('Environment') }}</h6>
            <p class="metric-value mb-0">
              <b-badge :variant="envVariant">{{ metrics.environment || '—' }}</b-badge>
            </p>
          </b-card>
        </b-col>
        <b-col md="6" lg="4" class="mb-3">
          <b-card class="metric-card h-100">
            <div class="metric-icon-wrap text-info">
              <lucide-icon name="server" />
            </div>
            <h6 class="text-muted text-uppercase mb-2">{{ $t('Database_Size') }}</h6>
            <p class="metric-value mb-0">{{ (metrics.database && metrics.database.size_human) || (metrics.database && metrics.database.error) || '—' }}</p>
          </b-card>
        </b-col>
        <b-col md="6" lg="4" class="mb-3">
          <b-card class="metric-card h-100">
            <div class="metric-icon-wrap text-secondary">
              <lucide-icon name="database" />
            </div>
            <h6 class="text-muted text-uppercase mb-2">{{ $t('Storage_Usage') }}</h6>
            <p class="metric-value mb-0">{{ (metrics.storage && metrics.storage.size_human) || '—' }}</p>
          </b-card>
        </b-col>
        <b-col md="6" lg="4" class="mb-3">
          <b-card class="metric-card h-100">
            <div class="metric-icon-wrap text-warning">
              <lucide-icon name="activity" />
            </div>
            <h6 class="text-muted text-uppercase mb-2">{{ $t('Queue_Status') }}</h6>
            <p class="metric-value mb-0">
              <span v-if="metrics.queue">
                {{ metrics.queue.driver }} —
                <span v-if="metrics.queue.pending !== undefined">Pending: {{ metrics.queue.pending }}</span>
                <span v-if="metrics.queue.failed !== undefined">, Failed: {{ metrics.queue.failed }}</span>
                <span v-if="metrics.queue.error"> ({{ metrics.queue.error }})</span>
              </span>
              <span v-else>—</span>
            </p>
          </b-card>
        </b-col>
        <b-col md="6" lg="4" class="mb-3">
          <b-card class="metric-card h-100">
            <div class="metric-icon-wrap text-success">
              <lucide-icon name="database-backup" />
            </div>
            <h6 class="text-muted text-uppercase mb-2">{{ $t('Last_Backup_Date') }}</h6>
            <p class="metric-value mb-0">
              {{ (metrics.last_backup && metrics.last_backup.human) || (metrics.last_backup && metrics.last_backup.message) || '—' }}
            </p>
          </b-card>
        </b-col>
      </b-row>

      <div v-if="generatedAt" class="text-muted small mt-2">
        {{ $t('Last_updated') }}: {{ generatedAt }}
      </div>
    </b-card>
  </div>
</template>

<script>
export default {
  metaInfo: {
    title: 'System Health'
  },
  data() {
    return {
      isLoading: true,
      refreshing: false,
      pdfLoading: false,
      metrics: {},
      generatedAt: null
    };
  },
  computed: {
    envVariant() {
      const env = (this.metrics.environment || '').toLowerCase();
      if (env === 'production') return 'success';
      if (env === 'local' || env === 'development') return 'info';
      return 'secondary';
    }
  },
  created() {
    this.fetchMetrics();
  },
  methods: {
    fetchMetrics() {
      const setLoading = this.refreshing ? () => {} : (v) => { this.isLoading = v; };
      axios
        .get('system_health')
        .then(response => {
          if (response.data && response.data.success && response.data.data) {
            this.metrics = response.data.data;
            this.generatedAt = response.data.generated_at || null;
          }
        })
        .catch(error => {
          const msg = (error.response && error.response.data && (error.response.data.message || error.response.data.error)) || this.$t('InvalidData');
          this.makeToast('danger', msg, this.$t('Failed'));
        })
        .finally(() => {
          this.isLoading = false;
          this.refreshing = false;
        });
    },
    refresh() {
      this.refreshing = true;
      this.fetchMetrics();
    },
    downloadPdf() {
      this.pdfLoading = true;
      axios
        .get('system_health/pdf', { responseType: 'blob' })
        .then(response => {
          const blob = new Blob([response.data], { type: 'application/pdf' });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'system-health-report.pdf';
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);
          this.makeToast('success', this.$t('Success'), this.$t('Success'));
        })
        .catch(error => {
          const msg = (error.response && error.response.data) ? (typeof error.response.data === 'string' ? error.response.data : (error.response.data.message || error.response.data.error)) : this.$t('InvalidData');
          this.makeToast('danger', msg || this.$t('Failed'), this.$t('Failed'));
        })
        .finally(() => {
          this.pdfLoading = false;
        });
    }
  }
};
</script>

<style scoped>
.system-health-card {
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}
.metric-card {
  border-radius: 8px;
  border: 1px solid #e9ecef;
  transition: box-shadow 0.2s ease;
}
.metric-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.metric-icon-wrap {
  margin-bottom: 12px;
  font-size: 2rem;
  line-height: 1;
}
.metric-icon-wrap i {
  font-size: 2rem;
}
.metric-value {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
}
.gap-2 { gap: 0.5rem; }
</style>
