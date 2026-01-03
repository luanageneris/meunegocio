<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'date',
        'total_entry',
        'total_exit',
        'profit',
        'is_closed',
    ];

    protected $casts = [
        'date' => 'date',
        'total_entry' => 'decimal:2',
        'total_exit' => 'decimal:2',
        'profit' => 'decimal:2',
        'is_closed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
