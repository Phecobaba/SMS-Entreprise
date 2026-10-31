<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\LoginDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('core.login');
    }

    public function login(Request $request, LoginDestination $destination): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required_without:email', 'string', 'max:255'],
            'email' => ['required_without:identifier', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $identifier = trim($credentials['identifier'] ?? $credentials['email']);
        $column = str_contains($identifier, '@') ? 'email' : 'username';
        $user = User::query()->whereRaw('LOWER('.$column.') = ?', [strtolower($identifier)])->where('status', 'active')->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $field = $request->filled('identifier') ? 'identifier' : 'email';

            return back()->withErrors([$field => 'Check your email or username and your password, and try again.'])->onlyInput($field);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();
        $user->forceFill(['last_signed_in_at' => now()])->save();

        $target = $destination->for($user);

        return $user->must_change_password ? redirect()->to($target) : redirect()->intended($target);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
