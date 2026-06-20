<template>
  <div class="portal-page portal-statement">
    <header class="pc-page-header">
      <div>
        <h1 class="pc-page-title">Account Statement</h1>
        <p class="pc-page-sub">Your account activity and balance</p>
      </div>
    </header>

    <div class="pc-card">
      <div class="pc-filters">
        <div class="pc-filter">
          <label>From</label>
          <input v-model="fromDate" type="date" />
        </div>
        <div class="pc-filter">
          <label>To</label>
          <input v-model="toDate" type="date" />
        </div>
        <button type="button" class="pc-btn-primary" @click="fetch">
          <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
          Apply
        </button>
      </div>

      <div v-if="data" class="pc-statement-body">
        <!-- Summary pills -->
        <div class="pc-summary">
          <div class="pc-summary-card">
            <span class="pc-summary-label">Account</span>
            <strong class="pc-summary-value">{{ data.client && data.client.name }}</strong>
          </div>
          <div class="pc-summary-card">
            <span class="pc-summary-label">Opening balance</span>
            <strong class="pc-summary-value">{{ formatMoney(data.current_opening_balance != null ? data.current_opening_balance : data.opening_balance) }}</strong>
          </div>
          <div class="pc-summary-card pc-summary-card-highlight">
            <span class="pc-summary-label">Closing balance</span>
            <strong class="pc-summary-value">{{ formatMoney(data.closing_balance) }}</strong>
          </div>
        </div>

        <!-- Desktop table -->
        <div class="pc-table-wrap pc-hide-mobile">
          <table class="pc-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Ref</th>
                <th>Description</th>
                <th class="pc-num">Debit</th>
                <th class="pc-num">Credit</th>
                <th class="pc-num">Balance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(e, i) in data.entries" :key="i">
                <td>{{ e.date }}</td>
                <td><span class="pc-type-pill">{{ e.type }}</span></td>
                <td>{{ e.ref }}</td>
                <td class="pc-desc">{{ e.description }}</td>
                <td class="pc-num">{{ e.debit ? formatMoney(e.debit) : '—' }}</td>
                <td class="pc-num pc-credit">{{ e.credit ? formatMoney(e.credit) : '—' }}</td>
                <td class="pc-num pc-balance">{{ formatMoney(e.balance) }}</td>
              </tr>
              <tr v-if="!(data.entries && data.entries.length)">
                <td colspan="7" class="pc-empty-cell">No entries in this period</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile list -->
        <ul class="pc-entry-list pc-show-mobile">
          <li v-for="(e, i) in data.entries" :key="'m-' + i" class="pc-entry">
            <div class="pc-entry-top">
              <span class="pc-type-pill">{{ e.type }}</span>
              <span class="pc-entry-date">{{ e.date }}</span>
            </div>
            <div class="pc-entry-desc">{{ e.description || e.ref }}</div>
            <div class="pc-entry-foot">
              <span v-if="e.debit" class="pc-debit">−{{ formatMoney(e.debit) }}</span>
              <span v-if="e.credit" class="pc-credit">+{{ formatMoney(e.credit) }}</span>
              <span class="pc-balance-m">Bal {{ formatMoney(e.balance) }}</span>
            </div>
          </li>
          <li v-if="!(data.entries && data.entries.length)" class="pc-empty">
            <div class="pc-empty-icon">📊</div>
            <p>No entries in this period</p>
          </li>
        </ul>
      </div>

      <div v-else class="pc-empty">
        <div class="pc-empty-icon">📊</div>
        <p>Select date range and tap Apply to load your statement.</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return { data: null, fromDate: '', toDate: '' };
  },
  mounted() {
    this.fetch();
  },
  methods: {
    async fetch() {
      try {
        const { data } = await axios.get('/portal/statement', { params: { from_date: this.fromDate || undefined, to_date: this.toDate || undefined } });
        this.data = data;
      } catch (_) {
        this.data = null;
      }
    },
    formatMoney(n) {
      if (n == null) return '0.00';
      return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
  },
};
</script>

<style scoped>
.portal-statement { padding-bottom: 1rem; }

.pc-page-header { margin-bottom: 1.25rem; }
.pc-page-title { font-size: 1.5rem; font-weight: 700; color: var(--pc-text); margin: 0 0 0.2rem; letter-spacing: -0.01em; }
.pc-page-sub { font-size: 0.9rem; color: var(--pc-text-muted); margin: 0; }

.pc-card {
  background: var(--pc-surface);
  border: 1px solid var(--pc-border);
  border-radius: var(--pc-radius);
  box-shadow: var(--pc-shadow-sm);
  overflow: hidden;
}

