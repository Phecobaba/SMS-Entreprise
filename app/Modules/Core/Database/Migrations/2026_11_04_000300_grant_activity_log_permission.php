<?php

use App\Modules\Core\Services\DefaultRoleCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(DefaultRoleCatalog::class)->addMissingPermissionsToExistingRoles();
    }

    public function down(): void
    {
        // Additive role grants are not removed from school-managed roles.
    }
};
