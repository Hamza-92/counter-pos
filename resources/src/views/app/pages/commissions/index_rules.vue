<template>
  <div>
    <breadcumb :page="$t('Commission_Rules')" :folder="$t('Commissions')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <b-card class="shadow-soft border-0">
        <div class="mb-3">
          <v-select
            v-model="filterProgramId"
            :reduce="p => p.id"
            :options="programsList"
            :placeholder="$t('Filter_by_Program')"
            label="name"
            class="d-inline-block"
            style="max-width: 280px;"
            @input="load(1)"
          />
        </div>
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="rules"
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
            <span v-if="props.column.field === 'type'">{{ props.row.type }} ({{ props.row.value }}{{ props.row.type === 'percentage' ? '%' : '' }})</span>
            <span v-else-if="props.column.field === 'source'">{{ props.row.source === 'sale_total' ? $t('Sale_Total') : $t('Paid_Amount') }}</span>
            <span v-else-if="props.column.field === 'applies_to'">{{ props.row.applies_to === 'all_agents' ? $t('All_Agents') : $t('Specific_Agent') }}</span>
            <span v-else-if="props.column.field === 'program'">{{ props.row.commission_program ? props.row.commission_program.name : '—' }}</span>
            <span v-else-if="props.column.field === 'agent'">{{ props.row.sales_agent ? props.row.sales_agent.name : '—' }}</span>
            <span v-else-if="props.column.field === 'is_active'">
              <b-badge :variant="props.row.is_active ? 'success' : 'secondary'">{{ props.row.is_active ? $t('Active') : $t('Inactive') }}</b-badge>
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

    <b-modal :title="editMode ? $t('Edit') : $t('Add')" hide-footer size="lg" id="form_modal" @hidden="resetForm">
      <b-form @submit.prevent="submit">
        <b-form-group :label="$t('Commission_Program')" label-for="program">
          <v-select v-model="form.commission_program_id" :reduce="p => p.id" :options="programsList" label="name" :placeholder="$t('PleaseSelect')" required />
        </b-form-group>
        <b-form-group :label="$t('Name')" label-for="name">
          <b-form-input id="name" v-model="form.name" required maxlength="192"></b-form-input>
        </b-form-group>
        <b-row>
          <b-col md="4">
            <b-form-group :label="$t('Type')">
              <b-form-select v-model="form.type">
                <option value="percentage">{{ $t('Percentage') }}</option>
                <option value="fixed">{{ $t('Fixed') }}</option>
              </b-form-select>
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group :label="$t('Source')">
              <b-form-select v-model="form.source">
                <option value="sale_total">{{ $t('Sale_Total') }}</option>
                <option value="paid_amount">{{ $t('Paid_Amount') }}</option>
              </b-form-select>
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group :label="$t('Value')">
              <b-form-input v-model.number="form.value" type="number" step="0.01" min="0" required></b-form-input>
            </b-form-group>
          </b-col>
        </b-row>
        <b-row>
          <b-col md="4">
            <b-form-group :label="$t('Min_Threshold')">
              <b-form-input v-model="form.min_threshold" type="number" step="0.01" min="0"></b-form-input>
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group :label="$t('Max_Cap')">
              <b-form-input v-model="form.max_cap" type="number" step="0.01" min="0"></b-form-input>
            </b-form-group>
          </b-col>
          <b-col md="4">
            <b-form-group :label="$t('Priority')">
              <b-form-input v-model.number="form.priority" type="number" min="0"></b-form-input>
            </b-form-group>
          </b-col>
        </b-row>
        <b-form-group :label="$t('Applies_To')">
          <b-form-select v-model="form.applies_to">
            <option value="all_agents">{{ $t('All_Agents') }}</option>
            <option value="specific_agent">{{ $t('Specific_Agent') }}</option>
          </b-form-select>
        </b-form-group>
        <b-form-group v-if="form.applies_to === 'specific_agent'" :label="$t('Sales_Agent')">
          <v-select v-model="form.sales_agent_id" :reduce="a => a.id" :options="agentsList" label="name" :placeholder="$t('PleaseSelect')" />
        </b-form-group>
        <b-form-group><b-form-checkbox v-model="form.is_active">{{ $t('Active') }}</b-form-checkbox></b-form-group>
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
  metaInfo: { title: 'Commission Rules' },
  data() {
    return {
      isLoading: true,
      rules: [],
      totalRows: 0,
      serverParams: { sort: { field: 'priority', type: 'desc' }, page: 1, perPage: 10 },
      limit: '10',
      search: '',
      filterProgramId: null,
      programsList: [],
      agentsList: [],
      editMode: false,
      form: { commission_program_id: null, name: '', type: 'percentage', source: 'sale_total', value: 0, min_threshold: '', max_cap: '', applies_to: 'all_agents', sales_agent_id: null, priority: 0, is_active: true },
    };
  },
  computed: {
    ...mapGetters(['currentUserPermissions']),
    columns() {
      return [
        { label: this.$t('Name'), field: 'name' },
        { label: this.$t('Program'), field: 'program' },
        { label: this.$t('Type'), field: 'type' },
        { label: this.$t('Source'), field: 'source' },
        { label: this.$t('Agent'), field: 'agent' },
        { label: this.$t('Active'), field: 'is_active' },
        { label: this.$t('Priority'), field: 'priority' },
        { label: this.$t('Action'), field: 'actions', sortable: false },
      ];
    },
  },
  created() {
    this.loadPrograms();
    this.loadAgents();
    this.load();
  },
  methods: {
    loadPrograms() {
      axios.get('commission_programs', { params: { limit: '-1' } }).then((res) => {
        const d = res.data.data || res.data;
        this.programsList = (d.programs || []).map(p => ({ id: p.id, name: p.name }));
      }).catch(() => {});
    },
    loadAgents() {
      axios.get('sales_agents_list_for_select').then((res) => {
        const d = res.data.data || res.data;
        this.agentsList = Array.isArray(d) ? d : (d.agents || []);
      }).catch(() => {});
    },
    load(page = 1) {
      NProgress.start();
      const params = { page, limit: this.limit, SortField: this.serverParams.sort.field, SortType: this.serverParams.sort.type, search: this.search };
      if (this.filterProgramId) params.commission_program_id = this.filterProgramId;
      axios.get('commission_rules', { params }).then((res) => {
        const d = res.data.data || res.data;
        this.rules = d.rules || [];
        this.totalRows = d.totalRows || 0;
        NProgress.done();
        this.isLoading = false;
      }).catch(() => { NProgress.done(); this.isLoading = false; });
    },
    onPageChange({ currentPage }) { this.serverParams.page = currentPage; this.load(currentPage); },
    onPerPageChange({ currentPerPage }) { this.limit = String(currentPerPage); this.load(1); },
    onSortChange(params) { if (params.length) { this.serverParams.sort = { field: params[0].field, type: params[0].type }; this.load(1); } },
    onSearch({ searchTerm }) { this.search = searchTerm || ''; this.load(1); },
    openModal(row = null) {
      this.editMode = !!row;
      if (row) {
        this.form = { id: row.id, commission_program_id: row.commission_program_id, name: row.name, type: row.type, source: row.source, value: parseFloat(row.value), min_threshold: row.min_threshold || '', max_cap: row.max_cap || '', applies_to: row.applies_to, sales_agent_id: row.sales_agent_id || null, priority: row.priority || 0, is_active: !!row.is_active };
      } else this.resetForm();
      this.$bvModal.show('form_modal');
    },
    resetForm() {
      this.form = { commission_program_id: this.filterProgramId || null, name: '', type: 'percentage', source: 'sale_total', value: 0, min_threshold: '', max_cap: '', applies_to: 'all_agents', sales_agent_id: null, priority: 0, is_active: true };
      delete this.form.id;
    },
    submit() {
      const url = this.editMode ? `commission_rules/${this.form.id}` : 'commission_rules';
      const method = this.editMode ? 'put' : 'post';
      const payload = { ...this.form, min_threshold: this.form.min_threshold || null, max_cap: this.form.max_cap || null, sales_agent_id: this.form.applies_to === 'specific_agent' ? this.form.sales_agent_id : null };
      if (this.editMode) delete payload.id;
      axios[method](url, payload).then(() => {
        this.makeToast('success', this.$t('Success'));
        this.$bvModal.hide('form_modal');
        this.load(this.serverParams.page);
      }).catch((e) => this.makeToast('danger', e.response?.data?.message || this.$t('Error')));
    },
    confirmDelete(row) {
      this.$bvModal.msgBoxConfirm(this.$t('Confirm_delete')).then((ok) => {
        if (ok) axios.delete(`commission_rules/${row.id}`).then(() => { this.makeToast('success', this.$t('Deleted')); this.load(this.serverParams.page); }).catch((e) => this.makeToast('danger', e.response?.data?.message || this.$t('Error')));
      });
    },
    makeToast(variant, msg) { this.$bvToast.toast(msg, { title: this.$t('Notice'), variant, solid: true }); },
  },
};
</script>
