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
            // Drop the unique constraint first
            $table->dropUnique(['gateway_payment_id']);
            
            // Modify the column to be nullable
            $table->string('gateway_payment_id')->nullable()->change();
            
            // Add back the unique constraint but allow nulls (ignoring null values)
            $table->index('gateway_payment_id', 'payments_gateway_payment_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['gateway_payment_id']);
            
            // Revert to not nullable
            $table->string('gateway_payment_id')->nullable(false)->change();
            
            // Add back the unique constraint
            $table->unique('gateway_payment_id');
        });
    }
};
