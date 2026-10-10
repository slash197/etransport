<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use DOMDocument;
use PHPUnit\Framework\TestCase;

final class ResourcesTest extends TestCase
{
    private const DIR = __DIR__ . '/../../resources/';

    public function testTheSchemaIsShippedUnmodified(): void
    {
        $file = self::DIR . 'schema_ETR_v2_20230126.xsd';

        self::assertSame('f4aa929c349197b5bd5040081483135e10a08c3c8a45a80c76bf23a60c2f8db5', hash_file('sha256', $file));
        self::assertSame('mfp:anaf:dgti:eTransport:declaratie:v2', $this->load($file)->documentElement?->getAttribute('targetNamespace'));
    }

    public function testTheRulesFileIsShippedUnmodified(): void
    {
        $file = self::DIR . 'eTransport-validation_v.2.0.2_12082024.sch';

        self::assertSame('c0e5e499c35759a62d073deb68936c4c2f4b259a05fe88920f0f19befe760a88', hash_file('sha256', $file));
        self::assertSame('xslt2', $this->load($file)->documentElement?->getAttribute('queryBinding'));
    }

    private function load(string $file): DOMDocument
    {
        $document = new DOMDocument();
        self::assertTrue($document->load($file));

        return $document;
    }
}