.pc-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.85rem;
  padding: 1rem 1.15rem;
  border-bottom: 1px solid var(--pc-border);
  background: var(--pc-surface);
}
.pc-filter { display: flex; flex-direction: column; gap: 0.3rem; }
.pc-filter label {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--pc-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.pc-filter input {
  padding: 0.55rem 0.85rem;
  border: 1px solid var(--pc-border-strong);
  border-radius: 10px;
  font-size: 0.9rem;
  background: var(--pc-surface);
  color: var(--pc-text);
  min-width: 150px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.pc-filter input:focus {
  outline: none;
  border-color: var(--pc-primary);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.pc-btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.6rem 1.1rem;
  background: var(--pc-primary);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, transform 0.15s;
  box-shadow: 0 4px 10px -4px rgba(79, 70, 229, 0.5);
}
.pc-btn-primary:hover { background: var(--pc-primary-600); transform: translateY(-1px); }

.pc-statement-body { padding: 1.15rem 1.15rem 1rem; }

.pc-summary {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.15rem;
}
.pc-summary-card {
  padding: 0.85rem 1rem;
  background: var(--pc-surface-alt);
  border: 1px solid var(--pc-border);
  border-radius: var(--pc-radius-sm);
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.pc-summary-label {
  font-size: 0.72rem;
  color: var(--pc-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 600;
}
.pc-summary-value {
  font-size: 1rem;
  font-weight: 700;
  color: var(--pc-text);
  letter-spacing: -0.01em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pc-summary-card-highlight {
  background: var(--pc-primary-50);
  border-color: #c7d2fe;
}
.pc-summary-card-highlight .pc-summary-value { color: var(--pc-primary-600); }

.pc-table-wrap { overflow-x: auto; }
.pc-table { width: 100%; border-collapse: collapse; font-size: 0.87rem; }
.pc-table th, .pc-table td { padding: 0.65rem 0.8rem; text-align: left; border-bottom: 1px solid var(--pc-border); }
.pc-table th {
  background: var(--pc-surface-alt);
  font-weight: 600;
  color: var(--pc-text-muted);
  font-size: 0.73rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.pc-table tbody tr:last-child td { border-bottom: none; }
.pc-num { text-align: right; }
.pc-credit { color: var(--pc-success); font-weight: 500; }
.pc-balance { font-weight: 600; color: var(--pc-text); }
.pc-desc { color: var(--pc-text-muted); }
.pc-empty-cell { text-align: center !important; color: var(--pc-text-soft); padding: 1.5rem !important; font-style: italic; }

.pc-type-pill {
  display: inline-block;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  background: var(--pc-primary-50);
  color: var(--pc-primary);
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: capitalize;
}

/* Mobile entry list */
.pc-entry-list { list-style: none; margin: 0; padding: 0; }
.pc-entry {
  padding: 0.85rem 0;
  border-bottom: 1px solid var(--pc-border);
}
.pc-entry:last-child { border-bottom: none; }
.pc-entry-top {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 0.4rem;
}
.pc-entry-date { font-size: 0.8rem; color: var(--pc-text-muted); }
.pc-entry-desc {
  font-size: 0.92rem;
  color: var(--pc-text);
  margin-bottom: 0.4rem;
  word-break: break-word;
}
.pc-entry-foot {
  display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;
  font-size: 0.85rem;
}
.pc-debit { color: var(--pc-danger); font-weight: 600; }
.pc-balance-m {
  margin-left: auto;
  color: var(--pc-text-muted);
  font-size: 0.82rem;
  background: var(--pc-surface-alt);
  padding: 0.18rem 0.6rem;
  border-radius: 999px;
  font-weight: 500;
}

.pc-empty { padding: 2.5rem 1rem; text-align: center; color: var(--pc-text-soft); }
.pc-empty-icon { font-size: 2.25rem; opacity: 0.7; margin-bottom: 0.5rem; }
.pc-empty p { margin: 0; font-size: 0.9rem; }

.pc-show-mobile { display: none; }
.pc-hide-mobile { display: block; }

@media (max-width: 768px) {
  .pc-page-title { font-size: 1.3rem; }
  .pc-filters { padding: 0.85rem; }
  .pc-filter input { min-width: 130px; font-size: 0.85rem; }
  .pc-btn-primary { margin-left: auto; }
  .pc-summary { grid-template-columns: 1fr; gap: 0.6rem; }
  .pc-summary-card { flex-direction: row; align-items: center; justify-content: space-between; }
  .pc-summary-label { letter-spacing: 0.03em; }
  .pc-show-mobile { display: block; }
  .pc-hide-mobile { display: none; }
  .pc-statement-body { padding: 1rem 1.15rem; }
}

@media (max-width: 480px) {
  .pc-filter { flex: 1; }
  .pc-filter input { min-width: 0; width: 100%; }
  .pc-btn-primary { width: 100%; justify-content: center; margin-left: 0; }
}
</style>
