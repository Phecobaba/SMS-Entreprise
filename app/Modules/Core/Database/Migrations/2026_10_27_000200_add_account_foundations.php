<?php

use App\Modules\Core\Services\DefaultRoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('last_signed_in_at')->nullable()->after('must_change_password');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('actor_name')->nullable()->after('user_id');
            $table->string('actor_role')->nullable()->after('actor_name');
        });

        app(DefaultRoleCatalog::class)->addMissingPermissionsToExistingRoles();
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn(['actor_name', 'actor_role']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['must_change_password', 'last_signed_in_at']);
        });
    }
};
