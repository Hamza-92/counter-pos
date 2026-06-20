@extends('layouts.store')

@section('content')
@php
  $currency = $s->currency_code ?? '$';
  use App\Models\StoreSetting;

  $s = $s ?? StoreSetting::first();
  $u = auth('store')->user();
  $client = $u ? $u->client : null;
  $stripeKey = config('services.stripe.key');
@endphp

<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6">
    <span class="section-kicker">{{ __('messages.Checkout') }}</span>
    <h1 class="section-title mt-1">{{ __('messages.Checkout') }}</h1>
  </div>
</section>

<div class="container py-8" id="checkout-app">
  <div class="max-w-3xl mx-auto">
    <div class="card">
      <div class="card-body space-y-6">

        @if ($client)
        <div>
          <h5 class="font-semibold mb-3 flex items-center gap-2">
            <x-store.icon name="truck" class="w-5 h-5 text-accent-500" />{{ __('messages.Shipping') }}
          </h5>
          <div class="text-sm space-y-1 text-fg-secondary">
            <div class="flex items-center gap-2"><x-store.icon name="user" class="w-4 h-4" /> {{ $client->name }}</div>
            <div class="flex items-center gap-2"><x-store.icon name="phone" class="w-4 h-4" /> {{ $client->phone }}</div>
            <div class="flex items-center gap-2"><x-store.icon name="map-pin" class="w-4 h-4" /> {{ $client->adresse }}</div>
          </div>
        </div>
        <hr class="border-line-subtle">
        @endif

        <div>
          <h5 class="font-semibold mb-3 flex items-center gap-2">
            <x-store.icon name="package" class="w-5 h-5 text-accent-500" />{{ __('messages.OrderSummary') }}
          </h5>

          <div id="summary-empty" class="empty-state py-8 hidden">
            <div class="empty-icon"><x-store.icon name="cart" class="w-10 h-10" /></div>
            <p class="mt-2 text-fg-muted">{{ __('messages.YourCartIsEmpty') }}</p>
            <a href="{{ route('store.shop') }}" class="btn btn-outline mt-3">{{ __('messages.GoToShop') }}</a>
          </div>

          <div id="summary-list" class="divide-y divide-line-subtle"></div>
        </div>

        <hr class="border-line-subtle">

        <div class="space-y-2">
          <div class="flex justify-between text-sm text-fg-muted">
            <span>{{ __('messages.Subtotal') }}</span>
            <strong id="sum-subtotal" class="text-fg-primary">{{ $currency }}0.00</strong>
          </div>
          <div class="flex justify-between text-lg font-bold">
            <span>{{ __('messages.GrandTotal') }}</span>
            <strong id="sum-grand" class="text-accent-500">{{ $currency }}0.00</strong>
          </div>
        </div>

        <hr class="border-line-subtle" id="payment-divider">

        {{-- ===== PAYMENT METHOD SELECTION ===== --}}
        <div id="payment-section">
          <h5 class="font-semibold mb-3 flex items-center gap-2">
            <x-store.icon name="credit-card" class="w-5 h-5 text-accent-500" />{{ __('messages.PaymentMethod') }}
          </h5>

          <div class="pay-methods" id="payment-methods">
            {{-- Credit Card (Stripe) --}}
            <label class="pay-option {{ $stripeKey ? '' : 'pay-option-disabled' }}" data-method="credit_card">
              <input type="radio" name="payment_method" value="credit_card" class="pay-radio" {{ $stripeKey ? '' : 'disabled' }}>
              <div class="pay-inner">
                <div class="pay-header">
                  <div class="flex items-center gap-3">
                    <div class="pay-icon pay-icon-card"><x-store.icon name="credit-card" class="w-5 h-5" /></div>
                    <div>
                      <div class="font-semibold">{{ __('messages.CreditCard') }}</div>
                      <div class="text-xs text-fg-muted">{{ __('messages.PayWithStripe') }}</div>
                    </div>
                  </div>
                  <div class="pay-check"><x-store.icon name="check-circle" class="w-6 h-6" /></div>
                </div>
                @if($stripeKey)
                <div class="pay-body" id="stripe-card-wrapper">
                  <div id="stripe-card-element" class="stripe-card-element"></div>
                  <div id="stripe-card-errors" class="text-danger text-xs mt-2" role="alert"></div>
                </div>
                @else
                <div class="pay-body pay-body-warning">
                  <div class="alert alert-warning text-xs mb-0 flex items-start gap-2">
                    <x-store.icon name="alert" class="w-4 h-4 mt-0.5 shrink-0" />
                    <span>{{ __('messages.StripeNotConfigured') }}</span>
                  </div>
                </div>
                @endif
              </div>
            </label>

            {{-- Mobile Money --}}
            <label class="pay-option" data-method="mobile_money">
              <input type="radio" name="payment_method" value="mobile_money" class="pay-radio">
              <div class="pay-inner">
                <div class="pay-header">
                  <div class="flex items-center gap-3">
                    <div class="pay-icon pay-icon-mobile"><x-store.icon name="phone" class="w-5 h-5" /></div>
                    <div>
                      <div class="font-semibold">{{ __('messages.MobileMoney') }}</div>
                      <div class="text-xs text-fg-muted">{{ __('messages.MobileMoneyDesc') }}</div>
                    </div>
                  </div>
                  <div class="pay-check"><x-store.icon name="check-circle" class="w-6 h-6" /></div>
                </div>
                <div class="pay-body" id="mobile-money-wrapper">
                  <div class="alert alert-info text-xs mb-0 flex items-start gap-2">
                    <x-store.icon name="info" class="w-4 h-4 mt-0.5 shrink-0" />
                    <span>{{ __('messages.MobileMoneyInstructions') }}</span>
                  </div>
                </div>
              </div>
            </label>

            {{-- Cash on Delivery --}}
            <label class="pay-option" data-method="cod">
              <input type="radio" name="payment_method" value="cod" class="pay-radio" checked>
              <div class="pay-inner">
                <div class="pay-header">
                  <div class="flex items-center gap-3">
                    <div class="pay-icon pay-icon-cod"><x-store.icon name="cash" class="w-5 h-5" /></div>
                    <div>
                      <div class="font-semibold">{{ __('messages.CashOnDelivery') }}</div>
                      <div class="text-xs text-fg-muted">{{ __('messages.CashOnDeliveryDesc') }}</div>
                    </div>
                  </div>
                  <div class="pay-check"><x-store.icon name="check-circle" class="w-6 h-6" /></div>
                </div>
              </div>
            </label>
          </div>
        </div>

        <div id="place-order-section">
          <button class="btn btn-primary btn-lg btn-block" id="btnPlaceOrder">
            <span id="btn-text" class="inline-flex items-center gap-2">
              <x-store.icon name="shield-check" class="w-5 h-5" />{{ __('messages.PlaceOrder') }}
            </span>
            <span id="btn-spinner" class="hidden inline-flex items-center gap-2">
              <svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              {{ __('messages.Processing') }}
            </span>
          </button>
        </div>

      </div>
    </div>
  </div>
