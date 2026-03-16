<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'province')) {
                $table->string('province', 120)->nullable()->after('zip');
            }

            if (!Schema::hasColumn('patients', 'country')) {
                $table->string('country', 120)->nullable()->after('province');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('patients')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('patients', 'province')) {
                $dropColumns[] = 'province';
            }

            if (Schema::hasColumn('patients', 'country')) {
                $dropColumns[] = 'country';
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};