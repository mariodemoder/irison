<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus_types', function (Blueprint $table) {
            if (!Schema::hasColumn('bonus_types', 'expires_at')) {
                $table->date('expires_at')->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bonus_types', function (Blueprint $table) {
            if (Schema::hasColumn('bonus_types', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
