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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('payment_account_id')->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable()->after('customer_email');
            $table->enum('type', ['payment', 'refund', 'upgrade', 'downgrade'])->default('payment')->after('customer_phone');
            $table->boolean('is_renewal')->default(false)->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn(['subscription_id', 'customer_name', 'type', 'is_renewal']);
        });
    }
};
