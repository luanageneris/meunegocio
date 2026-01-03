<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('commission_percentage', 5, 2);
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('commission_percentage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
