@extends('layouts.store')

@section('content')
@php
  $currency = $s->currency_code ?? '$';
  use App\Models\StoreSetting;

  $s = $s ?? StoreSetting::first();
@endphp

<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div>
        <a href="{{ route('account.orders') }}" class="inline-flex items-center gap-2 text-sm text-fg-secondary hover:text-accent-500">
          <x-store.icon name="arrow-left" class="w-4 h-4" />{{ __('messages.BackToOrders') }}
        </a>
        <h1 class="section-title mt-2">{{ __('messages.OrderDetails') }}</h1>
      </div>
      <div class="flex gap-2">
        <button class="btn btn-outline btn-sm" id="btnPrint">
          <x-store.icon name="printer" class="w-4 h-4" />{{ __('messages.Print') }}
        </button>
      </div>
    </div>
  </div>
</section>

<div class="container py-8" id="order-app" data-order-id="{{ $id ?? request()->route('id') }}">
  <div class="max-w-5xl mx-auto">

    {{-- Header card --}}
    <div class="card mb-4">
      <div class="card-body">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="text-fg-muted text-xs uppercase tracking-wide">{{ __('messages.Order') }}</div>
            <h3 class="text-xl font-bold m-0 mt-1">
              <span id="o-code">—</span>
            </h3>
            <div class="text-sm text-fg-muted mt-1">
              <span id="o-date">—</span> · <span id="o-time">—</span>
            </div>
          </div>
          <div class="text-end">
            <span id="o-status-badge" class="chip">—</span>
            <div class="text-xs text-fg-muted mt-2 flex items-center gap-1 justify-end">
              <x-store.icon name="package" class="w-3.5 h-3.5" />
              <span id="o-warehouse">—</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Items + Summary --}}
    <div class="grid lg:grid-cols-[1.4fr_1fr] gap-4">
      <div>
        <div class="card">
          <div class="card-body p-0">
            <div class="px-4 py-3 border-b border-line-subtle">
              <h6 class="font-semibold m-0">{{ __('messages.Items') }}</h6>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-bg-muted text-fg-secondary">
                  <tr>
                    <th class="text-start font-medium px-4 py-2">{{ __('messages.Product') }}</th>
                    <th class="text-center font-medium px-4 py-2" style="width:100px">{{ __('messages.Qty') }}</th>
                    <th class="text-end font-medium px-4 py-2" style="width:120px">{{ __('messages.Price') }}</th>
                    <th class="text-end font-medium px-4 py-2" style="width:140px">{{ __('messages.Total') }}</th>
                  </tr>
                </thead>
                <tbody id="o-items" class="divide-y divide-line-subtle">
                  <tr><td colspan="4" class="text-center text-fg-muted py-6">—</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div class="card">
          <div class="card-body">
            <h6 class="font-semibold mb-3">{{ __('messages.Summary') }}</h6>
            <ul class="list-none p-0 m-0 space-y-2">
              <li class="flex justify-between text-sm text-fg-muted">
                <span>{{ __('messages.Subtotal') }}</span>
                <strong class="text-fg-primary" id="o-subtotal">{{ $currency }}0.00</strong>
              </li>
              <li class="flex justify-between text-sm text-fg-muted">
                <span>{{ __('messages.Shipping') }}</span>
                <strong class="text-fg-primary" id="o-shipping">{{ $currency }}0.00</strong>
              </li>
              <li class="flex justify-between text-sm text-fg-muted">
                <span>{{ __('messages.Discount') }}</span>
                <strong class="text-fg-primary" id="o-discount">-{{ $currency }}0.00</strong>
              </li>
              <li class="flex justify-between text-lg font-bold border-t border-line-subtle pt-3 mt-2">
                <span>{{ __('messages.Total') }}</span>
                <strong class="text-accent-500" id="o-total">{{ $currency }}0.00</strong>
              </li>
            </ul>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h6 class="font-semibold mb-2">{{ __('messages.StatusHelp') }}</h6>
            <ul class="text-sm text-fg-muted m-0 p-0 list-none space-y-1.5">
              <li class="flex items-start gap-2"><span class="chip chip-warning shrink-0">pending</span> <span>{{ __('messages.StatusPendingHelp') }}</span></li>
              <li class="flex items-start gap-2"><span class="chip chip-success shrink-0">confirmed</span> <span>{{ __('messages.StatusConfirmedHelp') }}</span></li>
              <li class="flex items-start gap-2"><span class="chip chip-danger shrink-0">cancelled</span> <span>{{ __('messages.StatusCancelledHelp') }}</span></li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    {{-- Empty state --}}
    <div id="o-empty" class="empty-state py-12 hidden">
      <div class="empty-icon"><x-store.icon name="receipt" class="w-10 h-10" /></div>
      <h3>{{ __('messages.OrderNotFound') }}</h3>
      <a href="{{ route('account.orders') }}" class="btn btn-outline mt-4">{{ __('messages.BackToOrders') }}</a>
    </div>

  </div>
