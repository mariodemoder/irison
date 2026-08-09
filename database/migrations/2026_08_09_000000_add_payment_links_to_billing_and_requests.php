<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_payments', 'invoice_url')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                $table->text('invoice_url')->nullable();
            });
        }

        if (! Schema::hasColumn('billing_payments', 'receipt_url')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                $table->text('receipt_url')->nullable();
            });
        }

        if (! Schema::hasColumn('subscription_requests', 'invoice_url')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->text('invoice_url')->nullable();
            });
        }

        if (! Schema::hasColumn('subscription_requests', 'receipt_url')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->text('receipt_url')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('billing_payments', 'invoice_url')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                $table->dropColumn('invoice_url');
            });
        }

        if (Schema::hasColumn('billing_payments', 'receipt_url')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                $table->dropColumn('receipt_url');
            });
        }

        if (Schema::hasColumn('subscription_requests', 'invoice_url')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->dropColumn('invoice_url');
            });
        }

        if (Schema::hasColumn('subscription_requests', 'receipt_url')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->dropColumn('receipt_url');
            });
        }
    }
};
