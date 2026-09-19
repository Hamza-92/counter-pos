@extends('control.layout')
@section('title', 'Login')
@section('guest-content')
<main class="card login">
    <div class="brand">Counter<span>POS</span> Control</div>
    <p class="muted">Sign in to manage tenant domains and database connections.</p>
    @include('control.partials.messages')
    <form method="post" action="{{ route('control.login.submit') }}">
        @csrf
        <div class="field"><label>Username</label><input name="username" value="{{ old('username') }}" required autofocus autocomplete="username"></div>
        <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>
        <button class="btn" type="submit">Sign in</button>
    </form>
</main>
@endsection
