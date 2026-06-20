<template>
  <div class="main-content p-2 p-md-4">
    <breadcumb :page="$t('Customer_Loyalty_Points_Report')" :folder="$t('Reports')" />

    <!-- Toolbar -->
    <b-card class="shadow-soft border-0 mb-3">
      <div class="d-flex flex-wrap align-items-end">
        <div class="mr-3 mb-2 d-flex flex-column" :class="{ 'w-100': isMobile }">
          <label class="mb-1 d-block text-muted">{{$t('DateRange')}}</label>
          <date-range-picker
            v-model="dateRange"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="isMobile ? 'center' : 'right'"
            @update="fetchReport"
          >
            <template v-slot:input="picker">
              <b-button variant="light" class="btn-pill date-btn" :class="{ 'w-100': isMobile }">
                <lucide-icon class="mr-1" name="calendar-days" />
                <span class="d-none d-sm-inline">{{ fmt(picker.startDate) }} — {{ fmt(picker.endDate) }}</span>
                <span class="d-inline d-sm-none">{{ fmtShort(picker.startDate) }}–{{ fmtShort(picker.endDate) }}</span>
              </b-button>
            </template>
          </date-range-picker>
        </div>

        <div class="mr-3 mb-2" :class="{ 'w-100': isMobile }">
          <label class="mb-1 d-block text-muted">{{$t('QuickRanges')}}</label>
          <div class="quick-ranges" :class="{ 'w-100': isMobile }">
            <b-button size="sm" variant="outline-primary" @click="quick('7d')">7D</b-button>
            <b-button size="sm" variant="outline-primary" @click="quick('30d')">30D</b-button>
            <b-button size="sm" variant="outline-primary" @click="quick('90d')">90D</b-button>
            <b-button size="sm" variant="outline-primary" @click="quick('mtd')">{{$t('MTD')}}</b-button>
            <b-button size="sm" variant="outline-primary" @click="quick('ytd')">{{$t('YTD')}}</b-button>
          </div>
        </div>

        <div class="ml-auto mb-2 d-flex">
          <b-button variant="primary" class="btn-pill" @click="fetchReport">
            <lucide-icon class="mr-1" name="refresh-cw" /> {{$t('Refresh')}}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Loading -->
    <div v-if="isLoading" class="mb-4">
      <b-row>
        <b-col md="4" v-for="n in 6" :key="'skel-'+n">
          <b-skeleton-img class="rounded-xl shadow-soft" height="110px" />
        </b-col>
      </b-row>
    </div>

    <!-- Table -->
    <div v-else>
      <b-card class="shadow-soft border-0">
        <vue-good-table
          mode="remote"
          :rows="rows"
          :columns="columns"
          :totalRows="totalRows"
          styleClass="tableOne table-hover vgt-table"
          :pagination-options="{enabled:true, mode:'records'}"
          :search-options="{enabled:true, placeholder:$t('Search_this_table')}"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
              <lucide-icon name="printer" /> {{ $t("print") }}
            </b-button>
          </div>
          <template slot="table-actions-bottom">
            <div class="d-flex justify-content-end w-100 pt-2">
              <div class="font-weight-bold">
                {{$t('Totals')}}:
                <span class="ml-3">{{$t('Points_Earned')}} = {{ num(totals.earned_total) }}</span>
                <span class="ml-3">{{$t('Points_Redeemed')}} = {{ num(totals.redeemed_total) }}</span>
                <span class="ml-3">{{$t('Points_Balance')}} = {{ num(totals.balance_total) }}</span>
              </div>
            </div>
          </template>
        </vue-good-table>
      </b-card>
    </div>
  </div>
  </template>

<script>
import NProgress from 'nprogress';
import { mapGetters } from 'vuex';
import DateRangePicker from 'vue2-daterange-picker';
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css';
import moment from 'moment';

