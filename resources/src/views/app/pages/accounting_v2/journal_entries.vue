<template>
  <!-- NEW FEATURE - SAFE ADDITION -->
  <div class="main-content">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-1">{{ $t('Journal_Entries_Title') }}</h4>
        <div class="text-muted small">{{ $t('Journal_Entries_Subtitle') }}</div>
      </div>
    </div>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div class="card wrapper" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: $t('Next'), prevLabel: $t('Prev') }"
        styleClass="table-hover tableOne vgt-table"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="openCreate()" class="btn-rounded" variant="btn btn-primary btn-icon m-1">
            <lucide-icon name="plus" />
            {{ $t('Add') }}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'status'">
            <span class="badge" :class="props.row.status==='posted' ? 'badge-success' : 'badge-warning'">{{ statusLabel(props.row.status) }}</span>
          </span>
          <span v-else-if="props.column.field == 'actions'">
            <a @click="view(props.row)" :title="$t('View')" v-b-tooltip.hover>
              <lucide-icon class="text-25 text-primary cursor-pointer mr-2" name="eye" />
            </a>
            <a v-if="props.row.status!=='posted'" @click="post(props.row)" :title="$t('Post')" v-b-tooltip.hover :disabled="postingId===props.row.id">
              <lucide-icon class="text-25 text-success cursor-pointer mr-2" name="check" v-if="postingId!==props.row.id" />
              <span v-else class="spinner-border spinner-border-sm text-success mr-2"></span>
            </a>
            <a v-if="props.row.status!=='posted'" @click="tryEdit(props.row)" :title="$t('Edit')" v-b-tooltip.hover>
              <lucide-icon class="text-25 text-primary" name="pencil" />
            </a>
            <a v-if="props.row.status!=='posted'" @click="tryDelete(props.row)" :title="$t('Delete')" v-b-tooltip.hover>
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </div>

    <!-- Create / Edit Modal -->
    <div class="modal fade" tabindex="-1" role="dialog" :class="{ show: showModal }" :style="{ display: showModal ? 'block' : 'none' }">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ editing ? $t('Edit_Entry') : $t('New_Entry') }}</h5>
            <button type="button" class="close" @click="closeModal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>{{ $t('Date') }}</label>
                <input v-model="entry.date" type="date" class="form-control" />
              </div>
              <div class="form-group col-md-8">
                <label>{{ $t('Description') }}</label>
                <input v-model.trim="entry.description" class="form-control" :placeholder="$t('Description_Placeholder')" />
              </div>
            </div>

            <div class="table-responsive border rounded">
              <table class="table table-sm mb-0">
                <thead>
                  <tr class="bg-light">
                    <th style="width: 40%;">{{ $t('Account') }}</th>
                    <th style="width: 20%;" class="text-right">{{ $t('Debit') }}</th>
                    <th style="width: 20%;" class="text-right">{{ $t('Credit') }}</th>
                    <th style="width: 18%;">{{ $t('Memo') }}</th>
                    <th style="width: 2%;"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(l,idx) in entry.lines" :key="idx">
                    <td>
                      <select v-model="l.coa_id" class="form-control" :class="{ 'is-invalid': showErrors && !l.coa_id }">
                        <option :value="null" disabled>{{ $t('Select_Account') }}</option>
                        <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                      </select>
                    </td>
                    <td><input v-model.number="l.debit" @input="onAmountChange(idx, 'debit')" type="text" min="0" step="0.01" class="form-control text-right" :class="{ 'is-invalid': showErrors && !validRow(l) }" /></td>
                    <td><input v-model.number="l.credit" @input="onAmountChange(idx, 'credit')" type="text" min="0" step="0.01" class="form-control text-right" :class="{ 'is-invalid': showErrors && !validRow(l) }" /></td>
                    <td><input v-model.trim="l.memo" class="form-control" /></td>
                    <td class="text-right">
                      <button class="btn btn-link p-0" @click="removeLine(idx)" :disabled="entry.lines.length <= 1"><lucide-icon class="text-danger" name="x" /></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2">
              <button class="btn btn-outline-secondary btn-sm" @click="addLine"><lucide-icon name="plus" /> {{ $t('Add_Line') }}</button>
              <div>
                <span class="mr-3">{{ $t('Total_Debit') }}: <strong>{{ toMoney(totals.debit) }}</strong></span>
                <span>{{ $t('Total_Credit') }}: <strong>{{ toMoney(totals.credit) }}</strong></span>
                <span class="ml-3 badge" :class="balanced ? 'badge-success' : 'badge-warning'">{{ balanced ? $t('Balanced') : $t('Not_Balanced') }}</span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" @click="closeModal" :disabled="btnLoading">{{ $t('Cancel') }}</button>
            <button type="button" class="btn btn-primary" :disabled="btnLoading || !entry.date || !linesValid" @click="save">
              <span v-if="btnLoading" class="spinner-border spinner-border-sm mr-2"></span>
              <span>{{ btnLoading ? $t('Saving') : $t('Save') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" tabindex="-1" role="dialog" :class="{ show: showView }" :style="{ display: showView ? 'block' : 'none' }">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ $t('Journal_Number', { number: current && current.id }) }}</h5>
            <button type="button" class="close" @click="showView=false"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <div class="mb-2"><strong>{{ $t('Date') }}:</strong> {{ current && current.date }}</div>
            <div class="mb-3"><strong>{{ $t('Description') }}:</strong> {{ current && (current.description || '-') }}</div>
            <div class="table-responsive border rounded">
              <table class="table table-sm mb-0">
                <thead><tr class="bg-light"><th>{{ $t('Account') }}</th><th class="text-right">{{ $t('Debit') }}</th><th class="text-right">{{ $t('Credit') }}</th><th>{{ $t('Memo') }}</th></tr></thead>
                <tbody>
                  <tr v-for="(l,idx) in (current && current.lines || [])" :key="idx">
                    <td>{{ accountName(l.coa_id) }}</td>
                    <td class="text-right">{{ toMoney(l.debit) }}</td>
                    <td class="text-right">{{ toMoney(l.credit) }}</td>
                    <td>{{ l.memo || '' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" @click="showView=false">{{ $t('Close') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  name: "JournalEntriesV2",
  data() {
    return {
      isLoading: true,
      rows: [],
      accounts: [],
      totalRows: "",
      serverParams: {
        columnFilters: {},
        sort: { field: "date", type: "desc" },
        page: 1,
        perPage: 10
      },
      search: "",
      limit: "10",
      showModal: false,
      editing: false,
      entry: { id: null, date: "", description: "", lines: [] },
      showView: false,
      current: null,
      btnLoading: false,
      postingId: null,
      showErrors: false,
    };
  },
  computed: {
    columns() {
      return [
        { label: this.$t('Date'), field: 'date', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Description'), field: 'description', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Source'), field: 'reference_type', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Status'), field: 'status', sortable: false, tdClass: 'text-center', thClass: 'text-center' },
        { label: this.$t('Action'), field: 'actions', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
      ];
    },
    totals() {
      return this.entry.lines.reduce((acc,l) => {
        acc.debit += Number(l.debit || 0); acc.credit += Number(l.credit || 0); return acc;
      }, { debit: 0, credit: 0 });
    },
    balanced() { return Math.abs(this.totals.debit - this.totals.credit) < 0.0001; },
    linesValid() { return this.entry.lines.every(l => this.validRow(l)); }
  },
  created() {
    this.Get_Journals(1);
    this.fetchAccounts();
  },
  methods: {
    updateParams(newProps) { this.serverParams = Object.assign({}, this.serverParams, newProps); },
    onPageChange({ currentPage }) { if (this.serverParams.page !== currentPage) { this.updateParams({ page: currentPage }); this.Get_Journals(currentPage); } },
    onPerPageChange({ currentPerPage }) { if (this.limit !== currentPerPage) { this.limit = currentPerPage; this.updateParams({ page: 1, perPage: currentPerPage }); this.Get_Journals(1); } },
    onSortChange(params) { if (!params || !params.length) return; this.updateParams({ sort: { type: params[0].type, field: params[0].field } }); this.Get_Journals(this.serverParams.page); },
    onSearch(value) { this.search = value.searchTerm; this.Get_Journals(this.serverParams.page); },
    async Get_Journals(page) {
      NProgress.start(); NProgress.set(0.1);
      axios.get(
        "/accounting/v2/journal-entries?page=" + page +
        "&SortField=" + this.serverParams.sort.field +
        "&SortType=" + this.serverParams.sort.type +
        "&search=" + this.search +
        "&limit=" + this.limit
      )
      .then(({data}) => {
        // paginator shape (data, total) or custom
        this.rows = (data && (data.data || data.rows || [])) || [];
        this.totalRows = (data && (data.total ?? data.totalRows ?? this.rows.length)) || 0;
        NProgress.done(); this.isLoading = false;
      })
      .catch(() => { NProgress.done(); this.isLoading = false; });
    },
    async fetchAccounts() {
      try {
        const { data } = await axios.get("/accounting/v2/coa", { params: { limit: -1, SortField: 'code', SortType: 'asc', active: 1 } });
        this.accounts = (data && data.data) || [];
      } catch (e) {}
    },
    openCreate() {
      this.editing = false;
      this.entry = { id: null, date: new Date().toISOString().slice(0,10), description: "", lines: [ { coa_id: null, debit: 0, credit: 0, memo: "" }, { coa_id: null, debit: 0, credit: 0, memo: "" } ] };
      this.btnLoading = false;
      this.showErrors = false;
      this.showModal = true;
    },
    view(j) { this.current = j; this.showView = true; },
    tryEdit(j) {
      if (j.status === 'posted') return;
      this.editing = true;
      this.entry = { id: j.id, date: j.date, description: j.description || "", lines: (j.lines || []).map(l => ({ coa_id: l.coa_id, debit: l.debit, credit: l.credit, memo: l.memo })) };
      this.btnLoading = false;
      this.showErrors = false;
      this.showModal = true;
    },
    tryDelete(j) {
      if (j.status === 'posted') return;
      this.$swal({ title: this.$t('Delete'), text: this.$t('Delete_Draft_Entry_Question'), type: 'warning', showCancelButton: true, confirmButtonText: this.$t('Delete') })
        .then(r => { if (r.value) this.remove(j); });
    },
    addLine() { this.entry.lines.push({ coa_id: null, debit: 0, credit: 0, memo: "" }); },
    closeModal() { this.btnLoading = false; this.showErrors = false; this.showModal = false; },
    async save() {
      try {
        this.btnLoading = true;
        this.showErrors = true;
        if (!this.linesValid) { this.btnLoading = false; return this.makeToast('danger', this.$t('Complete_Lines_Message'), this.$t('Validation')); }
        const payload = { date: this.entry.date, description: this.entry.description, lines: this.entry.lines };
        if (this.editing && this.entry.id) {
          await axios.put(`/accounting/v2/journal-entries/${this.entry.id}`, payload);
          this.makeToast('success', this.$t('Entry_Updated'), this.$t('Success'));
        } else {
          await axios.post(`/accounting/v2/journal-entries`, payload);
          this.makeToast('success', this.$t('Entry_Created_Draft'), this.$t('Success'));
        }
        this.showModal = false; this.Get_Journals(this.serverParams.page);
      } catch (e) { this.makeToast('danger', this.$t('Operation_Failed'), this.$t('Error')); }
      finally { this.btnLoading = false; }
    },
    async remove(j) {
      try { await axios.delete(`/accounting/v2/journal-entries/${j.id}`); this.makeToast('success', this.$t('Deleted_Successfully'), this.$t('Success')); this.Get_Journals(this.serverParams.page); } catch (e) { this.makeToast('danger', this.$t('Delete_Failed'), this.$t('Error')); }
    },
    async post(j) {
      this.postingId = j.id;
      try {
        await axios.post(`/accounting/v2/journal-entries/${j.id}/post`);
        this.makeToast('success', this.$t('Posted_Successfully'), this.$t('Success'));
        this.Get_Journals(this.serverParams.page);
      } catch (e) {
        const fallback = this.$t('Post_Failed');
        const msg = (e && e.response && e.response.data && (e.response.data.message || e.response.data.error)) || fallback;
        this.makeToast('danger', msg, this.$t('Error'));
      } finally { this.postingId = null; }
    },
    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing toLocaleString behavior to preserve current behavior.
    toMoney(v) {
      try {
        const n = parseFloat(v || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, 2, effectiveKey);
      } catch (e) {
        const n = parseFloat(v || 0);
        return n.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
      }
    },
    accountName(id) { const a = this.accounts.find(x => x.id === id); return a ? `${a.code} — ${a.name}` : id; },
    statusLabel(status) {
      if (!status) return '';
      if (status === 'posted') return this.$t('Journal_Status_Posted');
      if (status === 'draft') return this.$t('Journal_Status_Draft');
      return this.$t(status);
    },
    onAmountChange(idx, field) {
      const l = this.entry.lines[idx];
      const val = Number(l[field] || 0);
      if (val < 0 || isNaN(val)) l[field] = 0;
      if (field === 'debit' && val > 0) l.credit = 0;
      if (field === 'credit' && val > 0) l.debit = 0;
    },
    validRow(l) {
      const debit = Number(l.debit || 0);
      const credit = Number(l.credit || 0);
      if (!l.coa_id) return false;
      const hasOne = (debit > 0) !== (credit > 0);
      const nonNegative = debit >= 0 && credit >= 0;
      return hasOne && nonNegative;
    },
    removeLine(idx) {
      if (this.entry.lines.length <= 1) { return this.makeToast('warning', this.$t('At_Least_One_Line'), this.$t('Notice')); }
      this.entry.lines.splice(idx,1);
    },
    makeToast(variant, msg, title) { this.$root.$bvToast.toast(msg, { title, variant, solid: true }); }
  }
};
</script>

<style scoped>
.modal { background: rgba(0,0,0,.35); }
</style>



