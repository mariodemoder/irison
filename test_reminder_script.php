<?php
require_once "vendor/autoload.php";
require_once "bootstrap/app.php";
//hey
use Illuminate\Contracts\Console\Kernel;
use App\Models\Clinic;
use App\Models\Appointment;
use App\Models\Reminder;
use Carbon\Carbon;

$app = require_once __DIR__ . '\''\'bootstrap/app.php'\'\'';
$kernel = $app->make(Kernel::class);

// Obtener clínica
$clinic = Clinic::first();
echo "Clínica obtenida: ID = " . ($clinic ? $clinic->id : "No encontrada") . "\n";

if (!$clinic) {
    echo "Error: No hay clínicas disponibles.\n";
    exit(1);
}

// Obtener o crear cita
$appointment = Appointment::first();
if (!$appointment) {
    echo "Creando nueva cita...\n";
    $appointment = Appointment::create([
        ''clinic_id'' => $clinic->id,
        ''patient_id'' => 1,
        ''start_time'' => Carbon::now(),
        ''end_time'' => Carbon::now()->addHour(),
        ''status'' => ''scheduled''
    ]);
}
echo "Cita obtenida: ID = " . $appointment->id . "\n";

// Crear Reminder
$reminder = Reminder::create([
    ''clinic_id'' => $clinic->id,
    ''appointment_id'' => $appointment->id,
    ''channel'' => ''email'',
    ''reminder_type'' => ''24h'',
    ''recipient_email'' => ''test@example.com'',
    ''status'' => ''sent'',
    ''sent_at'' => Carbon::now(),
    ''error_message'' => null
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
echo "\nJSON:\n" . json_encode($reminder->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
?>
