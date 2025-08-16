<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // stripe, paypal, etc.
            $table->string('display_name'); // Stripe, PayPal, etc.
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // للترتيب
            $table->json('supported_currencies')->nullable(); // ['USD', 'EUR', 'SAR']
            $table->json('supported_countries')->nullable(); // ['US', 'SA', 'AE']
            $table->json('configuration')->nullable(); // إعدادات إضافية
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};