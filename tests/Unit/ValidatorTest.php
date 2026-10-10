<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use slash197\eTransport\Exception\ETransportException;
use slash197\eTransport\Exception\UnsupportedRulesException;
use slash197\eTransport\Tests\Support\Cases;
use slash197\eTransport\Validation\Source;
use slash197\eTransport\Validation\Validator;

final class ValidatorTest extends TestCase
{
    #[DataProvider('validDocuments')]
    public function testAValidDocumentHasNoViolations(string $xml): void
    {
        $violations = (new Validator())->validate($xml);

        self::assertSame([], $violations->all());
        self::assertTrue($violations->isEmpty());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validDocuments(): iterable
    {
        foreach (Cases::all() as $name => $xml) {
            if (str_starts_with($name, 'valid ')) {
                yield $name => [$xml];
            }
        }
    }

    public function testSchemaAndRuleViolationsAreReportedTogether(): void
    {
        $violations = (new Validator())->validate(Cases::all()['BR-027 on both of two lines']);

        self::assertCount(4, $violations);
        self::assertSame(['BR-027'], $violations->rules());

        [$schema, , $rule, $second] = $violations->all();

        self::assertSame(Source::Schema, $schema->source);
        self::assertNull($schema->rule);
        self::assertSame(4, $schema->line);
        self::assertSame(
            "Element 'bunuriTransportate', attribute 'cantitate': [facet 'minExclusive'] The value '0' must be greater than '0'.",
            $schema->message
        );

        self::assertSame(Source::Rules, $rule->source);
        self::assertSame('BR-027', $rule->rule);
        self::assertSame('/eTransport/notificare/bunuriTransportate[1]/@cantitate', $rule->location);
        self::assertSame(4, $rule->line);
        self::assertSame('Cantitatea bunului transportat(BT-9) NU TREBUIE sa ia valori negative.', $rule->message);
        self::assertSame('/eTransport/notificare/bunuriTransportate[2]/@cantitate', $second->location);
    }

    public function testRuleMessagesCarryTheValuesAnafPutsInThem(): void
    {
        $violations = (new Validator())->validate(Cases::all()['BR-020 gross weight below net weight']);

        self::assertSame(
            'Greutatea bruta(BT-12) TREBUIE sa fie mai mare sau egala cu greutatea neta(BT-11) (greutateBruta = 100 < greutateNeta = 480.00 ).',
            $violations->all()[0]->message
        );
    }

    public function testAnotherKindOfDocumentFailsTheSchemaOnly(): void
    {
        $violations = (new Validator())->validate('<invoice/>');

        self::assertCount(1, $violations);
        self::assertSame(Source::Schema, $violations->all()[0]->source);
        self::assertSame([], $violations->rules());
    }

    public function testMalformedXmlIsReportedOnItsOwn(): void
    {
        $violations = (new Validator())->validate('<eTransport><notificare></eTransport>');

        self::assertFalse($violations->isEmpty());
        foreach ($violations as $violation) {
            self::assertSame(Source::Xml, $violation->source);
        }
        self::assertSame(1, $violations->all()[0]->line);
    }

    public function testAnEmptyDocumentIsReported(): void
    {
        $violations = (new Validator())->validate("  \n");

        self::assertCount(1, $violations);
        self::assertSame(Source::Xml, $violations->all()[0]->source);
    }

    public function testADocumentTypeDeclarationIsNotAccepted(): void
    {
        $xml = str_replace('<eTransport', '<!DOCTYPE eTransport [<!ENTITY e "10000001">]><eTransport', Cases::all()['valid deletion']);

        $violations = (new Validator())->validate($xml);

        self::assertCount(1, $violations);
        self::assertSame(Source::Xml, $violations->all()[0]->source);
    }

    public function testLibxmlErrorHandlingIsLeftAsItWas(): void
    {
        libxml_use_internal_errors(false);

        (new Validator())->validate('<broken');

        self::assertFalse(libxml_use_internal_errors(false));
        self::assertSame([], libxml_get_errors());
    }

    public function testARulesFileThatIsNotSupportedIsRefused(): void
    {
        $this->expectException(UnsupportedRulesException::class);

        (new Validator(rulesFile: Validator::SCHEMA))->validate(Cases::all()['valid deletion']);
    }

    public function testAMissingSchemaFileIsRefused(): void
    {
        $this->expectException(ETransportException::class);

        (new Validator(schemaFile: __DIR__ . '/missing.xsd'))->validate(Cases::all()['valid deletion']);
    }
}
