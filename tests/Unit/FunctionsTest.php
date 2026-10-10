<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\TestCase;
use slash197\eTransport\Exception\UnsupportedRulesException;
use slash197\eTransport\Validation\Schematron\Functions;

final class FunctionsTest extends TestCase
{
    public function testMatchesSearchesAnywhereUnlessAnchored(): void
    {
        self::assertTrue(Functions::matches('xxAB12xx', '[0-9A-Z]{2,20}'));
        self::assertTrue(Functions::matches('1234', '^\d{4}$'));
        self::assertFalse(Functions::matches('12345', '^\d{4}$'));
    }

    public function testDollarDoesNotTolerateATrailingNewline(): void
    {
        self::assertFalse(Functions::matches("1234\n", '^\d{4}$'));
    }

    public function testDigitsAreUnicodeDigitsAsInXPath(): void
    {
        self::assertTrue(Functions::matches('١٢٣٤', '^\d{4}$'));
    }

    public function testWhitespaceIsOnlyTheFourXmlCharacters(): void
    {
        self::assertTrue(Functions::matches("a\tb", 'a\sb'));
        self::assertFalse(Functions::matches("a\u{00A0}b", 'a\sb'));
        self::assertFalse(Functions::matches("a\u{00A0}b", 'a[\s]b'));
        self::assertTrue(Functions::matches('a@b', '^[^\.\s@]@[^\.\s@]$'));
    }

    public function testTheDelimiterIsEscaped(): void
    {
        self::assertTrue(Functions::matches('a~b', 'a~b'));
    }

    public function testFlagsAreRefused(): void
    {
        $this->expectException(UnsupportedRulesException::class);

        Functions::matches('a', 'A', 'i');
    }

    public function testUnbalancedPatternIsRefused(): void
    {
        $this->expectException(UnsupportedRulesException::class);

        Functions::regex('(a');
    }

    public function testCodepointOfOneCharacter(): void
    {
        self::assertSame(65.0, Functions::codepoint('A'));
        self::assertNan(Functions::codepoint(''));
        self::assertNan(Functions::codepoint('AB'));
    }

    public function testDecimalFollowsTheXmlSchemaLexicalForm(): void
    {
        self::assertSame(12.5, Functions::decimal(' 12.50 '));
        self::assertSame(5.0, Functions::decimal('+5'));
        self::assertSame(-0.5, Functions::decimal('-.5'));
        self::assertNan(Functions::decimal(''));
        self::assertNan(Functions::decimal('abc'));
        self::assertNan(Functions::decimal('1e3'));
        self::assertNan(Functions::decimal('1,5'));
    }
}
