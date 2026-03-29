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
            if (!Schema::hasColumn('clinics', 'invoice_background_path')) {
                $table->string('invoice_background_path', 255)->nullable()->after('zip');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('clinics')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            if (Schema::hasColumn('clinics', 'invoice_background_path')) {
                $table->dropColumn('invoice_background_path');
            }
        });
    }
};
