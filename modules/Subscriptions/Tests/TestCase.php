<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Tests;

use Modules\Subscriptions\Tests\Concerns\InteractsWithSubscriptions;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithSubscriptions;

    protected function tearDown(): void
    {
        $this->unfreezeCarbon();

        parent::tearDown();
    }
}
