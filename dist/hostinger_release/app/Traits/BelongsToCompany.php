<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    /**
     * Scope para filtrar por company atual
     */
    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        $companyId = $companyId ?? auth()->user()?->company_id;
        
        if (!$companyId) {
            return $query;
        }

        return $query->where('company_id', $companyId);
    }

    /**
     * Atribuir company_id automaticamente ao criar
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->company_id && auth()->check()) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }
}
