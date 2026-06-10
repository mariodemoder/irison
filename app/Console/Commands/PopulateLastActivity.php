<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateLastActivity extends Command
{
    protected $signature = 'clinics:populate-last-activity
                            {--dry-run : Mostrar valores sin actualizar}';

    protected $description = 'Calcula e inicializa last_activity_at para cada clínica basado en appointments, pagos, documentos y logins.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $clinics = Clinic::withoutGlobalScopes()->get();

        $bar = $this->output->createProgressBar($clinics->count());
        $bar->start();

        $updated = 0;

        foreach ($clinics as $clinic) {
            $candidates = collect([
                DB::table('appointments')->where('clinic_id', $clinic->id)->max('created_at'),
                DB::table('payments')->where('clinic_id', $clinic->id)->max('paid_at'),
                DB::table('payments')->where('clinic_id', $clinic->id)->max('created_at'),
                DB::table('documents')->where('clinic_id', $clinic->id)->max('date'),
                DB::table('documents')->where('clinic_id', $clinic->id)->max('created_at'),
                DB::table('users')->where('clinic_id', $clinic->id)->max('last_login_at'),
                DB::table('backoffice_clinic_activities')->where('clinic_id', $clinic->id)->max('created_at'),
                DB::table('activity_logs')->where('tenant_id', $clinic->id)->max('created_at'),
            ])->filter();

            $lastActivity = $candidates
                ->map(static fn ($value) => $value instanceof Carbon ? $value : Carbon::parse((string) $value))
                ->sortDesc()
                ->first();

            if ($dryRun) {
                $this->line(" [{$clinic->id}] {$clinic->name} -> " . ($lastActivity?->toDateTimeString() ?? 'sin actividad'));
            } elseif ($lastActivity) {
                $clinic->forceFill(['last_activity_at' => $lastActivity])->saveQuietly();
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->warn('Modo --dry-run: no se actualizó ningún registro.');
        } else {
            $this->info("{$updated} clínica(s) actualizadas con last_activity_at.");
        }

        return self::SUCCESS;
    }
}
