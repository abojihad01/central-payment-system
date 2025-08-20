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
        Schema::create('payment_schedule_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('schedule_type', ['verification', 'processing', 'retry', 'cleanup']);
            $table->integer('interval_minutes')->default(30);
            $table->integer('min_age_minutes')->default(5);
            $table->integer('max_age_minutes')->default(1440);
            $table->integer('batch_limit')->default(50);
            $table->boolean('is_active')->default(true);
            $table->text('command');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_schedule_settings');
    }
};
