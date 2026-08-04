@extends('control.layout')
@section('title', 'Secure login')
@section('guest-content')
<main class="card login">
    <div class="brand">Counter<span>POS</span> Control</div>
    <p class="muted">Restricted administration. Password and authenticator code are both required.</p>
    @include('control.partials.messages')
    <form method="post" action="{{ route('control.login.submit') }}">
        @csrf
        <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
        <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>
        <div class="field"><label>Authenticator or recovery code</label><input class="code" name="code" inputmode="numeric" required autocomplete="one-time-code"></div>
        <button class="btn" type="submit">Secure sign in</button>
    </form>
</main>
@endsection
