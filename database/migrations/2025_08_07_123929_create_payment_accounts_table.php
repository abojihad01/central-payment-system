<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_gateway_id')->constrained()->cascadeOnDelete();
            $table->string('account_id')->unique(); // معرف فريد للحساب
            $table->string('name'); // اسم الحساب
            $table->text('description')->nullable();
            $table->json('credentials'); // API keys مشفرة
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(false);
            $table->integer('successful_transactions')->default(0);
            $table->integer('failed_transactions')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->json('settings')->nullable(); // إعدادات إضافية للحساب
            $table->timestamps();
            
            $table->index(['payment_gateway_id', 'is_active']);
            $table->index(['successful_transactions', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};