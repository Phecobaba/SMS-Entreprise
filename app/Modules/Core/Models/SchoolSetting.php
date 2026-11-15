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
        'contact_email', 'contact_phone', 'address', 'motto', 'proprietor_comment_title',
        'admissions_form_target', 'result_access_payment_rule',
        'admissions_open', 'application_fee_enabled', 'application_fee_kobo', 'admissions_privacy_text',
        'one_step_payment_approval',
        'paystack_public_key', 'paystack_secret_key', 'paystack_secret_last_four',
        'paystack_mode', 'paystack_changed_by', 'paystack_changed_at',
        'registration_prefix', 'registration_number_length',
        'registration_include_year', 'registration_move_policy',
        'registration_move_policy_locked_at',
        'registration_include_school', 'registration_include_section', 'registration_include_level',
        'registration_separator', 'registration_year_digits', 'registration_school_code',
        'registration_next_number',
        'whatsapp_number', 'opening_hours', 'admissions_key_dates', 'admissions_reopen_text',
        'privacy_contact_email', 'privacy_last_updated_at', 'non_admitted_retention_text',
    ];

    protected $hidden = ['paystack_secret_key'];

    protected function casts(): array
    {
        return [
            'one_step_payment_approval' => 'boolean',
            'admissions_open' => 'boolean',
            'application_fee_enabled' => 'boolean',
            'application_fee_kobo' => 'integer',
            'paystack_secret_key' => 'encrypted',
            'paystack_changed_at' => 'datetime',
            'registration_include_year' => 'boolean',
            'registration_include_school' => 'boolean',
            'registration_include_section' => 'boolean',
            'registration_include_level' => 'boolean',
            'registration_move_policy_locked_at' => 'datetime',
            'privacy_last_updated_at' => 'date',
        ];
    }

    public function paystackChangedBy()
    {
        return $this->belongsTo(User::class, 'paystack_changed_by');
    }
}
