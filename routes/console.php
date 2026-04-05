<?php

use App\Jobs\SendAppointmentReminder24h;
use App\Jobs\SendAppointmentReminder2h;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reminders:send-24h', function (): void {
    app(SendAppointmentReminder24h::class)->handle();
    $this->info('Recordatorios 24h procesados.');
})->purpose('Send 24-hour appointment reminders');

Artisan::command('reminders:send-2h', function (): void {
    app(SendAppointmentReminder2h::class)->handle();
    $this->info('Recordatorios 2h procesados.');
})->purpose('Send 2-hour appointment reminders');
