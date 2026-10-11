<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\InAppNotifications;
use App\Modules\Portals\Services\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, InAppNotifications $notifications): View
    {
        return view('notifications.index', [
            'notifications' => $notifications->inbox($request->user()),
            'unreadCount' => $notifications->unreadCount($request->user()),
            'context' => app(PortalContext::class)->for($request),
        ]);
    }

    public function read(int $notification, Request $request, InAppNotifications $notifications): RedirectResponse
    {
        app(PortalContext::class)->for($request);
        $notifications->markRead($notification, $request->user());

        return back();
    }

    public function readAll(Request $request, InAppNotifications $notifications): RedirectResponse
    {
        app(PortalContext::class)->for($request);
        $notifications->markAllRead($request->user());

        return back()->with('status', 'All notifications marked as read.');
    }
}
