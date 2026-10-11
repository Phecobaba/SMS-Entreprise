<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>School profile</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
@include('core._shell-start')
    <main class="mx-auto max-w-3xl px-4 py-8">
        <a wire:navigate href="{{ route('dashboard') }}" class="text-sm text-blue-700 underline">Back to dashboard</a>
        <h1 class="mt-2 text-3xl font-semibold">School profile</h1>
        <p class="mt-2 text-slate-600">These details appear on the public landing page. The school name is {{ $school->name }}.</p>

        @if (session('status'))<div role="status" class="mt-5 rounded border border-green-300 bg-green-50 p-3 text-green-900">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div role="alert" class="mt-5 rounded border border-red-300 bg-red-50 p-3 text-red-900">
                <p class="font-medium">Please fix the following:</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="post" action="{{ route('school-profile.update') }}" enctype="multipart/form-data" class="mt-6 grid gap-5 rounded-lg bg-white p-6 shadow sm:grid-cols-2">
            @csrf
            @method('PUT')
            <label class="sm:col-span-2">Motto
                <input name="motto" maxlength="160" value="{{ old('motto', $settings->motto) }}" class="mt-1 w-full rounded border p-2">
            </label>
            <label class="sm:col-span-2">About the school
                <textarea name="about_text" rows="5" maxlength="3000" class="mt-1 w-full rounded border p-2">{{ old('about_text', $settings->about_text) }}</textarea>
            </label>
            <label>Email
                <input type="email" name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}" class="mt-1 w-full rounded border p-2">
            </label>
            <label>Phone
                <input name="contact_phone" maxlength="40" value="{{ old('contact_phone', $settings->contact_phone) }}" class="mt-1 w-full rounded border p-2">
            </label>
            <label class="sm:col-span-2">Address
                <input name="address" maxlength="255" value="{{ old('address', $settings->address) }}" class="mt-1 w-full rounded border p-2">
            </label>
            <label>Primary color
                <input type="color" name="primary_color" value="{{ old('primary_color', $settings->primary_color ?: '#2E4F56') }}" class="mt-1 h-11 w-full rounded border p-1">
            </label>
            <label>Accent color
                <input type="color" name="secondary_color" value="{{ old('secondary_color', $settings->secondary_color ?: '#F5E58A') }}" class="mt-1 h-11 w-full rounded border p-1">
            </label>
            <label class="sm:col-span-2">Admissions form link
                <input name="admissions_form_target" maxlength="255" value="{{ old('admissions_form_target', $settings->admissions_form_target) }}" placeholder="/admissions/register or https://example.com/apply" class="mt-1 w-full rounded border p-2">
                <span class="mt-1 block text-sm text-slate-600">Use a path on this site or a secure HTTPS address. Leave blank until the form is ready.</span>
            </label>
            <label class="sm:col-span-2">School logo
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" class="mt-1 block w-full rounded border p-2">
                <span class="mt-1 block text-sm text-slate-600">PNG, JPG, WEBP, or GIF; up to 2 MB. Leave empty to keep the current logo.</span>
            </label>
            @if ($settings->logo_path)
                <p class="text-sm text-slate-600 sm:col-span-2">Current logo: <img src="{{ Storage::disk('public')->url($settings->logo_path) }}" alt="Current school logo" class="mt-2 h-16 w-16 object-contain"></p>
            @endif
            <button class="rounded bg-blue-700 px-5 py-3 font-semibold text-white sm:col-span-2">Save school profile</button>
        </form>
    </main>
@include('core._shell-end')
@livewireScripts
</body>
</html>
