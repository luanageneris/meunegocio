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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // Rastreamento
            $table->string('action'); // 'create', 'update', 'delete', 'restore'
            $table->string('model'); // Nome da classe (ex: FinancialRecord, Collaborator)
            $table->unsignedBigInteger('model_id'); // ID do modelo alterado
            
            // Dados
            $table->json('old_values')->nullable(); // Valores anteriores (para update/delete)
            $table->json('new_values')->nullable(); // Novos valores (para create/update)
            $table->json('changes')->nullable(); // Apenas campos que mudaram
            
            // Context
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('endpoint')->nullable(); // Rota/endpoint que fez a alteração
            
            $table->timestamps();
            
            // Indexes para performance
            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model', 'model_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
