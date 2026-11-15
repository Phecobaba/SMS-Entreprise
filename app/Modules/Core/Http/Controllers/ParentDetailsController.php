<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\ParentEmail;
use App\Modules\Portals\Services\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParentDetailsController extends Controller
{
    public function update(Request $request, ParentEmail $emails): RedirectResponse
    {
        $context = app(PortalContext::class)->for($request);
        abort_unless(($context['kind'] ?? null) === 'parent', 403);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'current_password' => ['required', 'string'],
        ]);
        $emails->change($request->user(), $data['email'], $data['current_password']);

        return redirect()->route('account.show')->with('status', 'Email saved. Check the new address and confirm it within 60 minutes.');
    }

    public function confirm(User $user, Request $request, ParentEmail $emails): RedirectResponse
    {
        abort_unless($request->user()->is($user), 403);
        $emails->confirm($user, (string) $request->query('email'));

        return redirect()->route('account.show')->with('status', 'Email confirmed. You can now use it for password recovery.');
    }
}
