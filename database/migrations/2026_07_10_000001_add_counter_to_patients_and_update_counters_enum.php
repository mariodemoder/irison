<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (!Schema::hasColumn('patients', 'counter')) {
                    $table->string('counter', 12)->nullable()->after('id');
                    $table->index(['clinic_id', 'counter']);
                }
            });
        }

        // Extend the enum in counters_clinics to include 'patients'
        if (Schema::hasTable('counters_clinics')) {
            DB::statement("ALTER TABLE counters_clinics MODIFY COLUMN table_type ENUM('documents','payout','bonuses','payments','patients') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patients') && Schema::hasColumn('patients', 'counter')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropIndex(['clinic_id', 'counter']);
                $table->dropColumn('counter');
            });
        }

        if (Schema::hasTable('counters_clinics')) {
            DB::statement("ALTER TABLE counters_clinics MODIFY COLUMN table_type ENUM('documents','payout','bonuses','payments') NOT NULL");
        }
    }
};
