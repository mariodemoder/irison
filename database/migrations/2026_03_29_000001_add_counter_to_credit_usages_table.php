<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_usages') && !Schema::hasColumn('credit_usages', 'counter')) {
            Schema::table('credit_usages', function (Blueprint $table) {
                $table->string('counter', 12)->nullable()->after('amount');
                $table->index(['clinic_id', 'counter']);
            });
        }

        // Ampliar el enum de counters_clinics para incluir credit_usages
        if (Schema::hasTable('counters_clinics')) {
            DB::statement("ALTER TABLE counters_clinics MODIFY COLUMN table_type ENUM('documents', 'payout', 'bonuses', 'payments', 'credit_usages')");
        }
    }

    public function down(): void
    {
        // Clean up any credit_usages counters before reverting the enum
        if (Schema::hasTable('counters_clinics')) {
            DB::table('counters_clinics')->where('table_type', 'credit_usages')->delete();
        }

        if (Schema::hasTable('counters_clinics')) {
            DB::statement("ALTER TABLE counters_clinics MODIFY COLUMN table_type ENUM('documents', 'payout', 'bonuses', 'payments')");
        }

        if (Schema::hasTable('credit_usages') && Schema::hasColumn('credit_usages', 'counter')) {
            Schema::table('credit_usages', function (Blueprint $table) {
                $table->dropIndex(['clinic_id', 'counter']);
                $table->dropColumn('counter');
            });
        }
    }
};
