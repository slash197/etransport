<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation\Schematron;

use slash197\eTransport\Exception\UnsupportedRulesException;
use slash197\eTransport\Validation\Location;
use slash197\eTransport\Validation\Source;
use slash197\eTransport\Validation\Violation;

/**
 * A Schematron rules file, read once and then applied to any number of
 * documents with PHP's own XPath engine. No XSLT processor is involved.
 *
 * Within a pattern a node is checked by the first rule whose context
 * matches it and by no later one, as Schematron prescribes.
 *
 * @internal
 */
final class Schema
{
    private const NS = 'http://purl.oclc.org/dsdl/schematron';
    private const PHP_NS = 'http://php.net/xpath';
    private const QUERY_BINDINGS = ['', 'xslt', 'xslt2'];

    /**
     * @param array<string, string> $namespaces Prefix to URI
     * @param list<list<Rule>> $patterns
     */
    private function __construct(
        private readonly array $namespaces,
        private readonly array $patterns,
    ) {
    }

    public static function fromFile(string $file): self
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = is_file($file) && $document->load($file, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;
        if (!$loaded || $root === null || $root->namespaceURI !== self::NS || $root->localName !== 'schema') {
            throw new UnsupportedRulesException("Not a Schematron rules file: {$file}");
        }
        if (!in_array(strtolower($root->getAttribute('queryBinding')), self::QUERY_BINDINGS, true)) {
            throw new UnsupportedRulesException('Unsupported query binding: ' . $root->getAttribute('queryBinding'));
        }

        $children = self::children($root, ['ns', 'let', 'pattern', 'phase', 'title', 'p']);

        $namespaces = [];
        foreach ($children['ns'] as $ns) {
            $namespaces[$ns->getAttribute('prefix')] = $ns->getAttribute('uri');
        }

        $patterns = [];
        foreach ($children['pattern'] as $pattern) {
            $patterns[] = self::pattern($pattern, self::lets($children['let']));
        }

        $schema = new self($namespaces, $patterns);
        $schema->probe();

        return $schema;
    }

