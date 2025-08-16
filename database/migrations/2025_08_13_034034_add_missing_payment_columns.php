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
            // Add missing columns that are referenced in tests and models
            // Check and add only if they don't exist
            if (!Schema::hasColumn('payments', 'original_payment_id')) {
                $table->foreignId('original_payment_id')->nullable()->after('subscription_id')->constrained('payments')->onDelete('set null');
            }
            if (!Schema::hasColumn('payments', 'plan_id')) {
                $table->foreignId('plan_id')->nullable()->after('payment_account_id')->constrained()->onDelete('set null');
            }
            if (!Schema::hasColumn('payments', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('paid_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['original_payment_id']);
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'original_payment_id',
                'plan_id', 
                'customer_name',
                'type',
                'is_renewal',
                'retry_count',
                'retry_log',
                'confirmed_at',
                'notes'
            ]);
        });
    }
};
