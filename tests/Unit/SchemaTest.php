<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use slash197\eTransport\Exception\UnsupportedRulesException;
use slash197\eTransport\Validation\Schematron\Schema;

final class SchemaTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'sch');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    public function testANodeIsCheckedByTheFirstMatchingRuleOfEachPattern(): void
    {
        $schema = $this->schema(<<<'XML'
            <pattern>
              <rule context="@a"><assert id="first" test=". = 'x'">[first]-a is <value-of select="."/> on <name path=".."/></assert></rule>
              <rule context="//@a"><assert id="never" test="false()">never</assert></rule>
              <rule context="item | /doc/other"><assert id="third" test="@a">third</assert></rule>
            </pattern>
            <pattern>
              <rule context="@a"><assert id="again" test="false()">other pattern</assert></rule>
            </pattern>
            XML);

        $document = new \DOMDocument();
        $document->loadXML('<doc><item a="y"/><item/><other/></doc>');

        $found = [];
        foreach ($schema->validate($document) as $violation) {
            $found[] = "{$violation->rule} {$violation->location} {$violation->message}";
        }

        self::assertSame([
            'first /doc/item[1]/@a a is y on item',
            'third /doc/item[2] third',
            'third /doc/other third',
            'again /doc/item[1]/@a other pattern',
        ], $found);
    }

    #[DataProvider('unsupported')]
    public function testARulesFileThatCannotBeEvaluatedInFullIsRefused(string $body, string $reason): void
    {
        $this->expectException(UnsupportedRulesException::class);
        $this->expectExceptionMessage($reason);

        $this->schema($body);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupported(): iterable
    {
        $rule = static fn (string $content): string => "<pattern><rule context=\"a\">{$content}</rule></pattern>";

        yield 'an include' => ['<include href="more.sch"/>', 'Unsupported element <include>'];
        yield 'an abstract pattern' => ['<pattern abstract="true"/>', 'Abstract patterns'];
        yield 'an abstract rule' => ['<pattern><rule abstract="true" id="r"/></pattern>', 'Abstract rules'];
        yield 'a rule that extends another' => [$rule('<extends rule="r"/>'), 'Unsupported element <extends>'];
        yield 'a rule without a context' => ['<pattern><rule/></pattern>', 'no context'];
        yield 'an assert without an id' => [$rule('<assert test="@a">text</assert>'), 'no id'];
        yield 'an assert without a test' => [$rule('<assert id="x">text</assert>'), 'no test'];
        yield 'an unknown element in a message' => [$rule('<assert id="x" test="@a"><diagnostic/></assert>'), '<diagnostic>'];
        yield 'an XPath 2 function' => [$rule('<assert id="x" test="ends-with(@a, \'b\')">text</assert>'), 'ends-with()'];
        yield 'an XPath 2 function in a report' => [$rule('<report id="x" test="empty(@a)">text</report>'), 'empty()'];
        yield 'XPath 2 syntax' => [$rule('<assert id="x" test="if (@a) then @b else @c">text</assert>'), 'if()'];
        yield 'an XPath 2 operator' => [$rule('<assert id="x" test="@a eq @b">text</assert>'), 'Invalid expression'];
        yield 'an undeclared prefix' => [$rule('<assert id="x" test="zz:a">text</assert>'), 'zz:a'];
    }

    public function testAnotherQueryLanguageIsRefused(): void
    {
        file_put_contents($this->file, '<schema xmlns="http://purl.oclc.org/dsdl/schematron" queryBinding="xslt3"/>');

        $this->expectException(UnsupportedRulesException::class);
        $this->expectExceptionMessage('xslt3');

        Schema::fromFile($this->file);
    }

    public function testAFileThatIsNotSchematronIsRefused(): void
    {
        file_put_contents($this->file, '<schema/>');

        $this->expectException(UnsupportedRulesException::class);

        Schema::fromFile($this->file);
    }

    private function schema(string $body): Schema
    {
        file_put_contents($this->file, "<schema xmlns=\"http://purl.oclc.org/dsdl/schematron\" queryBinding=\"xslt2\">{$body}</schema>");

        return Schema::fromFile($this->file);
    }
}
