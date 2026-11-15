<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Section;
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
            'orphanLevels' => AcademicLevel::whereNull('section_id')->orderBy('name')->limit(100)->get(),
            'locked' => $settings->registration_move_policy_locked_at !== null
                || Student::whereNotNull('registration_number')->exists(),
            'highestIssued' => $numbers->highestIssued(),
            'nextNumber' => $numbers->nextNumber(),
        ]);
    }

    public function update(Request $request, RegistrationNumbers $numbers): RedirectResponse
    {
        Gate::authorize('manage_registration_numbers');
        $data = $request->validate([
            'registration_include_school' => ['nullable', 'boolean'],
            'registration_include_section' => ['nullable', 'boolean'],
            'registration_include_level' => ['nullable', 'boolean'],
            'registration_school_code' => ['nullable', 'string', 'max:6'],
            'registration_separator' => ['nullable', Rule::in(['-', '/', '.'])],
            'registration_number_length' => ['required', 'integer', 'between:3,6'],
            'registration_include_year' => ['nullable', 'boolean'],
            'registration_year_digits' => ['required', Rule::in([2, 4])],
            'registration_move_policy' => ['required', Rule::in([
                RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
                RegistrationNumbers::NEW_PREFIX_SEQUENCE,
                RegistrationNumbers::KEEP_WHOLE_NUMBER,
            ])],
            'section_codes' => ['nullable', 'array'], 'section_codes.*' => ['nullable', 'string', 'max:6'],
            'level_codes' => ['nullable', 'array'], 'level_codes.*' => ['nullable', 'string', 'max:6'],
            'registration_next_number' => ['required', 'integer', 'min:1'],
        ]);
        $data['registration_separator'] = $data['registration_separator'] ?? '';
        $numbers->saveSettings($data, $request->user());

        return redirect()->route('registration-numbers.edit')->with('status', 'Registration number settings saved.');
    }
}
