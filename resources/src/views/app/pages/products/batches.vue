<template>
  <div class="main-content">
    <breadcumb :page="$t('Batches')" :folder="$t('Products')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper" v-if="!isLoading">
      <!-- Filters row -->
      <b-row class="mb-3">
        <b-col md="3">
          <b-form-group :label="$t('Warehouse')">
            <b-form-select v-model="filters.warehouse_id" @change="onFilterChange">
              <option :value="''">{{ $t('All') }}</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </b-form-select>
          </b-form-group>
        </b-col>
        <b-col md="3">
          <b-form-group :label="$t('Status')">
            <b-form-select v-model="filters.status" @change="onFilterChange">
              <option value="all">{{ $t('All') }}</option>
              <option value="active">{{ $t('Batch_Status_active') }}</option>
              <option value="quarantined">{{ $t('Batch_Status_quarantined') }}</option>
              <option value="expired">{{ $t('Batch_Status_expired') }}</option>
              <option value="written_off">{{ $t('Batch_Status_written_off') }}</option>
            </b-form-select>
          </b-form-group>
        </b-col>
        <b-col md="3">
          <b-form-group :label="$t('Expiry_Window')">
            <b-form-select v-model="filters.expiry_window" @change="onFilterChange">
              <option value="all">{{ $t('All') }}</option>
              <option value="expired">{{ $t('Expired') }}</option>
              <option value="near">{{ $t('Near_Expiry') }}</option>
              <option value="valid">{{ $t('Valid') }}</option>
            </b-form-select>
          </b-form-group>
        </b-col>
        <b-col md="3" class="d-flex align-items-end">
          <small class="text-muted">
            {{ $t('Expiry_Warning_Days') }}: {{ expiryWarningDays }}
          </small>
        </b-col>
      </b-row>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="batches"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          enabled: true,
          placeholder: $t('Search_this_table'),
        }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="table-hover tableOne vgt-table"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'product'">
            <div>
              <strong>{{ props.row.product_name }}</strong>
              <small v-if="props.row.product_code" class="text-muted ml-1">[{{ props.row.product_code }}]</small>
            </div>
            <small v-if="props.row.generic_name" class="text-muted">
              {{ props.row.generic_name }}
              <span v-if="props.row.strength"> · {{ props.row.strength }}</span>
              <span v-if="props.row.dosage_form"> · {{ props.row.dosage_form }}</span>
            </small>
            <div v-if="props.row.variant_name">
              <small class="badge badge-light">{{ props.row.variant_name }}</small>
            </div>
          </span>

          <span v-else-if="props.column.field == 'expiry_date'">
            <span v-if="props.row.expiry_date">
              {{ props.row.expiry_date }}
              <br>
              <small
                :class="{
                  'text-danger': props.row.expiry_bucket === 'expired',
                  'text-warning': props.row.expiry_bucket === 'near',
                  'text-success': props.row.expiry_bucket === 'valid',
                }"
              >
                <span v-if="props.row.expiry_bucket === 'expired'">
                  {{ $t('Expired') }} ({{ Math.abs(props.row.days_to_expiry) }}d)
                </span>
                <span v-else-if="props.row.expiry_bucket === 'near'">
                  {{ $t('Expires_in') }} {{ props.row.days_to_expiry }}d
                </span>
                <span v-else>
                  {{ props.row.days_to_expiry }}d
                </span>
              </small>
            </span>
            <span v-else class="text-muted">—</span>
          </span>

          <span v-else-if="props.column.field == 'status'">
            <span class="badge" :class="statusBadge(props.row.status)">
              {{ $t('Batch_Status_' + props.row.status) }}
            </span>
          </span>

          <span v-else-if="props.column.field == 'qty'">
            {{ formatNumber(props.row.qty) }}
          </span>

          <span v-else-if="props.column.field == 'unit_cost'">
            <span v-if="props.row.unit_cost !== null">{{ formatNumber(props.row.unit_cost) }}</span>
            <span v-else class="text-muted">—</span>
          </span>

          <span v-else-if="props.column.field == 'actions'">
            <a
              v-if="canManage"
              @click="openEdit(props.row)"
              :title="$t('Edit')"
              v-b-tooltip.hover
            >
              <lucide-icon class="text-25 text-success" name="pencil" />
            </a>
            <a
              v-if="canWriteOff && props.row.status !== 'written_off'"
              @click="openWriteOff(props.row)"
              :title="$t('Write_Off')"
              v-b-tooltip.hover
            >
              <lucide-icon class="text-25 text-warning" name="trash-2" />
            </a>
            <a
              v-if="canWriteOff"
              @click="deleteBatch(props.row.id)"
              :title="$t('Delete')"
              v-b-tooltip.hover
            >
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </b-card>

    <!-- Edit modal -->
    <validation-observer ref="Edit_batch">
      <b-modal hide-footer size="md" id="Edit_batch" :title="$t('Edit_Batch')">
        <b-form @submit.prevent="submitEdit">
          <b-row>
            <b-col md="12">
              <b-form-group :label="$t('Product')">
                <b-form-input :value="editing.product_name" disabled></b-form-input>
              </b-form-group>
            </b-col>

            <b-col md="6">
              <validation-provider
                name="batch_no"
                :rules="{ required: true, max: 100 }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Batch_No') + ' *'">
                  <b-form-input
                    v-model="editing.batch_no"
                    :state="getValidationState(validationContext)"
                  ></b-form-input>
                  <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <b-col md="6">
              <b-form-group :label="$t('Status')">
                <b-form-select v-model="editing.status">
                  <option value="active">{{ $t('Batch_Status_active') }}</option>
                  <option value="quarantined">{{ $t('Batch_Status_quarantined') }}</option>
                  <option value="expired">{{ $t('Batch_Status_expired') }}</option>
                  <option value="written_off">{{ $t('Batch_Status_written_off') }}</option>
                </b-form-select>
              </b-form-group>
            </b-col>

            <b-col md="6">
              <b-form-group :label="$t('Expiry_Date')">
                <b-form-input type="date" v-model="editing.expiry_date"></b-form-input>
              </b-form-group>
            </b-col>

            <b-col md="6">
              <b-form-group :label="$t('Mfg_Date')">
                <b-form-input type="date" v-model="editing.mfg_date"></b-form-input>
              </b-form-group>
            </b-col>

            <b-col md="6">
              <b-form-group :label="$t('Quantity')">
                <b-form-input type="number" step="0.01" min="0" v-model.number="editing.qty"></b-form-input>
                <small class="text-muted">{{ $t('Batch_Qty_Edit_Hint') }}</small>
              </b-form-group>
            </b-col>

            <b-col md="6">
              <b-form-group :label="$t('UnitCost')">
                <b-form-input type="number" step="0.0001" min="0" v-model.number="editing.unit_cost"></b-form-input>
              </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Note')">
                <b-form-textarea rows="2" v-model="editing.notes"></b-form-textarea>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-2">
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing">
                <lucide-icon class="me-2 font-weight-bold" name="check" /> {{ $t('submit') }}
              </b-button>
              <div v-once class="typo__p" v-if="SubmitProcessing">
                <div class="spinner sm spinner-primary mt-3"></div>
              </div>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>

    <!-- Write-off modal -->
    <b-modal hide-footer size="md" id="WriteOff_batch" :title="$t('Write_Off')">
      <b-form @submit.prevent="submitWriteOff">
        <b-row>
          <b-col md="12">
            <b-alert show variant="warning">
              {{ $t('Write_Off_Confirm', {
                batch: writingOff.batch_no,
                product: writingOff.product_name,
                qty: writingOff.qty
              }) }}
            </b-alert>
          </b-col>
          <b-col md="12">
            <b-form-group :label="$t('Reason')">
              <b-form-textarea rows="3" v-model="writingOff.reason"></b-form-textarea>
            </b-form-group>
          </b-col>
          <b-col md="12">
            <b-button variant="warning" type="submit" :disabled="SubmitProcessing">
              <lucide-icon class="me-2" name="trash-2" /> {{ $t('Write_Off') }}
            </b-button>
          </b-col>
        </b-row>
      </b-form>
    </b-modal>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";

