<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropUnique('patients_nif_unique');
            });
        } catch (\Throwable $e) {
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE `patients` DROP INDEX `patients_nif_unique`');
                } elseif (DB::getDriverName() === 'pgsql') {
                    DB::statement('ALTER TABLE patients DROP CONSTRAINT IF EXISTS patients_nif_unique');
                }
            } catch (\Throwable $e) {
                // Silenciar: índice puede no existir o nombre distinto
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Restaurar índice único simple sobre nif (nombre: patients_nif_unique)
            try {
                $table->unique('nif');
            } catch (\Throwable $e) {
                // Silenciar errores en caso de que ya exista
            }
        });
    }
};
