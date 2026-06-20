<template>
  <div class="main-content">
    <breadcumb :page="$t('Assets_List')" :folder="$t('Assets')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-else class="page-wrapper">
      <div class="control-bar">
        <div class="control-right">
          <router-link to="/app/assets/store" class="btn btn-primary btn-sm">
            <lucide-icon class="mr-1" name="plus" />{{ $t('Add') }}
          </router-link>
        </div>
      </div>

      <div class="table-card">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="assets"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
          :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
          :pagination-options="{ enabled: true, mode: 'records' }"
          styleClass="tableOne vgt-table">

          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field == 'last_verification' || props.column.field == 'next_validation'">
              <span :class="getValidationRowClass(props)">{{ props.formattedRow[props.column.field] || '—' }}</span>
            </span>
            <span v-else-if="props.column.field == 'actions'">
              <router-link :to="'/app/assets/edit/' + props.row.id" class="btn btn-sm btn-outline-primary mr-2">
                <lucide-icon name="pencil" />
              </router-link>
              <button class="btn btn-sm btn-outline-danger" @click="removeAsset(props.row.id)">
                <lucide-icon name="x" />
              </button>
            </span>
            <span v-else>{{ props.formattedRow[props.column.field] }}</span>
          </template>

        </vue-good-table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AssetsIndex',
  data() {
    return {
      isLoading: true,
      assets: [],
      totalRows: 0,
      serverParams: {
        columnFilters: {},
        sort: { field: 'id', type: 'desc' },
        page: 1,
        perPage: 10,
        searchTerm: ''
      },
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
  mounted() {
    this.getAssets();
  },
  methods: {
    async getAssets() {
      this.isLoading = true;
      const params = {
        page: this.serverParams.page,
        limit: this.serverParams.perPage,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.serverParams.searchTerm
      };
      try {
        const { data } = await axios.get('assets', { params });
        this.assets = data.assets;
        this.totalRows = data.totalRows;
      } finally {
        this.isLoading = false;
      }
    },
    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.getAssets();
    },
    onPerPageChange({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage;
      this.getAssets();
    },
    onSortChange(params) {
      this.serverParams.sort.field = params[0].field;
      this.serverParams.sort.type = params[0].type;
      this.getAssets();
    },
    onSearch(params) {
      this.serverParams.searchTerm = params.searchTerm;
      this.getAssets();
    },
    async removeAsset(id) {
      const ok = await this.$bvModal.msgBoxConfirm(this.$t('AreYouSure'), { size: 'sm' });
      if (!ok) return;
      await axios.delete(`assets/${id}`);
      this.getAssets();
    },
    getValidationRowClass(props) {
      if (props.column.field !== 'next_validation' || !props.row.next_validation) return '';
      const d = new Date(props.row.next_validation);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      d.setHours(0, 0, 0, 0);
      const workingDaysFromNow = this.addWorkingDays(today, 5);
      if (d < today) return 'text-danger font-weight-bold';
      if (d <= workingDaysFromNow) return 'text-warning font-weight-bold';
      return '';
    },
    addWorkingDays(date, days) {
      const d = new Date(date);
      let added = 0;
      while (added < days) {
        d.setDate(d.getDate() + 1);
        if (d.getDay() !== 0 && d.getDay() !== 6) added++;
      }
      return d;
    }
  }
}
</script>