export default {
  metaInfo: {
    title: "Batches"
  },
  data() {
    return {
      isLoading: true,
      SubmitProcessing: false,
      serverParams: {
        sort: { field: "expiry_date", type: "asc" },
        page: 1,
        perPage: 10
      },
      totalRows: 0,
      search: "",
      limit: "10",
      batches: [],
      warehouses: [],
      expiryWarningDays: 90,
      filters: {
        warehouse_id: "",
        status: "all",
        expiry_window: "all"
      },
      editing: {
        id: null,
        product_name: "",
        batch_no: "",
        expiry_date: "",
        mfg_date: "",
        qty: 0,
        unit_cost: null,
        status: "active",
        notes: ""
      },
      writingOff: {
        id: null,
        batch_no: "",
        product_name: "",
        qty: 0,
        reason: ""
      }
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions"]),
    canManage() {
      const perms = this.currentUserPermissions || [];
      return perms.includes('manage_batches') || perms.includes('batch_manage');
    },
    canWriteOff() {
      const perms = this.currentUserPermissions || [];
      return perms.includes('writeoff_batches') || perms.includes('batch_writeoff');
    },
    columns() {
      return [
        { label: this.$t('Product'), field: 'product', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Batch_No'), field: 'batch_no', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Warehouse'), field: 'warehouse_name', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Expiry_Date'), field: 'expiry_date', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Quantity'), field: 'qty', tdClass: 'text-right', thClass: 'text-right' },
        { label: this.$t('UnitCost'), field: 'unit_cost', tdClass: 'text-right', thClass: 'text-right' },
        { label: this.$t('Status'), field: 'status', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Action'), field: 'actions', sortable: false, tdClass: 'text-left', thClass: 'text-left' }
      ];
    }
  },
  methods: {
    statusBadge(status) {
      switch (status) {
        case 'active': return 'badge-success';
        case 'quarantined': return 'badge-warning';
        case 'expired': return 'badge-danger';
        case 'written_off': return 'badge-secondary';
        default: return 'badge-light';
      }
    },

    formatNumber(v) {
      if (v === null || v === undefined || v === '') return '';
      const n = Number(v);
      if (Number.isNaN(n)) return v;
      return Number.isInteger(n) ? n.toString() : n.toFixed(2);
    },

    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    },

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.fetch(currentPage);
      }
    },
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.fetch(1);
      }
    },
    onSortChange(params) {
      this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
      this.fetch(this.serverParams.page);
    },
    onSearch(value) {
      this.search = value.searchTerm;
      this.fetch(1);
    },
    onFilterChange() {
      this.updateParams({ page: 1 });
      this.fetch(1);
    },

    fetch(page) {
      NProgress.start();
      NProgress.set(0.1);
      const params = {
        page: page || 1,
        limit: this.limit,
        SortField: this.serverParams.sort.field,
        SortType: this.serverParams.sort.type,
        search: this.search || '',
        status: this.filters.status,
        expiry_window: this.filters.expiry_window
      };
      if (this.filters.warehouse_id !== '') {
        params.warehouse_id = this.filters.warehouse_id;
      }
      axios
        .get('product_batches', { params })
        .then(response => {
          this.batches = response.data.batches || [];
          this.totalRows = response.data.totalRows || 0;
          this.warehouses = response.data.warehouses || [];
          if (response.data.expiry_warning_days) {
            this.expiryWarningDays = response.data.expiry_warning_days;
          }
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => { this.isLoading = false; }, 500);
        });
    },

    openEdit(row) {
      this.editing = {
        id: row.id,
        product_name: row.product_name,
        batch_no: row.batch_no,
        expiry_date: row.expiry_date || '',
        mfg_date: row.mfg_date || '',
        qty: row.qty,
        unit_cost: row.unit_cost,
        status: row.status,
        notes: row.notes || ''
      };
      this.$bvModal.show('Edit_batch');
    },

    submitEdit() {
      this.$refs.Edit_batch.validate().then(success => {
        if (!success) {
          this.makeToast('danger', this.$t('Please_fill_the_form_correctly'), this.$t('Failed'));
          return;
        }
        this.SubmitProcessing = true;
        const payload = {
          batch_no: this.editing.batch_no,
          expiry_date: this.editing.expiry_date || null,
          mfg_date: this.editing.mfg_date || null,
          qty: this.editing.qty,
          unit_cost: this.editing.unit_cost,
          status: this.editing.status,
          notes: this.editing.notes
        };
        axios
          .put('product_batches/' + this.editing.id, payload)
          .then(() => {
            this.SubmitProcessing = false;
            this.$bvModal.hide('Edit_batch');
            this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'));
            this.fetch(this.serverParams.page);
          })
          .catch(() => {
            this.SubmitProcessing = false;
            this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'));
          });
      });
    },

    openWriteOff(row) {
      this.writingOff = {
        id: row.id,
        batch_no: row.batch_no,
        product_name: row.product_name,
        qty: row.qty,
        reason: ''
      };
      this.$bvModal.show('WriteOff_batch');
    },

    submitWriteOff() {
      this.SubmitProcessing = true;
      axios
        .post('product_batches/' + this.writingOff.id + '/writeoff', {
          reason: this.writingOff.reason
        })
        .then(() => {
          this.SubmitProcessing = false;
          this.$bvModal.hide('WriteOff_batch');
          this.makeToast('success', this.$t('Successfully_Updated'), this.$t('Success'));
          this.fetch(this.serverParams.page);
        })
        .catch(() => {
          this.SubmitProcessing = false;
          this.makeToast('danger', this.$t('InvalidData'), this.$t('Failed'));
        });
    },

    deleteBatch(id) {
      this.$swal({
        title: this.$t('Delete_Title'),
        text: this.$t('Delete_Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: this.$t('Delete_cancelButtonText'),
        confirmButtonText: this.$t('Delete_confirmButtonText')
      }).then(result => {
        if (result.value) {
          axios
            .delete('product_batches/' + id)
            .then(() => {
              this.$swal(this.$t('Delete_Deleted'), this.$t('Deleted_in_successfully'), 'success');
              this.fetch(this.serverParams.page);
            })
            .catch(() => {
              this.$swal(this.$t('Delete_Failed'), this.$t('Delete_Therewassomethingwronge'), 'warning');
            });
        }
      });
    }
  },
  created() {
    this.fetch(1);
  }
};
</script>

