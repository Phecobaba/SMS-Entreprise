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
        $profile['logo_data_uri'] = $this->logoDataUri($path);

        return $profile;
    }

    private function logoDataUri(?string $path): ?string
    {
        if ($path && Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path);
            if (in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
                return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
            }
        }
        $publicPath = $this->publicLogoPath($path);
        if (! $publicPath) {
            return null;
        }
        $mime = mime_content_type($publicPath);

        return in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
            ? 'data:'.$mime.';base64,'.base64_encode(file_get_contents($publicPath))
            : null;
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
            'logo_url' => $this->logoUrl($settings?->logo_path),
            'primary_color' => is_string($primary) && preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#334155',
            'secondary_color' => is_string($secondary) && preg_match('/^#[0-9a-fA-F]{6}$/', $secondary) ? $secondary : '#E2E8F0',
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

    private function logoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return $this->publicLogoPath($path) ? asset(ltrim(str_replace('\\', '/', $path), '/')) : null;
    }

    private function publicLogoPath(?string $path): ?string
    {
        if (! $path || str_contains($path, '..')) {
            return null;
        }
        $root = realpath(public_path());
        $candidate = realpath(public_path(ltrim(str_replace('\\', '/', $path), '/')));
        if (! $root || ! $candidate) {
            return null;
        }
        $rootPrefix = strtolower(rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

        return str_starts_with(strtolower($candidate), $rootPrefix) ? $candidate : null;
    }
}
