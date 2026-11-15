<?php

namespace App\Modules\Core\Models;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'email_verified_at', 'password', 'must_change_password', 'last_signed_in_at', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_signed_in_at' => 'datetime',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->using(UserRole::class)
            ->withPivot('school_id')
            ->wherePivot('school_id', app(TenantContext::class)->id());
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_user')
            ->withPivot('school_id', 'is_head')
            ->wherePivot('school_id', app(TenantContext::class)->id())
            ->withTimestamps();
    }

    public function headSections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_user')
            ->withPivot('school_id', 'is_head')
            ->withPivotValue('is_head', true)
            ->wherePivot('school_id', app(TenantContext::class)->id())
            ->wherePivot('is_head', true)
            ->withTimestamps();
    }

    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }

    public function formTeacherArms(): HasMany
    {
        return $this->hasMany(ClassArm::class, 'form_teacher_user_id');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->relationLoaded('roles')
            && $this->roles->every(fn (Role $role): bool => $role->relationLoaded('permissions'))) {
            return $this->roles->contains(
                fn (Role $role): bool => $role->permissions->contains('name', $permission)
            );
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('permissions.name', $permission))
            ->exists();
    }
}
