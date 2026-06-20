<template>
  <div class="main-content">
    <breadcumb :page="$t('Service_Jobs_Report')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else class="print-table-only">
      <b-row class="mb-3">
        <b-col md="4">
          <b-form-group :label="$t('Customer')">
            <v-select
              :reduce="c => c.id"
              v-model="filters.client_id"
              :options="customers"
              label="name"
              :placeholder="$t('Choose_Customer')"
              @input="getReport(1)"
            />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group :label="$t('Technician')">
            <v-select
              :reduce="t => t.id"
              v-model="filters.technician_id"
              :options="technicians"
              label="full_name"
              :placeholder="$t('Choose_Technician')"
              @input="getReport(1)"
            />
          </b-form-group>
        </b-col>
        <b-col md="2">
          <b-form-group :label="$t('From')">
            <b-form-input v-model="filters.from" type="date" @change="getReport(1)" />
          </b-form-group>
        </b-col>
        <b-col md="2">
          <b-form-group :label="$t('To')">
            <b-form-input v-model="filters.to" type="date" @change="getReport(1)" />
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
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
export default {
  name: 'ServiceJobsReport',
  data() {
    return {
      isLoading: true,
      rows: [],
      totalRows: 0,
      customers: [],
      technicians: [],
      serverParams: {
        page: 1,
        perPage: 10
      },
      filters: {
        client_id: null,
        technician_id: null,
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
    await this.getReport(1);
    this.isLoading = false;
  },
  methods: {
    async getReport(page) {
      if (page) this.serverParams.page = page;
      const params = {
        page: this.serverParams.page,
        limit: this.serverParams.perPage,
        client_id: this.filters.client_id,
        technician_id: this.filters.technician_id,
        from: this.filters.from,
        to: this.filters.to
      };
      const { data } = await axios.get('report/service_jobs', { params });
      this.rows = data.rows || [];
      this.totalRows = data.totalRows || 0;
      this.customers = (data.clients || []).map(c => ({ id: c.id, name: c.name }));
      this.technicians = (data.technicians || []).map(t => ({
        id: t.id,
        full_name: t.name || `#${t.id}`
      }));
    },
    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.getReport();
    },
    onPerPageChange({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage;
      this.getReport();
    },

    //------ Print Table Only
    printTableOnly() {
      const root = this.$el;
      if (!root) {
        window.print();
        return;
      }

      const tableCard = root.querySelector(".print-table-only");
      if (!tableCard) {
        window.print();
        return;
      }

      // Get rows data
      const rowsData = this.rows || [];

      // Manually construct the table HTML from rows data
      let tableHtml = `<table class="vgt-table table table-hover tableOne">`;

      // Table Header
      tableHtml += `<thead><tr>`;
      this.columns.forEach(col => {
        tableHtml += `<th class="text-left">${col.label}</th>`;
      });
      tableHtml += `</tr></thead>`;

      // Table Body
      tableHtml += `<tbody>`;
      rowsData.forEach(row => {
        tableHtml += `<tr>`;
        this.columns.forEach(col => {
          let cellContent = row[col.field] || '';
          tableHtml += `<td class="text-left">${cellContent}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;
      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("Service_Jobs_Report")}`;
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
        .join("\n");

      const inlineStyles = Array.from(document.querySelectorAll("style"))
        .filter(s => !((s.textContent || "").includes("@media print")))
        .map(s => s.outerHTML)
        .join("\n");

      const doc = w.document;
      doc.open();
      doc.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <base href="${window.location.origin}/" />
    <title>${title}</title>
    ${links}
    ${inlineStyles}
    <style>
      @media print { body, body * { visibility: visible !important; } }
      body { margin: 0.3cm; }
      .print-header { font-weight: 600; margin-bottom: 8px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f2f2f2; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    ${tableHtml}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    }
  }
};
</script>


