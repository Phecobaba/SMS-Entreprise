<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Services\CandidateAccount;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('core.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required_without:email', 'string', 'max:255'],
            'email' => ['required_without:identifier', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $identifier = trim($credentials['identifier'] ?? $credentials['email']);
        $column = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$column => $identifier, 'password' => $credentials['password'], 'status' => 'active'], $request->boolean('remember'))) {
            $field = $request->filled('identifier') ? 'identifier' : 'email';

            return back()->withErrors([$field => 'These sign-in details are not correct.'])->onlyInput($field);
        }

        $request->session()->regenerate();

        $destination = route('dashboard');
        if (! Gate::allows('view_dashboard')) {
            if (app(StudentDirectory::class)->forUser($request->user())) {
                $destination = route('dashboard');
            } elseif (app(ParentAccountDirectory::class)->existsForUser((int) Auth::id())) {
                $destination = route('dashboard');
            } elseif (app(CandidateAccount::class)->existsForUser((int) Auth::id())) {
                $destination = route('admissions.mine');
            } elseif (Gate::allows('mark_attendance')) {
                $destination = route('attendance.index');
            } elseif (Gate::allows('mark_assignments')) {
                $destination = route('assignments.index');
            } elseif (Gate::allows('upload_enotes')) {
                $destination = route('enotes.index');
            } elseif (Gate::allows('view_attendance_alerts')) {
                $destination = route('attendance.alerts');
            } elseif (Gate::allows('enter_results')) {
                $destination = route('results.index');
            }
        }

        return redirect()->intended($destination);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
