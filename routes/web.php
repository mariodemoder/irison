<?php

use Illuminate\Support\Facades\Route;
/*
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Hash; 

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/dashboard', [DashboardController::class]);
});

require __DIR__.'/auth.php';

Route::get('/hash-test', function () { return Hash::make('HOLISholis123'); });

*/

use App\Http\Controllers\BillingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// Rutas públicas/auxiliares para billing (fake provider local)
Route::get('/billing/fake-success', [BillingController::class, 'fakeSuccess'])->name('billing.fake.success');
Route::get('/billing/thankyou', [BillingController::class, 'thankyou'])->name('billing.thankyou');

// Sandbox de maquetación: renderiza el Blade de factura en HTML con datos fake.
// Uso: /sandbox/invoice-blade?bg=1 (por defecto bg=1)
Route::get('/sandbox/invoice-blade', function (Request $request) {
    $user = $request->user();
    $clinic = $user?->clinic;

    $useBackground = $request->boolean('bg', true);
    $invoiceBackgroundDataUri = null;

    if ($useBackground && $clinic && !empty($clinic->invoice_background_path) && Storage::disk('public')->exists($clinic->invoice_background_path)) {
        $path = (string) $clinic->invoice_background_path;
        $content = Storage::disk('public')->get($path);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        $invoiceBackgroundDataUri = 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    $now = now();
    $patient = (object) [
        'name' => 'Paciente de ejemplo',
        'nif' => '12345678A',
        'email' => 'paciente.demo@example.com',
        'phone' => '600123123',
        'address' => 'Calle Demo 123',
        'zip' => '28001',
    ];

    $document = (object) [
        'id' => 0,
        'counter' => 'FR-000321',
        'type' => 'invoice',
        'typeinvoice' => 'appointment',
        'date' => $now,
        'created_at' => $now,
        'clinic_name' => $clinic?->name ?: 'Clinica Demo',
        'clinic_nif' => $clinic?->nif ?: 'B12345678',
        'clinic_address' => $clinic?->address ?: 'Calle Salud 42',
        'clinic_zip' => $clinic?->zip ?: '28001',
        'clinic_province' => $clinic?->province ?: 'Madrid',
        'clinic_country' => $clinic?->country ?: 'Espana',
        'user_full_name' => $user?->name ?: 'Profesional Demo',
        'status' => 'issued',
        'notes' => 'Sesion de fisioterapia y vendaje funcional.',
        'amount' => 60.00,
        'patient_full_name' => $patient->name,
        'patient_nif' => $patient->nif,
        'patient_email' => $patient->email,
        'patient_phone' => $patient->phone,
        'patient_address' => $patient->address,
        'patient_zip' => $patient->zip,
        'patient' => $patient,
    ];

    return response()
        ->view('pdf.document-invoice', [
            'document' => $document,
            'invoiceBackgroundDataUri' => $invoiceBackgroundDataUri,
        ])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
})->name('sandbox.invoice-blade');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');


