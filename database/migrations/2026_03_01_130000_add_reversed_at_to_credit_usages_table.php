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
            if (!Schema::hasColumn('credit_usages', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('reason');
                $table->index('reversed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('credit_usages') || !Schema::hasColumn('credit_usages', 'reversed_at')) {
            return;
        }

        Schema::table('credit_usages', function (Blueprint $table) {
            $table->dropIndex(['reversed_at']);
            $table->dropColumn('reversed_at');
        });
    }
};
