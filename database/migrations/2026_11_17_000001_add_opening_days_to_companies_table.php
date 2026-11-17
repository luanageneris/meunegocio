<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('opening_days')->nullable()->after('use_service_types');
        });

        DB::table('companies')
            ->whereNull('opening_days')
            ->update(['opening_days' => json_encode(range(0, 6))]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('opening_days');
        });
    }
};
