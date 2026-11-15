<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class RepairHeadRoles extends Command
{
    protected $signature = 'staff:repair-head-roles {--apply : Apply the reported missing Head roles}';

    protected $description = 'Report and optionally repair Head scope and role consistency';

    public function handle(TenantContext $tenant, AuditRecorder $audit): int
    {
        $apply = (bool) $this->option('apply');
        $missingRoleCount = 0;
        $roleWithoutScope = collect();

        foreach (School::orderBy('id')->get() as $school) {
            $tenant->set($school->id);
            try {
                $headRole = Role::where('name', 'Head')->first();
                if (! $headRole) {
                    $this->warn($school->name.': Head role not found.');

                    continue;
                }

                $missing = User::whereHas('headSections')
                    ->whereDoesntHave('roles', fn ($query) => $query->where('roles.id', $headRole->id))
                    ->orderBy('name')->get();
                $roleOnly = User::whereHas('roles', fn ($query) => $query->where('roles.id', $headRole->id))
                    ->whereDoesntHave('headSections')->orderBy('name')->get();
                $missingRoleCount += $missing->count();
                $roleWithoutScope = $roleWithoutScope->merge($roleOnly->map(fn (User $user): string => $school->name.': '.$user->name));

                if ($apply && $missing->isNotEmpty()) {
                    $actor = User::whereHas('roles', fn ($query) => $query->where('roles.name', 'School Admin'))->orderBy('id')->first();
                    if (! $actor) {
                        $this->error($school->name.': no School Admin is available to own the audit entries.');

                        return self::FAILURE;
                    }
                    foreach ($missing as $user) {
                        $user->roles()->syncWithoutDetaching([$headRole->id => ['school_id' => $school->id]]);
                        $audit->record($actor, 'head_role_repaired', $user, [
                            'head_section_ids' => $user->headSections()->pluck('sections.id')->all(),
                        ], 'Accounts', 'Head role granted to match existing Head scope.');
                    }
                }
            } finally {
                $tenant->clear();
            }
        }

        $this->line(($apply ? 'Applied' : 'Dry run').': '.$missingRoleCount.' account(s) need the Head role.');
        $this->line('Head role without Head scope: '.$roleWithoutScope->count().'.');
        $roleWithoutScope->each(fn (string $name) => $this->line(' - '.$name));

        return self::SUCCESS;
    }
}
