<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_account_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_account_id')->constrained()->onDelete('cascade');
            $table->string('gateway_name');
            $table->string('selection_method'); // 'unused', 'least_used', 'manual', 'weighted', 'round_robin'
            $table->json('selection_criteria'); // Currency, country, etc.
            $table->json('available_accounts'); // All accounts that were considered
            $table->json('account_stats'); // Stats at time of selection
            $table->string('selection_reason'); // Why this account was chosen
            $table->integer('selection_priority'); // Priority order (1 = first choice)
            $table->boolean('was_fallback')->default(false); // If this was after a failure
            $table->string('previous_account_id')->nullable(); // If fallback, what failed
            $table->decimal('selection_time_ms', 8, 2)->nullable(); // How long selection took
            $table->timestamps();

            $table->index(['payment_id', 'payment_account_id']);
            $table->index(['gateway_name', 'selection_method']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_account_selections');
    }
};