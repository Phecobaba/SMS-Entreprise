@props(['text', 'label' => 'More information'])
@php($tooltipId = 'oak-tooltip-'.str()->uuid())
<span class="oak-tooltip" data-oak-tooltip>
    <button type="button" class="oak-tooltip-trigger" aria-label="{{ $label }}" aria-describedby="{{ $tooltipId }}" aria-expanded="false" data-oak-tooltip-trigger>?</button>
    <span class="oak-tooltip-bubble" id="{{ $tooltipId }}" role="tooltip">{{ $text }}</span>
</span>
