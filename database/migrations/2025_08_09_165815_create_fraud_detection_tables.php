<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Risk Profiles table
        Schema::create('risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('ip_address')->nullable()->index();
            $table->string('country_code', 2)->nullable()->index();
            $table->integer('risk_score')->default(0); // 0-100, higher = riskier
            $table->enum('risk_level', ['low', 'medium', 'high', 'blocked'])->default('low');
            
            // Transaction statistics
            $table->integer('successful_payments')->default(0);
            $table->integer('failed_payments')->default(0);
            $table->integer('chargebacks')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            // Behavioral patterns
            $table->json('payment_patterns')->nullable(); // Times, amounts, frequencies
            $table->json('device_fingerprints')->nullable(); // Browser, OS, etc.
            $table->json('velocity_checks')->nullable(); // Recent activity
            
            // Status tracking
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('blocked_until')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['email', 'is_blocked']);
            $table->index(['ip_address', 'risk_level']);
            $table->index(['risk_score', 'risk_level']);
        });

        // Fraud Rules table
        Schema::create('fraud_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(50); // 1-100, higher = checked first
            
            // Rule configuration
            $table->json('conditions'); // Rule conditions (amount, country, etc.)
            $table->enum('action', ['allow', 'review', 'block']); // What to do when triggered
            $table->integer('risk_score_impact')->default(0); // How much to add/subtract from risk score
            
            // Statistics
            $table->integer('times_triggered')->default(0);
            $table->integer('false_positives')->default(0);
            $table->decimal('accuracy_rate', 5, 2)->default(0); // Percentage
            
            $table->timestamps();
            
            $table->index(['is_active', 'priority']);
        });

        // Fraud Alerts table
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_id')->unique();
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->string('email')->index();
            $table->string('ip_address')->nullable()->index();
            
            // Alert details
            $table->enum('alert_type', ['high_risk', 'velocity', 'pattern_anomaly', 'blacklist', 'manual_review']);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->integer('risk_score');
            $table->text('description');
            $table->json('triggered_rules'); // Which rules were triggered
            $table->json('metadata'); // Additional context data
            
            // Status
            $table->enum('status', ['pending', 'investigating', 'resolved', 'false_positive']);
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable(); // Admin user ID
            
            $table->timestamps();
            
            $table->index(['alert_type', 'status']);
            $table->index(['severity', 'created_at']);
        });

        // Blacklist table
        Schema::create('blacklists', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['email', 'ip', 'country', 'card_bin', 'phone']);
            $table->string('value')->index();
            $table->text('reason');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->string('added_by'); // Admin user ID or 'system'
            
            $table->timestamps();
            
            $table->unique(['type', 'value']);
            $table->index(['type', 'is_active']);
        });

        // Whitelist table
        Schema::create('whitelists', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['email', 'ip', 'country']);
            $table->string('value')->index();
            $table->text('reason');
            $table->boolean('is_active')->default(true);
            $table->string('added_by'); // Admin user ID or 'system'
            
            $table->timestamps();
            
            $table->unique(['type', 'value']);
            $table->index(['type', 'is_active']);
        });

        // Device Fingerprints table
        Schema::create('device_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint_hash')->unique();
            $table->json('fingerprint_data'); // Browser, screen, timezone, etc.
            $table->string('email')->nullable()->index();
            $table->string('ip_address')->nullable()->index();
            
            // Usage statistics
            $table->integer('usage_count')->default(1);
            $table->integer('successful_payments')->default(0);
            $table->integer('failed_payments')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            
            // Risk assessment
            $table->integer('risk_score')->default(0);
            $table->boolean('is_suspicious')->default(false);
            
            $table->timestamps();
            
            $table->index(['email', 'is_suspicious']);
            $table->index(['risk_score', 'usage_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_fingerprints');
        Schema::dropIfExists('whitelists');
        Schema::dropIfExists('blacklists');
        Schema::dropIfExists('fraud_alerts');
        Schema::dropIfExists('fraud_rules');
        Schema::dropIfExists('risk_profiles');
    }
};