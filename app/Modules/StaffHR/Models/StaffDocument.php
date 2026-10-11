<?php

namespace App\Modules\StaffHR\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StaffDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = ['staff_id', 'document_type', 'file_path', 'uploaded_at'];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }
}
