<template>
  <div class="main-content">
    <breadcumb :page="$t('Error_Logs')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="print-table-only" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="logs"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          perPage: perPage,
          nextLabel: 'Next',
          prevLabel: 'Prev',
        }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <div v-if="props.column.field === 'details'">
            <pre style="max-height: 100px; overflow-y: auto; white-space: pre-wrap;">{{ props.row.details }}</pre>
          </div>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>

export default {
  data() {
    return {
      logs: [],
      totalRows: 0,
      isLoading: true,
      perPage: 10,
      currentPage: 1,
      columns: [
        // { label: "ID", field: "id", sortable: true },
        { label: this.$t("Context"), field: "context", sortable: true },
        { label: this.$t("Message"), field: "message", sortable: false },
        { label: this.$t("Details"), field: "details", sortable: false },
        { label: this.$t("Occurred_At"), field: "occurred_at", sortable: true },
      ]
    };
  },
  mounted() {
    this.fetchLogs();
  },
  methods: {
    fetchLogs(page = 1) {
      this.isLoading = true;
      axios
        .get("/error-logs", {
          params: {
            page: page,
            per_page: this.perPage,
          }
        })
        .then(response => {
          this.logs = response.data.logs;
          this.totalRows = response.data.total;
        })
        .catch(error => {
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
    onPageChange(params) {
      this.currentPage = params.currentPage;
      this.fetchLogs(this.currentPage);
    },
    onPerPageChange(params) {
      this.perPage = params.currentPerPage;
      this.fetchLogs(this.currentPage);
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

      // Get logs data
      const logsData = this.logs || [];

      // Manually construct the table HTML from logs data
      let tableHtml = `<table class="vgt-table table table-hover tableOne">`;

      // Table Header
      tableHtml += `<thead><tr>`;
      this.columns.forEach(col => {
        tableHtml += `<th class="text-left">${col.label}</th>`;
      });
      tableHtml += `</tr></thead>`;

      // Table Body
      tableHtml += `<tbody>`;
      logsData.forEach(row => {
        tableHtml += `<tr>`;
        this.columns.forEach(col => {
          let cellContent = row[col.field] || '';
          // Handle details field with special formatting for long text
          if (col.field === 'details') {
            // Escape HTML and preserve whitespace
            cellContent = String(cellContent).replace(/</g, '&lt;').replace(/>/g, '&gt;');
          }
          tableHtml += `<td class="text-left" style="${col.field === 'details' ? 'max-width: 400px; word-wrap: break-word; white-space: pre-wrap;' : ''}">${cellContent}</td>`;
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

      const title = `${this.$t("Reports")} / ${this.$t("Error_Logs")}`;
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
      @media print { 
        body, body * { visibility: visible !important; }
        @page { size: A4 landscape; margin: 0.5cm; }
      }
      body { margin: 0.3cm; font-family: monospace; }
      .print-header { font-weight: 600; margin-bottom: 8px; }
      table { width: 100%; border-collapse: collapse; font-size: 10px; }
      th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
      th { background-color: #f2f2f2; font-weight: bold; }
      td pre { margin: 0; font-size: 9px; white-space: pre-wrap; word-wrap: break-word; }
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
    },

  }
};
</script>