</div>

<script>
(function(){
  const wrap  = document.getElementById('order-app');
  const id    = wrap?.dataset.orderId;
  const cur   = document.querySelector('meta[name="currency"]')?.content || '{{ $currency }}';

  const el = {
    code:      document.getElementById('o-code'),
    date:      document.getElementById('o-date'),
    time:      document.getElementById('o-time'),
    badge:     document.getElementById('o-status-badge'),
    wh:        document.getElementById('o-warehouse'),
    items:     document.getElementById('o-items'),
    subtotal:  document.getElementById('o-subtotal'),
    shipping:  document.getElementById('o-shipping'),
    discount:  document.getElementById('o-discount'),
    total:     document.getElementById('o-total'),
    empty:     document.getElementById('o-empty'),
  };

  function money(n){ return cur + Number(n||0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function badgeClass(status){
    status = String(status||'').toLowerCase();
    return status === 'pending'   ? 'chip chip-warning'
         : status === 'confirmed' ? 'chip chip-success'
         : status === 'cancelled' ? 'chip chip-danger'
         : 'chip';
  }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  async function load(){
    if (!id) return showEmpty();
    try{
      const res = await fetch(`/online_store/my/orders/${id}`, { headers:{'Accept':'application/json'} });
      if (!res.ok) throw new Error('not ok');
      const o = await res.json();

      el.code.textContent = o.code || ('#'+o.id);
      el.date.textContent = o.date || '—';
      el.time.textContent = o.time || '—';
      el.wh.textContent   = o.warehouse_name || '—';

      el.badge.className  = badgeClass(o.status);
      el.badge.textContent= o.status || '—';

      el.items.innerHTML = '';
      if (Array.isArray(o.items) && o.items.length){
        o.items.forEach(it=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="px-4 py-3">${escapeHtml(it.name||('#'+it.product_id))}</td>
            <td class="text-center px-4 py-3">${Number(it.qty||0)}</td>
            <td class="text-end px-4 py-3">${money(it.price)}</td>
            <td class="text-end px-4 py-3 font-semibold">${money((it.price||0)*(it.qty||0))}</td>
          `;
          el.items.appendChild(tr);
        });
      } else {
        el.items.innerHTML = `<tr><td colspan="4" class="text-center text-fg-muted py-6">—</td></tr>`;
      }

      el.subtotal.textContent = money(o.subtotal);
      el.shipping.textContent = money(o.shipping||0);
      el.discount.textContent = '-' + money(o.discount||0);
      el.total.textContent    = money(o.total);

    } catch(e){
      showEmpty();
    }
  }

  function showEmpty(){
    el.empty?.classList.remove('hidden');
  }

  document.getElementById('btnPrint')?.addEventListener('click', ()=> window.print());

  load();
})();
</script>
@endsection
