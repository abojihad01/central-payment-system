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
        Schema::table('plans', function (Blueprint $table) {
            // Subscription type: one_time, recurring
            $table->enum('subscription_type', ['one_time', 'recurring'])->default('one_time')->after('currency');
            
            // Billing interval: daily, weekly, monthly, quarterly, yearly
            $table->enum('billing_interval', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])->nullable()->after('subscription_type');
            
            // Billing interval count (e.g., every 2 months = monthly + 2)
            $table->integer('billing_interval_count')->default(1)->after('billing_interval');
            
            // Trial period in days
            $table->integer('trial_period_days')->nullable()->after('billing_interval_count');
            
            // Setup fee for first payment
            $table->decimal('setup_fee', 8, 2)->default(0)->after('trial_period_days');
            
            // Maximum billing cycles (null = unlimited)
            $table->integer('max_billing_cycles')->nullable()->after('setup_fee');
            
            // Whether to prorate when upgrading/downgrading
            $table->boolean('prorate_on_change')->default(true)->after('max_billing_cycles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_type',
                'billing_interval', 
                'billing_interval_count',
                'trial_period_days',
                'setup_fee',
                'max_billing_cycles',
                'prorate_on_change'
            ]);
        });
    }
};