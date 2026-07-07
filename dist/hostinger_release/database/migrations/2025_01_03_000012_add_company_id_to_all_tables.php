<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabelas que precisam de company_id
        $tables = [
            'collaborators',
            'service_types',
            'entry_types',
            'exit_types',
            'work_shifts',
            'financial_records',
            'commissions',
            'daily_closures',
            'balances',
            'import_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    // Adicionar company_id se não existir
                    if (!Schema::hasColumn($table->getTable(), 'company_id')) {
                        $table->foreignId('company_id')->after('id')->constrained('companies')->cascadeOnDelete();
                        $table->index('company_id');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'collaborators',
            'service_types',
            'entry_types',
            'exit_types',
            'work_shifts',
            'financial_records',
            'commissions',
            'daily_closures',
            'balances',
            'import_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeignKeyIfExists(['company_id']);
                    $table->dropIndexIfExists(['company_id']);
                    $table->dropColumnIfExists('company_id');
                });
            }
        }
    }
};
