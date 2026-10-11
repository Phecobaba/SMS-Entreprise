<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Services\CurrentSchoolProfile;
use App\Modules\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolProfileController extends Controller
{
    public function edit(TenantContext $context): View
    {
        $school = School::findOrFail($context->id());

        return view('core.school-profile', [
            'school' => $school,
            'settings' => SchoolSetting::first() ?? new SchoolSetting,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'motto' => ['nullable', 'string', 'max:160'],
            'about_text' => ['nullable', 'string', 'max:3000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'admissions_form_target' => [
                'nullable', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    if (CurrentSchoolProfile::isSafeAdmissionsTarget($value)) {
                        return;
                    }

                    $fail('The admissions link must be a site path or an HTTPS URL.');
                },
            ],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $logo = $data['logo'] ?? null;
        unset($data['logo']);

        if ($logo) {
            $data['logo_path'] = $logo->store('school-logos', 'public');
        }

        $settings = SchoolSetting::firstOrCreate([]);
        $settings->fill($data)->save();

        return back()->with('status', 'School profile updated.');
    }
}
