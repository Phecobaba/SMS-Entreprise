<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\SessionRevoker;
use App\Modules\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HeadAssignments
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SessionRevoker $sessions,
    ) {}

    public function setSectionHead(Section $section, ?User $head, User $actor): Section
    {
        Gate::forUser($actor)->authorize('view_all_sections');
        if ($head && ((int) $head->school_id !== (int) $section->school_id || $head->status !== User::STATUS_ACTIVE)) {
            throw ValidationException::withMessages(['head_user_id' => 'Choose an active staff account from this school.']);
        }

        return DB::transaction(function () use ($section, $head, $actor): Section {
            $before = $section->heads()->get();
            $newIds = $head ? [$head->id] : [];

            foreach ($before->reject(fn (User $user): bool => in_array($user->id, $newIds, true)) as $removed) {
                $this->setScope($removed, $section, false, $actor);
            }
            if ($head && ! $before->contains(fn (User $user): bool => $user->is($head))) {
                $this->setScope($head, $section, true, $actor);
            }

            return $section->refresh()->load('heads');
        });
    }

    /** @param list<int|string> $sectionIds */
    public function syncUser(User $user, array $sectionIds, User $actor): User
    {
        Gate::forUser($actor)->authorize('view_all_sections');
        $ids = collect($sectionIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if (Section::whereIn('id', $ids)->count() !== $ids->count()) {
            throw ValidationException::withMessages(['head_section_ids' => 'Choose Head Sections from this school only.']);
        }

        return DB::transaction(function () use ($user, $ids, $actor): User {
            $current = $user->headSections()->get()->keyBy('id');
            foreach ($current->keys()->diff($ids) as $sectionId) {
                $this->setScope($user, $current[(int) $sectionId], false, $actor);
            }
            foreach ($ids->diff($current->keys()) as $sectionId) {
                $this->setScope($user, Section::findOrFail((int) $sectionId), true, $actor);
            }

            return $user->refresh()->load(['roles', 'sections', 'headSections']);
        });
    }

    private function setScope(User $user, Section $section, bool $assigned, User $actor): void
    {
        $role = Role::where('name', 'Head')->firstOrFail();
        $existing = DB::table('section_user')
            ->where('school_id', app(TenantContext::class)->id())
            ->where('section_id', $section->id)
            ->where('user_id', $user->id);

        if ($assigned) {
            if ($existing->exists()) {
                $existing->update(['is_head' => true, 'updated_at' => now()]);
            } else {
                DB::table('section_user')->insert([
                    'school_id' => app(TenantContext::class)->id(),
                    'section_id' => $section->id,
                    'user_id' => $user->id,
                    'is_head' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $user->roles()->syncWithoutDetaching([$role->id => ['school_id' => $user->school_id]]);
            $action = 'section_head_assigned';
            $description = $user->name.' was assigned as Head of '.$section->name.'.';
        } else {
            $existing->update(['is_head' => false, 'updated_at' => now()]);
            $remaining = DB::table('section_user')->where('school_id', $user->school_id)
                ->where('user_id', $user->id)->where('is_head', true)->exists();
            if (! $remaining) {
                $user->roles()->detach($role->id);
            }
            $action = 'section_head_removed';
            $description = $user->name.' was removed as Head of '.$section->name.'.';
        }

        $this->audit->record($actor, $action, $section, [
            'head_user_id' => $user->id,
            'head_role_present' => $assigned || $user->roles()->whereKey($role->id)->exists(),
        ], 'Academics', $description);
        $this->sessions->all($user);
    }
}
