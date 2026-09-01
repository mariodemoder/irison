<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración del Portal del Paciente, en tabla dedicada (patrón booking_pages).
     *
     * - is_active: interruptor maestro de la clínica. Si está a false nadie puede
     *   usar el portal (independiente del acceso opt-in por paciente).
     * - max_horizon_days: fecha máxima (días) que un paciente puede solicitar al
     *   pedir o reprogramar una cita desde el portal. Default 60.
     * - cancellation_hours: antelación mínima (horas) para cancelar o reprogramar
     *   una cita desde el portal. Reemplaza el 24h fijo anterior. Default 24.
     *
     * El slug de la clínica se sigue guardando en `clinics.slug`; esta tabla solo
     * guarda la configuración de política/horizonte/estado.
     */
    public function up(): void
    {
        Schema::create('patient_portal_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('max_horizon_days')->default(60);
            $table->unsignedSmallInteger('cancellation_hours')->default(24);
            $table->timestamps();
            $table->unique('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_portal_settings');
    }
};