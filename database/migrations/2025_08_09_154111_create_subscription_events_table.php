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
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            
            // Event information
            $table->enum('event_type', [
                'created',
                'activated',
                'trial_started',
                'trial_ended',
                'payment_succeeded',
                'payment_failed',
                'billing_cycle_completed',
                'plan_upgraded',
                'plan_downgraded',
                'cancelled',
                'reactivated',
                'paused',
                'resumed',
                'expired',
                'grace_period_started',
                'grace_period_ended'
            ]);
            
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Store additional event data
            
            // Related objects
            $table->string('related_payment_id')->nullable();
            $table->string('related_plan_id')->nullable();
            
            // Event source
            $table->enum('event_source', ['system', 'webhook', 'admin', 'customer'])->default('system');
            $table->string('triggered_by')->nullable(); // User ID or system process
            
            $table->timestamps();
            
            // Indexes
            $table->index(['subscription_id', 'event_type']);
            $table->index(['event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};