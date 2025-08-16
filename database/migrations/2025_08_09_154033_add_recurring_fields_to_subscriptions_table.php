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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Gateway subscription ID (for recurring payments)
            $table->string('gateway_subscription_id')->nullable()->after('subscription_id');
            
            // Billing cycle information
            $table->integer('billing_cycle_count')->default(0)->after('gateway_subscription_id');
            $table->timestamp('next_billing_date')->nullable()->after('billing_cycle_count');
            $table->timestamp('last_billing_date')->nullable()->after('next_billing_date');
            
            // Trial information
            $table->boolean('is_trial')->default(false)->after('last_billing_date');
            $table->timestamp('trial_ends_at')->nullable()->after('is_trial');
            
            // Cancellation information
            $table->timestamp('cancelled_at')->nullable()->after('trial_ends_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            $table->boolean('cancel_at_period_end')->default(false)->after('cancellation_reason');
            
            // Grace period for failed payments
            $table->timestamp('grace_period_ends_at')->nullable()->after('cancel_at_period_end');
            $table->integer('failed_payment_count')->default(0)->after('grace_period_ends_at');
            
            // Pause/Resume functionality
            $table->timestamp('paused_at')->nullable()->after('failed_payment_count');
            $table->string('pause_reason')->nullable()->after('paused_at');
            
            // Upgrade/Downgrade tracking
            $table->json('plan_changes_history')->nullable()->after('pause_reason');
            
            // Update status enum to include more states
            $table->dropColumn('status');
        });
        
        // Add new status enum with more states
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', [
                'trial', 
                'active', 
                'past_due', 
                'cancelled', 
                'expired',
                'paused',
                'pending_cancellation'
            ])->default('active')->after('plan_changes_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_subscription_id',
                'billing_cycle_count',
                'next_billing_date',
                'last_billing_date',
                'is_trial',
                'trial_ends_at',
                'cancelled_at',
                'cancellation_reason',
                'cancel_at_period_end',
                'grace_period_ends_at',
                'failed_payment_count',
                'paused_at',
                'pause_reason',
                'plan_changes_history',
                'status'
            ]);
            
            $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
        });
    }
};