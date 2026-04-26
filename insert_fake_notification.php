<?php
require 'vendor/autoload.php';

use App\Models\Appointment;
use App\Models\Reminder;
use App\Models\Patient;
use App\Models\Clinic;

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Verificar que la clínica 18 existe
$clinic = Clinic::find(18);
if (!$clinic) {
    echo "❌ Clínica 18 no existe\n";
    exit(1);
}
echo "✅ Clínica encontrada: {$clinic->name}\n";

// Buscar un appointment existente de la clínica 18
$appointment = Appointment::where('clinic_id', 18)->first();

if (!$appointment) {
    echo "⚠️ No hay appointamentos para clínica 18. Creando uno falso...\n";
    
    // Crear un paciente falso
    $patient = Patient::create([
        'clinic_id' => 18,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'email' => 'juan.perez@example.com',
        'phone' => '1234567890',
    ]);
    echo "✅ Paciente creado: {$patient->first_name} {$patient->last_name}\n";
    
    // Crear un appointmento falso
    $appointment = Appointment::create([
        'clinic_id' => 18,
        'patient_id' => $patient->id,
        'appointment_type_id' => 1, // Ajusta según tus tipos de cita
        'scheduled_at' => now()->addHours(24),
        'status' => 'scheduled',
    ]);
    echo "✅ Appointamento creado para: {$appointment->scheduled_at}\n";
} else {
    echo "✅ Appointamento existente encontrado: {$appointment->patient->first_name} {$appointment->patient->last_name}\n";
}

// Crear la notificación fake
$reminder = Reminder::create([
    'clinic_id' => 18,
    'appointment_id' => $appointment->id,
    'channel' => 'email',
    'reminder_type' => '24h',
    'recipient_email' => $appointment->patient->email,
    'status' => 'sent',
    'sent_at' => now(),
    'error_message' => null,
]);

echo "✅ Notificación (Reminder) creada exitosamente!\n";
echo "   ID: {$reminder->id}\n";
echo "   Clínica: {$reminder->clinic_id}\n";
echo "   Paciente: {$appointment->patient->first_name} {$appointment->patient->last_name}\n";
echo "   Email: {$reminder->recipient_email}\n";
echo "   Tipo: {$reminder->reminder_type}\n";
echo "   Estado: {$reminder->status}\n";
