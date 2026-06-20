@php
  $currency = $currency ?? (isset($s) ? ($s->currency_code ?? '$') : '$');
  $nlBtn    = $nlBtn ?? __('messages.Subscribe');
@endphp

{{-- Quick View dialog --}}
<div id="quickViewModal" x-data="dialog()" @keydown.window="onEsc">
  <div x-show="isOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4">
    <div x-show="isOpen" x-transition.opacity class="dialog-backdrop" @click="close"></div>
    <div x-show="isOpen"
         x-transition:enter="transition ease-smooth duration-base"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="dialog-panel dialog-lg" role="dialog" aria-modal="true">
      <div class="grid grid-cols-1 lg:grid-cols-2">
        <div class="relative bg-bg-muted min-h-[320px] lg:min-h-[480px] flex items-center justify-center overflow-hidden">
          <img id="qvImg" src="" alt="" class="max-w-full max-h-[70vh] object-contain transition-transform duration-fast" style="cursor: zoom-in;">
          <div id="qvGalleryThumbs" class="hidden absolute bottom-0 left-0 right-0 flex-wrap gap-1.5 justify-center p-2 bg-gradient-to-t from-black/60 to-transparent z-[2]"></div>
        </div>
        <div class="p-5">
          <div class="flex items-start justify-between gap-3 mb-2">
            <h3 id="qvTitle" class="text-lg font-semibold leading-tight">—</h3>
            <button type="button" class="btn btn-ghost btn-icon shrink-0" @click="close" aria-label="{{ __('messages.Close') }}">
              <x-store.icon name="x" class="w-5 h-5" />
            </button>
          </div>
          <div id="qvPrice" class="price price-lg text-accent-500 mb-3">—</div>
          <div id="qvDesc" class="text-sm text-fg-secondary mb-4 max-h-60 overflow-auto">—</div>
          <div id="qvVariantWrap" class="hidden mb-4">
            <div class="form-label">{{ __('messages.ChooseVariant') }}</div>
            <ul id="qvVariantList" class="divide-y divide-line-subtle border border-line-subtle rounded-md mb-2"></ul>
            <div id="qvSelectedInfo" class="text-xs text-fg-muted">—</div>
          </div>
          <button type="button" id="qvAddBtn" class="btn btn-primary btn-block">
            <x-store.icon name="cart" class="w-4 h-4" />{{ __('messages.AddToCart') }}
          </button>
          <div id="qvStatus" class="text-xs text-fg-muted mt-3 min-h-[1rem]"></div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Variant Picker dialog --}}
