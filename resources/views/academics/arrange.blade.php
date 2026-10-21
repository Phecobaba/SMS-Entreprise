<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Arrange classes · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php $pageTitle = 'Arrange classes'; @endphp
@include('core._shell-start')
<main class="oak-dashboard">
    <div class="oak-dashboard-heading"><div><h1>Arrange your classes from first to last</h1><p>{{ $canViewAll ? 'All sections' : ($sections->first()?->name ?? 'Your section') }}</p></div><span class="oak-role-pill">{{ $canViewAll ? 'Admin' : ($sections->first()?->head_title ?? 'Head') }}</span></div>
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status"><x-icon name="circle-check" /><div><strong>Saved.</strong> Promotion is now available. You can still change the order with Move up and Move down on Classes.</div></div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><div><b>We couldn't save the order.</b> Nothing was changed.<ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    @if(!$hasSection)
        <div class="oak-locked"><x-icon name="lock" /><h3>You haven't been assigned to a section yet</h3><p>A head arranges only their own section's classes. Ask the Admin to assign you in Academic structure.</p></div>
    @elseif($allArranged)
        <div class="ad-bar"><a class="oak-btn oak-btn-secondary" href="{{ route('academics.classes.index') }}" wire:navigate>Back to classes</a>@if(Route::has('academics.promotion.index'))<a class="oak-btn oak-btn-primary" href="{{ route('academics.promotion.index') }}" wire:navigate>Go to Promotion</a>@endif</div>
    @else
        <div class="oak-notice oak-notice-info"><x-icon name="info-circle" /><div><b>This is a best guess.</b> We ordered your classes by section, then level, then name. Move any class that is out of place. Nothing, including Promotion, uses this order until you press <b>Save this order</b>.</div></div>
        <form method="post" action="{{ route('academics.classes.arrange.store') }}" data-arrange-form>@csrf
            @foreach($sections as $section)
                <input type="hidden" name="section_ids[]" value="{{ $section->id }}">
                <section class="ad-card ad-arrange-section">
                    <h2 class="ad-group-title">{{ $section->name }} <small>{{ $section->levels->sum(fn($level) => $level->classes->count()) }} classes</small></h2>
                    @foreach($arrangements[$section->id] as $levelBlock)
                        <h3 class="ad-meta ad-level-label">{{ $levelBlock['level']->name }}</h3>
                        <div class="ad-arrange-level" data-arrange-level>
                            @foreach($levelBlock['steps'] as $step)
                                @php $names = $step->pluck('name'); @endphp
                                <div class="ad-class-row ad-arrange-step" data-arrange-step>
                                    <input type="hidden" name="steps[{{ $section->id }}][]" value="{{ $step->pluck('id')->join(',') }}">
                                    <div><h4>{{ $names->join(' and ') }}</h4>@if($step->count() > 1)<small class="ad-meta">Same step. They move together.</small>@endif</div>
                                    <div class="ad-actions"><button class="ad-iconbtn" type="button" data-move-step="up" aria-label="Move {{ $names->join(' and ') }} up"><x-icon name="arrow-up" /></button><button class="ad-iconbtn" type="button" data-move-step="down" aria-label="Move {{ $names->join(' and ') }} down"><x-icon name="arrow-down" /></button></div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    @if($section->levels->sum(fn($level) => $level->classes->count()) === 0)<p class="oak-empty">No classes yet. Saving marks this empty section as arranged.</p>@endif
                </section>
            @endforeach
            <div class="ad-bar"><span class="ad-meta" data-arrange-status>Not saved yet.</span><button class="oak-btn oak-btn-primary">Save this order</button></div>
        </form>
    @endif
</main>
@include('core._shell-end')
@livewireScripts
<script>
document.addEventListener('DOMContentLoaded', function () {
    function refresh(group) {
        const rows = Array.from(group.querySelectorAll(':scope > [data-arrange-step]'));
        rows.forEach(function (row, index) {
            row.querySelector('[data-move-step="up"]').disabled = index === 0;
            row.querySelector('[data-move-step="down"]').disabled = index === rows.length - 1;
        });
    }
    document.querySelectorAll('[data-arrange-level]').forEach(refresh);
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-move-step]');
        if (!button || button.disabled) return;
        const row = button.closest('[data-arrange-step]');
        const group = row.parentElement;
        if (button.dataset.moveStep === 'up') group.insertBefore(row, row.previousElementSibling);
        else group.insertBefore(row.nextElementSibling, row);
        refresh(group);
        const status = document.querySelector('[data-arrange-status]');
        if (status) status.textContent = 'Changes not saved.';
    });
});
</script>
</body></html>
