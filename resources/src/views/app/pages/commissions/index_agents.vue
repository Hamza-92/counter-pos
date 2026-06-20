<template>
  <div>
    <breadcumb :page="$t('Sales_Agents')" :folder="$t('Commissions')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <b-card class="shadow-soft border-0">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="agents"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
          :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
          :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
          styleClass="tableOne table-hover vgt-table"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button v-if="currentUserPermissions && currentUserPermissions.includes('commissions_add')" variant="primary" size="sm" @click="openModal()">
              <lucide-icon name="plus" /> {{ $t('Add') }}
            </b-button>
          </div>
          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field === 'is_active'">
              <b-badge :variant="props.row.is_active ? 'success' : 'secondary'">{{ props.row.is_active ? $t('Active') : $t('Inactive') }}</b-badge>
            </span>
            <span v-else-if="props.column.field === 'user'">
              {{ props.row.user ? (props.row.user.firstname + ' ' + props.row.user.lastname).trim() || props.row.user.email : '—' }}
            </span>
            <span v-else-if="props.column.field === 'actions'">
              <b-button v-if="currentUserPermissions && currentUserPermissions.includes('commissions_edit')" variant="link" size="sm" class="p-0 mr-2" @click="openModal(props.row)"><lucide-icon class="text-success" name="pen" /></b-button>
              <b-button v-if="currentUserPermissions && currentUserPermissions.includes('commissions_delete')" variant="link" size="sm" class="p-0" @click="confirmDelete(props.row)"><lucide-icon class="text-danger" name="x" /></b-button>
            </span>
            <span v-else>{{ props.formattedRow[props.column.field] }}</span>
          </template>
        </vue-good-table>
      </b-card>
    </div>
    <b-modal :title="editMode ? $t('Edit') : $t('Add')" hide-footer id="form_modal" @hidden="resetForm">
      <b-form @submit.prevent="submit">
        <b-form-group :label="$t('Name')"><b-form-input v-model="form.name" required></b-form-input></b-form-group>
        <b-form-group :label="$t('Code')"><b-form-input v-model="form.code"></b-form-input></b-form-group>
        <b-form-group :label="$t('email')"><b-form-input type="email" v-model="form.email"></b-form-input></b-form-group>
        <b-form-group :label="$t('phone')"><b-form-input v-model="form.phone"></b-form-input></b-form-group>
        <b-form-group :label="$t('Link_User')">
          <v-select v-model="form.user_id" :reduce="u => u.id" :options="usersList" :placeholder="$t('PleaseSelect')" label="label"></v-select>
        </b-form-group>
        <b-form-checkbox v-model="form.is_active">{{ $t('Active') }}</b-form-checkbox>
        <b-form-group :label="$t('Notes')"><b-form-textarea v-model="form.notes" rows="2"></b-form-textarea></b-form-group>
        <div class="d-flex justify-content-end mt-3">
          <b-button type="button" variant="secondary" @click="$bvModal.hide('form_modal')">{{ $t('Cancel') }}</b-button>
          <b-button type="submit" variant="primary" class="ml-2">{{ $t('Submit') }}</b-button>
        </div>
      </b-form>
    </b-modal>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import NProgress from 'nprogress';

export default {
  data() {
    return {
      isLoading: true,
      agents: [],
      totalRows: 0,
      serverParams: { sort: { field: 'id', type: 'desc' }, page: 1, perPage: 10 },
      limit: '10',
      search: '',
      editMode: false,
      usersList: [],
      form: { name: '', code: '', email: '', phone: '', user_id: null, is_active: true, notes: '' },
    };
  },
  computed: {
    ...mapGetters(['currentUserPermissions']),
    columns() {
      return [
        { label: this.$t('Name'), field: 'name' },
        { label: this.$t('Code'), field: 'code' },
        { label: this.$t('email'), field: 'email' },
        { label: this.$t('User'), field: 'user' },
        { label: this.$t('Active'), field: 'is_active' },
        { label: this.$t('Commissions'), field: 'sale_commissions_count' },
        { label: this.$t('Action'), field: 'actions', sortable: false },
      ];
    },
  },
  created() {
    axios.get('users_list_for_select').then((res) => {
      const list = (res.data && res.data.users) || [];
      this.usersList = list.map(u => ({ id: u.id, label: ((u.firstname || '') + ' ' + (u.lastname || '')).trim() || u.username || u.email }));
    }).catch((e) => {
      this.usersList = [];
      this.makeToast('danger', (e.response && e.response.data && e.response.data.message) || this.$t('Error') || 'Could not load users');
    });
    this.load();
  },
  methods: {
    load(page) {
      page = page || 1;
      NProgress.start();
      axios.get('sales_agents', { params: { page, limit: this.limit, SortField: this.serverParams.sort.field, SortType: this.serverParams.sort.type, search: this.search } }).then((res) => {
        const d = res.data.data || res.data;
        this.agents = d.agents || [];
        this.totalRows = d.totalRows || 0;
        NProgress.done();
        this.isLoading = false;
      }).catch(() => { NProgress.done(); this.isLoading = false; });
    },
    onPageChange(p) { this.load(p.currentPage); },
    onPerPageChange(p) { this.limit = String(p.currentPerPage); this.load(1); },
    onSortChange(params) { if (params.length) { this.serverParams.sort = { field: params[0].field, type: params[0].type }; this.load(1); } },
    onSearch(p) { this.search = p.searchTerm || ''; this.load(1); },
    openModal(row) {
      this.editMode = !!row;
      this.form = row ? { id: row.id, name: row.name, code: row.code || '', email: row.email || '', phone: row.phone || '', user_id: row.user_id || null, is_active: !!row.is_active, notes: row.notes || '' } : { name: '', code: '', email: '', phone: '', user_id: null, is_active: true, notes: '' };
      if (!row) delete this.form.id;
      this.$bvModal.show('form_modal');
    },
    resetForm() { this.form = { name: '', code: '', email: '', phone: '', user_id: null, is_active: true, notes: '' }; },
    submit() {
      const url = this.editMode ? 'sales_agents/' + this.form.id : 'sales_agents';
      const method = this.editMode ? 'put' : 'post';
      const payload = { name: this.form.name, code: this.form.code || null, email: this.form.email || null, phone: this.form.phone || null, user_id: this.form.user_id || null, is_active: this.form.is_active, notes: this.form.notes || null };
      axios[method](url, payload).then(() => { this.makeToast('success', this.$t('Success')); this.$bvModal.hide('form_modal'); this.load(this.serverParams.page); }).catch((e) => this.makeToast('danger', (e.response && e.response.data && e.response.data.message) || this.$t('Error')));
    },
    confirmDelete(row) {
      this.$bvModal.msgBoxConfirm(this.$t('Confirm_delete')).then((ok) => {
        if (ok) axios.delete('sales_agents/' + row.id).then(() => { this.makeToast('success', this.$t('Deleted')); this.load(this.serverParams.page); }).catch((e) => this.makeToast('danger', (e.response && e.response.data && e.response.data.message) || this.$t('Error')));
      });
    },
    makeToast(variant, msg) { this.$bvToast.toast(msg, { title: this.$t('Notice'), variant, solid: true }); },
  },
};
</script>
