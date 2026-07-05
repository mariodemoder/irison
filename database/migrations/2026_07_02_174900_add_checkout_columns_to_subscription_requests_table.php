<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subscription_requests', 'stripe_checkout_session_id')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->string('stripe_checkout_session_id')->nullable();
            });
        }

        if (! Schema::hasColumn('subscription_requests', 'checkout_url')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->text('checkout_url')->nullable();
            });
        }

        if (! Schema::hasColumn('subscription_requests', 'completed_at')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_requests', 'completed_at')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->dropColumn('completed_at');
            });
        }

        if (Schema::hasColumn('subscription_requests', 'checkout_url')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->dropColumn('checkout_url');
            });
        }

        if (Schema::hasColumn('subscription_requests', 'stripe_checkout_session_id')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->dropColumn('stripe_checkout_session_id');
            });
        }
    }
};
