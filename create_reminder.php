<?php
require_once "vendor/autoload.php";

use App\Models\Clinic;
use App\Models\Appointment;
use App\Models\Reminder;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once "bootstrap/app.php";

// Obtener clinica
$clinic = Clinic::first();
if (!$clinic) {
    echo "Error: No hay clinicas disponibles.\n";
    exit(1);
}
echo "Clinica obtenida: ID = " . $clinic->id . "\n";

// Obtener o crear cita
$appointment = Appointment::first();
if (!$appointment && $clinic) {
    $appointment = Appointment::create([
        "clinic_id" => $clinic->id,
        "patient_id" => 1,
        "start_time" => Carbon::now(),
        "end_time" => Carbon::now()->addHour(),
        "status" => "scheduled"
    ]);
    echo "Cita creada: ID = " . $appointment->id . "\n";
} else {
    echo "Cita obtenida: ID = " . $appointment->id . "\n";
}

// Crear Reminder
$reminder = Reminder::create([
    "clinic_id" => $clinic->id,
    "appointment_id" => $appointment->id,
    "channel" => "email",
    "reminder_type" => "24h",
    "recipient_email" => "test@example.com",
    "status" => "sent",
    "sent_at" => Carbon::now(),
    "error_message" => null
]);

echo "\n=== REMINDER CREADO EXITOSAMENTE ===\n";
echo "ID del Reminder: " . $reminder->id . "\n";
echo "Clinic ID: " . $reminder->clinic_id . "\n";
echo "Appointment ID: " . $reminder->appointment_id . "\n";
echo "Channel: " . $reminder->channel . "\n";
echo "Reminder Type: " . $reminder->reminder_type . "\n";
echo "Recipient Email: " . $reminder->recipient_email . "\n";
echo "Status: " . $reminder->status . "\n";
echo "Sent At: " . $reminder->sent_at . "\n";
echo "Error Message: " . ($reminder->error_message ?? "null") . "\n";
?>