</div>

<style>
  .co-line {
    display: grid;
    grid-template-columns: 54px 1fr 140px 110px 40px;
    align-items: center;
    gap: .75rem;
    padding: .75rem 0;
  }
  .co-thumb { width:54px; height:54px; object-fit:cover; border-radius:.5rem; border:1px solid rgb(var(--color-border-subtle)); }

  .co-line .qty-stepper { width: 140px; }

  .co-line .js-line { text-align: end; min-width:110px; font-weight:600; }
  .co-line .js-remove {
    width:36px; height:36px; display:inline-flex; justify-content:center; align-items:center; padding:0;
  }

  /* Payment methods */
  .pay-methods { display: flex; flex-direction: column; gap: .75rem; }

  .pay-option { cursor: pointer; margin: 0; display: block; }
  .pay-option .pay-radio { position: absolute; opacity: 0; pointer-events: none; }

  .pay-inner {
    border: 2px solid rgb(var(--color-border-subtle));
    border-radius: 12px;
    transition: all .2s ease;
    overflow: hidden;
    background: rgb(var(--color-bg-surface));
  }
  .pay-option:hover .pay-inner { border-color: rgb(var(--color-border-strong)); }
  .pay-option .pay-radio:checked ~ .pay-inner {
    border-color: rgb(var(--color-accent-500));
    background: rgb(var(--color-accent-500) / .04);
    box-shadow: 0 0 0 1px rgb(var(--color-accent-500));
  }

  .pay-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem;
  }

  .pay-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    flex-shrink: 0;
  }
  .pay-icon-card   { background: linear-gradient(135deg, #667eea, #764ba2); }
  .pay-icon-mobile { background: linear-gradient(135deg, #f093fb, #f5576c); }
  .pay-icon-cod    { background: linear-gradient(135deg, #4facfe, #00f2fe); }

  .pay-check { color: rgb(var(--color-border-subtle)); transition: color .2s; }
  .pay-option .pay-radio:checked ~ .pay-inner .pay-check { color: rgb(var(--color-accent-500)); }

  .pay-body { display: none; padding: 0 1.25rem 1rem; }
  .pay-option .pay-radio:checked ~ .pay-inner .pay-body { display: block; }

  .pay-option-disabled { cursor: not-allowed; }
  .pay-option-disabled .pay-inner { opacity: .55; background: rgb(var(--color-bg-muted)); }
  .pay-option-disabled:hover .pay-inner { border-color: rgb(var(--color-border-subtle)); }
  .pay-body-warning { display: block !important; }

  .stripe-card-element {
    padding: .75rem;
    border: 1px solid rgb(var(--color-border-subtle));
    border-radius: 8px;
    background: rgb(var(--color-bg-surface));
    transition: border-color .2s;
  }
  .stripe-card-element.StripeElement--focus {
    border-color: rgb(var(--color-accent-500));
    box-shadow: 0 0 0 2px rgb(var(--color-accent-500) / .18);
  }
  .stripe-card-element.StripeElement--invalid {
    border-color: rgb(var(--color-danger));
  }

  @media (max-width: 560px) {
    .co-line { grid-template-columns: 54px 1fr; grid-auto-rows: auto; }
    .co-line .qty-stepper,
    .co-line .js-line,
    .co-line .js-remove {
      margin-inline-start: calc(54px + .75rem);
      margin-top: .4rem;
    }
  }
</style>

@if($stripeKey)
<script src="https://js.stripe.com/v3/"></script>
@endif

<script>
(function(){
  var currencyMeta = document.querySelector('meta[name="currency"]');
  var csrfMeta     = document.querySelector('meta[name="csrf-token"]');
  var CURRENCY     = currencyMeta ? currencyMeta.content : @json($currency);
  var CSRF         = csrfMeta ? csrfMeta.content : '';
  var NOIMG        = @json(asset('images/products/no-image.png'));
  var STRIPE_KEY   = @json($stripeKey ?? '');
  var T_PREORDER   = @json(__('messages.PreOrder'));
  var T_REMOVE     = @json(__('messages.Remove'));

  function fmt(v){ return CURRENCY + Number(v||0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function esc(s){ return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function getCart(){
    if (window.CartLS && typeof window.CartLS.get === 'function') return window.CartLS.get();
    try {
      var raw = JSON.parse(localStorage.getItem('shop.cart.v1')||'{}');
      if (!raw || !Array.isArray(raw.items)) return { items:[], currency:CURRENCY, subtotal:0, grand:0 };
      raw.subtotal = raw.items.reduce(function(a,i){ return a + (Number(i.price)||0)*(Number(i.qty)||0); }, 0);
      raw.grand = raw.subtotal;
      return raw;
    } catch(e){ return { items:[], currency:CURRENCY, subtotal:0, grand:0 }; }
  }

  function extractIds(item){
    var pid  = item.product_id != null ? Number(item.product_id) : null;
    var pvid = item.product_variant_id != null ? Number(item.product_variant_id) : null;
    if (pid == null){
      var parts = String(item.id||'').split(':');
      pid  = Number(parts[0] || 0) || 0;
      pvid = (pvid != null) ? pvid : (parts[1] ? Number(parts[1]) : null);
    }
    return { product_id: pid, product_variant_id: pvid };
  }

  var listEl   = document.getElementById('summary-list');
  var emptyEl  = document.getElementById('summary-empty');
  var subEl    = document.getElementById('sum-subtotal');
  var grandEl  = document.getElementById('sum-grand');
  var btn      = document.getElementById('btnPlaceOrder');
  var btnText  = document.getElementById('btn-text');
  var btnSpin  = document.getElementById('btn-spinner');
  var paySec   = document.getElementById('payment-section');
  var payDiv   = document.getElementById('payment-divider');
  var placeSec = document.getElementById('place-order-section');

  var stripe, cardElement;
  if (STRIPE_KEY) {
    stripe = Stripe(STRIPE_KEY);
    var elements = stripe.elements();
    var isDark = document.documentElement.classList.contains('dark');
    cardElement = elements.create('card', {
      style: {
        base: {
          fontSize: '15px',
          color: isDark ? '#e6e7eb' : '#111827',
          fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
          '::placeholder': { color: isDark ? '#6b7280' : '#9ca3af' }
        },
        invalid: { color: '#ef4444' }
      }
    });
    cardElement.mount('#stripe-card-element');
    cardElement.on('change', function(event) {
      var errEl = document.getElementById('stripe-card-errors');
      errEl.textContent = event.error ? event.error.message : '';
    });
  }

  function render(){
    var cart = getCart();

    if (!cart.items || !cart.items.length){
      emptyEl.classList.remove('hidden');
      listEl.innerHTML = '';
      subEl.textContent   = fmt(0);
      grandEl.textContent = fmt(0);
      if (paySec)   paySec.classList.add('hidden');
      if (payDiv)   payDiv.classList.add('hidden');
      if (placeSec) placeSec.classList.add('hidden');
      return;
    }

    emptyEl.classList.add('hidden');
    if (paySec)   paySec.classList.remove('hidden');
    if (payDiv)   payDiv.classList.remove('hidden');
    if (placeSec) placeSec.classList.remove('hidden');
    listEl.innerHTML = '';

    cart.items.forEach(function(it){
      var row = document.createElement('div');
      row.className = 'co-line';
      row.dataset.id = it.id;

      var variantBadge  = it.variant_name ? '<div class="mt-1"><span class="chip text-xs">'+ esc(it.variant_name) +'</span></div>' : '';
      var preorderBadge = it.is_preorder ? '<div class="mt-1"><span class="chip text-xs" style="color: rgb(var(--color-warning));">'+ T_PREORDER +'</span></div>' : '';

      row.innerHTML =
        '<img class="co-thumb" src="'+ esc(it.image || NOIMG) +'" alt="'+ esc(it.name||'') +'">' +
        '<div class="min-w-0">' +
          '<div class="font-semibold truncate" title="'+ esc(it.name||'') +'">'+ esc(it.name||'') +'</div>' +
          variantBadge +
          preorderBadge +
          '<div class="text-xs text-fg-muted mt-1">'+ fmt(it.price) +'</div>' +
        '</div>' +
        '<div class="qty-stepper">' +
          '<button class="js-dec" type="button">−</button>' +
          '<input type="number" class="js-qty" value="'+ (it.qty||1) +'" min="1">' +
          '<button class="js-inc" type="button">+</button>' +
        '</div>' +
        '<div class="js-line">'+ fmt((Number(it.price)||0)*(Number(it.qty)||0)) +'</div>' +
        '<button class="btn btn-ghost btn-icon btn-sm js-remove text-danger" type="button" title="'+ T_REMOVE +'" aria-label="'+ T_REMOVE +'">' +
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
        '</button>';

      listEl.appendChild(row);
    });

    var sub = cart.items.reduce(function(a,i){ return a + (Number(i.price)||0)*(Number(i.qty)||0); }, 0);
    subEl.textContent   = fmt(sub);
    grandEl.textContent = fmt(sub);
  }

  listEl.addEventListener('click', function(e){
    var row = e.target.closest('.co-line'); if(!row) return;
    var id  = row.dataset.id;

    if (e.target.closest('.js-dec')) {
      var inp = row.querySelector('.js-qty');
      var v   = Math.max(1, parseInt(inp.value||'1',10) - 1);
      inp.value = v;
      if (window.CartLS && CartLS.setQty) CartLS.setQty(id, v);
      render();
    }
    if (e.target.closest('.js-inc')) {
      var inp = row.querySelector('.js-qty');
      var v   = Math.max(1, parseInt(inp.value||'1',10) + 1);
      inp.value = v;
      if (window.CartLS && CartLS.setQty) CartLS.setQty(id, v);
      render();
    }
    if (e.target.closest('.js-remove')) {
      if (window.CartLS && CartLS.remove) CartLS.remove(id);
      render();
    }
  });

  listEl.addEventListener('change', function(e){
    var inp = e.target.closest('.js-qty'); if(!inp) return;
    var row = inp.closest('.co-line');
    var id  = row.dataset.id;
    var v   = Math.max(1, parseInt(inp.value||'1',10));
    inp.value = v;
    if (window.CartLS && CartLS.setQty) CartLS.setQty(id, v);
    render();
  });

  window.addEventListener('cart:changed', render);

  function getSelectedPaymentMethod() {
    var checked = document.querySelector('input[name="payment_method"]:checked');
    return checked ? checked.value : 'cod';
  }

  function setLoading(loading) {
    btn.disabled = loading;
    btnText.classList.toggle('hidden', loading);
    btnSpin.classList.toggle('hidden', !loading);
  }

  var THANKYOU_URL       = "{{ url('/online_store/thank-you') }}";
  var CREATE_URL         = "{{ route('store.orders.store') }}";
  var PAYMENT_INTENT_URL = "{{ route('store.payment.intent') }}";

  if (btn) {
    btn.addEventListener('click', function(){
      var cart = getCart();
      if (!cart.items || !cart.items.length) { alert('{{ __("messages.YourCartIsEmpty") }}'); return; }

      var items = cart.items.map(function(i){
        var ids = extractIds(i);
        return {
          product_id:         Number(ids.product_id || 0),
          product_variant_id: (ids.product_variant_id != null ? Number(ids.product_variant_id) : null),
          qty:                Number(i.qty||1),
          price:              Number(i.price||0),
          name:               i.name || null
        };
      });

      items = items.filter(function(x){ return x.product_id > 0 && x.qty > 0 && x.price >= 0; });
      if (!items.length){ alert('{{ __("messages.YourCartIsEmpty") }}'); return; }

      var paymentMethod = getSelectedPaymentMethod();
      setLoading(true);

      if (paymentMethod === 'credit_card') {
        handleStripePayment(items, cart);
      } else {
        submitOrder(items, cart, paymentMethod, null);
      }
    });
  }

  function handleStripePayment(items, cart) {
    if (!stripe || !cardElement) {
      alert('Stripe is not available.');
      setLoading(false);
      return;
    }

    var subtotal = cart.items.reduce(function(a,i){ return a + (Number(i.price)||0)*(Number(i.qty)||0); }, 0);

    fetch(PAYMENT_INTENT_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CSRF
      },
      body: JSON.stringify({
        amount: subtotal,
        currency: (cart.currency || CURRENCY || 'usd').toLowerCase().replace(/[^a-z]/g, '') || 'usd'
      })
    })
    .then(function(res){
      if (!res.ok) return res.json().then(function(e){ throw e; });
      return res.json();
    })
    .then(function(data){
      return stripe.confirmCardPayment(data.clientSecret, {
        payment_method: { card: cardElement }
      });
    })
    .then(function(result){
      if (result.error) {
        document.getElementById('stripe-card-errors').textContent = result.error.message;
        setLoading(false);
        return;
      }
      if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
        submitOrder(items, cart, 'credit_card', result.paymentIntent.id);
      } else {
        alert('{{ __("messages.PaymentFailed") }}');
        setLoading(false);
      }
    })
    .catch(function(err){
      console.error(err);
      var msg = (err && (err.message || err.error)) || '{{ __("messages.PaymentFailed") }}';
      alert(msg);
      setLoading(false);
    });
  }

  function submitOrder(items, cart, paymentMethod, stripePaymentIntentId) {
    var payload = {
      items: items,
      payment_method: paymentMethod,
      stripe_payment_intent_id: stripePaymentIntentId
    };

    fetch(CREATE_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CSRF
      },
      body: JSON.stringify(payload)
    })
    .then(function(res){
      if (!res.ok) return res.json().then(function(e){ throw e || new Error('Request failed'); });
      return res.json();
    })
    .then(function(order){
      var subtotal = cart.items.reduce(function(a,i){ return a + (Number(i.price)||0)*(Number(i.qty)||0); }, 0);
      var receipt  = {
        order_id: order.id,
        order_no: order.ref || order.code || ('#'+order.id),
        placed_at: new Date().toISOString(),
        currency: cart.currency || CURRENCY,
        items: cart.items,
        payment_method: paymentMethod,
        payment_status: order.payment_status || 'pending',
        totals: { subtotal: subtotal.toFixed(2), grand: Number(order.total||subtotal).toFixed(2) }
      };
      try { localStorage.setItem('shop.last_order', JSON.stringify(receipt)); } catch(e){}
      try { if (window.CartLS && CartLS.clear) CartLS.clear(); else localStorage.removeItem('shop.cart.v1'); } catch(e){}
      window.location.href = THANKYOU_URL;
    })
    .catch(function(err){
      console.error(err);
      var msg = (err && (err.message || err.error)) || '{{ __("messages.CouldNotPlaceOrder") }}';
      if (err && Array.isArray(err.items) && err.items.length) {
        msg = '{{ __("messages.InsufficientStockFor") }}\n' + err.items.map(function(x){
          return (x.name || ('#'+x.product_id)) + ' — {{ __("messages.Available") }}: ' + x.available + ', {{ __("messages.Required") }}: ' + x.required;
        }).join('\n');
      }
      alert(msg);
      setLoading(false);
    });
  }

  render();
})();
</script>
@endsection
