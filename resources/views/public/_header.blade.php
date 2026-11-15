<header class="hdr"><div class="wrap">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ $publicProfile['name'] }} home"><img class="logo" src="{{ config('landing.logo') }}" alt="" width="40" height="40"><span class="nm">Oakfield</span></a>
    @if(!empty($homePage))<nav aria-label="Main"><a href="#divisions">Divisions</a><a href="#life">School life</a><a href="#admissions">Admissions</a><a href="#contact">Contact</a></nav>@endif
    <a class="link" href="{{ route('login') }}">Sign in</a>
    @if($publicOpen)<a class="btn btn-gold" href="{{ route('applications.create') }}">Apply now</a>@else<span class="btn" aria-disabled="true">Closed</span>@endif
</div></header>
