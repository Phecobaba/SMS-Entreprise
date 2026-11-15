@php
    $homePage = true; $homeMeta = true;
    $title = $school['name'].' | Early Years, Primary and Secondary school in Enugu';
    $description = $school['name'].' in New GRA, Transekulu, Enugu: a caring, well-equipped school from Early Years to Secondary. Apply online today.';
    $img = fn (string $name, int $size = 1200) => "/images/landing/{$name}-{$size}.webp";
    $wa = preg_replace('/\D+/', '', (string) $school['whatsapp_number']);
@endphp
@extends('layouts.public')
@push('head')<link rel="preload" as="image" href="{{ $img('00_HERO_owner_pick_building_exterior') }}">@endpush
@section('content')
<main id="main">
<section class="hero" style="padding:0">
    <img src="{{ $img('00_HERO_owner_pick_building_exterior') }}" srcset="{{ $img('00_HERO_owner_pick_building_exterior',600) }} 600w, {{ $img('00_HERO_owner_pick_building_exterior') }} 1200w" sizes="100vw" alt="The Oakfield school building: a teal and peach block with glass windows beside an empty brick courtyard" width="1080" height="720" class="slide">
    <img data-src="{{ $img('04_hero_assembly_building') }}" srcset="{{ $img('04_hero_assembly_building',600) }} 600w, {{ $img('04_hero_assembly_building') }} 1200w" sizes="100vw" alt="Pupils in navy uniform lined up at assembly in front of the Oakfield Secondary School building" width="1080" height="720" class="slide">
    <button id="pause" class="pause" type="button" aria-pressed="false">Pause photos</button>
    <div class="wrap"><div class="in"><svg class="needles in" viewBox="0 0 96 56" aria-hidden="true">@foreach([[8,34],[20,18],[34,6],[48,2],[62,6],[76,18],[88,34]] as $i=>$point)<line x1="48" y1="54" x2="{{ $point[0] }}" y2="{{ $point[1] }}" style="--i:{{ $i }}"/>@endforeach</svg>
        @if($school['motto'])<p class="motto">{{ $school['motto'] }}</p>@endif<h1>{{ $school['name'] }}</h1>
        <p>A warm, well-equipped school in Enugu where children from Early Years to Secondary are nurtured to become beacons of greatness.</p>
        <div class="row">@if($school['admissions_form_target'])<a class="btn btn-gold" href="{{ route('applications.create') }}">Apply now</a>@else<span class="btn" aria-disabled="true">Admissions closed</span>@endif<a class="btn btn-line" href="{{ route('login') }}">Sign in</a></div>
    </div></div>
