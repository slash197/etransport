<?php

declare(strict_types=1);

namespace slash197\eTransport;

enum Environment: string
{
    case Test = 'test';
    case Production = 'production';

    public function apiUrl(): string
    {
        return match ($this) {
            self::Test => 'https://api.anaf.ro/test/ETRANSPORT/ws/v1',
            self::Production => 'https://api.anaf.ro/prod/ETRANSPORT/ws/v1',
        };
    }
}
