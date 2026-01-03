<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closures', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('total_entry', 10, 2)->default(0);
            $table->decimal('total_exit', 10, 2)->default(0);
            $table->decimal('profit', 10, 2)->default(0);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            // Índices
            $table->index('date');
            $table->index('is_closed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closures');
    }
};
