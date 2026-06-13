<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait CreatesApplication
{
    private function forceTestingEnvironment(): void
    {
        $forced = [
            'APP_ENV' => 'testing',
            'APP_LOCALE' => 'es',
            'APP_FALLBACK_LOCALE' => 'es',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        foreach ($forced as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $this->forceTestingEnvironment();

        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Force isolated DB for tests even if a cached config leaked local settings.
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $dbConnection = (string) config('database.default');
        if ($dbConnection !== 'sqlite') {
            throw new RuntimeException('Safety stop: tests must run on sqlite in-memory. Non-test database detected.');
        }

        return $app;
    }
}
