<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\TestCase;
use slash197\eTransport\Environment;

final class EnvironmentTest extends TestCase
{
    public function testEachEnvironmentHasItsOwnApiUrl(): void
    {
        self::assertSame('https://api.anaf.ro/test/ETRANSPORT/ws/v1', Environment::Test->apiUrl());
        self::assertSame('https://api.anaf.ro/prod/ETRANSPORT/ws/v1', Environment::Production->apiUrl());
    }
}
