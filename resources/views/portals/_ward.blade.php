@if(($context['kind'] ?? null) === 'parent' && $context['selected'])
    <div class="oak-ward-bar">
        <span class="oak-ward-avatar" aria-hidden="true">{{ mb_substr($context['selected']['name'], 0, 1) }}</span>
        <div class="oak-ward-who"><small>Viewing</small><strong>{{ $context['selected']['name'] }}</strong><span>{{ $context['selected']['class'] }}@if($context['selected']['section']) · {{ $context['selected']['section'] }}@endif</span></div>
        @if(count($context['wards']) > 1)<button class="oak-btn oak-btn-quiet" type="button" data-open-sheet="oak-ward-sheet">Switch child</button>@endif
    </div>
    @if(count($context['wards']) > 1)
        <div class="oak-scrim" id="oak-ward-sheet" role="dialog" aria-modal="true" aria-labelledby="oak-ward-title"><div class="oak-sheet">
            <h2 id="oak-ward-title">Switch child</h2><p class="oak-sheet-sub">Choose the child whose information you want to see.</p>
            <form method="get" action="{{ url()->current() }}">
                <fieldset class="oak-ward-opts"><legend class="sr-only">Your children</legend>
                    @foreach($context['wards'] as $ward)
                        <label class="oak-ward-opt"><input type="radio" name="student" value="{{ $ward['id'] }}" @checked($ward['id'] === $context['student_id'])>
                            <span class="oak-ward-avatar" aria-hidden="true">{{ mb_substr($ward['name'], 0, 1) }}</span><span class="oak-ward-who"><strong>{{ $ward['name'] }}</strong><span>{{ $ward['class'] }}@if($ward['section']) · {{ $ward['section'] }}@endif</span></span>
                            @if($ward['fees_due'])<span class="oak-status-pill oak-status-warning">Fees due</span>@endif
                        </label>
                    @endforeach
                </fieldset>
                <div class="oak-sheet-actions"><button class="oak-btn oak-btn-quiet" type="button" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary" type="submit">Show this child</button></div>
            </form>
        </div></div>
    @endif
@endif
