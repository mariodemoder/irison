<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments') || !Schema::hasColumn('appointments', 'price')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
