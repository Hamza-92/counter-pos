@extends('layouts.store')

@section('content')
@php
  $title   = __('messages.ContactUs');
  $email   = $s->contact_email ?? '';
  $phone   = $s->contact_phone ?? '';
  $address = $s->contact_address ?? '';
  use App\Models\StoreSetting;
  $s = $s ?? StoreSetting::first();
@endphp

<section class="py-8 border-b border-line-subtle"
         style="background: linear-gradient(135deg, rgb(var(--color-accent-500) / .12), rgb(var(--color-accent-400) / .06));">
  <div class="container">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <span class="section-kicker">{{ __('messages.ContactUs') }}</span>
        <h1 class="section-title mt-1">{{ $title }}</h1>
        <div class="text-fg-secondary mt-1">{{ __('messages.WeLoveToHearFromYou') }}</div>
      </div>
      <nav aria-label="breadcrumb">
        <ol class="flex items-center gap-2 text-sm text-fg-muted m-0 p-0 list-none">
          <li><a class="hover:text-accent-500" href="{{ route('store.index') }}">{{ __('messages.Home') }}</a></li>
          <li aria-hidden="true">/</li>
          <li class="text-fg-primary" aria-current="page">{{ $title }}</li>
        </ol>
      </nav>
    </div>
  </div>
</section>

