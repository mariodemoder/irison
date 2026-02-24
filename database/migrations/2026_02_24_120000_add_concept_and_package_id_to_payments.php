<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'concept')) {
                $table->string('concept')->nullable()->after('patient_id');
                $table->index('concept');
            }

            if (!Schema::hasColumn('payments', 'package_id')) {
                $table->foreignId('package_id')->nullable()->after('appointment_id')->constrained('packs')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('payments', 'pack_id') && Schema::hasColumn('payments', 'package_id')) {
            DB::statement('UPDATE payments SET package_id = pack_id WHERE package_id IS NULL AND pack_id IS NOT NULL');
        }

        if (Schema::hasColumn('payments', 'concept')) {
            DB::statement("UPDATE payments SET concept = CASE WHEN package_id IS NOT NULL THEN 'package' WHEN appointment_id IS NOT NULL THEN 'appointment' ELSE 'credit' END WHERE concept IS NULL");

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE payments MODIFY concept VARCHAR(50) NOT NULL DEFAULT 'appointment'");
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'package_id')) {
                $table->dropConstrainedForeignId('package_id');
            }

            if (Schema::hasColumn('payments', 'concept')) {
                $table->dropIndex(['concept']);
                $table->dropColumn('concept');
            }
        });
    }
};
