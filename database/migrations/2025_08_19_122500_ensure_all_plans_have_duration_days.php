<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Plan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing plans with NULL duration_days to have a default value
        $plansWithNullDuration = Plan::whereNull('duration_days')->get();
        
        foreach ($plansWithNullDuration as $plan) {
            // Default to 30 days (monthly) for most plans
            $defaultDuration = 30;
            
            // Special cases based on plan name or price
            if (str_contains(strtolower($plan->name), 'test')) {
                $defaultDuration = 7; // Test plans: weekly
            } elseif ($plan->price < 10) {
                $defaultDuration = 1; // Very cheap plans: daily
            } elseif ($plan->price > 500) {
                $defaultDuration = 365; // Expensive plans: yearly
            }
            
            $plan->update(['duration_days' => $defaultDuration]);
        }
        
        // Make duration_days NOT NULL with default value
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('duration_days')->default(30)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->integer('duration_days')->nullable()->change();
        });
    }
};