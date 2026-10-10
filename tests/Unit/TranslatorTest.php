<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use slash197\eTransport\Exception\UnsupportedRulesException;
use slash197\eTransport\Validation\Schematron\Functions;
use slash197\eTransport\Validation\Schematron\Translator;

final class TranslatorTest extends TestCase
{
    private const CONSTANTS = ['EU' => "('AT','BE')", 'TIN' => "'^[1-9]\\d+$'"];

    public function testXPath1IsLeftAlone(): void
    {
        self::assertSame(
            "count ( xx:notificare ) = 1 and not ( @a != '' )",
            (new Translator())->translate("count(xx:notificare) = 1 and not(@a != '')")
        );
    }

    public function testExistsBecomesBoolean(): void
    {
        self::assertSame('boolean ( @a ) or not ( boolean ( x:b ) )', (new Translator())->translate('exists(@a) or not (exists(x:b))'));
    }

    public function testXPath2FunctionsAreHandedToPhp(): void
    {
        $call = "php:functionString ( '" . Functions::class . "::%s' ,";

        self::assertSame(
            sprintf($call, 'decimal') . ' . ) > 0',
            (new Translator())->translate('xs:decimal(.) > 0')
        );
        self::assertSame(
            sprintf($call, 'matches') . " normalize-space ( . ) , '^[1-9]\\d+$' )",
            (new Translator(self::CONSTANTS))->translate('matches(normalize-space(.), $TIN)')
        );
        self::assertSame(
            'number ( ' . sprintf($call, 'codepoint') . ' substring ( . , 1 , 1 ) ) )',
            (new Translator())->translate('number(string-to-codepoints(substring(.,1,1)))')
        );
    }

    public function testComparisonWithAListIsWrittenOut(): void
    {
        self::assertSame(
            "( normalize-space ( . ) = '10' or normalize-space ( . ) = '20' ) and @a",
            (new Translator())->translate("normalize-space(.) = ('10', '20') and @a")
        );
        self::assertSame(
            "not ( ( normalize-space ( // @codTara ) = 'AT' or normalize-space ( // @codTara ) = 'BE' ) )",
            (new Translator(self::CONSTANTS))->translate('not(normalize-space(//@codTara) = $EU)')
        );
    }

    public function testAListOfOneNeedsNoRewriting(): void
    {
        self::assertSame("normalize-space ( . ) = ( '30' )", (new Translator())->translate("normalize-space(.) =('30')"));
    }

    public function testARuleVariableIsInlinedInParentheses(): void
    {
        $lets = ['SUM' => 'number(@a) + 1', 'CHECK' => 'substring(string($SUM), 1, 2)'];

        self::assertSame(
            'contains ( . , ( substring ( string ( ( number ( @a ) + 1 ) ) , 1 , 2 ) ) )',
            (new Translator())->translate('contains(., $CHECK)', $lets)
        );
    }

    #[DataProvider('unsupported')]
    public function testAnythingElseIsRefused(string $expression, string $reason): void
    {
        $this->expectException(UnsupportedRulesException::class);
        $this->expectExceptionMessage($reason);

        (new Translator(self::CONSTANTS))->translate($expression, ['HERE' => 'name(.)', 'LOOP' => '$LOOP']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupported(): iterable
    {
        yield 'an XPath 2 function that is not handled' => ['upper-case(@a) = "A"', 'Unsupported function upper-case()'];
        yield 'an unknown variable' => ['@a = $NOPE', 'Unknown variable $NOPE'];
        yield 'a rule variable inside a predicate' => ['x:a[name(.) = $HERE]', 'inside a predicate'];
        yield 'a variable that refers to itself' => ['$LOOP', 'refers to itself'];
        yield 'a list on the left' => ["('a', 'b') = @a", 'Unsupported list'];
        yield 'a list compared with a path' => ["@a = ('a', 'b')", 'Unsupported comparison with a list'];
        yield 'a list compared with part of a sum' => ["1 + number(@a) = (1, 2)", 'Unsupported comparison with a list'];
        yield 'a list compared with less-than' => ["number(@a) < (1, 2)", 'Unsupported list'];
        yield 'an XPath 2 comment' => ['(: note :) @a', 'Unexpected ":"'];
        yield 'a character outside XPath' => ['@a ! string()', 'Unexpected "!"'];
        yield 'a regular expression PHP would read differently' => ["matches(., '\\w+')", 'Unsupported escape \\w'];
    }

    public function testAVariableDeclaredOutsideARuleMustBeALiteral(): void
    {
        $this->expectException(UnsupportedRulesException::class);
        $this->expectExceptionMessage('$ROOT');

        new Translator(['ROOT' => 'name(/*)']);
    }
}
