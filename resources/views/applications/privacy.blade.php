@php($publicProfile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage())
@php($title = 'Privacy | '.($publicProfile['name'] ?? 'Oakfield'))
@php($description = 'How Oakfield uses and protects information submitted for admission.')
@extends('layouts.public')
@section('content')<main id="main" class="calm prose"><h1>Privacy</h1>
@if($publicProfile['privacy_last_updated_at'])<p class="small">Last updated: {{ $publicProfile['privacy_last_updated_at']->format('j F Y') }}</p>@endif
<h2>Who we are</h2><p>{{ collect([$publicProfile['name'],$publicProfile['address']])->filter()->join(', ') }}@if($publicProfile['privacy_contact_email']). Contact: <a href="mailto:{{ $publicProfile['privacy_contact_email'] }}">{{ $publicProfile['privacy_contact_email'] }}</a>@endif</p>
<h2>What we collect when you apply</h2><p>Your child's name, date of birth, gender, class applied for and previous school; the parent or guardian's name, relationship, phone, email and home address; the username you choose; and the time you agreed to this notice.</p>
<h2>Why we collect it</h2><p>To process your child's application, contact you, arrange the entrance exam or assessment, take the application fee, and set up school records if your child is admitted.</p>
<h2>Payments</h2><p>Application fees are paid through Paystack. We do not see or store your card details. We keep the payment reference and amount.</p>
<h2>Who sees it</h2><p>School staff who handle admissions, and Paystack for the payment. We do not sell your information.</p>
<h2>How long we keep it</h2><p>Applications that are not completed are deleted automatically after 7 days.@if($publicProfile['non_admitted_retention_text']) {{ $publicProfile['non_admitted_retention_text'] }}@endif If your child is admitted, the information becomes part of their school record.</p>
<h2>Your rights</h2><p>You may ask to see, correct or delete the information we hold about your family, subject to our legal duties.@if($publicProfile['privacy_contact_email']) Contact us at <a href="mailto:{{ $publicProfile['privacy_contact_email'] }}">{{ $publicProfile['privacy_contact_email'] }}</a>.@endif</p>
<h2>Complaints</h2><p>You may also contact the Nigeria Data Protection Commission.</p></main>@endsection
