<template>
  <div class="main-content">
    <breadcumb :page="$t('Checklist_Completion_Report')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else>
      <b-row class="mb-3">
        <b-col md="4">
          <b-form-group :label="$t('Customer')">
            <v-select
              :reduce="c => c.id"
              v-model="filters.client_id"
              :options="customers"
              label="name"
              :placeholder="$t('Choose_Customer')"
              @input="getReport"
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
              @input="getReport"
            />
          </b-form-group>
        </b-col>
        <b-col md="2">
          <b-form-group :label="$t('From')">
            <b-form-input v-model="filters.from" type="date" @change="getReport" />
          </b-form-group>
        </b-col>
        <b-col md="2">
          <b-form-group :label="$t('To')">
            <b-form-input v-model="filters.to" type="date" @change="getReport" />
          </b-form-group>
        </b-col>
      </b-row>

      <vue-good-table
        :columns="columns"
        :rows="rows"
        :pagination-options="{ enabled: false }"
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
  name: 'ChecklistCompletionReport',
  data() {
    return {
      isLoading: true,
      rows: [],
      customers: [],
      technicians: [],
      filters: {
        client_id: null,
        technician_id: null,
        from: '',
        to: ''
      },
      columns: [
        { label: this.$t('Category'), field: 'category_name' },
        { label: this.$t('Total_Items'), field: 'total_items' },
        { label: this.$t('Completed_Items'), field: 'completed_items' }
      ]
    };
  },
  async mounted() {
    await this.getReport();
    this.isLoading = false;
  },
  methods: {
    async getReport() {
      const params = {
        client_id: this.filters.client_id,
        technician_id: this.filters.technician_id,
        from: this.filters.from,
        to: this.filters.to
      };
      const { data } = await axios.get('report/service_checklist_completion', {
        params
      });
      this.rows = data.rows || [];
      this.customers = (data.clients || []).map(c => ({ id: c.id, name: c.name }));
      this.technicians = (data.technicians || []).map(t => ({
        id: t.id,
        full_name: t.name || `#${t.id}`
      }));
    },

    //------ Print Table Only - Print ALL checklist completion data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Checklist_Completion_Report")}`;
      const rows = Array.isArray(this.rows) ? this.rows : [];
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data
      rows.forEach(row => {
        tableHTML += '<tr>';
        this.columns.forEach(col => {
          let cellValue = row[col.field] || '';
          tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; text-align: left;">${cellValue}</td>`;
        });
        tableHTML += '</tr>';
      });
      
      tableHTML += '</tbody></table>';

      const w = window.open("", "_blank");
      if (!w) {
        alert("Please allow popups to print");
        return;
      }

      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
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
    <style>
      /* Force visibility in print (some global POS print CSS hides body) */
      @media print { 
        body, body * { visibility: visible !important; }
        @page { size: A4 landscape; margin: 0.3cm; }
      }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 10px; font-size: 14px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 10px; }
      th { background-color: #f5f5f5; font-weight: bold; }
      tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    ${tableHTML}
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


