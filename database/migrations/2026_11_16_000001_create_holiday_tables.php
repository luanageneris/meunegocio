<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_holiday_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->string('country_name');
            $table->string('region_code')->default('');
            $table->string('region_name')->default('');
            $table->string('city_code')->default('');
            $table->string('city_name')->default('');
            $table->timestamp('consented_at');
            $table->timestamps();
        });

        Schema::create('holiday_syncs', function (Blueprint $table) {
            $table->id();
            $table->char('location_key', 64);
            $table->unsignedSmallInteger('year');
            $table->string('source')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
            $table->unique(['location_key', 'year']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_sync_id')->constrained('holiday_syncs')->cascadeOnDelete();
            $table->string('external_key');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('name');
            $table->string('type');
            $table->string('scope');
            $table->timestamps();
            $table->unique(['holiday_sync_id', 'external_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('holiday_syncs');
        Schema::dropIfExists('user_holiday_locations');
    }
};
