<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentImport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'owner_id', 'status', 'original_name', 'stored_path', 'file_hash', 'row_count',
        'ready_count', 'skipped_count', 'error_count', 'report', 'confirmed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['report' => 'array', 'confirmed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
