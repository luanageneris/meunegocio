<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'model',
        'model_id',
        'old_values',
        'new_values',
        'changes',
        'ip_address',
        'user_agent',
        'endpoint',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByModel($query, $model)
    {
        return $query->where('model', $model);
    }

    // Helpers para legibilidade
    public function getActionLabel(): string
    {
        return match($this->action) {
            'create' => 'Criado',
            'update' => 'Atualizado',
            'delete' => 'Deletado',
            'restore' => 'Restaurado',
            default => ucfirst($this->action),
        };
    }

    public function getModelLabel(): string
    {
        return match($this->model) {
            'App\Models\Company' => 'Empresa',
            'App\Models\Collaborator' => 'Colaborador',
            'App\Models\FinancialRecord' => 'Registro Financeiro',
            'App\Models\Commission' => 'Comissão',
            'App\Models\WorkShift' => 'Turno de Trabalho',
            'App\Models\ServiceType' => 'Tipo de Serviço',
            'App\Models\EntryType' => 'Tipo de Entrada',
            'App\Models\ExitType' => 'Tipo de Saída',
            'App\Models\DailyClosure' => 'Fechamento Diário',
            'App\Models\Balance' => 'Saldo',
            'App\Models\ImportLog' => 'Importação',
            default => class_basename($this->model),
        };
    }

    public function getIconForAction(): string
    {
        return match($this->action) {
            'create' => '✨',
            'update' => '✏️',
            'delete' => '🗑️',
            'restore' => '↩️',
            default => '📝',
        };
    }
}
