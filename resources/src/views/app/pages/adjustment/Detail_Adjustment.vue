<template>
  <div class="main-content">
    <breadcumb :page="$t('AdjustmentDetail') || 'Adjustment Detail'" :folder="$t('ListAdjustments') || 'Adjustments'" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-if="!isLoading">
      <!-- Top action bar -->
      <div class="d-flex flex-wrap align-items-center mb-3">
        <b-button size="sm" variant="outline-secondary" class="btn-pill mr-2" @click="$router.push({ name: 'index_adjustment' })">
          <lucide-icon class="mr-1" name="arrow-left" />{{ $t('back') || 'Back' }}
        </b-button>

        <div class="ml-auto">
          <b-button
            v-if="currentUserPermissions && currentUserPermissions.includes('adjustment_edit')"
            size="sm"
            variant="outline-success"
            class="btn-pill mr-2"
            :to="{ name: 'edit_adjustment', params: { id: $route.params.id } }"
          >
            <lucide-icon class="mr-1" name="pencil" />{{ $t('Edit') || 'Edit' }}
          </b-button>
          <b-button size="sm" variant="outline-primary" class="btn-pill mr-2" @click="downloadPdf()">
            <lucide-icon class="mr-1" name="file-text" />PDF
          </b-button>
          <b-button size="sm" variant="outline-secondary" class="btn-pill" @click="printTable()">
            <lucide-icon class="mr-1" name="printer" />{{ $t('print') || 'Print' }}
          </b-button>
        </div>
      </div>

      <!-- Header card: meta info -->
      <b-card class="mb-3">
        <b-row>
          <b-col md="4">
            <div class="text-muted small text-uppercase">{{ $t('Reference') || 'Reference' }}</div>
            <div class="h4 mb-0 text-primary">{{ adjustment.Ref || '—' }}</div>
          </b-col>
          <b-col md="4">
            <div class="text-muted small text-uppercase">{{ $t('date') || 'Date' }}</div>
            <div class="h6 mb-0">{{ adjustment.date || '—' }}</div>
          </b-col>
          <b-col md="4">
            <div class="text-muted small text-uppercase">{{ $t('warehouse') || 'Warehouse' }}</div>
            <div class="h6 mb-0">
              <lucide-icon name="store" style="color:#0ea5e9; margin-right:4px;" />{{ adjustment.warehouse || '—' }}
            </div>
          </b-col>
        </b-row>
        <hr v-if="adjustment.created_by || adjustment.note">
        <b-row v-if="adjustment.created_by || adjustment.note">
          <b-col md="6" v-if="adjustment.created_by">
            <div class="text-muted small text-uppercase">{{ $t('Created_by') || 'Created by' }}</div>
            <div>{{ adjustment.created_by }}</div>
          </b-col>
          <b-col md="6" v-if="adjustment.note">
            <div class="text-muted small text-uppercase">{{ $t('Note') || 'Note' }}</div>
            <div>{{ adjustment.note }}</div>
          </b-col>
        </b-row>
      </b-card>

      <!-- KPI strip -->
      <b-row class="mb-3">
        <b-col md="3">
          <b-card no-body class="p-3 text-center">
            <div class="small text-muted">{{ $t('Items') || 'Items' }}</div>
            <div class="h3 mb-0">{{ details.length }}</div>
          </b-card>
        </b-col>
        <b-col md="3">
          <b-card no-body class="p-3 text-center" style="background: linear-gradient(180deg,#f0fdf4 0%,#ffffff 100%); border-color:#bbf7d0;">
            <div class="small text-muted">{{ $t('Addition') || 'Additions' }}</div>
            <div class="h3 mb-0 text-success">+ {{ totalAdd }}</div>
          </b-card>
        </b-col>
        <b-col md="3">
          <b-card no-body class="p-3 text-center" style="background: linear-gradient(180deg,#fef2f2 0%,#ffffff 100%); border-color:#fecaca;">
            <div class="small text-muted">{{ $t('Subtraction') || 'Subtractions' }}</div>
            <div class="h3 mb-0 text-danger">- {{ totalSub }}</div>
          </b-card>
        </b-col>
        <b-col md="3">
          <b-card no-body class="p-3 text-center">
            <div class="small text-muted">{{ $t('Net_Change') || 'Net Change' }}</div>
            <div class="h3 mb-0" :class="netChange >= 0 ? 'text-success' : 'text-danger'">
              {{ netChange >= 0 ? '+' : '' }}{{ netChange }}
            </div>
          </b-card>
        </b-col>
      </b-row>

      <!-- Products table -->
      <b-card>
        <h5 class="mb-3">{{ $t('Products') || 'Products' }}</h5>
        <div v-if="!details.length" class="text-center py-4 text-muted">
          <lucide-icon class="mr-1" name="info" />{{ $t('NodataAvailable') || 'No data available' }}
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover table-sm vgt-table mb-0">
            <thead>
              <tr style="background:#eef2ff;">
                <th style="color:#3730a3;">{{ $t('CodeProduct') || 'Code' }}</th>
                <th style="color:#3730a3;">{{ $t('ProductName') || 'Product' }}</th>
                <th style="color:#3730a3; text-align:right;">{{ $t('Quantity') || 'Quantity' }}</th>
                <th style="color:#3730a3; text-align:center;">{{ $t('type') || 'Type' }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(detail, idx) in details">
                <tr :key="'r-' + idx">
                  <td>{{ detail.code }}</td>
                  <td>
                    {{ detail.name }}
                    <span
                      v-if="detail.is_batch_tracked"
                      class="badge ml-1"
                      style="background:#eef2ff; color:#4f46e5; font-weight:600; letter-spacing:0.3px;"
                    >
                      <lucide-icon name="package" style="margin-right:3px;" />{{ $t('Batches') || 'Batches' }}
                    </span>
                  </td>
                  <td style="text-align:right; font-weight:600;">
                    {{ formatNumber(detail.quantity, 2) }} {{ detail.unit }}
                  </td>
                  <td style="text-align:center;">
                    <span v-if="detail.type === 'add'" class="badge badge-success">
                      <lucide-icon name="plus" style="margin-right:3px;" />{{ $t('Addition') || 'Addition' }}
                    </span>
                    <span v-else class="badge badge-danger">
                      <lucide-icon name="trash-2" style="margin-right:3px;" />{{ $t('Subtraction') || 'Subtraction' }}
                    </span>
                  </td>
                </tr>

                <!-- Batch breakdown row when product is batch-tracked -->
                <tr v-if="detail.is_batch_tracked && (detail.batches || []).length" :key="'b-' + idx" style="background:#ffffff;">
                  <td colspan="4" style="padding:0; border-top:0;">
                    <div style="margin:6px 4px 12px 4px; border:1px solid #e0e7ff; border-radius:8px; overflow:hidden; background:#f8faff;">
                      <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 12px; background:#4f46e5; color:#fff;">
                        <div style="display:flex; align-items:center; gap:8px;">
                          <lucide-icon name="package" />
                          <span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.3px;">{{ $t('Batches') || 'Batches' }}</span>
                        </div>
                        <span style="font-size:11px; font-weight:600; background:rgba(255,255,255,0.22); padding:1px 8px; border-radius:10px;">
                          {{ detail.batches.length }} {{ $t('items') || 'items' }}
                        </span>
                      </div>
                      <table style="width:100%; border-collapse:collapse; font-size:12px;">
                        <thead>
                          <tr style="background:#eef2ff;">
                            <th style="padding:6px 10px; text-align:left; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Batch_No') || 'Batch No' }}</th>
                            <th style="padding:6px 10px; text-align:left; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Mfg_Date') || 'Mfg Date' }}</th>
                            <th style="padding:6px 10px; text-align:left; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Expiry_Date') || 'Expiry Date' }}</th>
                            <th style="padding:6px 10px; text-align:center; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Direction') || 'Direction' }}</th>
                            <th style="padding:6px 10px; text-align:right; color:#3730a3; font-weight:700; text-transform:uppercase; font-size:10px; letter-spacing:0.3px;">{{ $t('Quantity') || 'Quantity' }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(b, bIdx) in detail.batches" :key="'ab-' + idx + '-' + bIdx" :style="{ background: bIdx % 2 === 1 ? '#f8faff' : '#ffffff', borderTop: '1px solid #e0e7ff' }">
                            <td style="padding:6px 10px; font-weight:600; color:#1f2937;">
                              <span v-if="b.batch_no">{{ b.batch_no }}</span>
                              <span v-else style="color:#9ca3af; font-style:italic;">—</span>
                            </td>
                            <td style="padding:6px 10px; color:#374151;">
                              <span v-if="b.mfg_date">{{ b.mfg_date }}</span>
                              <span v-else style="color:#9ca3af;">—</span>
                            </td>
                            <td style="padding:6px 10px;">
                              <span v-if="b.expiry_date" :style="expiry_pill_style(b.expiry_date)">{{ b.expiry_date }}</span>
                              <span v-else style="color:#9ca3af;">—</span>
                            </td>
                            <td style="padding:6px 10px; text-align:center;">
                              <span v-if="b.direction === 'in'" class="badge badge-success" style="font-size:10px;">
                                ↑ {{ $t('In') || 'In' }}
                              </span>
                              <span v-else class="badge badge-danger" style="font-size:10px;">
                                ↓ {{ $t('Out') || 'Out' }}
                              </span>
                            </td>
                            <td style="padding:6px 10px; text-align:right; color:#1f2937; font-weight:600;">
                              {{ formatNumber(b.qty, 2) }} {{ detail.unit }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </b-card>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";

export default {
  metaInfo: { title: "Adjustment Detail" },

  computed: {
    ...mapGetters(["currentUserPermissions"]),

    totalAdd() {
      const sum = (this.details || [])
        .filter(d => d.type === "add")
        .reduce((acc, d) => acc + (Number(d.quantity) || 0), 0);
      return this.formatNumber(sum, 2);
    },
    totalSub() {
      const sum = (this.details || [])
        .filter(d => d.type === "sub")
        .reduce((acc, d) => acc + (Number(d.quantity) || 0), 0);
      return this.formatNumber(sum, 2);
    },
    netChange() {
      const sum = (this.details || []).reduce((acc, d) => {
        const q = Number(d.quantity) || 0;
        return d.type === "add" ? acc + q : acc - q;
      }, 0);
      return this.formatNumber(sum, 2);
    }
  },

  data() {
    return {
      isLoading: true,
      adjustment: {},
      details: []
    };
  },

  methods: {
    formatNumber(number, dec) {
      if (number === null || number === undefined || number === "") return "0";
      const n = Number(number);
      if (Number.isNaN(n)) return "0";
      return Number.isInteger(n) && (dec === undefined || dec === 0) ? n.toString() : n.toFixed(dec || 2);
    },

    expiry_pill_style(dateStr) {
      const base = {
        display: "inline-block",
        padding: "2px 8px",
        borderRadius: "10px",
        fontSize: "11px",
        fontWeight: "600"
      };
      if (!dateStr) return Object.assign({}, base, { background: "#f3f4f6", color: "#6b7280" });
      const today = new Date(); today.setHours(0, 0, 0, 0);
      const exp = new Date(dateStr);
      if (isNaN(exp.getTime())) return Object.assign({}, base, { background: "#f3f4f6", color: "#6b7280" });
      exp.setHours(0, 0, 0, 0);
      const diffDays = Math.round((exp - today) / (1000 * 60 * 60 * 24));
      if (diffDays < 0) return Object.assign({}, base, { background: "#fee2e2", color: "#991b1b" });
      if (diffDays <= 30) return Object.assign({}, base, { background: "#fef3c7", color: "#92400e" });
      return Object.assign({}, base, { background: "#dcfce7", color: "#166534" });
    },

    fetch() {
      const id = this.$route.params.id;
      if (!id) return;
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(`adjustments/detail/${id}`)
        .then(response => {
          const data = response.data || {};
          this.adjustment = data.adjustment || {};
          this.details = data.details || [];
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => { this.isLoading = false; }, 500);
        });
    },

    downloadPdf() {
      const id = this.$route.params.id;
      if (!id) return;
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(`adjustment_pdf/${id}`, { responseType: "blob", headers: { "Content-Type": "application/json" } })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", `Adjustment_${this.adjustment.Ref || id}.pdf`);
          document.body.appendChild(link);
          link.click();
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => setTimeout(() => NProgress.done(), 500));
    },

    printTable() {
      const title = `${this.$t('AdjustmentDetail') || 'Adjustment Detail'} — ${this.adjustment.Ref || ''}`;
      const items = this.details || [];

      let header = `<div class="print-header">${title}</div>`;
      header += `<div class="meta">
        <strong>${this.$t('Reference') || 'Reference'}:</strong> ${this.adjustment.Ref || ''}<br>
        <strong>${this.$t('date') || 'Date'}:</strong> ${this.adjustment.date || ''}<br>
        <strong>${this.$t('warehouse') || 'Warehouse'}:</strong> ${this.adjustment.warehouse || ''}<br>
        ${this.adjustment.note ? '<strong>' + (this.$t('Note') || 'Note') + ':</strong> ' + this.adjustment.note : ''}
      </div>`;

      let html = '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
      html += '<thead><tr>';
      ["Code", "Product", "Qty", "Type"].forEach(h => {
        html += `<th style="border:1px solid #ddd; padding:6px; background:#f5f5f5; text-align:left;">${h}</th>`;
      });
      html += "</tr></thead><tbody>";
      items.forEach(d => {
        html += "<tr>";
        html += `<td style="border:1px solid #ddd; padding:6px;">${d.code || ""}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${d.name || ""}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px; text-align:right;">${this.formatNumber(d.quantity, 2)} ${d.unit || ""}</td>`;
        html += `<td style="border:1px solid #ddd; padding:6px;">${d.type === "add" ? (this.$t("Addition") || "Addition") : (this.$t("Subtraction") || "Subtraction")}</td>`;
        html += "</tr>";
      });
      html += "</tbody></table>";

      const w = window.open("", "_blank");
      if (!w) { alert("Please allow popups to print"); return; }
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML).join("\n");
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
      .meta { margin-bottom: 12px; font-size: 11px; line-height: 1.5; }
    </style>
  </head>
  <body>
    ${header}
    ${html}
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
    "$route.params.id": function() {
      this.isLoading = true;
      this.fetch();
    }
  }
};
</script>
