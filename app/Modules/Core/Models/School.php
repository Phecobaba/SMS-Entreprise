<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = ['name', 'domain', 'logo_path', 'motto'];
}
