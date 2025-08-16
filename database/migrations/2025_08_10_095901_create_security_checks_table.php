<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_checks', function (Blueprint $table) {
            $table->id();
            $table->string('check_type');
            $table->string('check_name');
            $table->text('description');
            $table->decimal('score', 5, 2);
            $table->enum('status', ['excellent', 'good', 'adequate', 'needs_improvement', 'critical']);
            $table->enum('priority', ['critical', 'high', 'medium', 'low']);
            $table->enum('frequency', ['continuous', 'real_time', 'daily', 'weekly', 'monthly']);
            $table->timestamp('last_checked');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_checks');
    }
};