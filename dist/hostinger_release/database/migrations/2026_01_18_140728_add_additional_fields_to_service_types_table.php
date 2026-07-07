<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('name'); // Ex: Folha, Hora, Serviço
            $table->decimal('service_value', 10, 2)->nullable()->after('unit'); // Valor do serviço
            $table->decimal('commission_value', 10, 2)->nullable()->after('service_value'); // Valor da comissão
            $table->decimal('resource_value', 10, 2)->nullable()->after('commission_value'); // Valor do recurso
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn(['unit', 'service_value', 'commission_value', 'resource_value']);
        });
    }
};
