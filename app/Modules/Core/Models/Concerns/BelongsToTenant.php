<?php

namespace App\Modules\Core\Models\Concerns;

use App\Modules\Core\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $builder->where($builder->getModel()->qualifyColumn('school_id'), app(TenantContext::class)->id());
        });

        static::creating(function (Model $model): void {
            $schoolId = app(TenantContext::class)->id();

            if ($model->getAttribute('school_id') !== null && (int) $model->getAttribute('school_id') !== $schoolId) {
                throw new RuntimeException('This record belongs to a different school.');
            }

            $model->setAttribute('school_id', $schoolId);
        });

        static::updating(function (Model $model): void {
            $schoolId = app(TenantContext::class)->id();

            if ((int) $model->getOriginal('school_id') !== $schoolId
                || (int) $model->getAttribute('school_id') !== $schoolId) {
                throw new RuntimeException('This record belongs to a different school.');
            }
        });

        static::deleting(function (Model $model): void {
            if ((int) $model->getOriginal('school_id') !== app(TenantContext::class)->id()) {
                throw new RuntimeException('This record belongs to a different school.');
            }
        });
    }
}
