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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_link_id')->constrained()->onDelete('cascade');
            $table->string('payment_gateway'); // stripe, paypal, etc
            $table->string('gateway_payment_id')->unique();
            $table->string('gateway_session_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'refunded']);
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
