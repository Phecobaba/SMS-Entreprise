@extends('portals.layout')
@section('title', 'Read-only access')
@section('content')
<div class="oak-empty"><x-icon name="lock" /><strong>This page is not available</strong><p>@if(($context['selected']['status'] ?? null) === \App\Modules\Admissions\Models\Student::STATUS_GRADUATED) This student has read-only access to past report cards, receipts and attendance. @else This student has read-only access to past report cards and receipts. @endif</p><div class="ad-actions"><a class="oak-btn oak-btn-secondary" href="{{ route('results.mine.index', ($context['kind'] ?? null) === 'parent' ? ['student' => $context['student_id']] : []) }}" wire:navigate>Past report cards</a><a class="oak-btn oak-btn-primary" href="{{ route('payments.mine.index', ($context['kind'] ?? null) === 'parent' ? ['student' => $context['student_id']] : []) }}" wire:navigate>Past receipts</a></div></div>
@endsection
