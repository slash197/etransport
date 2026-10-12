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
    /** Rules the reference never reaches: an earlier rule in ANAF's file claims the same nodes. */
    private const UNREACHABLE = ['BR-003', 'BR-209'];

    /** @var array<int, Schema> */
    private static array $schemas = [];

    /**
     * @param list<array{rule: string, location: string, message: string}>|string $expected
     */
    #[DataProvider('cases')]
    public function testCaseMatchesTheReferenceProcessor(string $name, string $xml, array|string $expected): void
    {
        $actual = $this->failures($xml, unreachable: false);

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
        $seen = [...array_keys($seen), ...self::UNREACHABLE];
        sort($seen);

        self::assertCount(61, $rules);
        self::assertSame($rules, $seen);
    }

    public function testUnreachableRulesAreLeftOutOnRequest(): void
    {
        foreach (Cases::all() as $xml) {
            $rules = array_column($this->failures($xml, unreachable: false), 'rule');

            self::assertSame([], array_intersect(self::UNREACHABLE, $rules));
        }
    }

    public function testUnreachableRulesAreAppliedOnTopOfTheReference(): void
    {
        $seen = [];
        foreach (Cases::all() as $xml) {
            $extra = [];
            $rest = [];
            foreach ($this->failures($xml) as $failure) {
                if (in_array($failure['rule'], self::UNREACHABLE, true)) {
                    $extra[] = $failure;
                } else {
                    $rest[] = $failure;
                }
            }

            self::assertSame($this->failures($xml, unreachable: false), $rest);
            $seen = [...$seen, ...array_column($extra, 'rule')];
        }

        self::assertEqualsCanonicalizing(self::UNREACHABLE, array_values(array_unique($seen)));
    }

    public function testAnUnreachableRuleReportsItsOwnMessage(): void
    {
        $failures = $this->failures(Cases::all()['BR-019 forbidden letters with right check digits']);

        self::assertSame([[
            'rule' => 'BR-003',
            'location' => '/eTransport/stergere/@uit',
            'message' => 'Identificatorul unic al transportului TREBUIE sa aibe un format valid (uit = OOOOOOOOOOOOOO06).',
        ]], $failures);
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
    private function failures(string $xml, bool $unreachable = true): array
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        $failures = [];
        foreach (self::schema($unreachable)->validate($document) as $violation) {
            $failures[] = [
                'rule' => (string) $violation->rule,
                'location' => (string) $violation->location,
                'message' => $violation->message,
            ];
        }
        usort($failures, static fn (array $a, array $b): int => [$a['rule'], $a['location']] <=> [$b['rule'], $b['location']]);

        return $failures;
    }

    private static function schema(bool $unreachable = true): Schema
    {
        return self::$schemas[(int) $unreachable] ??= Schema::fromFile(Validator::RULES, $unreachable);
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
