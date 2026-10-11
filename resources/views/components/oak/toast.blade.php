@props(['id' => 'oak-toast'])
<div id="{{ $id }}" {{ $attributes->class('oak-toast') }} role="status">{{ $slot }}</div>
