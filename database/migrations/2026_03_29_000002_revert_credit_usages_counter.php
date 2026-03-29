<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delete any counters for 'credit_usages' before reverting the enum
        if (Schema::hasTable('counters_clinics')) {
            DB::table('counters_clinics')->where('table_type', 'credit_usages')->delete();
        }

        // Revert the enum to exclude 'credit_usages' (no longer auto-generating counters for credit_usages)
        if (Schema::hasTable('counters_clinics')) {
            DB::statement("ALTER TABLE counters_clinics MODIFY COLUMN table_type ENUM('documents', 'payout', 'bonuses', 'payments')");
        }

        // Keep the counter column in credit_usages (will be filled from related payment.counter)
    }

    public function down(): void
    {
        // Restore the enum to include 'credit_usages'
        if (Schema::hasTable('counters_clinics')) {
            DB::statement("ALTER TABLE counters_clinics MODIFY COLUMN table_type ENUM('documents', 'payout', 'bonuses', 'payments', 'credit_usages')");
        }
    }
};
