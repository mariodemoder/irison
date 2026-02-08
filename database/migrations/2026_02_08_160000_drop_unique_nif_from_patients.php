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
        // Intentamos eliminar el índice único sobre `nif` si existe.
        Schema::table('patients', function (Blueprint $table) {
            try {
                $table->dropUnique('patients_nif_unique');
            } catch (\Throwable $e) {
                // Fallback: intentar mediante sentencia SQL si el nombre difiere
                try {
                    DB::statement('ALTER TABLE `patients` DROP INDEX `patients_nif_unique`');
                } catch (\Throwable $e) {
                    // Silenciar: índice puede no existir o nombre distinto
                }
            }
        });
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
