<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToCompany;

class FinancialRecord extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'type',
        'entry_type_id',
        'exit_type_id',
        'collaborator_id',
        'service_type_id',
        'work_shift_id',
        'amount',
        'quantity',
        'discount',
        'date',
        'description',
    ];

    protected $casts = [
        'type' => 'string',
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'quantity' => 'integer',
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relacionamentos
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(EntryType::class);
    }

    public function exitType(): BelongsTo
    {
        return $this->belongsTo(ExitType::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    // Scopes para filtros
    public function scopeByDate(Builder $query, $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeByWeek(Builder $query, $year, $week): Builder
    {
        return $query->whereBetween('date', [
            \Carbon\Carbon::createFromFormat('Y-W', "$year-$week")->startOfWeek(),
            \Carbon\Carbon::createFromFormat('Y-W', "$year-$week")->endOfWeek(),
        ]);
    }

    public function scopeByMonth(Builder $query, $year, $month): Builder
    {
        return $query->whereYear('date', $year)
            ->whereMonth('date', $month);
    }

    public function scopeByYear(Builder $query, $year): Builder
    {
        return $query->whereYear('date', $year);
    }

    public function scopeEntry(Builder $query): Builder
    {
        return $query->where('type', 'entry');
    }

    public function scopeExit(Builder $query): Builder
    {
        return $query->where('type', 'exit');
    }
}
