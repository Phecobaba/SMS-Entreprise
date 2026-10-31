<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SessionRevoker
{
    public function all(User $user): void
    {
        $this->deleteSessions($user);
        $user->forceFill(['remember_token' => null])->save();
    }

    public function others(User $user, ?string $currentSessionId): void
    {
        $this->deleteSessions($user, $currentSessionId);
        $user->forceFill(['remember_token' => null])->save();
    }

    private function deleteSessions(User $user, ?string $except = null): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }
        $query = DB::table('sessions')->where('user_id', $user->id);
        if ($except) {
            $query->where('id', '!=', $except);
        }
        $query->delete();
    }
}
