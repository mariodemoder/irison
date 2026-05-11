`$clinic = \App\Models\Clinic::first();
`$clinic;
`$appointment = \App\Models\Appointment::first();
if (!`$appointment) {
    `$appointment = \App\Models\Appointment::create([
        'clinic_id' => `$clinic->id,
        'patient_id' => 1,
        'start_time' => now(),
        'end_time' => now()->addHour(),
        'status' => 'scheduled'
    ]);
}
`$appointment;
`$reminder = \App\Models\Reminder::create([
    'clinic_id' => `$clinic->id,
    'appointment_id' => `$appointment->id,
    'channel' => 'email',
    'reminder_type' => '24h',
    'recipient_email' => 'test@example.com',
    'status' => 'sent',
    'sent_at' => \Carbon\Carbon::now(),
    'error_message' => null
]);
`$reminder;
exit;
