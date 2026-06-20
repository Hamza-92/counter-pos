<template>
  <div>
    <breadcumb :page="$t('Commission_Receipts')" :folder="$t('Commissions')" />
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <b-card class="shadow-soft border-0">
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="receipts"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
          @on-sort-change="onSortChange"
          @on-search="onSearch"
          :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
          :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
          styleClass="tableOne table-hover vgt-table"
        >
          <div slot="table-actions" class="mt-2 mb-3">
            <b-button v-if="currentUserPermissions && currentUserPermissions.includes('commissions_add')" variant="primary" size="sm" @click="openCreateModal()">
              <lucide-icon name="plus" /> {{ $t('Add') }}
            </b-button>
          </div>
          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field === 'paid_at'">{{ formatDate(props.row.paid_at) }}</span>
            <span v-else-if="props.column.field === 'amount'">{{ formatMoney(props.row.amount) }}</span>
            <span v-else-if="props.column.field === 'agent'">{{ props.row.sales_agent ? props.row.sales_agent.name : '—' }}</span>
            <span v-else-if="props.column.field === 'actions'">
              <b-button variant="link" size="sm" class="p-0" @click="viewReceipt(props.row)"><lucide-icon class="text-info" name="eye" /></b-button>
            </span>
            <span v-else>{{ props.formattedRow[props.column.field] }}</span>
          </template>
        </vue-good-table>
      </b-card>
    </div>
    <b-modal size="lg" :title="$t('Commission_Receipt')" hide-footer id="view_modal">
      <div v-if="viewReceiptData">
        <p><strong>Ref:</strong> {{ viewReceiptData.Ref }}</p>
        <p><strong>Agent:</strong> {{ viewReceiptData.sales_agent ? viewReceiptData.sales_agent.name : '—' }}</p>
        <p><strong>Amount:</strong> {{ formatMoney(viewReceiptData.amount) }}</p>
        <p><strong>Paid At:</strong> {{ formatDate(viewReceiptData.paid_at) }}</p>
      </div>
    </b-modal>

    <b-modal :title="$t('Add') + ' ' + $t('Commission_Receipt')" hide-footer id="create_modal" @show="onCreateModalShow" @hidden="resetCreateForm">
      <b-form @submit.prevent="submitCreateReceipt">
        <b-form-group :label="$t('Sales_Agent')" label-for="create_agent">
          <v-select
            id="create_agent"
            v-model="createForm.sales_agent_id"
            :reduce="a => a.id"
            :options="agentsList"
            label="name"
            :placeholder="$t('PleaseSelect')"
            @input="onCreateAgentSelect"
          />
        </b-form-group>
        <b-form-group v-if="createForm.sales_agent_id" :label="$t('Approved_Commissions') || 'Approved commissions'">
          <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
            <label v-for="c in approvedCommissions" :key="c.id" class="checkbox checkbox-outline-primary d-block mb-1">
              <input type="checkbox" :value="c.id" v-model="createForm.commission_ids">
              <span>{{ c.sale ? c.sale.Ref : '' }} — {{ formatMoney(c.commission_amount) }}</span>
              <span class="checkmark"></span>
            </label>
            <span v-if="!approvedCommissions.length" class="text-muted">{{ $t('NodataAvailable') }}</span>
          </div>
          <small class="text-muted">{{ $t('Total') }}: {{ formatMoney(createForm.amount) }}</small>
        </b-form-group>
        <b-form-group :label="$t('Ref')" label-for="create_ref">
          <b-form-input id="create_ref" v-model="createForm.Ref" maxlength="192" />
        </b-form-group>
        <b-form-group :label="$t('Amount')" label-for="create_amount">
          <b-form-input id="create_amount" v-model.number="createForm.amount" type="number" step="0.01" min="0" required />
        </b-form-group>
        <b-form-group :label="$t('Paid_At')" label-for="create_paid_at">
          <b-form-input id="create_paid_at" v-model="createForm.paid_at" type="date" required />
        </b-form-group>
        <b-form-group :label="$t('Payment_Method') || 'Payment method'" label-for="create_payment">
          <v-select
            id="create_payment"
            v-model="createForm.payment_method_id"
            :reduce="p => p.id"
            :options="paymentMethodsList"
            label="name"
            :placeholder="$t('PleaseSelect')"
          />
        </b-form-group>
        <b-form-group :label="$t('Notes')" label-for="create_notes">
          <b-form-textarea id="create_notes" v-model="createForm.notes" rows="2" />
        </b-form-group>
        <div class="d-flex justify-content-end mt-3">
          <b-button type="button" variant="secondary" @click="$bvModal.hide('create_modal')">{{ $t('Cancel') }}</b-button>
          <b-button type="submit" variant="primary" class="ml-2" :disabled="!createForm.sales_agent_id || !createForm.commission_ids.length || !createForm.amount">{{ $t('Submit') }}</b-button>
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
      receipts: [],
      totalRows: 0,
      serverParams: { sort: { field: 'paid_at', type: 'desc' }, page: 1, perPage: 10 },
      limit: '10',
      search: '',
      viewReceiptData: null,
      agentsList: [],
      paymentMethodsList: [],
      approvedCommissions: [],
      createForm: {
        sales_agent_id: null,
        commission_ids: [],
        Ref: '',
        amount: 0,
        paid_at: '',
        payment_method_id: null,
        notes: '',
      },
    };
  },
  watch: {
    'createForm.commission_ids'() {
      this.updateCreateAmountFromSelection();
    },
  },
  computed: {
    ...mapGetters(['currentUserPermissions']),
    columns() {
      return [
        { label: this.$t('Ref'), field: 'Ref' },
        { label: this.$t('Sales_Agent'), field: 'agent' },
        { label: this.$t('Amount'), field: 'amount' },
        { label: this.$t('Paid_At'), field: 'paid_at' },
        { label: this.$t('Action'), field: 'actions', sortable: false },
      ];
    },
  },
  created() { this.load(); },
  methods: {
    load(page) {
      page = page || 1;
      NProgress.start();
      axios.get('commission_receipts', { params: { page, limit: this.limit, SortField: this.serverParams.sort.field, SortType: this.serverParams.sort.type, search: this.search } }).then((res) => {
        const d = res.data.data || res.data;
        this.receipts = d.receipts || [];
        this.totalRows = d.totalRows || 0;
        NProgress.done();
        this.isLoading = false;
      }).catch(() => { NProgress.done(); this.isLoading = false; });
    },
    onPageChange(p) { this.load(p.currentPage); },
    onPerPageChange(p) { this.limit = String(p.currentPerPage); this.load(1); },
    onSortChange(params) { if (params.length) { this.serverParams.sort = { field: params[0].field, type: params[0].type }; this.load(1); } },
    onSearch(p) { this.search = p.searchTerm || ''; this.load(1); },
    formatDate(v) { return v ? new Date(v).toLocaleDateString() : '—'; },
    formatMoney(v) { return v != null ? Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'; },
    viewReceipt(row) {
      axios.get('commission_receipts/' + row.id).then((res) => {
        this.viewReceiptData = (res.data.data || res.data);
        this.$bvModal.show('view_modal');
      });
    },
    openCreateModal() {
      this.$bvModal.show('create_modal');
    },
    onCreateModalShow() {
      this.resetCreateForm();
      axios.get('commission_receipts/new_ref').then((res) => {
        const d = res.data.data || res.data;
        if (d && d.Ref) this.createForm.Ref = d.Ref;
      }).catch(() => {});
      axios.get('sales_agents_list_for_select').then((res) => {
        const d = res.data.data || res.data;
        this.agentsList = Array.isArray(d) ? d : (d.agents || []);
      }).catch(() => { this.agentsList = []; });
      axios.get('payment_methods', { params: { limit: '-1' } }).then((res) => {
        this.paymentMethodsList = (res.data.methods || res.data.data?.methods || []);
      }).catch(() => { this.paymentMethodsList = []; });
      const today = new Date().toISOString().slice(0, 10);
      this.createForm.paid_at = today;
    },
    onCreateAgentSelect() {
      this.createForm.commission_ids = [];
      this.approvedCommissions = [];
      if (!this.createForm.sales_agent_id) return;
      axios.get('commission_report', {
        params: { sales_agent_id: this.createForm.sales_agent_id, status: 'approved', limit: '-1' },
      }).then((res) => {
        const d = res.data.data || res.data;
        this.approvedCommissions = d.commissions || [];
      }).catch(() => { this.approvedCommissions = []; });
    },
    updateCreateAmountFromSelection() {
      let sum = 0;
      this.createForm.commission_ids.forEach((id) => {
        const c = this.approvedCommissions.find((x) => x.id === id);
        if (c && c.commission_amount != null) sum += Number(c.commission_amount);
      });
      this.createForm.amount = Math.round(sum * 100) / 100;
    },
    resetCreateForm() {
      this.createForm = {
        sales_agent_id: null,
        commission_ids: [],
        Ref: '',
        amount: 0,
        paid_at: new Date().toISOString().slice(0, 10),
        payment_method_id: null,
        notes: '',
      };
      this.approvedCommissions = [];
    },
    submitCreateReceipt() {
      if (!this.createForm.sales_agent_id || !this.createForm.commission_ids.length || this.createForm.amount == null) return;
      NProgress.start();
      axios.post('commission_receipts', {
        sales_agent_id: this.createForm.sales_agent_id,
        commission_ids: this.createForm.commission_ids,
        Ref: this.createForm.Ref || undefined,
        amount: this.createForm.amount,
        paid_at: this.createForm.paid_at,
        payment_method_id: this.createForm.payment_method_id || undefined,
        notes: this.createForm.notes || undefined,
      }).then(() => {
        NProgress.done();
        this.$bvModal.hide('create_modal');
        this.$toast.success(this.$t('Created_successfully') || 'Created successfully');
        this.load(1);
      }).catch((err) => {
        NProgress.done();
        const msg = (err.response && err.response.data && err.response.data.message) || err.message || 'Error';
        this.$toast.error(msg);
      });
    },
  },
};
</script>
