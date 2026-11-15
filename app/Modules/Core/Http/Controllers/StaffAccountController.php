<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AppNavigation;
use App\Modules\Core\Services\ChangeOwnPassword;
use App\Modules\Core\Services\RoleAuthority;
use App\Modules\Core\Services\SchoolUserDirectory;
use App\Modules\Core\Services\StaffCredentials;
use App\Modules\Core\Services\StaffSignIn;
use App\Modules\Core\Services\UsernameRules;
use App\Modules\Core\TenantContext;
use App\Modules\Portals\Services\PortalContext;
use App\Modules\StaffHR\Models\Staff;
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
            'roles' => $roles->baseRolesActorMayAssign($request->user()),
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
        return view('core.staff-access', [
            'accounts' => app(SchoolUserDirectory::class)->scopeStaffAccounts(User::with(['roles', 'sections']))->orderBy('name')->paginate(20),
        ]);
    }

    public function show(User $user, Request $request, RoleAuthority $roles): View
    {
        $user->load(['roles', 'sections', 'headSections']);
        $actor = $request->user()->loadMissing('roles');

        return view('core.staff-access-show', [
            'account' => $user,
            'roles' => $roles->baseRolesActorMayAssign($request->user()),
            'sections' => Section::orderBy('name')->get(),
            'baseRole' => $roles->primaryBaseRole($user),
            'canAssignHeads' => $request->user()->can('view_all_sections'),
            'viewOnly' => ! $roles->mayManage($actor, $user),
            'ownAccount' => $user->is($request->user()),
            'staffRecord' => Staff::where('user_id', $user->id)->first(),
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
            'head_section_ids' => ['array'],
            'head_section_ids.*' => ['integer'],
            'head_sections_present' => ['nullable', 'boolean'],
        ]);
        $role = Role::findOrFail((int) $data['role_id']);
        $headSectionIds = $request->user()->can('view_all_sections')
            ? ($request->boolean('head_sections_present')
                ? ($data['head_section_ids'] ?? [])
                : $user->headSections()->pluck('sections.id')->all())
            : null;
        $credentials->changeAccess(
            $user->loadMissing(['roles', 'headSections']),
            $role,
            $data['section_ids'] ?? [],
            $headSectionIds,
            $request->user()->loadMissing('roles'),
        );

        return redirect()->route('staff.access.show', $user)->with('status', 'Role and Sections saved.');
    }

    public function disable(User $user, Request $request, StaffSignIn $signIn): RedirectResponse
    {
        $signIn->disable($user->loadMissing('roles'), $request->user()->loadMissing('roles'));

        return redirect()->route('staff.access.show', $user)->with('status', 'Sign-in disabled.');
    }

    public function enable(User $user, Request $request, StaffSignIn $signIn): RedirectResponse
    {
        $signIn->enable($user->loadMissing('roles'), $request->user()->loadMissing('roles'));

        return redirect()->route('staff.access.show', $user)->with('status', 'Sign-in enabled.');
    }

    public function account(Request $request, AppNavigation $navigation): View
    {
        if ($navigation->staffKind($request->user()) === null) {
            $portal = app(PortalContext::class)->for($request);
            abort_unless(in_array($portal['kind'] ?? null, ['student', 'parent'], true), 403);

            return view('core.portal-account', [
                'account' => $request->user(),
                'portalKind' => $portal['kind'],
                'student' => $portal['kind'] === 'student' ? $portal['selected'] : null,
                'children' => $portal['kind'] === 'parent' ? $portal['wards'] : [],
            ]);
        }

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