export default {
  metaInfo: { title: 'Customer Loyalty Points Report' },
  components: { 'date-range-picker': DateRangePicker },
  data(){
    const end = new Date(), start = new Date(); start.setDate(end.getDate()-29);
    return {
      isLoading: true,
      rows: [], totalRows: 0,
      totals: { earned_total:0, redeemed_total:0, balance_total:0 },
      serverParams: { page:1, perPage:10, sort:{ field:'earned_points', type:'desc' } },
      limit: 10, search: '',
      dateRange: { startDate: start, endDate: end },
      locale:{
        Label: this.$t('Apply') || 'Apply',
        cancelLabel: this.$t('Cancel') || 'Cancel',
        weekLabel: 'W', customRangeLabel: this.$t('CustomRange') || 'Custom Range',
        daysOfWeek: moment.weekdaysMin(), monthNames: moment.monthsShort(), firstDay: 1
      },
      isMobile:false
    };
  },
  computed:{
    ...mapGetters(['currentUser']),
    columns(){
      return [
        {label:this.$t('Customer'),        field:'client_name',     sortable:true, tdClass:'text-left', thClass:'text-left'},
        {label:this.$t('Points_Earned'),   field:'earned_points',   type:'number', sortable:true},
        {label:this.$t('Points_Redeemed'), field:'redeemed_points', type:'number', sortable:true},
        {label:this.$t('Points_Balance'),  field:'current_points',  type:'number', sortable:true}
      ];
    }
  },
  methods:{
    fmt(d){ return moment(d).format('YYYY-MM-DD'); },
    fmtShort(d){ return moment(d).format('MMM D'); },
    num(v){ const n = Number(v||0); return isNaN(n)?'0':n.toLocaleString(); },
    handleResize(){ this.isMobile = window.innerWidth < 576; },
    quick(kind){
      const now = moment(); let s = now.clone().subtract(29,'days'), e = now.clone();
      if(kind==='7d')  s = now.clone().subtract(6,'days');
      if(kind==='30d') s = now.clone().subtract(29,'days');
      if(kind==='90d') s = now.clone().subtract(89,'days');
      if(kind==='mtd') s = now.clone().startOf('month');
      if(kind==='ytd') s = now.clone().startOf('year');
      this.dateRange = { startDate: s.toDate(), endDate: e.toDate() };
      this.fetchReport();
    },
    onPageChange({currentPage}){ this.serverParams.page = currentPage; this.fetchReport(); },
    onPerPageChange({currentPerPage}){ this.serverParams.perPage = currentPerPage; this.limit=currentPerPage; this.serverParams.page=1; this.fetchReport(); },
    onSortChange(params){ if(params && params[0]) this.serverParams.sort = params[0]; this.fetchReport(); },
    onSearch(v){ this.search = v.searchTerm || ''; this.fetchReport(); },
    async fetchReport(){
      try {
        NProgress.start(); NProgress.set(0.1); this.isLoading = true;
        const qs = new URLSearchParams({
          from: this.fmt(this.dateRange.startDate),
          to:   this.fmt(this.dateRange.endDate),
          page: String(this.serverParams.page),
          limit:String(this.serverParams.perPage || this.limit),
          SortField: this.serverParams?.sort?.field || 'earned_points',
          SortType:  this.serverParams?.sort?.type  || 'desc',
          search: this.search || ''
        }).toString();
        const { data } = await axios.get(`report/customer_loyalty_points?${qs}`).catch(()=>({data:{}}));
        this.rows      = Array.isArray(data.rows) ? data.rows : [];
        this.totalRows = Number(data.totalRows || 0);
        this.totals    = data.totals || { earned_total:0, redeemed_total:0, balance_total:0 };
      } finally { this.isLoading = false; NProgress.done(); }
    },

    //------ Print Table Only - Print ALL customer loyalty points data with all columns
    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("Customer_Loyalty_Points_Report")}`;
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
          let cellValue = '';
          
          if (col.field === 'client_name') {
            cellValue = row.client_name || '';
          } else if (col.field === 'earned_points' || col.field === 'redeemed_points' || col.field === 'current_points') {
            cellValue = this.num(row[col.field]);
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
    }
  },
  created(){ this.handleResize(); if (typeof window !== 'undefined') window.addEventListener('resize', this.handleResize); this.fetchReport(); },
  destroyed(){ if (typeof window !== 'undefined') window.removeEventListener('resize', this.handleResize); }
};
</script>

<style scoped>
.date-btn{ min-width: 230px; }
.quick-ranges{ display:flex; flex-wrap: wrap; gap: 6px; }
.quick-ranges .btn{ min-width: 60px; }

@media (max-width: 576px){
  .date-btn{ width: 100%; min-width: 0; }
  .quick-ranges{ width: 100%; }
  .quick-ranges .btn{ flex: 1 1 calc(33.33% - 6px); min-width: 0; }
  .d-flex.flex-wrap.align-items-end > .mr-3{ margin-right: 0 !important; }
}
</style>


