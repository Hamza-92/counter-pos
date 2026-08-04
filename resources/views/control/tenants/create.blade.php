@extends('control.layout')
@section('title', 'Register customer')
@section('heading', 'Register customer')
@section('content')
<div class="card"><form method="post" action="{{ route('control.tenants.store') }}">@csrf
<div class="fields">
<div class="field"><label>Business name</label><input name="name" value="{{ old('name') }}" required></div>
<div class="field"><label>Internal slug</label><input name="slug" value="{{ old('slug') }}" required pattern="[A-Za-z0-9_-]+"></div>
<div class="field"><label>Contact name</label><input name="contact_name" value="{{ old('contact_name') }}"></div>
<div class="field"><label>Contact email</label><input type="email" name="contact_email" value="{{ old('contact_email') }}"></div>
<div class="field"><label>Contact phone</label><input name="contact_phone" value="{{ old('contact_phone') }}"></div>
</div><p><button class="btn">Create provisioning record</button></p></form></div>
@endsection
