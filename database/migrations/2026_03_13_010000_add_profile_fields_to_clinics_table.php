<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clinics')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            if (!Schema::hasColumn('clinics', 'nif')) {
                $table->string('nif', 50)->nullable()->after('cif');
            }

            if (!Schema::hasColumn('clinics', 'locality')) {
                $table->string('locality', 120)->nullable()->after('address');
            }

            if (!Schema::hasColumn('clinics', 'province')) {
                $table->string('province', 120)->nullable()->after('locality');
            }

            if (!Schema::hasColumn('clinics', 'country')) {
                $table->string('country', 120)->nullable()->after('province');
            }

            if (!Schema::hasColumn('clinics', 'zip')) {
                $table->string('zip', 20)->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('clinics')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('clinics', 'nif')) {
                $dropColumns[] = 'nif';
            }

            if (Schema::hasColumn('clinics', 'locality')) {
                $dropColumns[] = 'locality';
            }

            if (Schema::hasColumn('clinics', 'province')) {
                $dropColumns[] = 'province';
            }

            if (Schema::hasColumn('clinics', 'country')) {
                $dropColumns[] = 'country';
            }

            if (Schema::hasColumn('clinics', 'zip')) {
                $dropColumns[] = 'zip';
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
