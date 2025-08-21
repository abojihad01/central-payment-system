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
        Schema::create('reseller_info', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->integer('credits')->default(0)->comment('Available credits or subscriptions');
            $table->boolean('enabled')->default(true);
            $table->string('api_key');
            $table->timestamp('last_fetched')->nullable();
            $table->timestamps();
            
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_info');
    }
};
