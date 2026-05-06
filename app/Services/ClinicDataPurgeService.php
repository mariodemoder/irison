<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClinicDataPurgeService
{
    /**
     * Elimina todos los datos operativos de la clínica manteniendo:
     * clinics, subscriptions, billing_payments.
     *
     * El borrado se realiza en orden inverso de dependencia de FK.
     */
    public function purge(Clinic $clinic): void
    {
        $clinicId = $clinic->id;

        Log::info("[PurgeClinicData] Iniciando purga de clinic_id={$clinicId} ({$clinic->name})");

        DB::transaction(function () use ($clinicId): void {
            // 1. Hijos de documents (sin clinic_id directo)
            DB::table('document_items')
                ->whereIn('document_id', function ($q) use ($clinicId): void {
                    $q->select('id')->from('documents')->where('clinic_id', $clinicId);
                })
                ->delete();

            // 2. Hijos de patients sin clinic_id directo
            $patientIds = DB::table('patients')
                ->where('clinic_id', $clinicId)
                ->pluck('id');

            if ($patientIds->isNotEmpty()) {
                DB::table('patient_images')
                    ->whereIn('patient_id', $patientIds)
                    ->delete();
            }

            // 3. Tablas con clinic_id (orden: children primero)
            DB::table('credit_usages')->where('clinic_id', $clinicId)->delete();
            DB::table('bonus_usages')->where('clinic_id', $clinicId)->delete();
            DB::table('reminders')->where('clinic_id', $clinicId)->delete();
            DB::table('clinical_records')->where('clinic_id', $clinicId)->delete();
            DB::table('payments')->where('clinic_id', $clinicId)->delete();
            DB::table('documents')->where('clinic_id', $clinicId)->delete();
            DB::table('appointments')->where('clinic_id', $clinicId)->delete();
            DB::table('bonuses')->where('clinic_id', $clinicId)->delete();
            DB::table('patients')->where('clinic_id', $clinicId)->delete();
            DB::table('appointment_types')->where('clinic_id', $clinicId)->delete();
            DB::table('bonus_types')->where('clinic_id', $clinicId)->delete();
            DB::table('products')->where('clinic_id', $clinicId)->delete();
            DB::table('counters_clinics')->where('clinic_id', $clinicId)->delete();

            // 4. Tokens de acceso personal de los usuarios de la clínica
            $userIds = DB::table('users')
                ->where('clinic_id', $clinicId)
                ->pluck('id');

            if ($userIds->isNotEmpty()) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();
            }

            // 5. Usuarios de la clínica
            DB::table('users')->where('clinic_id', $clinicId)->delete();
        });

        // Marcar la clínica como eliminada (soft delete)
        $clinic->delete();

        Log::info("[PurgeClinicData] Purga completada para clinic_id={$clinicId}");
    }
}
