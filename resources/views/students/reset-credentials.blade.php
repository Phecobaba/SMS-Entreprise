<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Password reset</title>@vite(['resources/css/app.css','resources/js/app.js'])</head><body>@include('core._shell-start')<main class="ad-page st-page"><header class="ad-page-head"><div><p class="ad-eyebrow">Password reset</p><h1>Give these details to {{ $heading }}</h1><p>This is the only time the temporary password is shown.</p></div></header>
@php
    $profile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage();
    $isParent = isset($parent);
    $credentialSlips = [[
        'type' => $isParent ? 'parent' : 'student', 'learner_term' => $isParent ? null : app(\App\Modules\Academics\Services\LearnerTerminology::class)->word($student), 'school_name' => $profile['name'] ?? 'School',
        'name' => $user->name, 'class' => $isParent ? null : $student->classArm->schoolClass->name,
        'arm' => $isParent ? null : $student->classArm->name,
        'registration_number' => $isParent ? null : $student->registration_number,
        'children' => $isParent ? $parent->students->pluck('user.name')->filter()->values()->all() : [],
        'username' => $user->username ?: $user->email, 'temporary_password' => $temporary_password,
        'sign_in_address' => route('login'),
    ]];
@endphp
<div class="ad-actions ac-no-print"><button class="oak-btn oak-btn-quiet" type="button" data-copy-credentials>Copy credentials</button><span role="status" aria-live="polite" data-copy-status></span><button class="oak-btn oak-btn-quiet" type="button" onclick="window.print()">Print / Save as PDF</button></div>
@include('components.credential-slips', ['credentialSlips' => $credentialSlips])
<label class="ac-noted ac-no-print"><input type="checkbox" data-noted> I have noted or printed these details</label><a class="oak-btn oak-btn-primary is-disabled ac-no-print" data-done aria-disabled="true" href="{{ $doneUrl ?? route('students.show',$student) }}">Done</a></main>@include('core._shell-end') @livewireScripts<script>const n=document.querySelector('[data-noted]'),d=document.querySelector('[data-done]'),s=document.querySelector('[data-secret]');n.addEventListener('change',()=>{d.setAttribute('aria-disabled',String(!n.checked));d.classList.toggle('is-disabled',!n.checked)});d.addEventListener('click',e=>{e.preventDefault();if(!n.checked)return;s.textContent='';window.location.replace(d.href)});window.addEventListener('pagehide',()=>s.textContent='')</script></body></html>
