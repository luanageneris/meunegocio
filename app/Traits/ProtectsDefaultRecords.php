<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use LogicException;

trait ProtectsDefaultRecords
{
    protected static function bootProtectsDefaultRecords(): void
    {
        static::deleting(function (Model $model): void {
            if ($model->isProtectedDefaultRecord()) {
                throw new LogicException($model->defaultProtectionMessage('delete'));
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isProtectedDefaultRecord() && $model->isProtectedDefaultRecordDirty()) {
                throw new LogicException($model->defaultProtectionMessage('update'));
            }
        });
    }

    abstract protected function protectedDefaultNames(): array;

    protected function protectedDefaultAttributes(): array
    {
        return ['name'];
    }

    protected function isProtectedDefaultRecord(): bool
    {
        $normalizedName = mb_strtolower(trim((string) $this->getAttribute('name')));

        foreach ($this->protectedDefaultNames() as $protectedName) {
            if ($normalizedName === mb_strtolower(trim($protectedName))) {
                return true;
            }
        }

        return false;
    }

    protected function isProtectedDefaultRecordDirty(): bool
    {
        foreach ($this->protectedDefaultAttributes() as $attribute) {
            if ($this->isDirty($attribute)) {
                return true;
            }
        }

        return false;
    }

    protected function defaultProtectionMessage(string $action): string
    {
        return match ($action) {
            'delete' => 'Este registro padrão não pode ser removido',
            'update' => 'Este registro padrão não pode ser alterado',
            default => 'Este registro padrão não pode ser alterado',
        };
    }
}