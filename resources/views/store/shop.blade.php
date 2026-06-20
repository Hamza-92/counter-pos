@extends('layouts.store')

@section('content')
@php
  $currency   = $s->currency_code ?? '$';
  $total      = $products->total();
  $hasFilters = filled($q ?? null) || filled($cat ?? null) || filled($collection ?? null) || filled($min ?? null) || filled($max ?? null);
@endphp

{{-- ===== Top bar ===== --}}
<section class="border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .04), rgb(var(--color-bg-surface)));">
  <div class="container py-6">
    <div class="flex items-end justify-between flex-wrap gap-4">
      <div>
        <span class="section-kicker">{{ __('messages.Shop') }}</span>
        <h1 class="section-title mt-1">{{ __('messages.Shop') }}</h1>
        <div class="text-sm text-fg-muted mt-1">
          {{ trans_choice('messages.products', $total, ['count' => $total]) }}
          @if($hasFilters) · <span class="text-accent-500">{{ __('messages.FiltersApplied') }}</span>@endif
        </div>
      </div>

      <form method="get" action="{{ route('store.shop') }}" class="flex items-end gap-2 flex-wrap">
        @foreach(request()->except(['sort','page']) as $k => $v)
          @if(is_array($v))
            @foreach($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
          @else
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
          @endif
        @endforeach

        <div class="flex items-end gap-2">
          <div>
            <label class="form-label text-xs mb-1">{{ __('messages.Sort') }}</label>
            <select name="sort" class="select h-9 text-sm py-1">
              <option value="latest" @selected(($sort ?? 'latest') === 'latest')>{{ __('messages.Latest') }}</option>
              <option value="price_asc" @selected($sort === 'price_asc')>{{ __('messages.PriceUp') }}</option>
              <option value="price_desc" @selected($sort === 'price_desc')>{{ __('messages.PriceDown') }}</option>
            </select>
          </div>
          <button class="btn btn-primary btn-sm" type="submit">
            <x-store.icon name="refresh" class="w-4 h-4" />{{ __('messages.Update') }}
          </button>
        </div>

        <button class="btn btn-outline btn-sm lg:hidden" type="button"
                @click="window.StoreUI.open('filtersDrawer')">
          <x-store.icon name="filter" class="w-4 h-4" />{{ __('messages.Filters') }}
        </button>
      </form>
    </div>
  </div>
</section>

<div class="container py-8">
  <div class="grid lg:grid-cols-[280px_1fr] gap-6">
    {{-- ===== Sidebar filters (desktop) ===== --}}
    <aside class="hidden lg:block">
      @include('store.partials.filters-card', [
        'q' => $q, 'cat' => $cat, 'collection' => $collection,
        'min' => $min, 'max' => $max, 'sort' => $sort,
        'categories' => $categories, 'collections' => $collections
      ])
    </aside>

    {{-- ===== Main content ===== --}}
    <main>
      @include('store.partials.shop-product-grid', [
        's' => $s,
        'products' => $products,
        'categories' => $categories,
        'collections' => $collections,
        'q' => $q,
        'cat' => $cat,
        'collection' => $collection,
        'min' => $min,
        'max' => $max,
        'sort' => $sort,
        'currency' => $currency,
      ])
    </main>
  </div>
</div>

{{-- ===== Drawer Filters (mobile) ===== --}}
<div x-data="drawer()" x-cloak id="filtersDrawer">
  <div x-show="isOpen" class="drawer-backdrop"
       x-transition.opacity
       @click="close()"></div>

  <aside class="drawer-panel drawer-end"
         x-show="isOpen"
         x-transition:enter="transition-transform duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition-transform duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         role="dialog" aria-modal="true" aria-label="{{ __('messages.Filters') }}">
    <div class="drawer-header">
      <h5 class="font-semibold m-0">{{ __('messages.Filters') }}</h5>
      <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="close()" aria-label="{{ __('messages.Close') }}">
        <x-store.icon name="x" class="w-5 h-5" />
      </button>
    </div>
    <div class="drawer-body">
      @include('store.partials.filters-card', [
        'q' => $q, 'cat' => $cat, 'collection' => $collection,
        'min' => $min, 'max' => $max, 'sort' => $sort,
        'categories' => $categories, 'collections' => $collections,
        'isDrawer' => true
      ])
    </div>
  </aside>
</div>

@include('store.partials.shop-modals-scripts', ['currency' => $currency])
@endsection
