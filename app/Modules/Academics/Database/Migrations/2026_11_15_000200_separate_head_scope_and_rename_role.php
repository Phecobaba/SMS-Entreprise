<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('section_user', 'is_head')) {
            Schema::table('section_user', function (Blueprint $table): void {
                $table->boolean('is_head')->default(false)->after('user_id');
                $table->index(['school_id', 'is_head'], 'section_user_head_scope_index');
            });
        }

        DB::table('section_user')->whereExists(function ($query): void {
            $query->selectRaw('1')
                ->from('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->whereColumn('user_roles.school_id', 'section_user.school_id')
                ->whereColumn('user_roles.user_id', 'section_user.user_id')
                ->where('roles.name', 'Principal');
        })->update(['is_head' => true, 'updated_at' => now()]);

        DB::table('roles')->where('name', 'Principal')->update([
            'name' => 'Head',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Head')->update([
            'name' => 'Principal',
            'updated_at' => now(),
        ]);

        Schema::table('section_user', function (Blueprint $table): void {
            $table->dropIndex('section_user_head_scope_index');
            $table->dropColumn('is_head');
        });
    }
};
