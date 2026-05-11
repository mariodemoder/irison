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

        $driver = DB::getDriverName();

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }

            if (!Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('payments', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        DB::table('payments')->where('status', 'paid')->update(['status' => 'completed']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY amount DECIMAL(8,2) NOT NULL");
            DB::statement("ALTER TABLE payments MODIFY method VARCHAR(50) NOT NULL DEFAULT 'cash'");
            DB::statement("ALTER TABLE payments MODIFY status VARCHAR(50) NOT NULL DEFAULT 'completed'");
            DB::statement("UPDATE payments SET paid_at = COALESCE(created_at, NOW()) WHERE paid_at IS NULL");
            DB::statement("ALTER TABLE payments MODIFY paid_at TIMESTAMP NOT NULL");
            DB::statement("ALTER TABLE appointments MODIFY payment_status ENUM('pending','partially_paid','paid','covered_by_pack') NOT NULL DEFAULT 'pending'");
        } else {
            DB::statement("UPDATE payments SET paid_at = COALESCE(created_at, CURRENT_TIMESTAMP) WHERE paid_at IS NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE payments SET status = 'paid' WHERE status = 'completed'");
            DB::statement("ALTER TABLE payments MODIFY amount DECIMAL(10,2) NOT NULL");
            DB::statement("ALTER TABLE payments MODIFY method ENUM('cash','card','transfer') NOT NULL DEFAULT 'cash'");
            DB::statement("ALTER TABLE payments MODIFY status ENUM('paid','pending') NOT NULL DEFAULT 'paid'");
            DB::statement("ALTER TABLE appointments MODIFY payment_status ENUM('pending','paid','covered_by_pack') NOT NULL DEFAULT 'pending'");
        } else {
            DB::statement("UPDATE payments SET status = 'paid' WHERE status = 'completed'");
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            if (Schema::hasColumn('payments', 'notes')) {
                $table->dropColumn('notes');
            }

            if (Schema::hasColumn('payments', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
