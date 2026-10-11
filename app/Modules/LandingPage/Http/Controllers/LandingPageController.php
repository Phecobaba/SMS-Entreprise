<?php

namespace App\Modules\LandingPage\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\CurrentSchoolProfile;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __invoke(CurrentSchoolProfile $profile): View
    {
        $school = $profile->landingPage();
        abort_if($school === null, 503, 'School setup is not complete yet.');

        return view('landing-page.home', ['school' => $school]);
    }
}
