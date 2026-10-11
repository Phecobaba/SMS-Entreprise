<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php($school = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage())
<div class="lg">
    <div class="lg-brand">@if($school['logo_url'] ?? null)<img src="{{ $school['logo_url'] }}" alt="{{ $school['name'] ?? 'Oakfield' }}">@else<span class="oak-school-name">{{ $school['name'] ?? 'Oakfield' }}</span>@endif</div>
    <div class="lg-top"><span class="oak-logo-crop">@if($school['logo_url'] ?? null)<img class="oak-logo-mark" src="{{ $school['logo_url'] }}" alt="">@endif</span><span class="oak-school-name">{{ $school['name'] ?? 'Oakfield' }}</span></div>
    <main class="lg-main"><div class="lg-card">
        <h1>Sign in</h1><p class="oak-sub">Use the email or username and password the school gave you.</p>
        @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><x-icon name="alert-triangle" /><span>{{ $errors->first() }}</span></div>@endif
        @if(session('retry_after'))<div class="oak-notice oak-notice-warning" role="alert" data-retry-seconds="{{ (int) session('retry_after') }}"><x-icon name="clock" /><span>Too many attempts. Try again in <strong data-retry-count>{{ (int) session('retry_after') }}</strong> seconds.</span></div>@endif
        <form method="post" action="{{ route('login.store') }}" class="lg-form">@csrf
            <div class="oak-field"><label for="identifier">Email or username</label><input id="identifier" name="identifier" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required value="{{ old('identifier') }}" @error('identifier') aria-invalid="true" @enderror></div>
            <div class="oak-field"><label for="password">Password</label><div class="lg-pw"><input id="password" name="password" type="password" autocomplete="current-password" required><button type="button" aria-label="Show password" aria-pressed="false" onclick="const p=document.getElementById('password');const show=p.type==='password';p.type=show?'text':'password';this.setAttribute('aria-pressed',String(show));this.setAttribute('aria-label',show?'Hide password':'Show password')"><x-icon name="eye" /></button></div></div>
            <label class="lg-check"><input name="remember" type="checkbox" value="1"> Keep me signed in on this device</label>
            <button class="oak-btn oak-btn-primary oak-btn-block" type="submit" @if(session('retry_after')) disabled @endif>Sign in</button>
        </form>
        <p class="oak-sub">Forgot your password? Ask the school office to reset it.</p>
        <a class="oak-link" href="{{ route('home') }}">Back to the school website</a>
    </div></main>
</div>
<script>const throttle=document.querySelector('[data-retry-seconds]');if(throttle){let left=Number(throttle.dataset.retrySeconds);const button=document.querySelector('.lg-form [type="submit"]');const count=throttle.querySelector('[data-retry-count]');const timer=setInterval(()=>{left=Math.max(0,left-1);count.textContent=String(left);if(left===0){clearInterval(timer);button.disabled=false;throttle.hidden=true}},1000)}</script>
</body>
</html>
