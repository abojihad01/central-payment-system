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
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('command');
            $table->string('cron_expression');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->integer('run_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->longText('last_output')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('timeout_seconds')->default(300);
            $table->integer('max_attempts')->default(3);
            $table->string('environment')->default('production');
            $table->timestamps();
            
            $table->index(['is_active', 'next_run_at']);
            $table->index('environment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
