<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'professional_id')) {
                $table->foreignId('professional_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->index(['clinic_id', 'professional_id']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'professional_id')) {
                $table->dropIndex(['clinic_id', 'professional_id']);
                $table->dropConstrainedForeignId('professional_id');
            }
        });
    }
};
