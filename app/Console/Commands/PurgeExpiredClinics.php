<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Services\ClinicDataPurgeService;
use Illuminate\Console\Command;

class PurgeExpiredClinics extends Command
{
    protected $signature = 'clinics:purge-expired
                            {--dry-run : Listar clínicas elegibles sin borrar datos}';

    protected $description = 'Elimina datos operativos de clínicas cuyo periodo de gracia (trial+7 o canceled+7) ha expirado.';

    public function __construct(private readonly ClinicDataPurgeService $purgeService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Solo consideramos clínicas en estados que puedan haber expirado
        $candidates = Clinic::whereIn('subscription_status', ['trial', 'canceled', 'cancelled'])
            ->with('saasSubscriptions')
            ->get();

        $eligible = $candidates->filter(
            fn (Clinic $clinic) =>
                ! $clinic->isSubscribed() &&
                ! $clinic->isTrialActive() &&
                ! $clinic->isInReadOnlyNoTransactionsWindow()
        );

        if ($eligible->isEmpty()) {
            $this->info('No hay clínicas elegibles para purgar.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nombre', 'Estado', 'Trial ends at'],
            $eligible->map(fn (Clinic $c) => [
                $c->id,
                $c->name,
                $c->subscription_status,
                $c->trial_ends_at?->toDateString() ?? '-',
            ])
        );

        if ($dryRun) {
            $this->warn('Modo --dry-run: no se ha borrado ningún dato.');
            return self::SUCCESS;
        }

        foreach ($eligible as $clinic) {
            $this->line("Purgando clinic_id={$clinic->id} ({$clinic->name})...");
            $this->purgeService->purge($clinic);
            $this->info("  ✓ Completado.");
        }

        $this->info("Purga finalizada. {$eligible->count()} clínica(s) procesadas.");

        return self::SUCCESS;
    }
}
