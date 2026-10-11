@props(['tone' => 'info', 'icon' => 'info-circle', 'role' => 'status'])
<div {{ $attributes->class(['oak-notice', 'oak-notice-'.$tone]) }} role="{{ $role }}"><x-icon :name="$icon" />{{ $slot }}</div>
