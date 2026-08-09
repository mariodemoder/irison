<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_payments', 'subscription_request_id')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                $table->foreignId('subscription_request_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('subscription_requests', 'upgrade_detail')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->json('upgrade_detail')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('billing_payments', 'subscription_request_id')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('subscription_request_id');
            });
        }

        if (Schema::hasColumn('subscription_requests', 'upgrade_detail')) {
            Schema::table('subscription_requests', function (Blueprint $table) {
                $table->dropColumn('upgrade_detail');
            });
        }
    }
};
