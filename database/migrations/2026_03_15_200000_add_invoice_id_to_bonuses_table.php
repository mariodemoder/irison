<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bonuses')) {
            return;
        }

        Schema::table('bonuses', function (Blueprint $table) {
            if (!Schema::hasColumn('bonuses', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('price')->constrained('documents')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bonuses')) {
            return;
        }

        Schema::table('bonuses', function (Blueprint $table) {
            if (Schema::hasColumn('bonuses', 'invoice_id')) {
                $table->dropConstrainedForeignId('invoice_id');
            }
        });
    }
};
