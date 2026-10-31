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
        'one_step_payment_approval',
        'paystack_public_key', 'paystack_secret_key', 'paystack_secret_last_four',
        'paystack_mode', 'paystack_changed_by', 'paystack_changed_at',
        'registration_prefix', 'registration_number_length',
        'registration_include_year', 'registration_move_policy',
        'registration_move_policy_locked_at',
    ];

    protected $hidden = ['paystack_secret_key'];

    protected function casts(): array
    {
        return [
            'one_step_payment_approval' => 'boolean',
            'paystack_secret_key' => 'encrypted',
            'paystack_changed_at' => 'datetime',
            'registration_include_year' => 'boolean',
            'registration_move_policy_locked_at' => 'datetime',
        ];
    }

    public function paystackChangedBy()
    {
        return $this->belongsTo(User::class, 'paystack_changed_by');
    }
}
