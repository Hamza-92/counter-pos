<template>
  <div>
    <breadcumb :page="$t('Commission_Programs')" :folder="$t('Commissions')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <b-card class="shadow-soft border-0">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="programs"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
          :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
          :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
          styleClass="tableOne table-hover vgt-table"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button
              v-if="currentUserPermissions && currentUserPermissions.includes('commissions_add')"
              variant="primary"
              size="sm"
              @click="openModal()"
            >
              <lucide-icon name="plus" /> {{ $t('Add') }}
            </b-button>
          </div>
          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field === 'is_active'">
              <b-badge :variant="props.row.is_active ? 'success' : 'secondary'">
                {{ props.row.is_active ? $t('Active') : $t('Inactive') }}
              </b-badge>
            </span>
            <span v-else-if="props.column.field === 'valid_from'">
              {{ props.row.valid_from ? formatDate(props.row.valid_from) : '—' }}
            </span>
            <span v-else-if="props.column.field === 'valid_to'">
              {{ props.row.valid_to ? formatDate(props.row.valid_to) : '—' }}
            </span>
            <span v-else-if="props.column.field === 'actions'">
              <b-button
                v-if="currentUserPermissions && currentUserPermissions.includes('commissions_edit')"
                variant="link"
                size="sm"
                class="p-0 mr-2"
                @click="openModal(props.row)"
              >
                <lucide-icon class="text-success" name="pen" />
              </b-button>
              <b-button
                v-if="currentUserPermissions && currentUserPermissions.includes('commissions_delete')"
                variant="link"
                size="sm"
                class="p-0"
                @click="confirmDelete(props.row)"
              >
                <lucide-icon class="text-danger" name="x" />
              </b-button>
            </span>
            <span v-else>{{ props.formattedRow[props.column.field] }}</span>
          </template>
        </vue-good-table>
      </b-card>
    </div>

    <b-modal :title="editMode ? $t('Edit') : $t('Add')" hide-footer id="form_modal" @hidden="resetForm">
      <b-form @submit.prevent="submit">
        <b-form-group :label="$t('Name')" label-for="name">
          <b-form-input id="name" v-model="form.name" required maxlength="192"></b-form-input>
        </b-form-group>
        <b-form-group :label="$t('Description')" label-for="description">
          <b-form-textarea id="description" v-model="form.description" rows="2" maxlength="500"></b-form-textarea>
        </b-form-group>
        <b-form-group>
          <b-form-checkbox v-model="form.is_active">{{ $t('Active') }}</b-form-checkbox>
        </b-form-group>
        <b-row>
          <b-col md="6">
            <b-form-group :label="$t('Valid_From')" label-for="valid_from">
              <b-form-input id="valid_from" type="date" v-model="form.valid_from"></b-form-input>
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Valid_To')" label-for="valid_to">
              <b-form-input id="valid_to" type="date" v-model="form.valid_to"></b-form-input>
            </b-form-group>
          </b-col>
        </b-row>
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
  metaInfo: { title: 'Commission Programs' },
  data() {
    return {
      isLoading: true,
      programs: [],
      totalRows: 0,
      serverParams: { sort: { field: 'id', type: 'desc' }, page: 1, perPage: 10 },
      limit: '10',
      search: '',
      editMode: false,
      form: { name: '', description: '', is_active: true, valid_from: '', valid_to: '' },
    };
  },
  computed: {
    ...mapGetters(['currentUserPermissions']),
    columns() {
      return [
        { label: this.$t('Name'), field: 'name' },
        { label: this.$t('Description'), field: 'description' },
        { label: this.$t('Active'), field: 'is_active' },
        { label: this.$t('Valid_From'), field: 'valid_from' },
        { label: this.$t('Valid_To'), field: 'valid_to' },
        { label: this.$t('Rules'), field: 'commission_rules_count' },
        { label: this.$t('Action'), field: 'actions', sortable: false },
      ];
    },
  },
  created() {
    this.load();
  },
  methods: {
    load(page = 1) {
      NProgress.start();
      const params = {
        page,
        limit: this.limit,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.search,
      };
      axios.get('commission_programs', { params }).then((res) => {
        const d = res.data.data || res.data;
        this.programs = d.programs || [];
        this.totalRows = d.totalRows || 0;
        NProgress.done();
        this.isLoading = false;
      }).catch(() => {
        NProgress.done();
        this.isLoading = false;
      });
    },
    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.load(currentPage);
    },
    onPerPageChange({ currentPerPage }) {
      this.limit = String(currentPerPage);
      this.serverParams.perPage = currentPerPage;
      this.load(1);
    },
    onSortChange(params) {
      if (params.length) {
        this.serverParams.sort = { field: params[0].field, type: params[0].type };
        this.load(1);
      }
    },
    onSearch({ searchTerm }) {
      this.search = searchTerm || '';
      this.load(1);
    },
    formatDate(v) {
      if (!v) return '—';
      return new Date(v).toLocaleDateString();
    },
    openModal(row = null) {
      this.editMode = !!row;
      if (row) {
        this.form = {
          id: row.id,
          name: row.name,
          description: row.description || '',
          is_active: !!row.is_active,
          valid_from: row.valid_from ? row.valid_from.slice(0, 10) : '',
          valid_to: row.valid_to ? row.valid_to.slice(0, 10) : '',
        };
      } else {
        this.resetForm();
      }
      this.$bvModal.show('form_modal');
    },
    resetForm() {
      this.form = { name: '', description: '', is_active: true, valid_from: '', valid_to: '' };
      delete this.form.id;
    },
    submit() {
      const url = this.editMode ? `commission_programs/${this.form.id}` : 'commission_programs';
      const method = this.editMode ? 'put' : 'post';
      const payload = {
        name: this.form.name,
        description: this.form.description,
        is_active: this.form.is_active,
        valid_from: this.form.valid_from || null,
        valid_to: this.form.valid_to || null,
      };
      axios[method](url, payload).then(() => {
        this.makeToast('success', this.$t('Success'));
        this.$bvModal.hide('form_modal');
        this.load(this.serverParams.page);
      }).catch((e) => {
        this.makeToast('danger', e.response?.data?.message || this.$t('Error'));
      });
    },
    confirmDelete(row) {
      this.$bvModal.msgBoxConfirm(this.$t('Confirm_delete')).then((ok) => {
        if (ok) {
          axios.delete(`commission_programs/${row.id}`).then(() => {
            this.makeToast('success', this.$t('Deleted'));
            this.load(this.serverParams.page);
          }).catch((e) => this.makeToast('danger', e.response?.data?.message || this.$t('Error')));
        }
      });
    },
    makeToast(variant, msg, title = '') {
      this.$bvToast.toast(msg, { title: title || this.$t('Notice'), variant, solid: true });
    },
  },
};
</script>
