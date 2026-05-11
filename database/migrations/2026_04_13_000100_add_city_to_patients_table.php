<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patients') || Schema::hasColumn('patients', 'city')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->string('city', 120)->nullable()->after('zip');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('patients') || !Schema::hasColumn('patients', 'city')) {
            return;
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};