<!-- Dark-mode patches. The page has no scoped styles, so the global
     _dark.scss rules already cover most surfaces (.wrapper b-card,
     .vgt-table, .form-control, .modal-content). These extra rules
     fill the gaps that aren't styled globally. -->
<style>
/* Action column icon hover state on dark rows */
.dark-theme .vgt-table td a:not(.btn):has(> .text-25):hover,
.dark-theme .vgt-table td a:not(.btn):has(> .text-25):focus {
  background: rgba(255, 255, 255, 0.06) !important;
  border-color: rgba(255, 255, 255, 0.18) !important;
}

/* Empty/dash placeholders inside vgt cells */
.dark-theme .vgt-table td .text-muted {
  color: rgba(216, 216, 216, 0.55) !important;
}

/* Filter row labels inside the b-card.wrapper */
.dark-theme .main-content > .wrapper .form-group label {
  color: #d8d8d8 !important;
}

/* Write-Off modal warning alert */
.dark-theme #WriteOff_batch .alert.alert-warning {
  background: rgba(237, 137, 54, 0.12) !important;
  border-color: rgba(237, 137, 54, 0.35) !important;
  color: #fbbf24 !important;
}

/* Edit modal disabled / readonly Product field */
.dark-theme #Edit_batch .form-control:disabled,
.dark-theme #Edit_batch .form-control[readonly] {
  background: #232323 !important;
  border-color: #2a2a2a !important;
  color: rgba(216, 216, 216, 0.55) !important;
}

/* Native date pickers' calendar icon */
.dark-theme #Edit_batch input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(0.8);
  opacity: 0.85;
}

/* Edit modal "Batch_Qty_Edit_Hint" small note */
.dark-theme #Edit_batch .form-group small.text-muted {
  color: rgba(216, 216, 216, 0.6) !important;
}
</style>
