<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Negative_Stock_Report')" :folder="$t('Reports')" />

    <b-card class="toolbar-card shadow-soft mb-3 border-0">
      <div class="d-flex flex-wrap align-items-center">
        <div class="ml-auto mb-2 actions-bar">
          <div class="warehouse-block mr-2 mb-2 mb-sm-0">
            <label class="mb-1 d-block text-muted">{{$t('warehouse')}}</label>
            <v-select
              class="w-280"
              v-model="warehouse_id"
              :reduce="opt => opt.value"
              :placeholder="$t('Choose_Warehouse')"
              :options="warehouses.map(w => ({label: w.name, value: w.id}))"
              :clearable="true"
              @input="fetchRows(1)"
            />
          </div>

          <div class="search-block mr-2 mb-2 mb-sm-0">
            <label class="mb-1 d-block text-muted">{{$t('Search')}}</label>
            <b-input-group class="search-input">
              <b-form-input v-model="search" :placeholder="$t('Search_this_table')" @keyup.enter="fetchRows(1)" />
              <b-input-group-append>
                <b-button variant="primary" class="btn-pill" @click="fetchRows(1)">{{$t('Search')}}</b-button>
              </b-input-group-append>
            </b-input-group>
          </div>

          <div class="export-block">
            <label class="mb-1 d-block text-muted">{{$t('Export')}}</label>
            <div class="btn-group">
              <b-button size="sm" variant="danger" class="btn-pill" @click="exportPDF"><lucide-icon class="mr-1" name="file-text" />{{$t('Export_PDF')}}</b-button>
              <vue-excel-xlsx
                class="btn btn-sm btn-outline-success btn-pill"
                :data="rows"
                :columns="columns"
                :file-name="'negative_stock_report'"
                :file-type="'xlsx'"
                :sheet-name="'negative_stock_report'"
              ><lucide-icon class="mr-1" name="file-spreadsheet" />{{$t('EXCEL')}}</vue-excel-xlsx>
            </div>
          </div>
        </div>
      </div>
    </b-card>

    <b-card class="shadow-soft border-0 mb-3">
      <vue-good-table
        :rows="rows"
        :columns="columns"
        :totalRows="totalRows"
        :search-options="{enabled:false}"
        :pagination-options="{enabled:true, mode:'records'}"
        :styleClass="'tableOne table-hover vgt-table'"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>
        <template slot="table-row" slot-scope="p">
          <span v-if="p.column.field==='quantity'" class="text-danger">{{ p.row.quantity }}</span>
          <span v-else>{{ p.formattedRow[p.column.field] }}</span>
        </template>
      </vue-good-table>
    </b-card>

    <!-- Chart: Negative quantity by warehouse (absolute sum) -->
    <b-card class="shadow-soft border-0 mb-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="m-0">{{$t('Warehouse')}} — {{$t('Quantity')}}</h6>
      </div>
      <apexchart type="bar" height="280" :options="apexBarOptions" :series="apexBarSeries" />
    </b-card>
  </div>
  </template>

<script>
import NProgress from 'nprogress';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import VueApexCharts from 'vue-apexcharts';

