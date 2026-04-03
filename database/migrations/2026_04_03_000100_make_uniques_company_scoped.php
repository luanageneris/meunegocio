<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropIndexIfExists('entry_types', 'entry_types_name_unique');
        $this->dropIndexIfExists('exit_types', 'exit_types_name_unique');
        $this->dropIndexIfExists('collaborators', 'collaborators_cpf_unique');

        $this->createUniqueIfMissing('entry_types', 'entry_types_company_id_name_unique', ['company_id', 'name']);
        $this->createUniqueIfMissing('exit_types', 'exit_types_company_id_name_unique', ['company_id', 'name']);
        $this->createUniqueIfMissing('collaborators', 'collaborators_company_id_cpf_unique', ['company_id', 'cpf']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('entry_types', 'entry_types_company_id_name_unique');
        $this->dropIndexIfExists('exit_types', 'exit_types_company_id_name_unique');
        $this->dropIndexIfExists('collaborators', 'collaborators_company_id_cpf_unique');

        $this->createUniqueIfMissing('entry_types', 'entry_types_name_unique', ['name']);
        $this->createUniqueIfMissing('exit_types', 'exit_types_name_unique', ['name']);
        $this->createUniqueIfMissing('collaborators', 'collaborators_cpf_unique', ['cpf']);
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $indexName));
        }
    }

    private function createUniqueIfMissing(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        $columnList = implode(', ', array_map(fn (string $column) => sprintf('`%s`', $column), $columns));
        DB::statement(sprintf('ALTER TABLE `%s` ADD UNIQUE `%s` (%s)', $table, $indexName, $columnList));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select(sprintf("PRAGMA index_list('%s')", $table));

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT COUNT(*) AS count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return (int) ($result->count ?? 0) > 0;
    }
};