</section>
<section id="divisions"><div class="wrap"><h2>Our three divisions</h2><div class="grid3">
@foreach([['07_ey_courtyard_circle','Teachers and Early Years children in yellow tops playing in a circle on green artificial turf in the school courtyard','Early Years','Nursery 1 to 3 and Kindergarten/Creche','Bright, safe spaces where our youngest children learn through play and build confidence, language and friendships.'],['21_class_raised_hands','A teacher at the front of a classroom as pupils at blue desks raise their hands','Primary','Primary 1 to 6','A strong foundation in reading, numeracy and curiosity, with music, chess and sport alongside the classroom.'],['23_secondary_building_signage','The front of the Oakfield Int’l Secondary School building with its green sign','Secondary','JS1 to SS3','Students are prepared for their examinations and for life, in a school that values character as much as grades.']] as $card)
<article class="card rv"><img src="{{ $img($card[0],600) }}" srcset="{{ $img($card[0],600) }} 600w, {{ $img($card[0]) }} 1200w" sizes="(min-width:820px) 33vw, 100vw" alt="{{ $card[1] }}" width="1080" height="720" loading="lazy"><div class="body"><h3>{{ $card[2] }}</h3><p class="small division-range">{{ $card[3] }}</p><p class="flush">{{ $card[4] }}</p></div></article>@endforeach
</div></div></section>
<section class="alt"><div class="wrap"><h2>Why Oakfield</h2><div class="grid3 grid4">
@foreach([['One campus, three divisions','Early Years, Primary and Secondary, with a courtyard and play area for our youngest children.'],['More than lessons','Music (strings, guitar, saxophone and drums), chess, table tennis, football and Scrabble.'],['Places to learn','A library and an ICT lab for our pupils.'],['Getting to school','School vans carry the Oakfield name.']] as $fact)<div class="rv"><h3>{{ $fact[0] }}</h3><p>{{ $fact[1] }}</p></div>@endforeach
</div></div></section>
<section id="life"><div class="wrap"><h2>Life at the school</h2><div class="mosaic">
@foreach([['00_LIBRARY_owner_pick_shelves','The school library, with blue and white shelves of colourful books',true],['35_music_band','Pupils in school uniform playing violins, a guitar and a saxophone in a music class',false],['14_chess_a','Pupils in yellow tops sitting at a chess board during a game',false],['29_sports_field_wide','Pupils in yellow sportswear playing on the school’s green sports field',false],['10_ict_lab','Pupils at computers in the school ICT lab',false]] as $photo)
<figure class="rv {{ $photo[2]?'big':'' }}"><img src="{{ $img($photo[0],600) }}" srcset="{{ $img($photo[0],600) }} 600w, {{ $img($photo[0]) }} 1200w" sizes="{{ $photo[2]?'(min-width:820px) 50vw, 100vw':'(min-width:820px) 25vw, 50vw' }}" alt="{{ $photo[1] }}" width="1080" height="720" loading="lazy"></figure>@endforeach
</div></div></section>
<section id="admissions"><div class="wrap split"><div><h2>Admissions</h2>
@if($school['admissions_form_target'])<p>Applying takes a few minutes. Here is how it works.</p><p><a class="btn btn-teal" href="{{ route('applications.create') }}">Start your application</a></p><ol class="steps"><li><span><strong>Apply online.</strong> Fill in one short form for your child.</span></li><li><span><strong>Pay the application fee securely online, if one applies.</strong> The amount is shown before you pay.</span></li><li><span><strong>Sit the entrance exam</strong> at the school on the date we send you.</span></li><li><span><strong>Receive your child's sign-in details</strong> at the school on exam day.</span></li></ol>
@else<p class="notice"><strong>Admissions are closed at the moment.</strong>@if($school['admissions_reopen_text'])<br>{{ $school['admissions_reopen_text'] }}@endif</p>@endif
@if($school['admissions_key_dates'])<p class="preline">{{ $school['admissions_key_dates'] }}</p>@endif<p><a class="tl" href="{{ route('applications.status') }}">Already applied? Check your application</a></p></div>
<img src="{{ $img('02_large_hall_exam_setting',600) }}" srcset="{{ $img('02_large_hall_exam_setting',600) }} 600w, {{ $img('02_large_hall_exam_setting') }} 1200w" sizes="(min-width:820px) 50vw, 100vw" alt="Pupils seated apart at desks in the large school hall during an entrance assessment" width="1280" height="960" loading="lazy" class="rv admissions-photo"></div></section>
<section class="alt"><div class="wrap"><h2>Questions parents ask</h2>
@foreach([['How do I apply?','Complete the online application form, pay the application fee if one applies, and we will confirm by email. We will send the entrance exam date when it is set.'],['Is there an application fee?','Where an application fee applies, the amount is shown on the application form before you pay.'],['I paid, but my application page says “Still confirming”. What should I do?','Please do not pay again. Press “Check again” on your application page.'],['Where is the entrance exam taken?','At the school, under supervision.'],['When do we get sign-in details?','On exam day at the school.'],['Will my child in Early Years sit an online exam?','Children applying to Early Years are assessed in person at the school.'],['Which classes can my child apply for?','From Early Years through Primary 1 to 6 and JS1 to SS3, subject to places being available.'],['Where is the school?',$school['address'] ?: 'Contact the school for directions.']] as $faq)<details class="rv"><summary>{{ $faq[0] }}</summary><p>{{ $faq[1] }}</p></details>@endforeach
</div></section>
<section id="contact"><div class="wrap"><h2>Contact and visit</h2>
@if($school['address'])<p>{{ $school['address'] }}</p>@endif
@if($school['contact_phone'])<p><a href="tel:{{ preg_replace('/\s+/', '', $school['contact_phone']) }}">{{ $school['contact_phone'] }}</a></p>@endif
@if($school['contact_email'])<p><a href="mailto:{{ $school['contact_email'] }}">{{ $school['contact_email'] }}</a></p>@endif
@if($wa)<p><a href="https://wa.me/{{ $wa }}" rel="noopener">WhatsApp {{ $school['whatsapp_number'] }}</a></p>@endif
@if($school['opening_hours'])<p>{{ $school['opening_hours'] }}</p>@endif
@if($school['address'])<p><a class="btn btn-teal" href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($school['address']) }}" rel="noopener">Get directions</a></p>@endif
</div></section>
</main>
@endsection
@push('scripts')@vite('resources/js/home.js')@endpush
