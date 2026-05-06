<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('credit_usages')) {
            return;
        }

        Schema::table('credit_usages', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_usages', 'reversed_reason')) {
                $table->string('reversed_reason')->nullable()->after('reversed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('credit_usages') || !Schema::hasColumn('credit_usages', 'reversed_reason')) {
            return;
        }

        Schema::table('credit_usages', function (Blueprint $table) {
            $table->dropColumn('reversed_reason');
        });
    }
};
