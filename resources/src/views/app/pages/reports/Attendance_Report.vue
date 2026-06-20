<template>
  <div class="main-content">
    <breadcumb :page="$t('Attendance')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <b-row class="mb-3">
        <b-col md="2" sm="6">
          <b-form-group :label="$t('Report')">
            <b-form-select v-model="filter.scope" :options="scopeOptions" />
          </b-form-group>
        </b-col>

        <b-col md="2" sm="6" v-if="filter.scope === 'daily'">
          <b-form-group :label="$t('date')">
            <b-form-input type="date" v-model="filter.date" />
          </b-form-group>
        </b-col>

        <b-col md="2" sm="6" v-if="filter.scope === 'monthly'">
          <b-form-group label="Month">
            <b-form-input type="month" v-model="filter.month" />
          </b-form-group>
        </b-col>

        <b-col md="3" sm="6">
          <b-form-group :label="$t('Company')">
            <v-select
              :reduce="label => label.value"
              :placeholder="$t('Company')"
              v-model="filter.company_id"
              :options="companies.map(c => ({ label: c.name, value: c.id }))"
            />
          </b-form-group>
        </b-col>

        <b-col md="3" sm="6">
          <b-form-group :label="$t('Employee')">
            <v-select
              :reduce="label => label.value"
              :placeholder="$t('Choose_Employee')"
              v-model="filter.employee_id"
              :options="employees.map(e => ({ label: e.username, value: e.id }))"
            />
          </b-form-group>
        </b-col>

        <b-col md="2" sm="6" class="d-flex align-items-end">
          <b-button @click="fetchReport(1)" variant="primary" size="sm" block>
            <lucide-icon name="filter" /> {{ $t('Filter') }}
          </b-button>
        </b-col>
        <b-col md="2" sm="6" class="d-flex align-items-end">
          <b-button @click="resetFilter" variant="danger" size="sm" block>
            <lucide-icon name="power" /> {{ $t('Reset') }}
          </b-button>
        </b-col>
      </b-row>

      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="card-title m-0">{{$t('Attendance')}} {{$t('Report')}}</h4>
          <small class="text-muted" v-if="rangeText">{{ rangeText }}</small>
        </div>
        <apexchart type="bar" height="300" :options="apexOptions" :series="apexSeries" />
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ placeholder: $t('Search_this_table'), enabled: true }"
        :pagination-options="{ enabled: true, mode: 'records' }"
        :styleClass="'mt-2 order-table vgt-table'"
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
import NProgress from 'nprogress';
import VueApexCharts from 'vue-apexcharts';

export default {
  metaInfo: { title: 'Attendance Report' },
  components: { apexchart: VueApexCharts },
  data() {
    const today = new Date().toISOString().slice(0, 10);
    return {
      isLoading: true,
      companies: [],
      employees: [],
      rows: [],
      totalRows: 0,
      limit: '10',
      search: '',
      from: '',
      to: '',
      serverParams: {
        sort: { field: 'employee_username', type: 'asc' },
        page: 1,
        perPage: 10,
      },
      filter: {
        scope: 'daily',
        date: today,
        month: '',
        company_id: '',
        employee_id: '',
      },
      scopeOptions: [
        { value: 'daily', text: this.$t('Daily') },
        { value: 'monthly', text: 'Monthly' },
      ],
      apexOptions: {
        chart: { toolbar: { show: false } },
        plotOptions: { bar: { horizontal: false, columnWidth: '45%', endingShape: 'rounded' } },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: [] },
        yaxis: { labels: { formatter: (val) => `${Number(val).toFixed(1)}h` } },
        tooltip: { y: { formatter: (val) => `${Number(val).toFixed(2)} h` } },
        legend: { show: false },
      },
      apexSeries: [{ name: 'Hours', data: [] }],
    };
  },
  computed: {
    columns() {
      return [
        { label: this.$t('Employee'), field: 'employee_username', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Company'), field: 'company_name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Work_Duration'), field: 'total_work', tdClass: 'text-left', thClass: 'text-left' },
      ];
    },
    rangeText() {
      if (this.from && this.to) return `${this.from} → ${this.to}`;
      return '';
    }
  },
  methods: {
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.fetchReport(currentPage);
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.fetchReport(1);
      }
    },
    onSortChange(params) {
      const field = params[0].field;
      this.updateParams({
        sort: { type: params[0].type, field },
      });
      this.fetchReport(this.serverParams.page);
    },
    onSearch(value) {
      this.search = value.searchTerm;
      this.fetchReport(this.serverParams.page);
    },
    resetFilter() {
      const today = new Date().toISOString().slice(0, 10);
      this.filter.scope = 'daily';
      this.filter.date = today;
      this.filter.month = '';
      this.filter.company_id = '';
      this.filter.employee_id = '';
      this.fetchReport(1);
    },

    //------ Print Table Only - Print ALL attendance data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Attendance")}`;
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
    <div class="print-header">${title}${this.rangeText ? ' - ' + this.rangeText : ''}</div>
    ${tableHTML}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    },
    fetchReport(page) {
      NProgress.start();
      NProgress.set(0.1);

      const params = new URLSearchParams();
      params.set('page', page);
      params.set('limit', this.limit);
      params.set('SortField', this.serverParams.sort.field);
      params.set('SortType', this.serverParams.sort.type);
      params.set('scope', this.filter.scope);
      if (this.filter.scope === 'daily') {
        params.set('date', this.filter.date);
      } else {
        if (this.filter.month) params.set('month', this.filter.month);
      }
      if (this.filter.company_id) params.set('company_id', this.filter.company_id);
      if (this.filter.employee_id) params.set('employee_id', this.filter.employee_id);

      this.isLoading = true;
      axios
        .get(`/report/attendance_summary?${params.toString()}`)
        .then(({ data }) => {
          this.rows = data.report || [];
          this.totalRows = data.totalRows || 0;
          this.companies = data.companies || [];
          this.employees = data.employees || [];
          this.from = data.from || '';
          this.to = data.to || '';

          const categories = (this.rows || []).map(r => r.employee_username);
          const values = (this.rows || []).map(r => Number(r.total_hours || 0));
          this.apexOptions = Object.assign({}, this.apexOptions, { xaxis: { categories } });
          this.apexSeries = [{ name: 'Hours', data: values }];
        })
        .finally(() => {
          NProgress.done();
          this.isLoading = false;
        });
    },
  },
  created() {
    this.fetchReport(1);
  },
};
</script>


