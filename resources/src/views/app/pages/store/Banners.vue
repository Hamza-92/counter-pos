<template>
  <div class="main-content">
<breadcumb :page="$t('Banners')" :folder="$t('Store')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else class="wrapper">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        :pagination-options="{ enabled: true, perPage: serverParams.perPage }"
        :search-options="{ enabled: false }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="$router.push({name:'StoreBannerEdit', params:{id:'new'}})" size="sm" class="btn-rounded" variant="btn btn-primary btn-icon m-1">
            <lucide-icon name="plus" /> {{ $t('Add') }}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field==='preview'">
            <img :src="props.row.image_url" height="32">
          </span>
          <span v-else-if="props.column.field==='active'">
            <b-badge :variant="props.row.active ? 'success':'secondary'">{{ props.row.active ? $t('Active') : $t('Disabled') }}</b-badge>
          </span>
          <span v-else-if="props.column.field==='actions'">
            <a v-b-tooltip.hover :title="$t('Edit')" @click="$router.push({name:'StoreBannerEdit', params:{id:props.row.id}})">
              <lucide-icon class="text-20 text-info" name="pencil" />
            </a>
            <a v-b-tooltip.hover :title="$t('Delete')" class="ml-2" @click="remove(props.row.id)">
              <lucide-icon class="text-20 text-danger" name="x" />
            </a>
          </span>
          <span v-else>{{ props.formattedRow[props.column.field] }}</span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
export default {
  metaInfo: {
    title: "Store Banners"
  },
  data(){ return {
    isLoading:true,
    rows:[], totalRows:0,
    columns:[
      {label:this.$t('Preview'), field:'preview'},
      {label:this.$t('Title'), field:'title', sortable:true},
      {label:this.$t('Position'), field:'position', sortable:true},
      {label:this.$t('Active'), field:'active', sortable:true},
      {label:this.$t('Updated'), field:'updated_at', sortable:true},
      {label:this.$t('Actions'), field:'actions'}
    ],
    serverParams:{ page:1, perPage:10, sort:[{field:'updated_at', type:'desc'}] }
  }},
  mounted(){ this.fetch() },
  methods:{
    async fetch() {
      this.isLoading = true
      try {
        const { data } = await axios.get('/store/banners', {
          params: {
            page: this.serverParams.page,
            per_page: this.serverParams.perPage,
            sort: this.serverParams.sort?.[0]?.field,
            dir: this.serverParams.sort?.[0]?.type,
          }
        })
        this.rows = data.data || []
        this.totalRows = data.meta?.total || this.rows.length
      } finally {
        this.isLoading = false
      }
    },

    onPageChange({currentPage}){ this.serverParams.page=currentPage; this.fetch() },
    onPerPageChange({currentPerPage}){ this.serverParams.perPage=currentPerPage; this.fetch() },
    onSortChange(params){ this.serverParams.sort = params[0] ? [params[0]] : []; this.fetch() },
   remove(id) {
      var self = this;

      self.$swal({
        title: self.$t('Delete_Title'),
        text: self.$t('Delete_Text'),
        type: 'warning', // for newer SweetAlert2 you can use icon: 'warning'
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: self.$t('Delete_cancelButtonText'),
        confirmButtonText: self.$t('Delete_confirmButtonText')
      }).then(function (result) {
        // support both old (result.value) and new (result.isConfirmed)
        var confirmed = !!(result && (result.value === true || result.isConfirmed === true));
        if (!confirmed) return;

        axios.delete('/store/banners/' + id)
          .then(function () {

            self.$swal(
              self.$t('Delete_Deleted'),
              self.$t('Deleted_in_successfully'),
              'success'
            );

           
            self.fetch();
          })
          .catch(function (e) {

            var msg = (e && e.response && e.response.data && (e.response.data.message || e.response.data.error))
              || self.$t('Delete_Therewassomethingwronge');

            self.$swal(
              self.$t('Delete_Failed'),
              msg,
              'warning'
            );
          });
      });
    }


  }
}
</script>
