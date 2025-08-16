<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_selection_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'global', 'stripe', 'paypal', etc.
            $table->string('selection_strategy')->default('least_used'); // 'least_used', 'round_robin', 'weighted', 'manual'
            $table->json('strategy_config')->nullable(); // Configuration for each strategy
            $table->boolean('enable_fallback')->default(true);
            $table->integer('max_fallback_attempts')->default(3);
            $table->json('account_weights')->nullable(); // For weighted strategy
            $table->json('account_priorities')->nullable(); // Manual ordering
            $table->boolean('exclude_failed_accounts')->default(false);
            $table->integer('failed_account_cooldown_minutes')->default(60);
            $table->boolean('enable_load_balancing')->default(true);
            $table->decimal('max_account_load_percentage', 5, 2)->default(70.00); // Max 70% of transactions on one account
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_selection_configs');
    }
};