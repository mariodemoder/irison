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
            if (!Schema::hasColumn('clinics', 'logo_path')) {
                $table->string('logo_path', 255)->nullable()->after('theme_color');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('clinics')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            if (Schema::hasColumn('clinics', 'logo_path')) {
                $table->dropColumn('logo_path');
            }
        });
    }
};
