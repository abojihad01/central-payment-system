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
        Schema::create('bot_detections', function (Blueprint $table) {
            $table->id();
            $table->ipAddress('ip_address');
            $table->string('user_agent', 1000)->nullable();
            $table->string('detection_type'); // bot_user_agent, honeypot, rate_limit, timing
            $table->string('url_requested')->nullable();
            $table->string('method', 10)->default('GET');
            $table->json('request_data')->nullable();
            $table->json('headers')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_blocked')->default(true);
            $table->text('detection_details')->nullable();
            $table->integer('risk_score')->default(0); // 0-100
            $table->timestamp('detected_at');
            $table->timestamps();
            
            $table->index(['ip_address', 'detected_at']);
            $table->index(['detection_type', 'detected_at']);
            $table->index(['is_blocked', 'detected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_detections');
    }
};
