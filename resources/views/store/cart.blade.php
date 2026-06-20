@extends('layouts.store')

@section('content')
<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6">
    <span class="section-kicker">{{ __('messages.Cart') }}</span>
    <h1 class="section-title mt-1">{{ __('messages.YourCart') }}</h1>
  </div>
</section>

<div class="container py-8" id="cart-page">
  <div id="cart-empty" class="empty-state py-16 hidden">
    <div class="empty-icon">
      <x-store.icon name="cart" class="w-12 h-12" />
    </div>
    <h3>{{ __('messages.YourCartEmpty') }}</h3>
    <a href="{{ route('store.shop') }}" class="btn btn-primary mt-4">
      <x-store.icon name="bag" class="w-5 h-5" />{{ __('messages.GoToShop') }}
    </a>
  </div>

  <div id="cart-filled" class="hidden">
    <div class="card">
      <div class="card-body p-0">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-bg-muted text-fg-secondary">
              <tr>
                <th class="text-start font-medium px-4 py-3">{{ __('messages.Product') }}</th>
                <th class="text-end font-medium px-4 py-3">{{ __('messages.Price') }}</th>
                <th class="text-center font-medium px-4 py-3" style="width:180px">{{ __('messages.Qty') }}</th>
                <th class="text-end font-medium px-4 py-3">{{ __('messages.Total') }}</th>
                <th class="px-4 py-3" style="width:56px"></th>
              </tr>
            </thead>
            <tbody id="cart-body" class="divide-y divide-line-subtle"></tbody>
            <tfoot class="border-t-2 border-line">
              <tr>
                <td colspan="3" class="text-end text-fg-muted px-4 py-3">{{ __('messages.Subtotal') }}</td>
                <td class="text-end font-semibold px-4 py-3"><span id="subtotal-val">$0.00</span></td>
                <td></td>
              </tr>
              <tr>
                <td colspan="3" class="text-end text-lg font-bold px-4 py-4">{{ __('messages.GrandTotal') }}</td>
                <td class="text-end text-lg font-bold text-accent-500 px-4 py-4"><span id="grand-val">$0.00</span></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <div class="flex justify-between items-center mt-6 flex-wrap gap-2">
      <a href="{{ route('store.shop') }}" class="btn btn-outline">
        <x-store.icon name="arrow-left" class="w-4 h-4" />{{ __('messages.ContinueShopping') }}
      </a>
      <div class="flex gap-2">
        <button class="btn btn-outline-danger" id="btn-clear">
          <x-store.icon name="trash" class="w-4 h-4" />{{ __('messages.ClearCart') }}
        </button>
        <button class="btn btn-primary btn-lg" id="btn-checkout">
          <x-store.icon name="lightning" class="w-5 h-5" />{{ __('messages.Checkout') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const $empty       = document.getElementById('cart-empty');
  const $filled      = document.getElementById('cart-filled');
  const $body        = document.getElementById('cart-body');
  const $subtotal    = document.getElementById('subtotal-val');
  const $grand       = document.getElementById('grand-val');
  const $btnClear    = document.getElementById('btn-clear');
  const $btnCheckout = document.getElementById('btn-checkout');

  const CURRENCY   = document.querySelector('meta[name="currency"]')?.content || '$';
  const NOIMG      = @json(asset('images/products/no-image.png'));
  const T_REMOVE   = @json(__('messages.Remove'));
  const T_DECR     = @json(__('messages.Decrease'));
  const T_INCR     = @json(__('messages.Increase'));
  const T_VARIANT  = @json(__('messages.Variant'));
  const HIDE_PRICES = !!window.__HIDE_PRICES__;

  function money(val, currency) {
    const sym = currency || CURRENCY;
    return sym + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function escapeHtml(s){
    return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function variantBadge(it){
    const pill = (txt) => `<span class="chip">${txt}</span>`;
    if (it.variant_name && String(it.variant_name).trim()) {
      return pill(escapeHtml(it.variant_name));
    }
    if (it.product_variant_id) {
      return pill(`${T_VARIANT} #${String(it.product_variant_id)}`);
    }
    if (typeof it.id === 'string' && it.id.includes(':')) {
      const parts = it.id.split(':');
      if (parts[1]) return pill(`${T_VARIANT} #${parts[1]}`);
    }
    return '';
  }

  function render() {
    const cart = (window.CartLS && window.CartLS.get) ? CartLS.get() : {items:[], currency:CURRENCY, subtotal:0, grand:0};

    if (!cart.items.length) {
      $empty.classList.remove('hidden');
      $filled.classList.add('hidden');
      return;
    }
    $empty.classList.add('hidden');
    $filled.classList.remove('hidden');

    $body.innerHTML = '';
    for (const it of cart.items) {
      const tr = document.createElement('tr');
      tr.dataset.id = it.id;

      const vBadge   = variantBadge(it);
      const imgSrc   = it.image || NOIMG;
      const safeName = escapeHtml(it.name);
      const allowOverselling = window.__ALLOW_OVERSELLING__ !== false;
      const maxAttr = (!allowOverselling && it.stock != null) ? ' max="' + Math.max(1, Number(it.stock)) + '"' : '';

      tr.innerHTML = `
        <td class="px-4 py-3">
          <div class="flex items-center gap-3">
            <img src="${imgSrc}" alt="${safeName}" class="w-16 h-16 object-cover rounded-md border border-line-subtle">
            <div class="min-w-0">
              <div class="font-semibold text-fg-primary truncate">${safeName}</div>
              ${vBadge ? `<div class="mt-1">${vBadge}</div>` : ''}
              <div class="text-fg-muted text-xs mt-0.5">#${escapeHtml(String(it.id))}</div>
            </div>
          </div>
        </td>
        <td class="text-end px-4 py-3">${HIDE_PRICES ? '—' : money(it.price, it.currency)}</td>
        <td class="text-center px-4 py-3">
          <div class="qty-stepper mx-auto">
            <button class="js-dec" type="button" aria-label="${T_DECR}">−</button>
            <input type="number" class="js-qty" min="1"${maxAttr} value="${it.qty}">
            <button class="js-inc" type="button" aria-label="${T_INCR}">+</button>
          </div>
        </td>
        <td class="text-end font-semibold px-4 py-3 js-line">${HIDE_PRICES ? '—' : money((Number(it.price)||0) * (Number(it.qty)||0), it.currency)}</td>
        <td class="text-end px-4 py-3">
          <button class="btn btn-ghost btn-icon btn-sm js-remove text-danger" title="${T_REMOVE}" aria-label="${T_REMOVE}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </td>
      `;
      $body.appendChild(tr);
    }

    $subtotal.textContent = HIDE_PRICES ? '—' : money(cart.subtotal, cart.currency);
    $grand.textContent    = HIDE_PRICES ? '—' : money(cart.grand, cart.currency);
  }

  document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-id]');
    if (!row) return;

    if (e.target.closest('.js-dec')) {
      const input = row.querySelector('.js-qty');
      input.value = Math.max(1, parseInt(input.value || '1', 10) - 1);
      CartLS.setQty(row.dataset.id, parseInt(input.value, 10));
      render(); return;
    }
    if (e.target.closest('.js-inc')) {
      const input = row.querySelector('.js-qty');
      input.value = Math.max(1, parseInt(input.value || '1', 10) + 1);
      CartLS.setQty(row.dataset.id, parseInt(input.value, 10));
      render(); return;
    }
    if (e.target.closest('.js-remove')) {
      CartLS.remove(row.dataset.id);
      render(); return;
    }
  });

  document.addEventListener('change', (e) => {
    const input = e.target.closest('.js-qty');
    if (!input) return;
    const row = input.closest('tr[data-id]');
    const val = Math.max(1, parseInt(input.value || '1', 10));
    input.value = val;
    CartLS.setQty(row.dataset.id, val);
    render();
  });

  $btnClear?.addEventListener('click', () => {
    CartLS.clear();
    render();
  });

  $btnCheckout?.addEventListener('click', (e)=>{
    e.preventDefault();
    const CHECKOUT_URL = @json(route('checkout'));
    if (window.__LOGGED_IN__) {
      window.location.href = CHECKOUT_URL;
    } else if (window.StoreUI) {
      window.StoreUI.open('authModal');
    } else {
      window.location.href = @json(route('store.login.page', [], false));
    }
  });

  render();
  window.addEventListener('cart:changed', render);
})();
</script>
@endsection
