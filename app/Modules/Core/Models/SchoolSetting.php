<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'school_id';

    public $incrementing = false;

    protected $fillable = [
        'logo_path', 'primary_color', 'secondary_color', 'about_text',
        'contact_email', 'contact_phone', 'address', 'motto',
        'admissions_form_target', 'result_access_payment_rule',
    ];
}