<section class="py-10">
  <div class="container">
    <div class="grid lg:grid-cols-[1fr_2fr] gap-5">

      {{-- Contact info --}}
      <div>
        <div class="card h-full">
          <div class="card-body space-y-4">
            <h5 class="font-semibold mb-2">{{ __('messages.ContactInformation') }}</h5>

            <div class="flex items-start gap-3">
              <span class="w-10 h-10 rounded-full flex items-center justify-center text-accent-500 shrink-0"
                    style="background: rgb(var(--color-accent-500) / .1);">
                <x-store.icon name="mail" class="w-4 h-4" />
              </span>
              <div class="min-w-0">
                <div class="text-xs text-fg-muted">{{ __('messages.Email') }}</div>
                @if($email)
                  <a href="mailto:{{ $email }}" class="text-accent-500 hover:underline break-all">{{ $email }}</a>
                @else
                  <span class="text-fg-muted">{{ __('messages.NotProvided') }}</span>
                @endif
              </div>
            </div>

            <div class="flex items-start gap-3">
              <span class="w-10 h-10 rounded-full flex items-center justify-center text-accent-500 shrink-0"
                    style="background: rgb(var(--color-accent-500) / .1);">
                <x-store.icon name="phone" class="w-4 h-4" />
              </span>
              <div class="min-w-0">
                <div class="text-xs text-fg-muted">{{ __('messages.Phone') }}</div>
                @if($phone)
                  <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="text-accent-500 hover:underline">{{ $phone }}</a>
                @else
                  <span class="text-fg-muted">{{ __('messages.NotProvided') }}</span>
                @endif
              </div>
            </div>

            <div class="flex items-start gap-3">
              <span class="w-10 h-10 rounded-full flex items-center justify-center text-accent-500 shrink-0"
                    style="background: rgb(var(--color-accent-500) / .1);">
                <x-store.icon name="map-pin" class="w-4 h-4" />
              </span>
              <div class="min-w-0">
                <div class="text-xs text-fg-muted">{{ __('messages.Address') }}</div>
                @if($address)
                  <div>{{ $address }}</div>
                @else
                  <span class="text-fg-muted">{{ __('messages.NotProvided') }}</span>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Form --}}
      <div class="space-y-4">
        <div class="card">
          <div class="card-body">
            <h5 class="font-semibold mb-4">{{ __('messages.SendUsAMessage') }}</h5>

            <form id="contactForm" method="POST" action="{{ route('store.contact.send') }}" class="grid md:grid-cols-2 gap-4" novalidate>
              @csrf

              <div>
                <label class="form-label">{{ __('messages.YourName') }} *</label>
                <input type="text" name="name" class="input" required>
              </div>

              <div>
                <label class="form-label">{{ __('messages.EmailAddress') }} *</label>
                <input type="email" name="email" class="input" required>
              </div>

              <div>
                <label class="form-label">{{ __('messages.PhoneOptional') }}</label>
                <input type="text" name="phone" class="input">
              </div>

              <div>
                <label class="form-label">{{ __('messages.Subject') }}</label>
                <input type="text" name="subject" class="input" placeholder="{{ __('messages.HowCanWeHelp') }}">
              </div>

              <div class="md:col-span-2">
                <label class="form-label">{{ __('messages.Message') }} *</label>
                <textarea name="message" rows="5" class="input" required></textarea>
              </div>

              <div style="position:absolute; left:-10000px; top:auto;">
                <input type="text" name="company" tabindex="-1" autocomplete="off">
              </div>

              <div class="md:col-span-2 flex items-center justify-between flex-wrap gap-2">
                <small class="text-fg-muted">{{ __('messages.ReplyWithinOneBusinessDay') }}</small>
                <button id="contactSubmit" type="submit" class="btn btn-primary">
                  <x-store.icon name="send" class="w-4 h-4" />{{ __('messages.SendMessage') }}
                </button>
              </div>

              <div class="md:col-span-2">
                <div id="contactAlert" class="alert hidden" role="alert" aria-live="polite"></div>
              </div>
            </form>
          </div>
        </div>

        @if($address)
          <div class="card">
            <div class="card-body p-0">
              <div class="aspect-video w-full">
                <iframe
                  class="w-full h-full rounded-lg"
                  src="https://www.google.com/maps?q={{ urlencode($address) }}&output=embed"
                  loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade"
                  allowfullscreen>
                </iframe>
              </div>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('contactForm');
  var btn  = document.getElementById('contactSubmit');
  var box  = document.getElementById('contactAlert');

  function clearValidation() {
    var invalids = form.querySelectorAll('.is-invalid');
    for (var i = 0; i < invalids.length; i++) {
      invalids[i].classList.remove('is-invalid');
      invalids[i].style.borderColor = '';
    }
    var dyn = form.querySelectorAll('.js-dyn-err');
    for (var j = 0; j < dyn.length; j++) dyn[j].parentNode.removeChild(dyn[j]);
  }

  function showAlert(type, html) {
    box.className = 'alert alert-' + type;
    box.innerHTML = html;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearValidation();
    box.classList.add('hidden');

    var originalBtnHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>{{ __("messages.Sending") }}';

    var fd = new FormData(form);

    fetch(form.action, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
      },
      body: fd
    })
    .then(function (resp) {
      var ct = (resp.headers.get('content-type') || '').toLowerCase();
      if (ct.indexOf('application/json') !== -1) {
        return resp.json().then(function (json) { return { ok: resp.ok, status: resp.status, data: json }; });
      }
      return resp.text().then(function (text) { return { ok: resp.ok, status: resp.status, data: { message: text } }; });
    })
    .then(function (res) {
      if (res.ok) {
        showAlert('success', (res.data && res.data.message) ? res.data.message : '{{ __("messages.ContactSuccess") }}');
        form.reset();
        return;
      }

      if (res.status === 422 && res.data && res.data.errors) {
        var errors = res.data.errors || {};
        var listHtml = '<strong>{{ __("messages.FixFollowingAndTryAgain") }}</strong><ul class="mb-0 list-disc ps-5 mt-1">';
        for (var field in errors) {
          if (!errors.hasOwnProperty(field)) continue;
          var msgs = errors[field];
          for (var k = 0; k < msgs.length; k++) listHtml += '<li>' + msgs[k] + '</li>';
          var input = form.querySelector('[name="' + field + '"]');
          if (input) {
            input.classList.add('is-invalid');
            input.style.borderColor = 'rgb(var(--color-danger))';
            var div = document.createElement('div');
            div.className = 'js-dyn-err text-xs text-danger mt-1';
            div.textContent = msgs[0];
            if (input.parentNode) input.parentNode.appendChild(div);
          }
        }
        listHtml += '</ul>';
        showAlert('danger', listHtml);
        return;
      }

      var msg = (res.data && (res.data.message || res.data.error)) || '{{ __("messages.SomethingWentWrong") }}';
      showAlert('danger', msg);
    })
    .catch(function () {
      showAlert('danger', '{{ __("messages.NetworkErrorTryAgain") }}');
    })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = originalBtnHtml;
    });
  });
});
</script>
@endsection
