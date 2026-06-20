@php
  $currency = $currency ?? (isset($s) ? ($s->currency_code ?? '$') : '$');
@endphp
@include('store.partials.home-modals-scripts', ['currency' => $currency, 'nlBtn' => __('messages.Subscribe')])
<script>
  document.addEventListener('DOMContentLoaded', function(){ var f = document.getElementById('newsletterForm'); if (f) f.remove(); });
</script>
