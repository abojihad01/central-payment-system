<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->unique();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            
            // Location information
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code')->nullable();
            
            // Customer status
            $table->enum('status', ['active', 'blocked', 'suspended', 'inactive'])->default('active');
            $table->integer('risk_score')->default(0);
            $table->enum('risk_level', ['low', 'medium', 'high', 'blocked'])->default('low');
            
            // Statistics
            $table->integer('total_subscriptions')->default(0);
            $table->integer('active_subscriptions')->default(0);
            $table->decimal('lifetime_value', 15, 2)->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->integer('successful_payments')->default(0);
            $table->integer('failed_payments')->default(0);
            $table->integer('chargebacks')->default(0);
            $table->integer('refunds')->default(0);
            
            // Behavioral data
            $table->json('preferences')->nullable();
            $table->json('payment_methods')->nullable(); // Preferred payment methods
            $table->json('subscription_history')->nullable();
            $table->json('tags')->nullable(); // Customer tags for segmentation
            
            // Activity tracking
            $table->timestamp('first_purchase_at')->nullable();
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('acquisition_source')->nullable(); // Where customer came from
            
            // Notes and communication
            $table->text('notes')->nullable(); // Internal notes
            $table->boolean('marketing_consent')->default(false);
            $table->boolean('email_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['email', 'status']);
            $table->index(['risk_score', 'status']);
            $table->index(['country_code', 'status']);
            $table->index(['lifetime_value', 'status']);
            $table->index(['last_purchase_at', 'status']);
        });

        // Customer Events table for tracking all customer interactions
        Schema::create('customer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // login, purchase, subscription_created, payment_failed, etc.
            $table->text('description');
            $table->json('metadata')->nullable(); // Additional event data
            $table->string('source')->nullable(); // web, api, admin, etc.
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            
            $table->timestamps();
            
            $table->index(['customer_id', 'event_type']);
            $table->index(['event_type', 'created_at']);
        });

        // Customer Segments table for marketing segmentation
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->json('criteria'); // Segmentation criteria
            $table->boolean('is_active')->default(true);
            $table->integer('customer_count')->default(0);
            $table->string('created_by'); // Admin user ID
            
            $table->timestamps();
        });

        // Customer Communications table
        Schema::create('customer_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['email', 'sms', 'push', 'in_app']);
            $table->string('subject')->nullable();
            $table->text('content');
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'bounced']);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->json('metadata')->nullable(); // Campaign info, etc.
            
            $table->timestamps();
            
            $table->index(['customer_id', 'type', 'status']);
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_communications');
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('customer_events');
        Schema::dropIfExists('customers');
    }
};