<div id="variantPickerModal" x-data="dialog()" @keydown.window="onEsc">
  <div x-show="isOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4">
    <div x-show="isOpen" x-transition.opacity class="dialog-backdrop" @click="close"></div>
    <div x-show="isOpen"
         x-transition:enter="transition ease-smooth duration-base"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="dialog-panel dialog-md" role="dialog" aria-modal="true">
      <div class="dialog-header">
        <h5 class="dialog-title">{{ __('messages.ChooseVariant') }}</h5>
        <button type="button" class="btn btn-ghost btn-icon" @click="close" aria-label="{{ __('messages.Close') }}">
          <x-store.icon name="x" class="w-5 h-5" />
        </button>
      </div>
      <div class="dialog-body">
        <div id="vpProductTitle" class="font-semibold mb-3">—</div>
        <ul id="vpVariantList" class="divide-y divide-line-subtle border border-line-subtle rounded-md mb-4"></ul>
        <div class="flex items-center justify-between">
          <div id="vpSelectedInfo" class="text-xs text-fg-muted">—</div>
          <button type="button" id="vpConfirmBtn" class="btn btn-primary" disabled>
            <x-store.icon name="cart" class="w-4 h-4" />{{ __('messages.AddToCart') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const NOIMG = @json(asset('images/products/no-image.png'));
  const CURRENCY = @json($currency);
  const ALLOW_OVERSELLING = window.__ALLOW_OVERSELLING__ !== false;
  const OUT_OF_STOCK_MSG = @json(__('messages.OutOfStock'));
  const PREORDER_MSG = @json(__('messages.PreOrderNow'));
  const ADD_MSG = @json(__('messages.AddToCart'));
  const SELECT_VARIANT_MSG = @json(__('messages.SelectVariant'));
  const HIDE_PRICES = !!window.__HIDE_PRICES__;
  const SHOW_STOCK = window.__SHOW_STOCK__ !== false;

  function safeParse(str){ try { return JSON.parse(str || '[]'); } catch(e){ return []; } }
  function formatPrice(amount, currency) {
    const sym = currency || CURRENCY;
    const num = Number(amount || 0);
    return sym + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  const money = (v, c) => formatPrice(v, c);
  const html = (s) => String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  // ---------- Quick View ----------
  const qvEl = document.getElementById('quickViewModal');
  const imgEl = document.getElementById('qvImg');
  const titleEl = document.getElementById('qvTitle');
  const priceEl = document.getElementById('qvPrice');
  const descEl = document.getElementById('qvDesc');
  const wrapEl = document.getElementById('qvVariantWrap');
  const listEl = document.getElementById('qvVariantList');
  const infoEl = document.getElementById('qvSelectedInfo');
  const addBtn = document.getElementById('qvAddBtn');
  const statusEl = document.getElementById('qvStatus');
  const qvThumbsEl = document.getElementById('qvGalleryThumbs');
  let qvProduct = null, qvSelected = null;
  let zoom = 1, maxZoom = 2.2;

  function setupQuickViewGallery(trigger, primaryUrl) {
    if (!qvThumbsEl) return;
    qvThumbsEl.innerHTML = '';
    const parsed = safeParse(trigger.dataset.gallery);
    const urls = (Array.isArray(parsed) && parsed.length) ? parsed : (primaryUrl ? [primaryUrl] : [NOIMG]);
    const clean = urls.map(u => (u && String(u).trim()) ? String(u).trim() : NOIMG);
    imgEl.src = clean[0] || NOIMG;
    if (clean.length > 1) {
      qvThumbsEl.classList.remove('hidden');
      qvThumbsEl.classList.add('flex');
      clean.forEach((u, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'w-[52px] h-[52px] rounded overflow-hidden border-2 border-transparent opacity-70 bg-black/30 cursor-pointer transition-all' + (i === 0 ? ' !opacity-100 !border-white' : '');
        const im = document.createElement('img');
        im.src = u;
        im.alt = '';
        im.className = 'w-full h-full object-cover';
        b.appendChild(im);
        b.addEventListener('click', function(ev) {
          ev.preventDefault();
          Array.prototype.forEach.call(qvThumbsEl.querySelectorAll('button'), (x, j) => {
            x.classList.toggle('!opacity-100', j === i);
            x.classList.toggle('!border-white', j === i);
          });
          imgEl.src = u || NOIMG;
          zoom = 1;
          imgEl.style.transform = 'scale(1)';
          imgEl.style.transformOrigin = 'center center';
          imgEl.style.cursor = 'zoom-in';
        });
        qvThumbsEl.appendChild(b);
      });
    } else {
      qvThumbsEl.classList.add('hidden');
      qvThumbsEl.classList.remove('flex');
    }
  }

  document.addEventListener('click', function(e){
    const trigger = e.target.closest('.js-quick-view');
    if(!trigger) return;
    e.preventDefault();
    const variants = safeParse(trigger.dataset.variants);
    const simpleStock = (trigger.dataset.stock !== undefined && trigger.dataset.stock !== '')
      ? parseInt(trigger.dataset.stock, 10) : null;
    const cardBtn = trigger.closest('.product-card')
      ? trigger.closest('.product-card').querySelector('.js-add-to-cart') : null;
    const isPreorder = cardBtn ? (cardBtn.dataset.isPreorder === '1') : false;
    const primaryImg = trigger.dataset.image || NOIMG;

    setupQuickViewGallery(trigger, primaryImg);
    qvProduct = {
      id: Number(trigger.dataset.id),
      slug: trigger.dataset.slug,
      name: trigger.dataset.name || '',
      price: parseFloat(trigger.dataset.price || '0'),
      image: primaryImg,
      currency: trigger.dataset.currency || CURRENCY,
      description: trigger.dataset.description || '',
      variants: Array.isArray(variants) ? variants : [],
      stock: simpleStock,
      is_preorder: isPreorder
    };
    titleEl.textContent = qvProduct.name || '—';
    descEl.innerHTML = (qvProduct.description || '').split('\n').map(html).join('<br>');
    listEl.innerHTML = '';
    qvSelected = null;

    if ((qvProduct.variants || []).length) {
      wrapEl.classList.remove('hidden');
      (qvProduct.variants || []).forEach(v => {
        const priceShow = (typeof v.display_price !== 'undefined') ? v.display_price : v.price;
        const vStock = (typeof v.stock !== 'undefined' && v.stock !== null) ? Number(v.stock) : null;
        const outOfStock = !ALLOW_OVERSELLING && vStock !== null && vStock <= 0;
        const stockLabel = (SHOW_STOCK && outOfStock) ? ' <span class="text-danger text-xs">— ' + html(OUT_OF_STOCK_MSG) + '</span>' : '';
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between p-3' + (outOfStock ? ' opacity-60' : '');
        li.innerHTML =
          '<div><div class="font-medium">'+html(v.name || '')+stockLabel+'</div>' +
          '<div class="text-xs text-fg-muted font-mono">'+(HIDE_PRICES ? '' : money(priceShow, qvProduct.currency))+'</div></div>' +
          '<div><input class="radio" type="radio" name="qvChoice" value="'+v.id+'"></div>';
        listEl.appendChild(li);
      });
      infoEl.textContent = SELECT_VARIANT_MSG;
      addBtn.disabled = true || HIDE_PRICES;
      listEl.addEventListener('change', onQVChange, { once:true });
      priceEl.textContent = HIDE_PRICES ? '' : money(qvProduct.price, qvProduct.currency);
    } else {
      wrapEl.classList.add('hidden');
      infoEl.textContent = '—';
      const simpleOut = !ALLOW_OVERSELLING && qvProduct.stock != null && Number(qvProduct.stock) <= 0;
      const canPreorder = qvProduct.is_preorder && simpleOut;
      addBtn.disabled = (simpleOut && !canPreorder) || HIDE_PRICES;
      if (canPreorder) {
        addBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>' + PREORDER_MSG + '</span>';
        addBtn.classList.remove('btn-primary'); addBtn.classList.add('btn-warning');
      } else {
        addBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2 3h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57L21 6H5"/></svg><span>' + ADD_MSG + '</span>';
        addBtn.classList.remove('btn-warning'); addBtn.classList.add('btn-primary');
        statusEl.textContent = (SHOW_STOCK && simpleOut) ? OUT_OF_STOCK_MSG : '';
      }
      priceEl.textContent = HIDE_PRICES ? '' : money(qvProduct.price, qvProduct.currency);
    }
    statusEl.textContent = '';
    window.StoreUI.open('quickViewModal');
  });

  function onQVChange(){
    const chosen = listEl.querySelector('input[name="qvChoice"]:checked');
    if (!chosen) {
      qvSelected = null;
      infoEl.textContent = SELECT_VARIANT_MSG;
      addBtn.disabled = true;
      statusEl.textContent = '';
    } else {
      const id = Number(chosen.value);
      qvSelected = (qvProduct.variants || []).find(v => Number(v.id) === id) || null;
      if (qvSelected) {
        const priceShow = (typeof qvSelected.display_price !== 'undefined') ? qvSelected.display_price : qvSelected.price;
        const vStock = (typeof qvSelected.stock !== 'undefined' && qvSelected.stock !== null) ? Number(qvSelected.stock) : null;
        const outOfStock = !ALLOW_OVERSELLING && vStock !== null && vStock <= 0;
        infoEl.textContent = (qvSelected.name || '')
          + (HIDE_PRICES ? '' : ' — ' + money(priceShow, qvProduct.currency))
          + (SHOW_STOCK && outOfStock ? ' (' + OUT_OF_STOCK_MSG + ')' : '');
        priceEl.textContent = HIDE_PRICES ? '' : money(priceShow, qvProduct.currency);
        addBtn.disabled = outOfStock || HIDE_PRICES;
        statusEl.textContent = (SHOW_STOCK && outOfStock) ? OUT_OF_STOCK_MSG : '';
      }
    }
    listEl.addEventListener('change', onQVChange, { once:true });
  }

  addBtn.addEventListener('click', function(){
    if (!qvProduct || addBtn.disabled) return;
    const isPreorder = !!qvProduct.is_preorder;
    if (!isPreorder && !ALLOW_OVERSELLING && (qvProduct.variants || []).length && qvSelected
        && (qvSelected.stock == null ? false : Number(qvSelected.stock) <= 0)) return;
    if (!isPreorder && !ALLOW_OVERSELLING && !(qvProduct.variants || []).length
        && qvProduct.stock != null && Number(qvProduct.stock) <= 0) return;
    let item;
    if ((qvProduct.variants || []).length) {
      if (!qvSelected) { statusEl.textContent = SELECT_VARIANT_MSG; return; }
      const priceUse = (typeof qvSelected.display_price !== 'undefined') ? qvSelected.display_price : qvSelected.price;
      const stock = (typeof qvSelected.stock !== 'undefined' && qvSelected.stock !== null) ? Number(qvSelected.stock) : null;
      item = {
        id: String(qvProduct.id) + ':' + String(qvSelected.id),
        product_id: qvProduct.id,
        product_variant_id: Number(qvSelected.id),
        name: (qvProduct.name || 'Item') + ' — ' + (qvSelected.name || ''),
        variant_name: qvSelected.name || '',
        price: Number(priceUse || 0),
        qty: 1,
        image: qvProduct.image || NOIMG,
        slug: qvProduct.slug || '',
        currency: qvProduct.currency || CURRENCY,
        stock: stock,
        is_preorder: isPreorder
      };
    } else {
      item = {
        id: String(qvProduct.id),
        product_id: qvProduct.id,
        name: qvProduct.name || 'Item',
        price: Number(qvProduct.price || 0),
        qty: 1,
        image: qvProduct.image || NOIMG,
        slug: qvProduct.slug || '',
        currency: qvProduct.currency || CURRENCY,
        stock: qvProduct.stock != null ? Number(qvProduct.stock) : null,
        is_preorder: isPreorder
      };
    }
    try { CartLS.add(item, 1); } catch(e){ console.error(e); }
    window.StoreUI.close('quickViewModal');
  });

  imgEl.addEventListener('click', function(){
    zoom = (zoom === 1 ? maxZoom : 1);
    imgEl.style.transform = 'scale(' + zoom + ')';
    imgEl.style.cursor = (zoom > 1 ? 'zoom-out' : 'zoom-in');
  });
  imgEl.addEventListener('mousemove', function(e){
    if(zoom === 1) return;
    const rect = imgEl.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    imgEl.style.transformOrigin = x + '% ' + y + '%';
  });
  qvEl.addEventListener('dialog-closed', () => {
    zoom = 1;
    imgEl.style.transform = 'scale(1)';
    imgEl.style.transformOrigin = 'center center';
    if (qvThumbsEl) { qvThumbsEl.innerHTML = ''; qvThumbsEl.classList.add('hidden'); qvThumbsEl.classList.remove('flex'); }
  });

  // ---------- Variant Picker ----------
  const vpEl = document.getElementById('variantPickerModal');
  const vpTitle = document.getElementById('vpProductTitle');
  const vpList = document.getElementById('vpVariantList');
  const vpInfo = document.getElementById('vpSelectedInfo');
  const vpBtn = document.getElementById('vpConfirmBtn');
  let vpProduct = null, vpSelected = null;

  function openVariantPicker(source){
    const variants = safeParse(source.dataset.variants);
    vpProduct = {
      id: Number(source.dataset.productId || source.dataset.id),
      name: source.dataset.name || source.getAttribute('data-name') || '',
      image: source.dataset.productImage || source.dataset.image || NOIMG,
      currency: source.dataset.currency || CURRENCY,
      slug: source.dataset.slug || '',
      variants: Array.isArray(variants) ? variants : []
    };
    vpSelected = null;
    vpTitle.textContent = vpProduct.name || '';
    vpList.innerHTML = '';
    (vpProduct.variants || []).forEach(v => {
      const priceShow = (typeof v.display_price !== 'undefined') ? v.display_price : v.price;
      const vStock = (typeof v.stock !== 'undefined' && v.stock !== null) ? Number(v.stock) : null;
      const outOfStock = !ALLOW_OVERSELLING && vStock !== null && vStock <= 0;
      const stockLabel = (SHOW_STOCK && outOfStock) ? ' <span class="text-danger text-xs">— ' + html(OUT_OF_STOCK_MSG) + '</span>' : '';
      const li = document.createElement('li');
      li.className = 'flex items-center justify-between p-3' + (outOfStock ? ' opacity-60' : '');
      li.innerHTML =
        '<div><div class="font-medium">'+html(v.name || '')+stockLabel+'</div>' +
        '<div class="text-xs text-fg-muted font-mono">'+(HIDE_PRICES ? '' : money(priceShow, vpProduct.currency))+'</div></div>' +
        '<div><input class="radio" type="radio" name="vpChoice" value="'+v.id+'"></div>';
      vpList.appendChild(li);
    });
    vpInfo.textContent = SELECT_VARIANT_MSG;
    vpBtn.disabled = true || HIDE_PRICES;
    vpList.addEventListener('change', onVPChange, { once:true, passive:false });
    window.StoreUI.open('variantPickerModal');
  }

  function onVPChange(){
    const chosen = vpList.querySelector('input[name="vpChoice"]:checked');
    if (!chosen){
      vpSelected = null;
      vpBtn.disabled = true;
      vpInfo.textContent = SELECT_VARIANT_MSG;
    } else {
      const id = Number(chosen.value);
      vpSelected = (vpProduct.variants || []).find(v => Number(v.id) === id) || null;
      if (vpSelected){
        const priceShow = (typeof vpSelected.display_price !== 'undefined') ? vpSelected.display_price : vpSelected.price;
        vpInfo.textContent = (vpSelected.name || '') + (HIDE_PRICES ? '' : ' — ' + money(priceShow, vpProduct.currency));
        vpBtn.disabled = HIDE_PRICES;
      }
    }
    vpList.addEventListener('change', onVPChange, { once:true, passive:false });
  }

  vpBtn.addEventListener('click', function(){
    if (!vpProduct || !vpSelected || vpBtn.disabled) return;
    if (!ALLOW_OVERSELLING && vpSelected.stock != null && Number(vpSelected.stock) <= 0) return;
    const priceUse = (typeof vpSelected.display_price !== 'undefined') ? vpSelected.display_price : vpSelected.price;
    const stock = (typeof vpSelected.stock !== 'undefined' && vpSelected.stock !== null) ? Number(vpSelected.stock) : null;
    const item = {
      id: String(vpProduct.id) + ':' + String(vpSelected.id),
      product_id: vpProduct.id,
      product_variant_id: Number(vpSelected.id),
      name: (vpProduct.name || 'Item') + ' — ' + (vpSelected.name || ''),
      variant_name: vpSelected.name || '',
      price: Number(priceUse || 0),
      qty: 1,
      image: vpProduct.image || NOIMG,
      slug: vpProduct.slug || '',
      currency: vpProduct.currency || CURRENCY,
      stock: stock
    };
    try { CartLS.add(item, 1); } catch(e){ console.error(e); }
    window.StoreUI.close('variantPickerModal');
  });

  // Intercept .js-add-to-cart for products that have variants — open picker first.
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-add-to-cart');
    if (!btn) return;
    const variants = safeParse(btn.dataset.variants);
    if (Array.isArray(variants) && variants.length){
      e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
      if (!btn.dataset.productId) btn.dataset.productId = btn.dataset.id || '';
      if (!btn.dataset.productImage) btn.dataset.productImage = btn.dataset.image || NOIMG;
      openVariantPicker(btn);
      return;
    }
  }, true);

  // ---------- Newsletter ----------
  document.addEventListener("DOMContentLoaded", function(){
    const form = document.getElementById("newsletterForm");
    const emailInput = document.getElementById("newsletterEmail");
    const btn = document.getElementById("newsletterBtn");
    const msg = document.getElementById("newsletterMsg");
    if(!form) return;
    form.addEventListener("submit", async function(e){
      e.preventDefault();
      msg.textContent = "";
      btn.disabled = true;
      const originalHTML = btn.innerHTML;
      btn.innerHTML = '<span class="spinner spinner-sm"></span>';
      try {
        const resp = await fetch(@json(route('newsletter.subscribe')), {
          method: "POST",
          headers: {
            "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
            "Accept": "application/json"
          },
          body: new FormData(form)
        });
        const data = await resp.json().catch(() => ({}));
        if (resp.ok) {
          msg.className = "text-success text-sm mt-2";
          msg.textContent = @json(__('messages.NewsletterThanks'));
          emailInput.value = "";
        } else {
          msg.className = "text-danger text-sm mt-2";
          msg.textContent = data.message || @json(__('messages.NewsletterFailed'));
        }
      } catch (err) {
        msg.className = "text-danger text-sm mt-2";
        msg.textContent = @json(__('messages.NetworkErrorTryAgain'));
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      }
    });
  });
})();
</script>
