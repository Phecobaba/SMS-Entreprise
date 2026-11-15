@props(['attention', 'variant' => 'ticker'])
@if($attention)
    <a href="{{ $attention['url'] }}" wire:navigate @class([
        'oak-result-attention',
        'oak-result-attention-dashboard' => $variant === 'dashboard',
        'oak-result-attention-ticker' => $variant === 'ticker',
    ])>
        <x-icon name="alert-triangle" />
        <strong>{{ $attention['text'] }}</strong>
        <span>View</span>
    </a>
@endif
