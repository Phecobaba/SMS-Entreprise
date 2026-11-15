<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffSignIn
{
    public function __construct(
        private readonly SessionRevoker $sessions,
        private readonly LastActiveAdminGuard $admins,
        private readonly AuditRecorder $audit,
    ) {}

    public function disable(User $target, User $actor): void
    {
        DB::transaction(function () use ($target, $actor): void {
            $this->disableForRelatedAction($target, $actor);
            $this->audit->record(
                $actor,
                'staff_sign_in_disabled',
                $target,
                module: 'Staff access',
                description: 'Disabled sign-in for '.$target->name.'.',
            );
        });
    }

    public function enable(User $target, User $actor): void
    {
        $this->assertNotSelf($target, $actor);

        DB::transaction(function () use ($target, $actor): void {
            $target->forceFill(['status' => User::STATUS_ACTIVE])->save();
            $this->audit->record(
                $actor,
                'staff_sign_in_enabled',
                $target,
                module: 'Staff access',
                description: 'Enabled sign-in for '.$target->name.'.',
            );
        });
    }

    /** Caller must wrap this with the related state change in one transaction. */
    public function disableForRelatedAction(User $target, User $actor): void
    {
        $this->assertMayDisable($target, $actor);
        $target->forceFill(['status' => User::STATUS_DISABLED])->save();
        $this->sessions->all($target);
    }

    public function assertMayDisable(User $target, User $actor): void
    {
        $this->assertNotSelf($target, $actor);
        $this->admins->assertCanDisable($target);
    }

    private function assertNotSelf(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'account' => "You can't disable or enable your own sign-in here.",
            ]);
        }
    }
}