export default {
  metaInfo: { title: 'Negative Stock Report' },
  components: { apexchart: VueApexCharts },
  data(){
    return {
      warehouses: [],
      warehouse_id: null,
      search: '',
      rows: [],
      totalRows: 0,
      serverParams: { page:1, perPage:10 },
    };
  },
  computed: {
    columns(){
      return [
        { label: this.$t('Ref'), field:'code' },
        { label: this.$t('Name_product'), field:'name' },
        { label: this.$t('warehouse'), field:'warehouse_name' },
        { label: this.$t('Quantity'), field:'quantity', type:'number' },
      ];
    },

    // Aggregation for chart
    warehouseAgg(){
      const map = new Map();
      (this.rows||[]).forEach(r=>{
        const key = r.warehouse_name || '-';
        const val = Math.abs(Number(r.quantity||0));
        map.set(key, (map.get(key)||0) + val);
      });
      const labels = Array.from(map.keys());
      const data = labels.map(k => map.get(k));
      return { labels, data };
    },
    apexBarOptions(){
      return {
        chart: { type:'bar', toolbar:{ show:false } },
        plotOptions: { bar: { horizontal:false, columnWidth:'45%' } },
        dataLabels: { enabled:false },
        xaxis: { categories: this.warehouseAgg.labels },
        yaxis: { labels: { formatter: (v)=> Number(v||0).toLocaleString() } },
        tooltip: { y: { formatter: (v)=> Number(v||0).toLocaleString() } },
        legend: { show:false }
      };
    },
    apexBarSeries(){
      return [ { name: this.$t('Quantity'), data: this.warehouseAgg.data } ];
    }
  },
  methods: {
    onPageChange({ currentPage }){ if(this.serverParams.page!==currentPage){ this.serverParams.page=currentPage; this.fetchRows(currentPage);} },
    onPerPageChange({ currentPerPage }){ if(this.serverParams.perPage!==currentPerPage){ this.serverParams.perPage=currentPerPage; this.fetchRows(1);} },
    exportPDF(){
      const doc = new jsPDF('p','pt');
      const fontPath = '/fonts/Vazirmatn-Bold.ttf';
      try { doc.addFont(fontPath,'Vazirmatn','normal'); doc.addFont(fontPath,'Vazirmatn','bold'); } catch(e){}
      doc.setFont('Vazirmatn','normal');
      const head = [[ this.$t('Ref'), this.$t('Name_product'), this.$t('warehouse'), this.$t('Quantity') ]];
      const body = (this.rows||[]).map(r => [r.code, r.name, r.warehouse_name, r.quantity]);
      const marginX = 40;
      autoTable(doc, {
        head, body, startY: 80,
        styles:{ font:'Vazirmatn', fontSize:9, cellPadding:6 },
        headStyles:{ font:'Vazirmatn', fontStyle:'bold', fillColor:[26,86,219], textColor:255 },
        margin:{ left:marginX, right:marginX },
        didDrawPage: (d)=>{
          const pageW = doc.internal.pageSize.getWidth();
          doc.setFillColor(26,86,219); doc.rect(0,0,pageW,52,'F');
          doc.setTextColor(255); doc.setFont('Vazirmatn','bold'); doc.setFontSize(14);
          doc.text('Negative Stock Report', marginX, 32);
          doc.setTextColor(33);
        }
      });
      doc.save('negative_stock_report.pdf');
    },

    //------ Print Table Only - Print ALL negative stock data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Negative_Stock_Report")}`;
      const rows = Array.isArray(this.rows) ? this.rows : [];
      
      // Build table header with all columns
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      // Build table rows with all data - format each cell according to column type
      rows.forEach(row => {
        tableHTML += '<tr>';
        this.columns.forEach(col => {
          let cellValue = '';
          
          if (col.field === 'code') {
            cellValue = row.code || '';
          } else if (col.field === 'name') {
            cellValue = row.name || '';
          } else if (col.field === 'warehouse_name') {
            cellValue = row.warehouse_name || '';
          } else if (col.field === 'quantity') {
            // Quantity - show as is (already negative values)
            cellValue = row.quantity || 0;
          } else {
            // Default: get value directly from row object
            cellValue = row[col.field] || '';
          }
          
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
    },

    fetchRows(page){
      NProgress.start(); NProgress.set(0.1);
      const qs = new URLSearchParams({
        page: page||this.serverParams.page,
        limit: this.serverParams.perPage,
        warehouse_id: this.warehouse_id || '',
        search: this.search || ''
      }).toString();
      axios.get(`report/negative_stock?${qs}`).then(({data})=>{
        this.rows = data.rows || [];
        this.totalRows = data.totalRows || 0;
        this.warehouses = data.warehouses || [];
        NProgress.done();
      }).catch(()=> NProgress.done());
    }
  },
  created(){ this.fetchRows(1); }
}
</script>

<style scoped>
.rounded-xl { border-radius: 1rem; }
.shadow-soft { box-shadow: 0 12px 24px rgba(0,0,0,0.06), 0 2px 6px rgba(0,0,0,0.05); }
.toolbar-card { background:#fff; }
.btn-pill { border-radius:999px; }
.w-280 { width: 280px; }

.actions-bar { display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; width:100%; gap:12px; }
  .actions-bar .warehouse-block { flex: 0 1 280px; min-width:220px; max-width:320px; }
.actions-bar .search-block { flex: 1 1 360px; min-width:260px; max-width:520px; }
.actions-bar .search-input { width:100%; }
.export-block { display:flex; flex-direction:column; align-items:flex-start; }
.export-block .btn-group > * + * { margin-left:8px; }

@media (max-width: 576px) {
    .actions-bar { flex-wrap:wrap; justify-content:flex-start; gap:8px; }
    .actions-bar .warehouse-block { flex:1 1 100%; max-width:100%; }
  .actions-bar .search-block { flex:1 1 100%; max-width:100%; }
  .export-block { width:100%; margin-top:8px; }
}
</style>


