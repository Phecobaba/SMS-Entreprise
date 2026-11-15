<?php

namespace Database\Seeders;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Database\Seeder;

class DemoPrincipalSectionSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = (int) config('tenancy.default_school_id');
        School::findOrFail($schoolId);
        app(TenantContext::class)->set($schoolId);

        $principal = User::whereIn('name', ['Demo Head', 'Demo Principal'])->firstOrFail();
        $section = Section::firstOrCreate(
            ['name' => 'Secondary School'],
            ['head_title' => 'Principal'],
        );
        $principal->headSections()->syncWithoutDetaching([
            $section->id => ['school_id' => $schoolId],
        ]);

        AcademicLevel::where('name', 'Junior Secondary')->update([
            'section_id' => $section->id,
        ]);
    }
}
