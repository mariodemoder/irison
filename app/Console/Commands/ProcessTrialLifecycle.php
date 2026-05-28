<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Trials\TrialLifecycleService;
use Illuminate\Console\Command;

class ProcessTrialLifecycle extends Command
{
    protected $signature = 'trials:process';

    protected $description = 'Procesa hitos automáticos de trial: emails, read-only y churn';

    public function handle(TrialLifecycleService $service): int
    {
        $stats = $service->process();

        $this->info(sprintf(
            'Trials procesados=%d, emails=%d, read_only=%d, churn=%d',
            (int) ($stats['processed'] ?? 0),
            (int) ($stats['emails_sent'] ?? 0),
            (int) ($stats['read_only_activated'] ?? 0),
            (int) ($stats['churn_marked'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
