@php
  // Expected vars: $q, $cat, $collection, $min, $max, $sort, $categories, $collections
  $isDrawer = $isDrawer ?? $isOffcanvas ?? false;
@endphp

<form method="get" action="{{ route('store.shop') }}" class="space-y-4">
  @foreach(request()->except(['page']) as $k => $v)
    @if(!in_array($k, ['q','category','collection','min','max','sort']))
      @if(is_array($v))
        @foreach($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
      @else
        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
      @endif
    @endif
  @endforeach

  <div class="card">
    <div class="card-body space-y-4">
      <div class="flex items-center justify-between">
        <h6 class="font-semibold m-0">{{ __('messages.Filters') }}</h6>
        <a href="{{ route('store.shop') }}" class="text-xs text-fg-muted hover:text-accent-500">{{ __('messages.Reset') }}</a>
      </div>

      <div>
        <label class="form-label">{{ __('messages.Search') }}</label>
        <input type="text" name="q" value="{{ $q }}" class="input"
               placeholder="{{ __('messages.SearchProducts') }}">
      </div>

      <div>
        <label class="form-label">{{ __('messages.Category') }}</label>
        <select name="category" class="select">
          <option value="">{{ __('messages.AllCategories') }}</option>
          @foreach($categories as $c)
            <option value="{{ $c->id }}" @selected((string)$cat === (string)$c->id)>{{ $c->name }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="form-label">{{ __('messages.Collection') }}</label>
        <select name="collection" class="select">
          <option value="">{{ __('messages.AllCollections') }}</option>
          @foreach($collections as $co)
            @php $isSelected = (string)$collection === (string)$co->slug || (string)$collection === (string)$co->id; @endphp
            <option value="{{ $co->slug }}" @selected($isSelected)>{{ $co->title ?: $co->slug }}</option>
          @endforeach
        </select>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="form-label">{{ __('messages.MinPrice') }}</label>
          <input type="number" step="0.01" min="0" name="min" value="{{ $min }}" class="input" placeholder="0">
        </div>
        <div>
          <label class="form-label">{{ __('messages.MaxPrice') }}</label>
          <input type="number" step="0.01" min="0" name="max" value="{{ $max }}" class="input" placeholder="9999">
        </div>
      </div>

      <div>
        <label class="form-label">{{ __('messages.Sort') }}</label>
        <select name="sort" class="select">
          <option value="latest" @selected(($sort ?? 'latest') === 'latest')>{{ __('messages.Latest') }}</option>
          <option value="price_asc" @selected($sort === 'price_asc')>{{ __('messages.PriceUp') }}</option>
          <option value="price_desc" @selected($sort === 'price_desc')>{{ __('messages.PriceDown') }}</option>
        </select>
      </div>

      <button class="btn btn-primary btn-block">
        <x-store.icon name="filter" class="w-4 h-4" />
        {{ $isDrawer ? __('messages.ApplyAndClose') : __('messages.ApplyFilters') }}
      </button>
    </div>
  </div>
</form>
