@extends('layouts.store')

@section('content')
@php
  $currency = $s->currency_code ?? 'USD';
@endphp

<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6 flex items-center justify-between flex-wrap gap-3">
    <div>
      <span class="section-kicker">{{ __('messages.Account') }}</span>
      <h1 class="section-title mt-1">{{ __('messages.MyOrders') }}</h1>
      <div class="text-fg-muted text-sm mt-1">{{ __('messages.TrackOrdersStatus') }}</div>
    </div>
    <a href="{{ url('/online_store/account') }}" class="btn btn-outline">
      <x-store.icon name="user" class="w-4 h-4" />{{ __('messages.Account') }}
    </a>
  </div>
</section>

<div class="container py-8" id="orders-app">
  <div class="card">
    <div class="card-body">
      <div class="flex flex-wrap gap-3 items-end mb-4">
        <div>
          <label class="form-label text-xs mb-1">{{ __('messages.Search') }}</label>
          <input type="text" id="ord-q" class="input h-9 text-sm py-1" placeholder="{{ __('messages.RefOrDate') }}">
        </div>
        <div>
          <label class="form-label text-xs mb-1">{{ __('messages.Status') }}</label>
          <select id="ord-status" class="select h-9 text-sm py-1">
            <option value="">{{ __('messages.All') }}</option>
            <option value="pending">{{ __('messages.pending') }}</option>
            <option value="confirmed">{{ __('messages.confirmed') }}</option>
            <option value="cancelled">{{ __('messages.cancelled') }}</option>
          </select>
        </div>
        <button id="ord-refresh" class="btn btn-primary btn-sm">
          <x-store.icon name="refresh" class="w-4 h-4" />{{ __('messages.Update') }}
        </button>
      </div>

      <div id="ord-empty" class="empty-state py-12 hidden">
        <div class="empty-icon"><x-store.icon name="package" class="w-10 h-10" /></div>
        <h3>{{ __('messages.NoOrdersYet') }}</h3>
        <a href="{{ route('store.shop') }}" class="btn btn-outline mt-4">
          <x-store.icon name="bag" class="w-4 h-4" />{{ __('messages.GoShopping') }}
        </a>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm" id="ord-table">
          <thead class="bg-bg-muted text-fg-secondary">
            <tr>
              <th class="text-start font-medium px-4 py-2">{{ __('messages.Ref') }}</th>
              <th class="text-start font-medium px-4 py-2">{{ __('messages.Date') }}</th>
              <th class="text-center font-medium px-4 py-2">{{ __('messages.Status') }}</th>
              <th class="text-end font-medium px-4 py-2">{{ __('messages.Total') }}</th>
              <th class="text-end font-medium px-4 py-2">{{ __('messages.Action') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-subtle"></tbody>
        </table>
      </div>

      <nav class="mt-4">
        <ul class="pagination" id="ord-pager"></ul>
      </nav>
    </div>
  </div>
</div>

<script>
(function(){
  const CURRENCY = document.querySelector('meta[name="currency"]')?.content || @json($currency);
  const tableBody = document.querySelector('#ord-table tbody');
  const pager     = document.getElementById('ord-pager');
  const emptyBox  = document.getElementById('ord-empty');
  const qInp      = document.getElementById('ord-q');
  const stSel     = document.getElementById('ord-status');
  const btnRef    = document.getElementById('ord-refresh');

  const STATUS_LABELS = {
    pending:   @json(__('messages.pending')),
    confirmed: @json(__('messages.confirmed')),
    cancelled: @json(__('messages.cancelled')),
  };

  function money(n){
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: CURRENCY }).format(Number(n || 0));
    } catch(e){
      return (CURRENCY || '$') + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
  }

  function esc(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  async function fetchOrders(page = 1){
    const params = new URLSearchParams({
      page: page,
      q: (qInp.value || '').trim(),
      status: stSel.value || '',
      mine: '1'
    });

    const urls = [
      '/online_store/my/orders?' + params.toString(),
      '/online_store/orders?' + params.toString()
    ];

    for (const u of urls) {
      try {
        const r = await fetch(u, { headers: { 'Accept': 'application/json' } });
        if (!r.ok) continue;
        const data = await r.json();
        return normalize(data);
      } catch(e){ /* try next */ }
    }
    return { rows: [], total: 0, page: 1, pages: 1 };
  }

  function normalize(resp){
    const rows = Array.isArray(resp) ? resp : (resp.data || resp.rows || []);
    const meta = resp.meta || {};
    return {
      rows,
      total: meta.total ?? rows.length,
      page:  meta.page  ?? 1,
      pages: meta.pages ?? 1
    };
  }

  function rowHtml(o){
    const code   = o.code || o.ref || ('#' + o.id);
    const date   = o.date || o.created_at || '';
    const total  = money(o.total || 0);
    const status = String(o.status || '').toLowerCase();

    const chipCls =
      status === 'confirmed' ? 'chip-success' :
      status === 'pending'   ? 'chip-warning' :
      status === 'cancelled' ? 'chip-danger'  : '';

    const statusLabel = STATUS_LABELS[status] || status || '—';
    const viewUrlBase = @json(url('/online_store/account/orders/'));
    const viewUrl     = viewUrlBase + '/' + o.id;

    return `
      <tr>
        <td class="font-semibold px-4 py-3">${esc(code)}</td>
        <td class="px-4 py-3">${esc(date)}</td>
        <td class="text-center px-4 py-3"><span class="chip ${chipCls} uppercase tracking-wide text-[11px]">${esc(statusLabel)}</span></td>
        <td class="text-end font-semibold px-4 py-3">${total}</td>
        <td class="text-end px-4 py-3">
          <a class="btn btn-outline btn-sm" href="${viewUrl}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            {{ __('messages.View') }}
          </a>
        </td>
      </tr>
    `;
  }

  function renderPager(page, pages){
    pager.innerHTML = '';
    if (pages <= 1) return;

    const make = (p, label, active=false, disabled=false) => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.className = 'page-link' + (active ? ' active' : '') + (disabled ? ' disabled' : '');
      a.href = '#';
      a.textContent = label;
      a.addEventListener('click', (e) => { e.preventDefault(); if (!disabled && !active) load(p); });
      li.appendChild(a);
      pager.appendChild(li);
    };

    make(Math.max(1, page - 1), '‹', false, page === 1);
    for (let i = 1; i <= pages; i++) make(i, String(i), i === page, false);
    make(Math.min(pages, page + 1), '›', false, page === pages);
  }

  async function load(page = 1){
    const res = await fetchOrders(page);
    if (!res.rows.length){
      emptyBox.classList.remove('hidden');
      tableBody.innerHTML = '';
      pager.innerHTML = '';
      return;
    }
    emptyBox.classList.add('hidden');
    tableBody.innerHTML = res.rows.map(rowHtml).join('');
    renderPager(res.page, res.pages);
  }

  btnRef.addEventListener('click', () => load(1));
  qInp.addEventListener('keydown', (e) => { if (e.key === 'Enter') load(1); });
  stSel.addEventListener('change', () => load(1));

  load(1);
})();
</script>
@endsection