    /**
     * @return list<Violation>
     */
    public function validate(\DOMDocument $document): array
    {
        $xpath = $this->xpath($document);
        $violations = [];

        foreach ($this->patterns as $rules) {
            $claimed = [];
            foreach ($rules as $rule) {
                foreach (self::nodes($xpath, $rule->context, $document) as $node) {
                    $path = (string) $node->getNodePath();
                    if (isset($claimed[$path])) {
                        continue;
                    }
                    $claimed[$path] = true;

                    foreach ($rule->assertions as $assertion) {
                        if (self::evaluate($xpath, "boolean({$assertion->test})", $node) === true) {
                            continue;
                        }
                        $violations[] = new Violation(
                            Source::Rules,
                            self::message($xpath, $assertion, $node),
                            $assertion->id,
                            Location::of($node),
                            ($node instanceof \DOMAttr ? $node->ownerElement : $node)?->getLineNo(),
                        );
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function assertionIds(): array
    {
        $ids = [];
        foreach ($this->patterns as $rules) {
            foreach ($rules as $rule) {
                foreach ($rule->assertions as $assertion) {
                    $ids[] = $assertion->id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param array<string, string> $constants
     *
     * @return list<Rule>
     */
    private static function pattern(\DOMElement $pattern, array $constants): array
    {
        if ($pattern->getAttribute('abstract') === 'true' || $pattern->hasAttribute('is-a')) {
            throw new UnsupportedRulesException('Abstract patterns are not supported.');
        }

        $children = self::children($pattern, ['let', 'rule', 'title', 'p']);
        $translator = new Translator([...$constants, ...self::lets($children['let'])]);

        $rules = [];
        foreach ($children['rule'] as $rule) {
            if ($rule->getAttribute('abstract') === 'true') {
                throw new UnsupportedRulesException('Abstract rules are not supported.');
            }
            if (trim($rule->getAttribute('context')) === '') {
                throw new UnsupportedRulesException('A rule has no context.');
            }

            $parts = self::children($rule, ['let', 'assert', 'report']);
            $lets = self::lets($parts['let']);

            // A report only describes the document, so it is translated to prove it could be, and dropped.
            foreach ($parts['report'] as $report) {
                $translator->translate($report->getAttribute('test'), $lets);
            }

            $assertions = [];
            foreach ($parts['assert'] as $assert) {
                $assertions[] = self::assertion($assert, $translator, $lets);
            }

            $rules[] = new Rule(self::context($translator->translate($rule->getAttribute('context'))), $assertions);
        }

        return $rules;
    }

    /**
     * @param array<string, string> $lets
     */
    private static function assertion(\DOMElement $assert, Translator $translator, array $lets): Assertion
    {
        $message = [];
        $text = '';

        foreach ($assert->childNodes as $node) {
            if ($node instanceof \DOMText) {
                // Text that is only whitespace is dropped, as an XSLT processor drops it from a stylesheet.
                if (trim($node->data) !== '') {
                    $message[] = [Assertion::TEXT, $node->data];
                    $text .= $node->data;
                }
            } elseif ($node instanceof \DOMElement && $node->namespaceURI === self::NS) {
                $message[] = match ($node->localName) {
                    'value-of' => [Assertion::SELECT, $translator->translate($node->getAttribute('select'), $lets)],
                    'name' => [Assertion::SELECT, 'name(' . $translator->translate($node->getAttribute('path') ?: '.', $lets) . ')'],
                    'emph', 'span', 'dir' => [Assertion::TEXT, $node->textContent],
                    default => throw new UnsupportedRulesException("Unsupported element <{$node->localName}> in a rule message."),
                };
            }
        }

        // ANAF's file has asserts whose id ended up in the text, after a misplaced ">".
        $id = $assert->getAttribute('id');
        if ($id === '' && preg_match('/\[([A-Z]+(?:-[A-Z0-9]+)+)\]/', $text, $match) === 1) {
            $id = $match[1];
        }
        if ($id === '') {
            throw new UnsupportedRulesException('A rule has no id: ' . trim($text));
        }
        if (trim($assert->getAttribute('test')) === '') {
            throw new UnsupportedRulesException("Rule {$id} has no test.");
        }

        return new Assertion($id, $translator->translate($assert->getAttribute('test'), $lets), $message);
    }

    /**
     * A rule context is a pattern that a node anywhere in the document may
     * match, so every alternative that is not absolute is searched for
     * from the root.
     */
    private static function context(string $pattern): string
    {
        $alternatives = [[]];
        $depth = 0;

        foreach (Lexer::tokenize($pattern) as [$type, $text]) {
            if ($type === Lexer::SYMBOL) {
                $depth += match ($text) {
                    '(', '[' => 1,
                    ')', ']' => -1,
                    default => 0,
                };
                if ($text === '|' && $depth === 0) {
                    $alternatives[] = [];

                    continue;
                }
            }
            $alternatives[array_key_last($alternatives)][] = $text;
        }

        $paths = [];
        foreach ($alternatives as $tokens) {
            $paths[] = (in_array($tokens[0] ?? '', ['/', '//'], true) ? '' : '// ') . Lexer::join($tokens);
        }

        return implode(' | ', $paths);
    }

    /**
     * @param list<\DOMElement> $elements
     *
     * @return array<string, string>
     */
    private static function lets(array $elements): array
    {
        $lets = [];
        foreach ($elements as $let) {
            $lets[$let->getAttribute('name')] = $let->getAttribute('value');
        }

        return $lets;
    }

    /**
     * The Schematron child elements of an element, by name. One that is not
     * expected stops the load: ignoring it could change what a rule means.
     *
     * @param list<string> $expected
     *
     * @return array<string, list<\DOMElement>>
     */
    private static function children(\DOMElement $parent, array $expected): array
    {
        $children = array_fill_keys($expected, []);
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->namespaceURI !== self::NS) {
                continue;
            }
            if (!in_array($node->localName, $expected, true)) {
                throw new UnsupportedRulesException("Unsupported element <{$node->localName}> in <{$parent->localName}>.");
            }
            $children[$node->localName][] = $node;
        }

        return $children;
    }

    /**
     * Runs every expression once against an empty document, so that one
     * PHP cannot evaluate is found now and not when a document reaches it.
     */
    private function probe(): void
    {
        $document = new \DOMDocument();
        $root = $document->appendChild($document->createElement('probe'));
        $xpath = $this->xpath($document);

        foreach ($this->patterns as $rules) {
            foreach ($rules as $rule) {
                self::nodes($xpath, $rule->context, $document);
                foreach ($rule->assertions as $assertion) {
                    self::evaluate($xpath, "boolean({$assertion->test})", $root);
                    self::message($xpath, $assertion, $root);
                }
            }
        }
    }

    private function xpath(\DOMDocument $document): \DOMXPath
    {
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('php', self::PHP_NS);
        $xpath->registerPhpFunctions([
            Functions::class . '::matches',
            Functions::class . '::decimal',
            Functions::class . '::codepoint',
        ]);
        foreach ($this->namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }

    private static function message(\DOMXPath $xpath, Assertion $assertion, \DOMNode $node): string
    {
        $text = '';
        foreach ($assertion->message as [$type, $value]) {
            if ($type === Assertion::TEXT) {
                $text .= $value;

                continue;
            }

            // Like XSLT 2, list every node an expression selects, not only the first.
            $result = self::evaluate($xpath, $value, $node);
            if ($result instanceof \DOMNodeList) {
                $values = [];
                foreach ($result as $selected) {
                    $values[] = $selected instanceof \DOMNode ? $selected->textContent : '';
                }
                $text .= implode(' ', $values);
            } else {
                $string = self::evaluate($xpath, "string({$value})", $node);
                $text .= is_string($string) ? $string : '';
            }
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return (string) preg_replace('/^.*?\[' . preg_quote($assertion->id, '/') . '\]\s*-\s*/u', '', $text, 1);
    }

    /**
     * @return \DOMNodeList<\DOMNode>
     */
    private static function nodes(\DOMXPath $xpath, string $expression, \DOMNode $node): \DOMNodeList
    {
        $nodes = self::evaluate($xpath, $expression, $node);
        if (!$nodes instanceof \DOMNodeList) {
            throw new UnsupportedRulesException("A rule context does not select nodes: {$expression}");
        }

        return $nodes;
    }

    private static function evaluate(\DOMXPath $xpath, string $expression, \DOMNode $node): mixed
    {
        set_error_handler(static function (int $severity, string $message) use ($expression): never {
            throw new UnsupportedRulesException("{$message} in expression: {$expression}");
        });

        try {
            return $xpath->evaluate($expression, $node);
        } finally {
            restore_error_handler();
        }
    }
}
