<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Forgot password?</title>@vite(['resources/css/app.css','resources/js/app.js'])</head><body class="lg-body">
@php($profile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage())
<div class="lg">@include('core._recovery-brand')
<main class="lg-main"><div class="lg-card ac-recovery-card"><p class="ad-eyebrow">Account recovery</p><h1>Forgot password?</h1><p class="oak-sub">Enter your email address or username.</p>
@if($errors->any())<div class="oak-notice oak-notice-danger" role="alert">{{ $errors->first() }}</div>@endif
<form class="lg-form" method="post" action="{{ route('password.email') }}">@csrf<div class="oak-field"><label for="identifier">Email or username</label><input id="identifier" name="identifier" value="{{ old('identifier') }}" autocomplete="username" required></div><button class="oak-btn oak-btn-primary oak-btn-block">Send reset link</button></form>
<a class="oak-link" href="{{ route('login') }}">Back to sign in</a></div></main></div></body></html>
