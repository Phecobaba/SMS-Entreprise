@php
    $selectedHeadId = (string) ($selectedHeadId ?? '');
@endphp
<div class="oak-field {{ $wide ?? false ? 'ad-wide' : '' }}" data-head-picker>
    <label for="{{ $pickerId }}-search">Search active staff</label>
    <input
        id="{{ $pickerId }}-search"
        type="search"
        autocomplete="off"
        placeholder="Type a staff name"
        aria-controls="{{ $pickerId }}"
        data-head-search
    >
    <label for="{{ $pickerId }}">Head</label>
    <select id="{{ $pickerId }}" name="head_user_id" data-head-select>
        <option value="">{{ $emptyLabel ?? 'Not assigned' }}</option>
        @foreach($headOptions as $head)
            <option
                value="{{ $head->id }}"
                data-head-name="{{ mb_strtolower($head->name) }}"
                @selected($selectedHeadId === (string) $head->id)
            >{{ $head->name }}</option>
        @endforeach
    </select>
    <small><span data-head-count>{{ $headOptions->count() }}</span> active staff accounts shown.</small>
</div>
