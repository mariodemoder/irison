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

        Schema::table('payments', function (Blueprint $table) {
            try {
                $table->dropConstrainedForeignId('pack_id');
            } catch (\Throwable $e) {
                $table->dropColumn('pack_id');
            }
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
