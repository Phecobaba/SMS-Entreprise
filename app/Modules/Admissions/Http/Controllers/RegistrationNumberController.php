<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\RegistrationNumberSequence;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\RegistrationNumbers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistrationNumberController extends Controller
{
    public function edit(RegistrationNumbers $numbers): View
    {
        Gate::authorize('manage_registration_numbers');
        $settings = $numbers->settings();

        return view('students.registration-settings', [
            'settings' => $settings,
            'sections' => Section::with('levels')->orderBy('name')->limit(100)->get(),
            'sequences' => RegistrationNumberSequence::orderBy('sequence_key')->get()->keyBy('sequence_key'),
            'locked' => Student::whereNotNull('registration_number')->exists(),
        ]);
    }

    public function update(Request $request, RegistrationNumbers $numbers): RedirectResponse
    {
        Gate::authorize('manage_registration_numbers');
        $data = $request->validate([
            'registration_prefix' => ['required', 'regex:/^[A-Za-z0-9]{2,6}$/'],
            'registration_number_length' => ['required', 'integer', 'between:3,6'],
            'registration_include_year' => ['nullable', 'boolean'],
            'registration_move_policy' => ['required', Rule::in([
                RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
                RegistrationNumbers::NEW_PREFIX_SEQUENCE,
                RegistrationNumbers::KEEP_WHOLE_NUMBER,
            ])],
            'section_prefixes' => ['array'], 'section_prefixes.*' => ['nullable', 'regex:/^[A-Za-z0-9]{2,6}$/'],
            'level_prefixes' => ['array'], 'level_prefixes.*' => ['nullable', 'regex:/^[A-Za-z0-9]{2,6}$/'],
            'next_numbers' => ['array'], 'next_numbers.*' => ['integer', 'min:1'],
        ]);
        $numbers->saveSettings($data, $request->user());

        return redirect()->route('registration-numbers.edit')->with('status', 'Registration number settings saved.');
    }
}
