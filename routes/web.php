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
use App\Http\Controllers\Backoffice\AdminUserController;
use App\Http\Controllers\Backoffice\ClinicController;
use App\Http\Controllers\Backoffice\Auth\LoginController as BackofficeLoginController;
use App\Http\Controllers\Backoffice\DashboardController as BackofficeDashboardController;
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

$backofficeDomain = trim((string) env('BACKOFFICE_DOMAIN', ''));
$backofficeRoutes = Route::middleware('web');

if ($backofficeDomain !== '' && ! app()->environment('testing')) {
    $backofficeRoutes = $backofficeRoutes->domain($backofficeDomain);
} else {
    $backofficeRoutes = $backofficeRoutes->prefix('backoffice');
}

$backofficeRoutes->as('backoffice.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [BackofficeLoginController::class, 'create'])->name('login');
        Route::post('/login', [BackofficeLoginController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth:admin', 'admin.active'])->group(function () {
        Route::get('/', BackofficeDashboardController::class)->name('dashboard');
        Route::get('/dashboard', BackofficeDashboardController::class);
        Route::post('/logout', [BackofficeLoginController::class, 'destroy'])->name('logout');

        Route::middleware('admin.role:super_admin,support,billing,readonly')->group(function () {
            Route::get('/clinics', [ClinicController::class, 'index'])->name('clinics.index');
            Route::get('/clinics/{clinic}', [ClinicController::class, 'show'])->name('clinics.show');
        });

        Route::middleware('admin.role:super_admin,support')->group(function () {
            Route::get('/clinics/{clinic}/edit', [ClinicController::class, 'edit'])->name('clinics.edit');
            Route::put('/clinics/{clinic}', [ClinicController::class, 'update'])->name('clinics.update');
            Route::patch('/clinics/{clinic}/extend-trial', [ClinicController::class, 'extendTrial'])->name('clinics.extend-trial');
            Route::patch('/clinics/{clinic}/suspend', [ClinicController::class, 'suspend'])->name('clinics.suspend');
            Route::patch('/clinics/{clinic}/reactivate', [ClinicController::class, 'reactivate'])->name('clinics.reactivate');
        });

        Route::middleware('admin.role:super_admin,billing')->group(function () {
            Route::post('/clinics/{clinic}/cancel-subscription', [ClinicController::class, 'cancelSubscription'])->name('clinics.cancel-subscription');
            Route::patch('/clinics/{clinic}/change-plan', [ClinicController::class, 'changePlan'])->name('clinics.change-plan');
        });

        Route::middleware('admin.role:super_admin')->group(function () {
            Route::post('/clinics/{clinic}/impersonate', [ClinicController::class, 'impersonate'])->name('clinics.impersonate');
            Route::post('/impersonate/stop', [ClinicController::class, 'stopImpersonation'])->name('impersonate.stop');
        });

        Route::middleware('admin.role:super_admin')->group(function () {
            Route::get('/admin-users', [AdminUserController::class, 'index'])->name('admin-users.index');
            Route::get('/admin-users/create', [AdminUserController::class, 'create'])->name('admin-users.create');
            Route::post('/admin-users', [AdminUserController::class, 'store'])->name('admin-users.store');
            Route::get('/admin-users/{adminUser}/edit', [AdminUserController::class, 'edit'])->name('admin-users.edit');
            Route::put('/admin-users/{adminUser}', [AdminUserController::class, 'update'])->name('admin-users.update');
            Route::patch('/admin-users/{adminUser}/toggle', [AdminUserController::class, 'toggleActive'])->name('admin-users.toggle');
        });
    });
});

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');


