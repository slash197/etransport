<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use slash197\eTransport\Data\Judet;
use slash197\eTransport\Data\ScopOperatiune;
use slash197\eTransport\Data\TipConfirmare;
use slash197\eTransport\Data\TipDocument;
use slash197\eTransport\Data\TipOperatiune;
use slash197\eTransport\Nomenclators;
use slash197\eTransport\Validation\Validator;

/**
 * Holds the code lists of this package to the ones in ANAF's files, so
 * that a new version of those files shows what has to change here.
 */
final class NomenclatorsTest extends TestCase
{
    /**
     * @param list<int|string> $codes
     */
    #[DataProvider('lists')]
    public function testAListHasTheCodesOfAnafsSchema(string $type, array $codes): void
    {
        $schema = new \DOMDocument();
        $schema->load(Validator::SCHEMA);
        $xpath = new \DOMXPath($schema);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        $expected = [];
        foreach ($xpath->query("//xs:simpleType[@name='{$type}']//xs:enumeration/@value") ?: [] as $value) {
            $expected[] = (string) $value->nodeValue;
        }

        // ANAF's schema lists "PS" twice.
        self::assertNotSame([], $expected);
        self::assertEqualsCanonicalizing(array_values(array_unique($expected)), array_map(strval(...), $codes));
    }

    /**
     * @return iterable<string, array{string, list<int|string>}>
     */
    public static function lists(): iterable
    {
        yield 'border crossing points' => ['CodPtfType', array_keys(Nomenclators::puncteTrecereFrontiera())];
        yield 'customs offices' => ['CodBirouVamalType', array_keys(Nomenclators::birouriVamale())];
        yield 'countries' => ['CodTaraType', array_keys(Nomenclators::tari())];
        yield 'counties' => ['CodJudetType', array_column(Judet::cases(), 'value')];
        yield 'operation types' => ['CodTipOperatiuneType', array_column(TipOperatiune::cases(), 'value')];
        yield 'operation purposes' => ['CodScopOperatiuneType', array_column(ScopOperatiune::cases(), 'value')];
        yield 'confirmation types' => ['TipConfirmareType', array_column(TipConfirmare::cases(), 'value')];
        yield 'document types' => ['TipDocumentType', array_column(TipDocument::cases(), 'value')];
    }

    public function testEuCountriesAreTheOnesOfAnafsRules(): void
    {
        $rules = (string) file_get_contents(Validator::RULES);
        preg_match('/name="ISO-3166-EU-CODES" value="\(([^)]*)\)"/', $rules, $match);

        self::assertEqualsCanonicalizing(explode(',', str_replace("'", '', $match[1] ?? '')), Nomenclators::tariUe());
        self::assertSame([], array_diff(Nomenclators::tariUe(), array_keys(Nomenclators::tari())));
    }

    public function testCodesHaveTheirNames(): void
    {
        self::assertSame('Borș (HU)', Nomenclators::puncteTrecereFrontiera()[2]);
        self::assertSame('BVI Alba Iulia (ROBV0300)', Nomenclators::birouriVamale()[12801]);
        self::assertSame('Grecia', Nomenclators::tari()['EL']);
        self::assertArrayNotHasKey('GR', Nomenclators::tari());

        foreach ([Nomenclators::puncteTrecereFrontiera(), Nomenclators::birouriVamale(), Nomenclators::tari()] as $list) {
            foreach ($list as $name) {
                self::assertNotSame('', trim($name));
            }
        }
    }
}
