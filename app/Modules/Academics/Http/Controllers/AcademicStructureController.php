<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Department;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\ClassOrderingService;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Academics\Services\SectionAdministration;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicStructureController extends Controller
{
    public function sections(Request $request): View
    {
        abort_unless($request->user()->can('manage_academics') && $request->user()->can('view_all_sections'), 403);

        return view('academics.sections', [
            'sections' => Section::with(['heads', 'levels'])->withCount(['levels'])->orderBy('name')->limit(100)->get(),
            'levels' => AcademicLevel::with('section')->withCount('classes')->orderBy('name')->limit(100)->get(),
            'headOptions' => User::where('status', 'active')
                ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.name', 'approve_results'))
                ->whereDoesntHave('roles.permissions', fn ($query) => $query->where('permissions.name', 'view_all_sections'))
                ->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function storeSection(Request $request, TenantContext $context, SectionAdministration $administration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sections')->where('school_id', $context->id())],
            'head_title' => ['required', 'string', 'max:100'],
            'head_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $context->id())],
        ]);
        $head = isset($data['head_user_id']) ? User::findOrFail($data['head_user_id']) : null;
        DB::transaction(function () use ($administration, $data, $head, $request): void {
            $section = $administration->createSection($data['name'], $data['head_title'], $request->user());
            $administration->assignHead($section, $head, $request->user());
        });

        return back()->with('status', 'Section added. Assign every level before using section-scoped work.');
    }

    public function updateSection(Request $request, Section $section, TenantContext $context, SectionAdministration $administration): RedirectResponse
    {
        abort_unless($request->user()->can('manage_academics') && $request->user()->can('view_all_sections'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sections')->where('school_id', $context->id())->ignore($section->id)],
            'head_title' => ['required', 'string', 'max:100'],
            'head_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('school_id', $context->id())],
        ]);
        $head = isset($data['head_user_id']) ? User::findOrFail($data['head_user_id']) : null;
        DB::transaction(function () use ($administration, $data, $head, $request, $section): void {
            $administration->assignHead($section, $head, $request->user());
            $section->update(['name' => trim($data['name']), 'head_title' => trim($data['head_title'])]);
        });

        return back()->with('status', 'Section updated.');
    }

    public function assignLevel(Request $request, AcademicLevel $level, TenantContext $context, SectionAdministration $administration): RedirectResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $context->id())],
        ]);
        $administration->assignLevel($level, Section::findOrFail($data['section_id']), $request->user());

        return back()->with('status', $level->name.' and its classes now belong to the selected section. No records were deleted.');
    }

    public function departments(Request $request): View
    {
        abort_unless($request->user()->can('manage_academics') && $request->user()->can('view_all_sections'), 403);

        return view('academics.departments', [
            'departments' => Department::withCount('classes')->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function storeDepartment(Request $request, TenantContext $context, SectionAdministration $administration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments')->where('school_id', $context->id())],
        ]);
        $administration->createDepartment($data['name'], $request->user());

        return back()->with('status', 'Department added. Department fields are now available on classes.');
    }

    public function updateDepartment(Request $request, Department $department, TenantContext $context): RedirectResponse
    {
        abort_unless($request->user()->can('manage_academics') && $request->user()->can('view_all_sections'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments')->where('school_id', $context->id())->ignore($department->id)],
        ]);
        if (Department::whereKeyNot($department->id)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
            throw ValidationException::withMessages(['name' => 'A department with this name already exists.']);
        }
        $department->update(['name' => trim($data['name'])]);

        return back()->with('status', 'Department renamed.');
    }

    public function destroyDepartment(Request $request, Department $department): RedirectResponse
    {
        abort_unless($request->user()->can('manage_academics') && $request->user()->can('view_all_sections'), 403);
        if ($department->classes()->exists()) {
            throw ValidationException::withMessages(['department' => 'This department is used by one or more classes and cannot be deleted.']);
        }
        $department->delete();

        return back()->with('status', 'Department deleted.');
    }

    public function classes(Request $request, SectionAccess $access, ClassOrderingService $ordering): View
    {
        $actor = $request->user();
        $canViewAll = $actor->can('view_all_sections');
        $canEdit = $ordering->mayManage($actor);
        $assignedIds = $access->sectionIds($actor);

        $sections = Section::with('heads')->when(! $canViewAll, fn ($query) => $query->whereIn('id', $assignedIds))->orderBy('name')->get();
        $requested = $this->selectedSection($request, $canViewAll, $assignedIds, $access);
        $query = $access->scopeClasses(
            SchoolClass::with(['level.section', 'department', 'arms'])
                ->withCount(['arms', 'classSubjects', 'activeStudents as students_count']),
            $actor,
            $requested,
        );
        if ($request->filled('level')) {
            $access->assertLevel($actor, $request->integer('level'));
            $query->where('academic_level_id', $request->integer('level'));
        }
        if ($request->filled('department') && Department::exists()) {
            $query->where('department_id', $request->integer('department'));
        }
        $levels = $access->scopeLevels(AcademicLevel::with('section'), $actor, $requested)->orderBy('name')->limit(100)->get();
        $optionClasses = $access->scopeClasses(SchoolClass::query(), $actor, $requested)
            ->orderByRaw('CASE WHEN position IS NULL THEN 1 ELSE 0 END')->orderBy('position')->orderBy('name')->limit(100)->get();

        return view('academics.classes', [
            'classes' => $query->orderByRaw('CASE WHEN position IS NULL THEN 1 ELSE 0 END')->orderBy('position')->orderBy('name')->paginate(20)->withQueryString(),
            'sections' => $sections,
            'selectedSection' => $requested,
            'levels' => $levels,
            'departments' => Department::orderBy('name')->get(),
            'comesAfterByLevel' => $optionClasses->groupBy('academic_level_id'),
            'arrangedBySection' => $sections->mapWithKeys(fn (Section $section) => [$section->id => $ordering->sectionIsArranged($section)]),
            'canEdit' => $canEdit,
            'canViewAll' => $canViewAll,
            'hasSection' => $canViewAll || $assignedIds !== [],
        ]);
    }

    public function moveClass(Request $request, SchoolClass $class, ClassOrderingService $ordering): RedirectResponse
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $ordering->move($class, $data['direction'], $request->user());

        return back()->with('status', 'Class order updated. The whole step moved together.');
    }

    public function destroyClass(Request $request, SchoolClass $class, ClassOrderingService $ordering): RedirectResponse
    {
        $name = $class->name;
        $ordering->delete($class, $request->user());

        return back()->with('status', $name.' deleted. No student or school history was removed.');
    }

    public function arrange(Request $request, SectionAccess $access, ClassOrderingService $ordering): View
    {
        $actor = $request->user();
        abort_unless($ordering->mayManage($actor), 403);
        $sectionIds = $actor->can('view_all_sections') ? null : $access->sectionIds($actor);
        $sections = Section::with(['levels.classes.department'])
            ->when($sectionIds !== null, fn ($query) => $query->whereIn('id', $sectionIds))
            ->orderBy('name')->get();

        return view('academics.arrange', [
            'sections' => $sections,
            'arrangements' => $sections->mapWithKeys(fn (Section $section) => [$section->id => $ordering->arrangementFor($section)]),
            'canViewAll' => $actor->can('view_all_sections'),
            'hasSection' => $actor->can('view_all_sections') || $sectionIds !== [],
            'allArranged' => $sections->isNotEmpty() && $sections->every(fn (Section $section) => $ordering->sectionIsArranged($section)),
        ]);
    }

    public function saveArrangement(Request $request, ClassOrderingService $ordering): RedirectResponse
    {
        $data = $request->validate([
            'section_ids' => ['required', 'array'],
            'section_ids.*' => ['integer'],
            'steps' => ['nullable', 'array'],
        ]);
        $submitted = [];
        $steps = $data['steps'] ?? [];
        foreach ($data['section_ids'] as $sectionId) {
            $submitted[(int) $sectionId] = $steps[(string) $sectionId] ?? $steps[(int) $sectionId] ?? [];
        }
        $ordering->saveArrangement($submitted, $request->user());

        return redirect()->route('academics.classes.arrange')->with('status', 'Saved. Promotion is now available for the arranged sections.');
    }

    private function selectedSection(Request $request, bool $canViewAll, array $assignedIds, SectionAccess $access): ?int
    {
        if (! $canViewAll) {
            if ($request->filled('section')) {
                $access->assertSection($request->user(), $request->integer('section'));
            }

            return $assignedIds[0] ?? null;
        }
        if ($request->query('section') === 'all') {
            $request->session()->forget('academic_section');

            return null;
        }
        if ($request->filled('section')) {
            $id = $request->integer('section');
            $access->assertSection($request->user(), $id);
            $request->session()->put('academic_section', $id);

            return $id;
        }

        $remembered = $request->session()->get('academic_section');

        return $remembered && Section::whereKey($remembered)->exists() ? (int) $remembered : null;
    }
}
