<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_records', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['entry', 'exit']);
            $table->foreignId('entry_type_id')->nullable()->constrained('entry_types')->cascadeOnDelete();
            $table->foreignId('exit_type_id')->nullable()->constrained('exit_types')->cascadeOnDelete();
            $table->foreignId('collaborator_id')->nullable()->constrained('collaborators')->cascadeOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained('service_types')->cascadeOnDelete();
            $table->foreignId('work_shift_id')->nullable()->constrained('work_shifts')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->integer('quantity')->nullable();
            $table->decimal('discount', 10, 2)->nullable()->default(0);
            $table->date('date');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('type');
            $table->index('date');
            $table->index('collaborator_id');
            $table->index('service_type_id');
            $table->index('entry_type_id');
            $table->index('exit_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_records');
    }
};
