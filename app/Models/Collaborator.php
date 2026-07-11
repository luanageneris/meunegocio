<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToCompany;
use App\Traits\ProtectsDefaultRecords;

class Collaborator extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany, ProtectsDefaultRecords;

    protected $fillable = [
        'company_id',
        'name',
        'cpf',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relacionamentos
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workShifts(): HasMany
    {
        return $this->hasMany(WorkShift::class);
    }

    public function financialRecords(): HasMany
    {
        return $this->hasMany(FinancialRecord::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    protected function protectedDefaultNames(): array
    {
        return ['Não definido', 'Nao definido'];
    }

    protected function protectedDefaultAttributes(): array
    {
        return ['name'];
    }
}
