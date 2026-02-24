<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'pack_id')) {
            return;
        }

        if (Schema::hasColumn('payments', 'package_id')) {
            DB::statement('UPDATE payments SET package_id = pack_id WHERE package_id IS NULL AND pack_id IS NOT NULL');
        }

        if (DB::getDriverName() === 'mysql') {
            $foreignKeyName = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->select('CONSTRAINT_NAME')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'payments')
                ->where('COLUMN_NAME', 'pack_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->value('CONSTRAINT_NAME');

            if ($foreignKeyName) {
                DB::statement(sprintf('ALTER TABLE payments DROP FOREIGN KEY %s', $foreignKeyName));
            }
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('pack_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments') || Schema::hasColumn('payments', 'pack_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('pack_id')->nullable()->after('appointment_id')->constrained('packs')->nullOnDelete();
        });

        if (Schema::hasColumn('payments', 'package_id')) {
            DB::statement('UPDATE payments SET pack_id = package_id WHERE pack_id IS NULL AND package_id IS NOT NULL');
        }
    }
};
