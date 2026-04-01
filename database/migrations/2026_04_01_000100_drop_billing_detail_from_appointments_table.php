<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('appointments', 'billing_detail')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('billing_detail');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointments', 'billing_detail')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->text('billing_detail')->nullable()->after('notes');
        });
    }
};
