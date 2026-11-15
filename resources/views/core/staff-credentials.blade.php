<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>One-time credentials</title>@vite(['resources/css/app.css','resources/js/app.js'])</head><body>
@include('core._shell-start')<main class="ad-page ac-page"><header class="ad-page-head"><div><p class="ad-eyebrow">{{ $context === 'reset' ? 'Password reset' : 'Account created' }}</p><h1>Give these details to {{ $user->name }}</h1><p>This is the only time the temporary password is shown.</p></div></header>
@php
    $profile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage();
    $credentialSlips = [[
        'type' => 'staff', 'school_name' => $profile['name'] ?? 'School', 'name' => $user->name,
        'role' => $user->roles->pluck('name')->join(', '), 'username' => $user->username,
        'temporary_password' => $temporary_password, 'sign_in_address' => route('login'),
    ]];
@endphp
<div class="ad-actions ac-no-print"><button class="oak-btn oak-btn-quiet" type="button" data-copy-credentials>Copy credentials</button><span role="status" aria-live="polite" data-copy-status></span><button class="oak-btn oak-btn-quiet" type="button" onclick="window.print()">Print / Save as PDF</button>@if($context === 'created')<a class="oak-btn oak-btn-secondary" href="{{ route('staff.index',['user'=>$user->id]) }}#add-employment-record">Add employment record</a>@endif</div>
@include('components.credential-slips', ['credentialSlips' => $credentialSlips])
<label class="ac-noted ac-no-print"><input type="checkbox" data-noted> I have noted or printed these details</label><a class="oak-btn oak-btn-primary ac-no-print" data-done aria-disabled="true" href="{{ route('staff.access.index') }}">Done</a></main>@include('core._shell-end') @livewireScripts
<script>const noted=document.querySelector('[data-noted]'),done=document.querySelector('[data-done]'),password=document.getElementById('temporary-password');noted.addEventListener('change',()=>{done.setAttribute('aria-disabled',String(!noted.checked));done.classList.toggle('is-disabled',!noted.checked)});done.addEventListener('click',e=>{e.preventDefault();if(!noted.checked)return;password.textContent='';window.location.replace(done.href)});window.addEventListener('pagehide',()=>{password.textContent=''});done.classList.add('is-disabled');</script></body></html>
