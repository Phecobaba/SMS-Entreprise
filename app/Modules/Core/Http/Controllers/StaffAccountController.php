<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AppNavigation;
use App\Modules\Core\Services\ChangeOwnPassword;
use App\Modules\Core\Services\RoleAuthority;
use App\Modules\Core\Services\StaffCredentials;
use App\Modules\Core\Services\UsernameRules;
use App\Modules\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffAccountController extends Controller
{
    public function create(Request $request, RoleAuthority $roles): View
    {
        return view('core.staff-add', [
            'roles' => $roles->rolesActorMayAssign($request->user()),
            'sections' => Section::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, StaffCredentials $credentials): Response
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'integer'],
            'section_ids' => ['array'],
            'section_ids.*' => ['integer'],
            'username' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->where('school_id', app(TenantContext::class)->id())],
        ]);
        $created = $credentials->create($data, $request->user());

        return response()->view('core.staff-credentials', $created + ['context' => 'created'])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    public function index(): View
    {
        $staffRoleNames = ['School Admin', 'Principal', 'Bursar', 'Teacher'];

        return view('core.staff-access', [
            'accounts' => User::with(['roles', 'sections'])->whereHas('roles', fn ($query) => $query->whereIn('roles.name', $staffRoleNames))->orderBy('name')->paginate(20),
        ]);
    }

    public function show(User $user, Request $request, RoleAuthority $roles): View
    {
        $user->load(['roles', 'sections']);
        $actor = $request->user()->loadMissing('roles');

        return view('core.staff-access-show', [
            'account' => $user,
            'roles' => $roles->rolesActorMayAssign($request->user()),
            'sections' => Section::orderBy('name')->get(),
            'viewOnly' => ! $roles->mayManage($actor, $user),
            'ownAccount' => $user->is($request->user()),
        ]);
    }

    public function reset(User $user, Request $request, StaffCredentials $credentials): Response
    {
        $reset = $credentials->reset($user->loadMissing('roles'), $request->user()->loadMissing('roles'));

        return response()->view('core.staff-credentials', $reset + ['context' => 'reset'])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    public function update(User $user, Request $request, StaffCredentials $credentials): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer'],
            'section_ids' => ['array'],
            'section_ids.*' => ['integer'],
        ]);
        $role = Role::findOrFail((int) $data['role_id']);
        $credentials->changeAccess($user->loadMissing('roles'), $role, $data['section_ids'] ?? [], $request->user()->loadMissing('roles'));

        return redirect()->route('staff.access.show', $user)->with('status', 'Role and Section saved.');
    }

    public function account(Request $request, AppNavigation $navigation): View
    {
        abort_unless($navigation->staffKind($request->user()) !== null, 403);

        return view('core.account', ['account' => $request->user()->load(['roles', 'sections'])]);
    }

    public function changeOwnPassword(Request $request, ChangeOwnPassword $passwords): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $passwords->change($request->user(), $data['password'], $data['current_password'], $request->session()->getId(), true);
        $request->session()->regenerate();

        return redirect()->route('account.show')->with('status', 'Password changed. Other devices have been signed out.');
    }

    public function rules(): View
    {
        return view('core.sign-in-rules');
    }

    public function checkUsername(Request $request, UsernameRules $rules): RedirectResponse
    {
        $username = $rules->normalize((string) $request->validate(['username' => ['required', 'string', 'max:30']])['username']);
        $error = $rules->formatError($username);
        $result = $error ?: ($rules->isTaken($username) ? 'That username is already in use.' : 'That username is available.');

        return redirect()->route('sign-in-rules.show')->withInput(['username' => $username])->with('check_result', $result);
    }
}
