<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Core\Services\AppNavigation;
use App\Modules\Core\Services\DashboardOverview;
use App\Modules\Notifications\Services\InAppNotifications;
use App\Modules\Portals\Services\PortalContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardOverview $overview, AppNavigation $navigation): View
    {
        $request->user()->load('roles.permissions');
        $staffKind = $overview->staffKind($request->user());
        $portal = $staffKind ? null : app(PortalContext::class)->for($request);
        $sections = collect();
        $selectedSection = null;
        $selectedSectionId = null;
        if ($staffKind === 'admin') {
            $sections = Section::orderBy('name')->limit(100)->get();
            $selectedSectionId = $this->selectedAdminSection($request, $sections, app(SectionAccess::class));
            $selectedSection = $sections->firstWhere('id', $selectedSectionId);
        } elseif ($staffKind === 'head') {
            $assignedIds = app(SectionAccess::class)->sectionIds($request->user());
            if ($request->filled('section')) {
                app(SectionAccess::class)->assertSection($request->user(), $request->integer('section'));
            }
            $sections = Section::whereIn('id', $assignedIds)->orderBy('name')->limit(100)->get();
            $selectedSection = $sections->first();
            $selectedSectionId = $selectedSection?->id;
        }
        $data = $portal && $portal['kind'] === 'parent' && ! $portal['student_id']
            ? ['kind' => 'parent_empty', 'wards' => [], 'selected' => null]
            : $overview->for(
                $request->user(),
                $portal['student_id'] ?? ($request->integer('student') ?: null),
                $portal,
                $selectedSectionId,
                $selectedSection,
            );
        if (in_array($data['kind'], ['admin', 'head'], true)) {
            $data['sections'] = $sections;
            $data['selected_section_id'] = $selectedSectionId;
            $data['show_section_switcher'] = $data['kind'] === 'admin' && $sections->count() > 1;
        }
        if ($portal && $portal['kind'] === 'parent' && $portal['student_id']) {
            $data['wards'] = $portal['wards'];
            $data['selected'] = $portal['selected'];
        }
        abort_if($data['kind'] === 'general' && ! $request->user()->can('view_dashboard'), 403);

        return view('core.dashboard', [
            'overview' => $data,
            'dashboardLinks' => $data['kind'] === 'general' ? $navigation->items($request->user()) : [],
            'notifications' => app(InAppNotifications::class)->latestUnread($request->user()),
        ]);
    }

    private function selectedAdminSection(Request $request, $sections, SectionAccess $access): ?int
    {
        if ($request->query('section') === 'all') {
            $request->session()->forget('dashboard_section');

            return null;
        }
        if ($request->filled('section')) {
            $id = $request->integer('section');
            $access->assertSection($request->user(), $id);
            $request->session()->put('dashboard_section', $id);

            return $id;
        }
        $remembered = (int) $request->session()->get('dashboard_section', 0);

        return $remembered && $sections->contains('id', $remembered) ? $remembered : null;
    }
}
