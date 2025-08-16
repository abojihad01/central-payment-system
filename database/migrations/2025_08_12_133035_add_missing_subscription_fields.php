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
            $table->string('cancellation_type')->nullable()->after('cancellation_reason');
            $table->text('cancellation_notes')->nullable()->after('cancellation_type');
            $table->boolean('will_cancel_at_period_end')->default(false)->after('cancellation_notes');
            $table->integer('grace_period_days')->nullable()->after('grace_period_ends_at');
            $table->timestamp('resumed_at')->nullable()->after('paused_at');
            $table->integer('scheduled_plan_change')->nullable()->after('plan_changes_history');
            $table->string('plan_change_type')->nullable()->after('scheduled_plan_change');
            $table->timestamp('reactivated_at')->nullable()->after('plan_change_type');
            $table->timestamp('expired_at')->nullable()->after('reactivated_at');
            $table->timestamp('transferred_at')->nullable()->after('expired_at');
            $table->string('previous_customer_email')->nullable()->after('transferred_at');
            $table->string('transfer_reason')->nullable()->after('previous_customer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_type',
                'cancellation_notes',
                'will_cancel_at_period_end',
                'grace_period_days',
                'resumed_at',
                'scheduled_plan_change',
                'plan_change_type',
                'reactivated_at',
                'expired_at',
                'transferred_at',
                'previous_customer_email',
                'transfer_reason'
            ]);
        });
    }
};