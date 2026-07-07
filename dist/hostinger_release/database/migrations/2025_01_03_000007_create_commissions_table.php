<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaborator_id')->constrained('collaborators')->cascadeOnDelete();
            $table->foreignId('financial_record_id')->constrained('financial_records')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('collaborator_id');
            $table->index('paid_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
