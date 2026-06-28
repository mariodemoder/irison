<?php

use App\Jobs\SendAppointmentReminder24h;
use App\Jobs\SendAppointmentReminder2h;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Stripe\StripeClient;

Artisan::command('user:verify {email : Email del usuario a verificar}', function () {
    $email = $this->argument('email');
    $user = \App\Models\User::where('email', $email)->first();

    if (! $user) {
        $this->error("No se encontró un usuario con email: {$email}");

        return self::FAILURE;
    }

    if ($user->email_verified_at) {
        $this->warn("El usuario {$email} ya estaba verificado desde {$user->email_verified_at}.");

        return self::SUCCESS;
    }

    $user->forceFill(['email_verified_at' => now()])->save();
    $this->info("Usuario {$email} verificado correctamente.");

    return self::SUCCESS;
})->purpose('Verificar manualmente el email de un usuario');

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

Artisan::command('stripe:plan:create
    {--name=SaaS Fisio : Nombre del producto en Stripe}
    {--amount=2900 : Precio en centimos (29 EUR = 2900)}
    {--currency=eur : Moneda ISO, ej. eur}
    {--interval=month : Intervalo de cobro (day|week|month|year)}
    {--save-env : Guarda STRIPE_PRICE_ID en .env}', function (): int {
    $secret = (string) config('services.stripe.secret');

    if ($secret === '' || str_contains($secret, 'xxx')) {
        $this->error('STRIPE_SECRET no esta configurado con una clave real.');
        $this->line('Configura STRIPE_SECRET test real y vuelve a ejecutar este comando.');
        return self::FAILURE;
    }

    $name = (string) $this->option('name');
    $amount = (int) $this->option('amount');
    $currency = strtolower((string) $this->option('currency'));
    $interval = strtolower((string) $this->option('interval'));

    if ($amount <= 0) {
        $this->error('El monto debe ser mayor a 0 centimos.');
        return self::FAILURE;
    }

    if (! in_array($interval, ['day', 'week', 'month', 'year'], true)) {
        $this->error('Intervalo invalido. Usa: day, week, month o year.');
        return self::FAILURE;
    }

    try {
        $stripe = new StripeClient($secret);

        $product = $stripe->products->create([
            'name' => $name,
        ]);

        $price = $stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $amount,
            'currency' => $currency,
            'recurring' => ['interval' => $interval],
        ]);
    } catch (\Throwable $e) {
        $this->error('No se pudo crear producto/precio en Stripe: ' . $e->getMessage());
        return self::FAILURE;
    }

    $this->info('Producto creado: ' . $product->id);
    $this->info('Price creado: ' . $price->id);

    if ((bool) $this->option('save-env')) {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            $this->warn('No existe archivo .env para guardar STRIPE_PRICE_ID.');
            return self::SUCCESS;
        }

        $env = File::get($envPath);
        if (preg_match('/^STRIPE_PRICE_ID=.*/m', $env) === 1) {
            $env = (string) preg_replace('/^STRIPE_PRICE_ID=.*/m', 'STRIPE_PRICE_ID=' . $price->id, $env);
        } else {
            $env .= PHP_EOL . 'STRIPE_PRICE_ID=' . $price->id . PHP_EOL;
        }

        File::put($envPath, $env);
        $this->info('STRIPE_PRICE_ID guardado en .env');
    }

    return self::SUCCESS;
})->purpose('Create Stripe product+recurring price and optionally save STRIPE_PRICE_ID in .env');
