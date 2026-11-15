<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student created</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body>
@include('core._shell-start')
<main class="ad-page st-page">
    <header class="ad-page-head"><div><p class="ad-eyebrow">Student created</p><h1>Give these sign-in details to the family</h1><p>This is the only time the temporary passwords are shown.</p></div></header>
    @php
        $profile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage();
        $credentialSlips = collect([[
            'type' => 'student', 'learner_term' => app(\App\Modules\Academics\Services\LearnerTerminology::class)->word($student), 'school_name' => $profile['name'] ?? 'School', 'name' => $student_user->name,
            'class' => $student->classArm->schoolClass->name, 'arm' => $student->classArm->name,
            'registration_number' => $student->registration_number, 'username' => $student_user->username,
            'temporary_password' => $student_password, 'sign_in_address' => route('login'),
        ]]);
        if ($parent_user && $parent_password) $credentialSlips->push([
            'type' => 'parent', 'school_name' => $profile['name'] ?? 'School', 'name' => $parent_user->name,
            'children' => [$student_user->name], 'username' => $parent_user->username,
            'temporary_password' => $parent_password, 'sign_in_address' => route('login'),
        ]);
    @endphp
    <div class="ad-actions ac-no-print"><button class="oak-btn oak-btn-quiet" type="button" data-copy-credentials>Copy credentials</button><span role="status" aria-live="polite" data-copy-status></span><button class="oak-btn oak-btn-quiet" type="button" onclick="window.print()">Print / Save as PDF</button></div>
    @include('components.credential-slips', ['credentialSlips' => $credentialSlips])
    <label class="ac-noted ac-no-print"><input type="checkbox" data-noted> I have noted or printed these details</label>
    <a class="oak-btn oak-btn-primary ac-no-print is-disabled" data-done aria-disabled="true" href="{{ route('students.show',$student) }}">Done</a>
</main>
@include('core._shell-end')
@livewireScripts
<script>const noted=document.querySelector('[data-noted]'),done=document.querySelector('[data-done]');const clear=()=>document.querySelectorAll('[data-secret]').forEach(x=>x.textContent='');noted.addEventListener('change',()=>{done.setAttribute('aria-disabled',String(!noted.checked));done.classList.toggle('is-disabled',!noted.checked)});done.addEventListener('click',e=>{e.preventDefault();if(!noted.checked)return;clear();window.location.replace(done.href)});window.addEventListener('pagehide',clear)</script>
</body>
</html>

