<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\ChangeOwnPassword;
use App\Modules\Core\Services\LoginDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless($request->user()->must_change_password, 404);

        return view('core.set-password');
    }

    public function store(Request $request, ChangeOwnPassword $passwords, LoginDestination $destination): RedirectResponse
    {
        abort_unless($request->user()->must_change_password, 404);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $passwords->change($request->user(), $data['password'], null, $request->session()->getId(), false);
        $request->session()->regenerate();

        return redirect()->to($destination->for($request->user()->refresh()))->with('status', 'Your password is set.');
    }
}
