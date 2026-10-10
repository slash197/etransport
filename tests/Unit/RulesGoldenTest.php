<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use slash197\eTransport\Tests\Support\Cases;
use slash197\eTransport\Validation\Schematron\Schema;
use slash197\eTransport\Validation\Validator;

/**
 * Holds the PHP evaluation of ANAF's rules to what a reference Schematron
 * processor reports for the same documents: the same rules, at the same
 * nodes, with the same messages. tests/golden/rules.json is that record.
 */
final class RulesGoldenTest extends TestCase
{
    /** Rules that can never be reached: an earlier rule in ANAF's file claims the same nodes. */
    private const SHADOWED = ['BR-003', 'BR-209'];

    private static ?Schema $schema = null;

    /**
     * @param list<array{rule: string, location: string, message: string}>|string $expected
     */
    #[DataProvider('cases')]
    public function testCaseMatchesTheReferenceProcessor(string $name, string $xml, array|string $expected): void
    {
        $actual = $this->failures($xml);

        if (is_string($expected)) {
            self::assertArrayHasKey($name, Cases::whereTheReferenceStops());
            self::assertSame(Cases::whereTheReferenceStops()[$name], array_column($actual, 'rule'));

            return;
        }

        self::assertSame($expected, $actual);
    }

    public function testEveryCaseIsRecorded(): void
    {
        self::assertSame(
            array_keys(Cases::all()),
            array_keys(self::golden()),
            'tests/golden/rules.json is out of date; run make golden.'
        );
    }

    public function testEveryRuleIsSeenFailing(): void
    {
        $seen = [];
        foreach (self::golden() as $failures) {
            foreach (is_array($failures) ? $failures : [] as $failure) {
                $seen[$failure['rule']] = true;
            }
        }

        $rules = self::schema()->assertionIds();
        sort($rules);
        $seen = [...array_keys($seen), ...self::SHADOWED];
        sort($seen);

        self::assertCount(61, $rules);
        self::assertSame($rules, $seen);
    }

    public function testShadowedRulesAreNeverReported(): void
    {
        foreach (Cases::all() as $xml) {
            self::assertSame([], array_intersect(self::SHADOWED, array_column($this->failures($xml), 'rule')));
        }
    }

    /**
     * @return iterable<string, array{string, string, list<array{rule: string, location: string, message: string}>|string}>
     */
    public static function cases(): iterable
    {
        $golden = self::golden();
        foreach (Cases::all() as $name => $xml) {
            yield $name => [$name, $xml, $golden[$name] ?? []];
        }
    }

    /**
     * @return list<array{rule: string, location: string, message: string}>
     */
    private function failures(string $xml): array
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        $failures = [];
        foreach (self::schema()->validate($document) as $violation) {
            $failures[] = [
                'rule' => (string) $violation->rule,
                'location' => (string) $violation->location,
                'message' => $violation->message,
            ];
        }
        usort($failures, static fn (array $a, array $b): int => [$a['rule'], $a['location']] <=> [$b['rule'], $b['location']]);

        return $failures;
    }

    private static function schema(): Schema
    {
        return self::$schema ??= Schema::fromFile(Validator::RULES);
    }

    /**
     * @return array<string, list<array{rule: string, location: string, message: string}>|string>
     */
    private static function golden(): array
    {
        /** @var array<string, list<array{rule: string, location: string, message: string}>|string> */
        return json_decode((string) file_get_contents(__DIR__ . '/../golden/rules.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
