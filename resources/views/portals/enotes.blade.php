@extends('portals.layout')
@section('title', 'E-Notes')
@section('content')
<div class="oak-section-head"><div><h1>E-Notes</h1><p class="oak-subpage-note">Notes uploaded for your class</p></div></div>
<nav class="oak-chip-nav" aria-label="Filter by subject"><a class="oak-chip" href="{{ route('enotes.mine') }}" @if(! request('subject')) aria-current="true" @endif wire:navigate>All</a>@foreach($subjects as $id => $label)<a class="oak-chip" href="{{ route('enotes.mine', ['subject' => $id]) }}" @if((int) request('subject') === (int) $id) aria-current="true" @endif wire:navigate>{{ $label }}</a>@endforeach</nav>
<section class="oak-section">@forelse($notes as $note)<div class="oak-row"><div><h3>{{ $note->title }}</h3><p>{{ $note->subject?->name ?? 'Class note' }} · {{ $note->created_at->diffForHumans() }}</p></div><div class="oak-row-end">@if($note->link)<a class="oak-btn oak-btn-quiet" href="{{ $note->link }}" target="_blank" rel="noopener noreferrer" aria-label="Open {{ $note->title }} in a new tab">Open link</a>@elseif($note->file_path)<a class="oak-btn oak-btn-quiet" href="{{ route('enotes.mine.download', $note) }}">Download</a>@endif</div></div>@empty<div class="oak-empty"><x-icon name="notebook" /><strong>No notes yet</strong><p>Notes from your teachers will appear here.</p></div>@endforelse</section>
@if($notes->hasPages()){{ $notes->links() }}@endif
@endsection
