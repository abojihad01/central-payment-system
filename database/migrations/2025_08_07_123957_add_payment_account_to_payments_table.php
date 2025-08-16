<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->after('generated_link_id')->constrained()->nullOnDelete();
            $table->integer('retry_count')->default(0)->after('payment_account_id');
            $table->json('retry_log')->nullable()->after('retry_count');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_account_id']);
            $table->dropColumn(['payment_account_id', 'retry_count', 'retry_log']);
        });
    }
};