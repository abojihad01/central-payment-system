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
            // Drop the foreign key first
            $table->dropForeign(['payment_id']);
            
            // Make the column nullable
            $table->unsignedBigInteger('payment_id')->nullable()->change();
            
            // Re-add the foreign key constraint but nullable
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop the foreign key
            $table->dropForeign(['payment_id']);
            
            // Make the column not nullable
            $table->unsignedBigInteger('payment_id')->nullable(false)->change();
            
            // Re-add the original foreign key constraint
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });
    }
};