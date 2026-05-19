<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus_types', function (Blueprint $table) {
            if (! Schema::hasColumn('bonus_types', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('bonuses', function (Blueprint $table) {
            if (! Schema::hasColumn('bonuses', 'bonus_type_id')) {
                $table->foreignId('bonus_type_id')->nullable()->after('patient_id')->constrained('bonus_types')->nullOnDelete();
                $table->index('bonus_type_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bonuses', function (Blueprint $table) {
            if (Schema::hasColumn('bonuses', 'bonus_type_id')) {
                $table->dropConstrainedForeignId('bonus_type_id');
            }
        });

        Schema::table('bonus_types', function (Blueprint $table) {
            if (Schema::hasColumn('bonus_types', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
