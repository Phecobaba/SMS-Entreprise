<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\TenantContext;
use Illuminate\Support\Facades\Storage;

class CurrentSchoolProfile
{
    public function printProfile(): ?array
    {
        $profile = $this->landingPage();
        if (! $profile) {
            return null;
        }
        $path = SchoolSetting::first()?->logo_path;
        $profile['logo_data_uri'] = null;
        if ($path && Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path);
            if (in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
                $profile['logo_data_uri'] = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
            }
        } elseif ($this->hasDefaultLogo()) {
            $profile['logo_data_uri'] = 'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('branding/oakfield-logo.jpg')));
        }

        return $profile;
    }

    public function landingPage(): ?array
    {
        $school = School::find(app(TenantContext::class)->id());
        if (! $school) {
            return null;
        }

        $settings = SchoolSetting::first();
        $primary = $settings?->primary_color;
        $secondary = $settings?->secondary_color;

        return [
            'name' => $school->name,
            'motto' => $settings?->motto ?: $school->motto,
            'logo_url' => $settings?->logo_path && Storage::disk('public')->exists($settings->logo_path)
                ? Storage::disk('public')->url($settings->logo_path)
                : ($this->hasDefaultLogo() ? asset('branding/oakfield-logo.jpg') : null),
            'primary_color' => is_string($primary) && preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#2E4F56',
            'secondary_color' => is_string($secondary) && preg_match('/^#[0-9a-fA-F]{6}$/', $secondary) ? $secondary : '#F5E58A',
            'about_text' => $settings?->about_text,
            'contact_email' => $settings?->contact_email,
            'contact_phone' => $settings?->contact_phone,
            'address' => $settings?->address,
            'admissions_form_target' => $this->safeAdmissionsTarget($settings?->admissions_form_target),
        ];
    }

    public static function isSafeAdmissionsTarget(?string $target): bool
    {
        if (! $target || preg_match('/[\\x00-\\x20\\x7f\\\\]/', $target)) {
            return false;
        }

        if ((str_starts_with($target, '/') && ! str_starts_with($target, '//'))
            || (str_starts_with($target, 'https://') && filter_var($target, FILTER_VALIDATE_URL))) {
            return true;
        }

        return false;
    }

    private function safeAdmissionsTarget(?string $target): ?string
    {
        return self::isSafeAdmissionsTarget($target) ? $target : null;
    }

    private function hasDefaultLogo(): bool
    {
        return config('tenancy.mode') === 'single_school'
            && file_exists(public_path('branding/oakfield-logo.jpg'));
    }
}
