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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');
            $table->enum('type', ['MAG', 'M3U']);
            $table->json('credentials')->nullable()->comment('MAC for MAG, username/password for M3U');
            $table->bigInteger('pack_id');
            $table->integer('sub_duration')->comment('Subscription duration in months');
            $table->string('notes')->nullable();
            $table->char('country', 2)->nullable();
            $table->bigInteger('api_user_id')->nullable()->comment('User ID from GOLD PANEL API');
            $table->string('url')->nullable()->comment('Portal URL or M3U URL');
            $table->enum('status', ['enable', 'disable'])->default('enable');
            $table->date('expire_date')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index('expire_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
