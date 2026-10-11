<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::applyInitialPasswordForGoogleUsers();
    }

    public function down(): void
    {
        // Não reverte a senha inicial para preservar o acesso do usuário após a migração.
    }
};
