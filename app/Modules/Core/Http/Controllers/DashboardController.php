<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
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
        $portal = app(PortalContext::class)->for($request);
        $data = $portal && $portal['kind'] === 'parent' && ! $portal['student_id']
            ? ['kind' => 'parent_empty', 'wards' => [], 'selected' => null]
            : $overview->for($request->user(), $portal['student_id'] ?? ($request->integer('student') ?: null));
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
}
