@php
  /** @var \App\Models\Product $p */
  $productSlug = $p->slug ?? (string) $p->id;
  $galleryFilenames = $p->productGalleryFilenames();
  $galleryUrls = collect($galleryFilenames)
    ->map(fn ($f) => $f ? asset('images/products/' . $f) : null)
    ->filter()
    ->values()
    ->all();
  $primaryFile = $p->primaryProductImageFilename();
  $imgUrl = $primaryFile ? asset('images/products/' . $primaryFile) : asset('images/products/no-image.png');
  $descShort = \Illuminate\Support\Str::limit(strip_tags($p->note ?? ''), 600);
  $minPrice  = (float) ($p->display_price ?? ($p->price ?? 0));
  $variants  = $p->relationLoaded('variants') ? $p->variants : collect($p->variants ?? []);
  $variants  = collect($variants);
  $variantPayload = $variants->map(function($v) use ($currency) {
    $final = (float) ($v->display_price ?? ($v->price ?? 0));
    return [
      'id' => (int) ($v->id ?? 0),
      'name' => (string) ($v->name ?? ''),
      'price' => (float) ($v->price ?? 0),
      'display_price' => $final,
      'display_price_formatted' => $currency . number_format($final, 2, '.', ','),
      'image' => !empty($v->image) ? asset('images/products/' . $v->image) : null,
      'stock' => (int) max(0, $v->stock ?? $v->qty ?? 0),
    ];
  })->values();
  $productStock = $variants->isEmpty() ? (int) max(0, $p->stock ?? 0) : null;

  $isPreorder = (bool) ($p->is_preorder ?? false);
  $preorderDate = $p->preorder_available_date ? $p->preorder_available_date->format('M d, Y') : null;

  $allowOverselling = isset($s) ? (bool) ($s->allow_overselling ?? true) : true;
  $hidePrices = !Auth::guard('store')->check() && isset($s) && ($s->hide_prices_for_guests ?? false);

  $isPreorderActive = false;
  if ($isPreorder) {
    $outOfStock = $variants->isEmpty()
      ? ($productStock !== null && $productStock <= 0)
      : !$variantPayload->contains(fn($v) => ($v['stock'] ?? 0) > 0);
    if ($outOfStock) { $isPreorderActive = true; }
  }

  if ($isPreorderActive) {
    $isAvailable = true;
    $availabilityLabel = $preorderDate
      ? __('messages.PreorderAvailableOn', ['date' => $preorderDate])
      : __('messages.PreorderAvailable');
    $stockDotClass = 'stock-dot-warn';
  } elseif ($allowOverselling) {
    $isAvailable = true;
    $availabilityLabel = null;
    $stockDotClass = 'stock-dot-ok';
  } else {
    if ($variants->isEmpty()) {
      $isAvailable = $productStock !== null && $productStock > 0;
      $availabilityLabel = $productStock !== null
        ? ($productStock > 0 ? __('messages.X_in_stock', ['count' => $productStock]) : __('messages.OutOfStock'))
        : null;
    } else {
      $isAvailable = $variantPayload->contains(fn($v) => ($v['stock'] ?? 0) > 0);
      $availabilityLabel = $isAvailable ? __('messages.InStock') : __('messages.OutOfStock');
    }
    $stockDotClass = $isAvailable ? 'stock-dot-ok' : 'stock-dot-out';
  }
@endphp

<article class="product-card">
  <a href="#" class="product-media js-quick-view"
     data-id="{{ $p->id }}"
     data-slug="{{ $productSlug }}"
     data-name="{{ e($p->name) }}"
     data-price="{{ number_format($minPrice, 2, '.', '') }}"
     data-image="{{ $imgUrl }}"
     data-gallery='@json($galleryUrls)'
     data-currency="{{ $currency }}"
     data-description="{{ e($descShort) }}"
     data-stock="{{ $productStock !== null ? $productStock : '' }}"
     data-variants='@json($variantPayload)'
     aria-label="{{ __('messages.QuickView') }}: {{ $p->name }}"
     @click.prevent>
    <img src="{{ $imgUrl }}" alt="{{ $p->name }}" loading="lazy">

    @if($isPreorderActive)
      <span class="product-badge product-badge-pre">{{ __('messages.PreOrder') }}</span>
    @elseif(!$isAvailable)
      <span class="product-badge product-badge-out">{{ __('messages.OutOfStock') }}</span>
    @endif

    <div class="product-actions">
      <button type="button" class="product-action-btn js-quick-view"
              title="{{ __('messages.QuickView') }}"
              data-id="{{ $p->id }}"
              data-slug="{{ $productSlug }}"
              data-name="{{ e($p->name) }}"
              data-price="{{ number_format($minPrice, 2, '.', '') }}"
              data-image="{{ $imgUrl }}"
              data-gallery='@json($galleryUrls)'
              data-currency="{{ $currency }}"
              data-description="{{ e($descShort) }}"
              data-stock="{{ $productStock !== null ? $productStock : '' }}"
              data-variants='@json($variantPayload)'
              aria-label="{{ __('messages.QuickView') }}">
        <x-store.icon name="eye" class="w-4 h-4" />
      </button>
    </div>
  </a>

  <div class="product-body">
    <h3 class="product-title" title="{{ $p->name }}">{{ $p->name }}</h3>

    @if(empty($hidePrices))
      <div class="price">{{ $currency }}{{ number_format($minPrice, 2, '.', ',') }}</div>
    @endif

    @if($availabilityLabel !== null && ($s->show_stock ?? true))
      <div class="product-meta">
        <span class="stock-dot {{ $stockDotClass }}"></span>
        <span>{{ $availabilityLabel }}</span>
      </div>
    @endif

    @if(empty($hidePrices))
      <button type="button"
              class="btn {{ $isPreorderActive ? 'btn-warning' : 'btn-primary' }} btn-sm btn-block mt-2 js-add-to-cart"
              @if(!$isAvailable) disabled @endif
              data-out-of-stock="{{ $isAvailable ? '0' : '1' }}"
              data-is-preorder="{{ $isPreorderActive ? '1' : '0' }}"
              data-id="{{ $p->id }}"
              data-slug="{{ $productSlug }}"
              data-name="{{ e($p->name) }}"
              data-price="{{ number_format($minPrice, 2, '.', '') }}"
              data-image="{{ $imgUrl }}"
              data-gallery='@json($galleryUrls)'
              data-currency="{{ $currency }}"
              data-qty="1"
              data-product-id="{{ $p->id }}"
              data-product-image="{{ $imgUrl }}"
              data-variants='@json($variantPayload)'
              data-stock="{{ $productStock !== null ? $productStock : '' }}"
              data-added-label="{{ __('messages.Added') }}">
        @if($isPreorderActive)
          <x-store.icon name="clock" class="w-4 h-4" />{{ __('messages.PreOrderNow') }}
        @else
          <x-store.icon name="cart" class="w-4 h-4" />{{ __('messages.AddToCart') }}
        @endif
      </button>
    @endif
    <div class="js-add-status text-xs text-fg-muted min-h-[1rem] mt-1"></div>
  </div>
</article>
