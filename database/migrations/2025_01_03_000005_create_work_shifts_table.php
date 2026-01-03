<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaborator_id')->constrained('collaborators')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('total_hours', 5, 2);
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('collaborator_id');
            $table->index('date');
            $table->unique(['collaborator_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
