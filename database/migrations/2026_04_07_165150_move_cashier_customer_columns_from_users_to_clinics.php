<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            if (! Schema::hasColumn('clinics', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->index();
            }

            if (! Schema::hasColumn('clinics', 'pm_type')) {
                $table->string('pm_type')->nullable();
            }

            if (! Schema::hasColumn('clinics', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable();
            }

            if (! Schema::hasColumn('clinics', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable();
            }
        });

    }

    public function down(): void
    {
        $clinicColumnsToDrop = [];
        foreach (['stripe_id', 'pm_type', 'pm_last_four'] as $column) {
            if (Schema::hasColumn('clinics', $column)) {
                $clinicColumnsToDrop[] = $column;
            }
        }

        if (! empty($clinicColumnsToDrop)) {
            Schema::table('clinics', function (Blueprint $table) use ($clinicColumnsToDrop) {
                $table->dropColumn($clinicColumnsToDrop);
            });
        }
    }
};
