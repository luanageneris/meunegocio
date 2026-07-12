<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('daily_enabled')->default(false);
            $table->string('daily_condition', 32)->default('missing_entry_or_exit');
            $table->json('daily_days_of_week')->nullable();
            $table->time('send_time')->default('18:00:00');
            $table->boolean('monthly_enabled')->default(false);
            $table->boolean('yearly_enabled')->default(false);
            $table->text('pdf_pin');
            $table->date('last_daily_sent_for_date')->nullable();
            $table->string('last_monthly_sent_for_period', 7)->nullable();
            $table->unsignedSmallInteger('last_yearly_sent_for_period')->nullable();
            $table->timestamps();

            $table->index(['daily_enabled', 'monthly_enabled', 'yearly_enabled'], 'reminder_channels_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reminder_settings');
    }
};
