<?php

namespace Database\Seeders;

use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\TenantContext;
use Illuminate\Database\Seeder;

class OakfieldBrandingSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = (int) config('tenancy.default_school_id');
        School::findOrFail($schoolId);
        app(TenantContext::class)->set($schoolId);

        SchoolSetting::updateOrCreate([], [
            'logo_path' => 'branding/oakfield-logo.jpg',
            'primary_color' => '#2E4F56',
            'secondary_color' => '#F5E58A',
        ]);
    }
}
