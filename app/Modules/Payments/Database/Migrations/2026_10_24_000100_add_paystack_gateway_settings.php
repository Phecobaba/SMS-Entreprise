<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->string('paystack_public_key')->nullable();
            $table->text('paystack_secret_key')->nullable();
            $table->string('paystack_secret_last_four', 4)->nullable();
            $table->string('paystack_mode', 8)->nullable();
            $table->foreignId('paystack_changed_by')->nullable();
            $table->timestamp('paystack_changed_at')->nullable();
            $table->foreign(['school_id', 'paystack_changed_by'], 'school_settings_paystack_changer_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        $permissionId = DB::table('permissions')->where('name', 'manage_payment_gateway')->value('id')
            ?: DB::table('permissions')->insertGetId([
                'name' => 'manage_payment_gateway', 'created_at' => now(), 'updated_at' => now(),
            ]);

        foreach (DB::table('roles')->where('name', 'School Admin')->get(['id', 'school_id']) as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'school_id' => $role->school_id,
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->dropForeign('school_settings_paystack_changer_fk');
            $table->dropColumn([
                'paystack_public_key', 'paystack_secret_key', 'paystack_secret_last_four',
                'paystack_mode', 'paystack_changed_by', 'paystack_changed_at',
            ]);
        });
    }
};
