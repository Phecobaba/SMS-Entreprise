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
    <section class="ad-cred" id="credential-panel">
        <h2>Student</h2>
        <dl class="ac-credentials">
            <div><dt>Name</dt><dd>{{ $student_user->name }}</dd></div>
            @if($student->registration_number)<div><dt>Registration number</dt><dd>{{ $student->registration_number }}</dd></div>@endif
            <div><dt>Username</dt><dd>{{ $student_user->username }}</dd></div>
            <div><dt>Temporary password</dt><dd><code data-secret>{{ $student_password }}</code></dd></div>
        </dl>
        @if($parent_user)
            <h2>Parent</h2>
            <dl class="ac-credentials">
                <div><dt>Name</dt><dd>{{ $parent_user->name }}</dd></div>
                <div><dt>Username</dt><dd>{{ $parent_user->username }}</dd></div>
                <div><dt>Temporary password</dt><dd><code data-secret>{{ $parent_password }}</code></dd></div>
            </dl>
        @endif
        <button class="oak-btn oak-btn-quiet ac-no-print" type="button" onclick="window.print()">Print slip</button>
    </section>
    <label class="ac-noted ac-no-print"><input type="checkbox" data-noted> I have noted or printed these details</label>
    <a class="oak-btn oak-btn-primary ac-no-print is-disabled" data-done aria-disabled="true" href="{{ route('students.show',$student) }}">Done</a>
</main>
@include('core._shell-end')
@livewireScripts
<script>const noted=document.querySelector('[data-noted]'),done=document.querySelector('[data-done]');const clear=()=>document.querySelectorAll('[data-secret]').forEach(x=>x.textContent='');noted.addEventListener('change',()=>{done.setAttribute('aria-disabled',String(!noted.checked));done.classList.toggle('is-disabled',!noted.checked)});done.addEventListener('click',e=>{e.preventDefault();if(!noted.checked)return;clear();window.location.replace(done.href)});window.addEventListener('pagehide',clear)</script>
</body>
</html>

