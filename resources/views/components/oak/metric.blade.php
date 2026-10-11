@props(['label', 'tone' => null])
<div {{ $attributes->class('oak-metric') }}><span>{{ $label }}</span><strong @class(['oak-metric-'.$tone => $tone])>{{ $slot }}</strong></div>
