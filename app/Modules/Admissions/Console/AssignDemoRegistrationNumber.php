<?php

namespace App\Modules\Admissions\Console;

use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\RegistrationNumbers;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class AssignDemoRegistrationNumber extends Command
{
    protected $signature = 'oakfield:assign-demo-registration-number
        {--dry-run : Show the number that would be assigned without writing}
        {--actor= : User ID recorded for the real change}';

    protected $description = 'Assign a registration number to the local demo student';

    public function handle(TenantContext $tenant, RegistrationNumbers $numbers): int
    {
        if (! app()->environment('local')) {
            $this->error('This demo-data command may run only in the local environment.');

            return self::FAILURE;
        }
        $school = School::find((int) config('tenancy.default_school_id'));
        if (! $school) {
            $this->error('The configured school does not exist.');

            return self::FAILURE;
        }
        $tenant->set($school->id);
        $student = Student::whereHas('user', fn ($query) => $query->where('email', 'demo-student@oakfield.test'))->first();
        if (! $student) {
            $this->error('The demo student does not exist.');

            return self::FAILURE;
        }
        if ($student->registration_number) {
            $this->info('The demo student already has '.$student->registration_number.'.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->info('DRY RUN — would assign '.$numbers->preview($student->class_arm_id).'. No changes written.');

            return self::SUCCESS;
        }
        $actor = User::find((int) $this->option('actor'));
        if (! $actor) {
            $this->error('A valid user from this school is required. Re-run with --actor=<user-id>.');

            return self::FAILURE;
        }
        $numbers->assignAutomatic($student, $actor);
        $this->info('Assigned '.$student->refresh()->registration_number.' to the demo student.');

        return self::SUCCESS;
    }
}
