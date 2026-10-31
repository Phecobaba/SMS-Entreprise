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
        // Defaults are additive so deliberate role changes are never removed.
    }
};
