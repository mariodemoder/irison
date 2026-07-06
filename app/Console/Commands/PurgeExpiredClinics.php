<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Services\Backoffice\ClinicManagementService;
use Illuminate\Console\Command;

class PurgeExpiredClinics extends Command
{
    protected $signature = 'clinics:purge-expired
                            {--dry-run : Listar clínicas elegibles sin borrar datos}';

    protected $description = 'Elimina datos funcionales de clínicas cuyo periodo de gracia (trial+7 o canceled+7) ha expirado.';

    public function __construct(private readonly ClinicManagementService $clinicManagementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Clinic::whereNull('functional_data_deleted_at')
            ->whereIn('subscription_status', ['trial', 'canceled', 'cancelled'])
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
            $this->clinicManagementService->hardDeleteFunctionalData($clinic);
            $this->info("  ✓ Completado.");
        }

        $this->info("Purga finalizada. {$eligible->count()} clínica(s) procesadas.");

        return self::SUCCESS;
    }
}
