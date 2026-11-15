@php($publicProfile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage())
@php($title = 'Apply to Oakfield | '.($publicProfile['name'] ?? 'Oakfield'))
@php($description = 'Apply online for admission to '.($publicProfile['name'] ?? 'Oakfield').'.')
@extends('layouts.public')
@section('content')
<main id="main" class="calm"><h1>Apply to Oakfield</h1>
@if(!$open)
<div class="status"><p class="big">Admissions are closed at the moment.</p>@if($publicProfile['admissions_reopen_text'])<p>{{ $publicProfile['admissions_reopen_text'] }}</p>@endif<p><a class="btn btn-teal" href="{{ route('applications.status') }}">Check an existing application</a></p></div>
@else
<ol class="prog"><li class="cur">1 Apply</li>@if($feeKobo>0)<li>2 Pay</li><li>3 Exam day</li>@else<li>2 Exam day</li>@endif</ol>
<form class="form" method="post" action="{{ route('applications.store') }}" id="application-form" novalidate>@csrf
<div class="honeypot" aria-hidden="true"><label>Website<input tabindex="-1" autocomplete="off" name="website"></label></div>
@if(session('status'))<p class="notice">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert"><strong>Please check the form.</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<h2>Child</h2>
@foreach([['child_first_name','First name',false],['child_surname','Surname',false],['child_other_names','Other names',true]] as [$name,$label,$optional])
<div @class(['err'=>$errors->has($name)])><label for="{{ $name }}">{{ $label }} @if($optional)<span class="opt">(optional)</span>@endif</label><input id="{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" @required(!$optional) @if($errors->has($name)) aria-invalid="true" @endif>@error($name)<p class="msg">{{ $message }}</p>@enderror</div>@endforeach
<div @class(['err'=>$errors->has('date_of_birth')])><label for="date_of_birth">Date of birth</label><input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required>@error('date_of_birth')<p class="msg">{{ $message }}</p>@enderror</div>
<div><label for="gender">Gender</label><select id="gender" name="gender" required><option value="">Choose</option><option @selected(old('gender')==='Female')>Female</option><option @selected(old('gender')==='Male')>Male</option></select></div>
<div @class(['err'=>$errors->has('applied_level_id')])><label for="applied_level_id">Class applying for</label><select id="applied_level_id" name="applied_level_id" required><option value="">Choose a class</option>@foreach($levels as $section=>$items)<optgroup label="{{ $section }}">@foreach($items as $level)<option value="{{ $level->id }}" @selected((string)old('applied_level_id')===(string)$level->id)>{{ $level->name }}</option>@endforeach</optgroup>@endforeach</select>@error('applied_level_id')<p class="msg">{{ $message }}</p>@enderror</div>
<div><label for="previous_school">Previous school <span class="opt">(optional)</span></label><input id="previous_school" name="previous_school" value="{{ old('previous_school') }}"></div>
<h2 class="form-heading">Parent or guardian</h2>
@foreach([['guardian_name','Full name','text'],['guardian_relationship','Relationship to child','text'],['guardian_phone','Phone number','tel'],['guardian_email','Email','email'],['guardian_address','Home address','text']] as [$name,$label,$type])
<div @class(['err'=>$errors->has($name)])><label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}" required>@error($name)<p class="msg">{{ $message }}</p>@enderror</div>@endforeach
<div @class(['err'=>$errors->has('username')])><label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}" maxlength="30" required><p class="hint">3–30 lower-case letters, numbers, dots, dashes or underscores. You will use this to sign in.</p>@error('username')<p class="msg">{{ $message }}</p>@enderror</div>
<p><label class="chk"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>I agree to how Oakfield uses my family's information, as explained on the <a class="tl-in" href="{{ route('privacy') }}">privacy page</a>.</span></label></p>
@if($feeKobo>0)<p class="fee">Application fee: ₦{{ number_format($feeKobo/100,2) }}, paid securely online with Paystack.</p><button class="btn btn-teal full" type="submit">Continue to payment</button>@else<p class="fee">There is no application fee.</p><button class="btn btn-teal full" type="submit">Submit application</button>@endif
</form>@endif</main>
@endsection
