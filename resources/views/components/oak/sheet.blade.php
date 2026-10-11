@props(['id', 'labelledby' => null, 'label' => null])
<div id="{{ $id }}" {{ $attributes->class('oak-scrim') }} role="dialog" aria-modal="true" @if($labelledby) aria-labelledby="{{ $labelledby }}" @elseif($label) aria-label="{{ $label }}" @endif>{{ $slot }}</div>
