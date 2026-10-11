<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $school['name'] }} — school information, admissions and contact details.">
    <title>{{ $school['name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-900" style="--school-primary: {{ $school['primary_color'] }}; --school-secondary: {{ $school['secondary_color'] }}">
    <header class="oak-landing-header shadow-sm">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4">
            <a wire:navigate href="{{ route('home') }}" class="flex items-center gap-3 text-lg font-bold" aria-label="{{ $school['name'] }} home">
                @if ($school['logo_url'])
                    <img src="{{ $school['logo_url'] }}" alt="{{ $school['name'] }} logo" class="h-12 w-12 rounded-full object-contain">
                @else
                    <span aria-hidden="true" class="oak-mark">{{ mb_substr($school['name'], 0, 1) }}</span>
                @endif
                <span>{{ $school['name'] }}</span>
            </a>
            <nav class="flex items-center gap-5 text-sm font-medium" aria-label="Main navigation">
                <a href="#about" class="hover:underline">About</a>
                <a href="#contact" class="hover:underline">Contact</a>
                <a wire:navigate href="{{ route('login') }}" class="oak-landing-signin rounded px-4 py-2">Portal sign in</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="oak-landing-hero">
            <div class="mx-auto grid max-w-6xl gap-10 px-5 py-20 md:grid-cols-2 md:items-center">
                <div>
                    <p class="mb-4 inline-block rounded-full px-4 py-1 text-sm font-semibold text-slate-900" style="background-color: var(--school-secondary)">Welcome</p>
                    <h1 class="text-4xl font-bold leading-tight md:text-6xl">{{ $school['name'] }}</h1>
                    @if ($school['motto'])<p class="mt-5 text-xl text-white/90">{{ $school['motto'] }}</p>@endif
                    <div class="mt-8 flex flex-wrap gap-3">
                        @if ($school['admissions_form_target'])
                            <a href="{{ $school['admissions_form_target'] }}" class="rounded px-6 py-3 font-semibold text-slate-900" style="background-color: var(--school-secondary)">Apply for admission</a>
                        @endif
                        <a href="#about" class="rounded border border-white/60 px-6 py-3 font-semibold">Learn about us</a>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/25 bg-white/10 p-8 backdrop-blur-sm">
                    @if ($school['logo_url'])<img src="{{ $school['logo_url'] }}" alt="{{ $school['name'] }} logo and motto" class="oak-home-logo mx-auto mb-6 rounded-2xl">@endif
                    <p class="text-sm uppercase tracking-widest text-white/70">A place to grow</p>
                    <p class="mt-4 text-2xl font-medium leading-relaxed">Learning begins with a welcoming community and a clear path forward.</p>
                    @if ($school['admissions_form_target'])
                        <p class="mt-6 text-white/80">Admissions information and the application form are available online.</p>
                    @else
                        <p class="mt-6 text-white/80">For admissions information, contact the school using the details below.</p>
                    @endif
                </div>
            </div>
        </section>

        <section id="about" class="mx-auto max-w-6xl px-5 py-16">
            <div class="max-w-3xl">
                <p class="text-sm font-bold uppercase tracking-widest" style="color: var(--school-primary)">About us</p>
                <h2 class="mt-2 text-3xl font-semibold">Get to know {{ $school['name'] }}</h2>
                @if ($school['about_text'])
                    <p class="mt-5 whitespace-pre-line text-lg leading-8 text-slate-700">{{ $school['about_text'] }}</p>
                @else
                    <p class="mt-5 text-lg leading-8 text-slate-700">More about our school will be shared here soon.</p>
                @endif
            </div>
        </section>

        <section id="contact" class="bg-white">
            <div class="mx-auto max-w-6xl px-5 py-16">
                <p class="text-sm font-bold uppercase tracking-widest" style="color: var(--school-primary)">Get in touch</p>
                <h2 class="mt-2 text-3xl font-semibold">Contact the school</h2>
                <div class="mt-8 grid gap-5 sm:grid-cols-3">
                    @if ($school['address'])
                        <div class="rounded-lg bg-stone-50 p-5"><h3 class="font-semibold">Visit</h3><p class="mt-2 whitespace-pre-line text-slate-700">{{ $school['address'] }}</p></div>
                    @endif
                    @if ($school['contact_email'])
                        <div class="rounded-lg bg-stone-50 p-5"><h3 class="font-semibold">Email</h3><a class="mt-2 block break-all text-blue-700 underline" href="mailto:{{ $school['contact_email'] }}">{{ $school['contact_email'] }}</a></div>
                    @endif
                    @if ($school['contact_phone'])
                        <div class="rounded-lg bg-stone-50 p-5"><h3 class="font-semibold">Call</h3><a class="mt-2 block text-blue-700 underline" href="tel:{{ $school['contact_phone'] }}">{{ $school['contact_phone'] }}</a></div>
                    @endif
                    @if (! $school['address'] && ! $school['contact_email'] && ! $school['contact_phone'])
                        <p class="text-slate-600">Contact details will be available soon.</p>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="px-5 py-6 text-center text-sm text-white" style="background-color: var(--school-primary)">
        &copy; {{ now()->year }} {{ $school['name'] }}.
    </footer>
@livewireScripts
</body>
</html>
