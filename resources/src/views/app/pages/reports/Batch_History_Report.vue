<template>
  <div class="main-content">
    <breadcumb :page="$t('Batch_History') || 'Batch History'" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-if="!isLoading">
      <!-- Top action bar -->
      <div class="d-flex flex-wrap align-items-center mb-3">
        <b-button size="sm" variant="outline-secondary" class="btn-pill mr-2" @click="$router.push({ name: 'batch_register_report' })">
          <lucide-icon class="mr-1" name="arrow-left" />{{ $t('back') || 'Back' }}
        </b-button>
        <h4 class="m-0">
          {{ $t('Batch_History') || 'Batch History' }}:
          <span class="text-primary">{{ batch.batch_no || '—' }}</span>
        </h4>
        <div class="ml-auto">
          <b-button size="sm" variant="outline-secondary" class="btn-pill" @click="printTable()">
            <lucide-icon class="mr-1" name="printer" />{{ $t('print') }}
          </b-button>
        </div>
      </div>

      <!-- Batch summary card -->
      <b-card class="mb-3">
        <b-row>
          <b-col md="6">
            <div><strong>{{ $t('Product') }}:</strong> {{ batch.product_name }}
              <small v-if="batch.product_code" class="text-muted">[{{ batch.product_code }}]</small>
            </div>
            <div v-if="batch.generic_name" class="small text-muted">
              {{ batch.generic_name }}
              <span v-if="batch.strength"> · {{ batch.strength }}</span>
              <span v-if="batch.dosage_form"> · {{ batch.dosage_form }}</span>
            </div>
            <div v-if="batch.variant_name" class="mt-1">
              <span class="badge badge-pill" style="background:#ede9fe; color:#6d28d9;">{{ batch.variant_name }}</span>
            </div>
            <div class="mt-2"><strong>{{ $t('Warehouse') }}:</strong> {{ batch.warehouse_name }}</div>
            <div><strong>{{ $t('Status') }}:</strong>
              <span class="badge ml-1" :class="statusBadge(batch.status)">
                {{ $t('Batch_Status_' + batch.status) || batch.status }}
              </span>
            </div>
          </b-col>
          <b-col md="6">
            <div><strong>{{ $t('Mfg_Date') }}:</strong> {{ batch.mfg_date || '—' }}</div>
            <div>
              <strong>{{ $t('Expiry_Date') }}:</strong>
              <span v-if="batch.expiry_date" :style="expiryPillStyle(batch.expiry_bucket)" class="ml-1">
                {{ batch.expiry_date }}
              </span>
              <span v-else class="text-muted ml-1">—</span>
              <small v-if="batch.expiry_date" class="ml-2"
                :class="{
                  'text-danger': batch.expiry_bucket === 'expired',
                  'text-warning': batch.expiry_bucket === 'near',
                  'text-success': batch.expiry_bucket === 'valid'
                }"
              >
                <span v-if="batch.expiry_bucket === 'expired'">
                  {{ $t('Expired') }} ({{ Math.abs(batch.days_to_expiry) }}d)
                </span>
                <span v-else-if="batch.expiry_bucket === 'near'">
                  {{ $t('Expires_in') }} {{ batch.days_to_expiry }}d
                </span>
              </small>
            </div>
            <div class="mt-2 h4 mb-0">
              <strong>{{ $t('Current_Quantity') || 'Current Qty' }}:</strong>
              <span class="text-primary">{{ formatNumber(batch.qty) }}</span>
            </div>
            <div v-if="batch.notes" class="mt-2 small text-muted">{{ batch.notes }}</div>
          </b-col>
        </b-row>
      </b-card>

      <!-- Movements table -->
      <b-card>
        <div class="d-flex flex-wrap align-items-end mb-3">
          <h5 class="m-0">{{ $t('Movements') || 'Movements' }}
            <small class="text-muted">({{ filteredTransactions.length }})</small>
          </h5>
          <div class="ml-auto">
            <b-form-select v-model="typeFilter" size="sm" class="w-auto">
              <option value="all">{{ $t('All') }}</option>
              <option value="in">↑ {{ $t('In') || 'In' }}</option>
              <option value="out">↓ {{ $t('Out') || 'Out' }}</option>
              <option value="purchase">{{ $t('Purchase') || 'Purchase' }}</option>
              <option value="sale">{{ $t('Sale') || 'Sale' }}</option>
              <option value="sale_return">{{ $t('Sales_Return') || 'Sales Return' }}</option>
              <option value="purchase_return">{{ $t('Purchase_Return') || 'Purchase Return' }}</option>
              <option value="adjustment">{{ $t('Adjustment') || 'Adjustment' }}</option>
              <option value="transfer_in">{{ $t('Transfer_In') || 'Transfer In' }}</option>
              <option value="transfer_out">{{ $t('Transfer_Out') || 'Transfer Out' }}</option>
              <option value="damage">{{ $t('Damage') || 'Damage' }}</option>
              <option value="quotation">{{ $t('Quotation') || 'Quotation' }}</option>
            </b-form-select>
          </div>
        </div>

        <div v-if="!filteredTransactions.length" class="text-center py-4 text-muted">
          <lucide-icon class="mr-1" name="info" />
          {{ $t('No_movements_recorded') || 'No movements recorded for this batch yet.' }}
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover table-sm vgt-table mb-0">
            <thead>
              <tr style="background:#eef2ff;">
                <th style="color:#3730a3;">{{ $t('Type') || 'Type' }}</th>
                <th style="color:#3730a3;">{{ $t('Date') }}</th>
                <th style="color:#3730a3;">{{ $t('Reference') }}</th>
                <th style="color:#3730a3;">{{ $t('Party') || 'Party' }}</th>
                <th style="color:#3730a3; text-align:right;">{{ $t('In') || 'In' }}</th>
                <th style="color:#3730a3; text-align:right;">{{ $t('Out') || 'Out' }}</th>
                <th style="color:#3730a3; text-align:right;">{{ $t('Unit_Value') || 'Unit Value' }}</th>
                <th style="color:#3730a3; text-align:right;">{{ $t('Balance') || 'Balance' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(t, idx) in filteredTransactions" :key="idx">
                <td>
                  <span class="badge" :class="typeBadgeClass(t.type)">
                    <i :class="typeIcon(t.type)" style="margin-right:3px;"></i>{{ typeLabel(t.type) }}
                  </span>
                </td>
                <td>{{ t.date || '—' }}</td>
                <td>
                  <router-link
                    v-if="sourceLink(t)"
                    :to="sourceLink(t)"
                    class="text-primary font-weight-bold"
                  >{{ t.ref }}</router-link>
                  <span v-else>{{ t.ref }}</span>
                </td>
                <td>
                  <span class="d-block">{{ t.party_name || '—' }}</span>
                  <small class="text-muted">{{ t.party_label }}</small>
                </td>
                <td style="text-align:right;">
                  <span v-if="t.qty_in" class="text-success font-weight-bold">+{{ formatNumber(t.qty_in) }}</span>
                  <span v-else-if="t.type === 'quotation' && t.reserved_qty"
                        class="text-muted"
                        :title="$t('Quotation_Reserved') || 'Quotation reserved (not yet sold)'">
                    ({{ formatNumber(t.reserved_qty) }})
                  </span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td style="text-align:right;">
                  <span v-if="t.qty_out" class="text-danger font-weight-bold">-{{ formatNumber(t.qty_out) }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td style="text-align:right;">
                  <span v-if="t.unit_value !== null">{{ currencySymbol }} {{ formatNumber(t.unit_value) }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td style="text-align:right; font-weight:bold;">{{ formatNumber(t.running_balance) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Reconciliation footer -->
        <div class="mt-4 p-3" style="background:#f8faff; border:1px solid #e0e7ff; border-radius:8px;">
          <h6 class="mb-3 text-primary">
            <lucide-icon name="receipt-text" /> {{ $t('Reconciliation') || 'Reconciliation' }}
          </h6>
          <b-row>
            <b-col md="3" class="text-center">
              <div class="small text-muted">{{ $t('Total_In') || 'Total In' }}</div>
              <div class="h5 mb-0 text-success">+ {{ formatNumber(totals.in) }}</div>
            </b-col>
            <b-col md="3" class="text-center">
              <div class="small text-muted">{{ $t('Total_Out') || 'Total Out' }}</div>
              <div class="h5 mb-0 text-danger">- {{ formatNumber(totals.out) }}</div>
            </b-col>
            <b-col md="3" class="text-center">
              <div class="small text-muted">{{ $t('Computed_Qty') || 'Computed Qty' }}</div>
              <div class="h5 mb-0">{{ formatNumber(totals.computed_qty) }}</div>
            </b-col>
            <b-col md="3" class="text-center">
              <div class="small text-muted">{{ $t('Actual_Qty') || 'Actual Qty' }}</div>
              <div class="h5 mb-0">{{ formatNumber(totals.actual_qty) }}</div>
            </b-col>
          </b-row>
          <div
            class="mt-3 p-2 text-center"
            :style="{
              background: hasDrift ? '#fee2e2' : '#dcfce7',
              color: hasDrift ? '#991b1b' : '#166534',
              borderRadius: '6px',
              fontWeight: 600
            }"
          >
            <span v-if="hasDrift">
              <lucide-icon class="mr-1" name="alert-triangle" />
              {{ $t('Drift_Warning') || 'Ledger drift detected' }}: {{ formatNumber(totals.drift) }}
            </span>
            <span v-else>
              <lucide-icon class="mr-1" name="check" />
              {{ $t('Ledger_Balanced') || 'Ledger balanced — actual qty matches sum of transactions.' }}
            </span>
          </div>
        </div>
      </b-card>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";

export default {
  metaInfo: { title: "Batch History" },

  data() {
    return {
      isLoading: true,
      batch: {},
      transactions: [],
      totals: { in: 0, out: 0, computed_qty: 0, actual_qty: 0, drift: 0 },
      typeFilter: 'all'
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),

    currencySymbol() {
      return (this.currentUser && this.currentUser.currency) || '';
    },

    filteredTransactions() {
      if (this.typeFilter === 'all') return this.transactions;
      if (this.typeFilter === 'in') return this.transactions.filter(t => t.direction === 'in');
      if (this.typeFilter === 'out') return this.transactions.filter(t => t.direction === 'out');
      return this.transactions.filter(t => t.type === this.typeFilter);
    },

    hasDrift() {
      return Math.abs(Number(this.totals.drift) || 0) > 0.0001;
    }
  },

  methods: {
    formatNumber(v) {
      if (v === null || v === undefined || v === '') return '0';
      const n = Number(v);
      if (Number.isNaN(n)) return '0';
      return Number.isInteger(n) ? n.toString() : n.toFixed(2);
    },

    statusBadge(status) {
      switch (status) {
        case 'active': return 'badge-success';
        case 'quarantined': return 'badge-warning';
        case 'expired': return 'badge-danger';
        case 'written_off': return 'badge-secondary';
        default: return 'badge-light';
      }
    },

    typeBadgeClass(type) {
      switch (type) {
        case 'purchase': return 'badge-success';
        case 'sale_return': return 'badge-info';
        case 'sale': return 'badge-danger';
        case 'purchase_return': return 'badge-warning';
        case 'adjustment': return 'badge-primary';
        case 'transfer_in': return 'badge-info';
        case 'transfer_out': return 'badge-secondary';
        case 'damage': return 'badge-dark';
        case 'quotation': return 'badge-light';
        default: return 'badge-light';
      }
    },

    typeIcon(type) {
      switch (type) {
        case 'purchase': return 'shopping-cart';
        case 'sale_return': return 'refresh-cw';
        case 'sale': return 'calculator';
        case 'purchase_return': return 'refresh-cw';
        case 'adjustment': return 'pen';
        case 'transfer_in': return 'download';
        case 'transfer_out': return 'upload';
        case 'damage': return 'x';
        case 'quotation': return 'clipboard-list';
        default: return 'file-text';
      }
    },

    typeLabel(type) {
      const labels = {
        purchase: this.$t('Purchase') || 'Purchase',
        sale: this.$t('Sale') || 'Sale',
        sale_return: this.$t('Sales_Return') || 'Sales Return',
        purchase_return: this.$t('Purchase_Return') || 'Purchase Return',
        adjustment: this.$t('Adjustment') || 'Adjustment',
        transfer_in: this.$t('Transfer_In') || 'Transfer In',
        transfer_out: this.$t('Transfer_Out') || 'Transfer Out',
        damage: this.$t('Damage') || 'Damage',
        quotation: this.$t('Quotation') || 'Quotation'
      };
      return labels[type] || type;
    },

    expiryPillStyle(bucket) {
      const base = {
        display: 'inline-block',
        padding: '2px 8px',
        borderRadius: '10px',
        fontSize: '12px',
        fontWeight: '600'
      };
      const palettes = {
        expired: { background: '#fee2e2', color: '#991b1b' },
        near: { background: '#fef3c7', color: '#92400e' },
        valid: { background: '#dcfce7', color: '#166534' }
      };
      return Object.assign({}, base, palettes[bucket] || { background: '#f3f4f6', color: '#6b7280' });
    },

    sourceLink(t) {
      if (!t || !t.ref_id) return null;
      switch (t.type) {
        case 'purchase': return { name: 'detail_purchase', params: { id: t.ref_id } };
        case 'sale': return { name: 'detail_sale', params: { id: t.ref_id } };
        case 'sale_return': return { name: 'detail_sale_return', params: { id: t.ref_id } };
        case 'purchase_return': return { name: 'detail_purchase_return', params: { id: t.ref_id } };
        case 'adjustment': return { name: 'detail_adjustment', params: { id: t.ref_id } };
        case 'transfer_in':
        case 'transfer_out': return { name: 'detail_transfer', params: { id: t.ref_id } };
        case 'damage': return { name: 'edit_damage', params: { id: t.ref_id } };
        case 'quotation': return { name: 'detail_quotation', params: { id: t.ref_id } };
        default: return null;
      }
    },

    fetch() {
      const id = this.$route.params.id;
      if (!id) return;
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(`report/batches/${id}/history`)
        .then(response => {
          const data = response.data || {};
          this.batch = data.batch || {};
          this.transactions = data.transactions || [];
          this.totals = data.totals || this.totals;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => { this.isLoading = false; }, 500);
        });
    },

    printTable() {
      const title = `${this.$t('Reports')} / ${this.$t('Batch_History') || 'Batch History'} — ${this.batch.batch_no || ''}`;
      const items = this.filteredTransactions || [];

      let header = `<div class="print-header">${title}</div>`;
      header += `<div class="batch-meta">
        <strong>${this.$t('Product')}:</strong> ${this.batch.product_name || ''} ${this.batch.product_code ? '['+this.batch.product_code+']' : ''}<br>
        <strong>${this.$t('Warehouse')}:</strong> ${this.batch.warehouse_name || ''}<br>
        <strong>${this.$t('Mfg_Date')}:</strong> ${this.batch.mfg_date || '—'} &nbsp; · &nbsp;
        <strong>${this.$t('Expiry_Date')}:</strong> ${this.batch.expiry_date || '—'}<br>
        <strong>${this.$t('Current_Quantity') || 'Current Qty'}:</strong> ${this.formatNumber(this.batch.qty)}
      </div>`;

      let html = '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
      html += '<thead><tr>';
      ['Type','Date','Reference','Party','In','Out','Unit Value','Balance'].forEach(h => {
        html += `<th style="border:1px solid #ddd; padding:6px; background:#f5f5f5; text-align:left;">${h}</th>`;
      });
      html += '</tr></thead><tbody>';
      items.forEach(r => {
        html += '<tr>';
        html += `<td style="border:1px solid #ddd; padding:6px;">${this.typeLabel(r.type)}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.date || '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.ref || ''}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${r.party_name || ''}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px; text-align:right;">${r.qty_in ? '+'+this.formatNumber(r.qty_in) : '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px; text-align:right;">${r.qty_out ? '-'+this.formatNumber(r.qty_out) : '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px; text-align:right;">${r.unit_value !== null ? this.formatNumber(r.unit_value) : '—'}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px; text-align:right; font-weight:bold;">${this.formatNumber(r.running_balance)}</td>`;
        html += '</tr>';
      });
      html += '</tbody></table>';

      let recon = `<div style="margin-top:14px; padding:10px; border:1px solid #e0e7ff; background:#f8faff;">
        <strong>${this.$t('Reconciliation') || 'Reconciliation'}</strong><br>
        ${this.$t('Total_In') || 'Total In'}: ${this.formatNumber(this.totals.in)} &nbsp;
        ${this.$t('Total_Out') || 'Total Out'}: ${this.formatNumber(this.totals.out)} &nbsp;
        ${this.$t('Computed_Qty') || 'Computed Qty'}: ${this.formatNumber(this.totals.computed_qty)} &nbsp;
        ${this.$t('Actual_Qty') || 'Actual Qty'}: ${this.formatNumber(this.totals.actual_qty)}
        <br>
        <strong style="color:${this.hasDrift ? '#991b1b' : '#166534'};">
          ${this.hasDrift ? ((this.$t('Drift_Warning') || 'Ledger drift detected') + ': ' + this.formatNumber(this.totals.drift)) : (this.$t('Ledger_Balanced') || 'Ledger balanced')}
        </strong>
      </div>`;

      const w = window.open('', '_blank');
      if (!w) { alert('Please allow popups to print'); return; }
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML).join('\n');
      const doc = w.document;
      doc.open();
      doc.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <base href="${window.location.origin}/" />
    <title>${title}</title>
    ${links}
    <style>
      @media print { body, body * { visibility: visible !important; } @page { size: A4; margin: 0.3cm; } }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 6px; font-size: 14px; }
      .batch-meta { margin-bottom: 12px; font-size: 11px; line-height: 1.5; }
    </style>
  </head>
  <body>
    ${header}
    ${html}
    ${recon}
  </body>
</html>`);
      doc.close();
      w.focus();
      setTimeout(() => { w.print(); w.close(); }, 400);
    }
  },

  created() {
    this.fetch();
  },

  watch: {
    '$route.params.id': function() {
      this.isLoading = true;
      this.fetch();
    }
  }
};
</script>
