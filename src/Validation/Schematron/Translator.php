<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation\Schematron;

use slash197\eTransport\Exception\UnsupportedRulesException;

/**
 * Rewrites the XPath 2 expressions of a rules file into XPath 1, which is
 * what PHP can evaluate. It knows the constructs ANAF's rules use and
 * refuses anything else, so that no rule is evaluated wrongly in silence.
 *
 * @internal
 *
 * @phpstan-import-type Token from Lexer
 */
final class Translator
{
    private const CORE_FUNCTIONS = [
        'last', 'position', 'count', 'local-name', 'namespace-uri', 'name', 'string', 'concat', 'starts-with',
        'contains', 'substring-before', 'substring-after', 'substring', 'string-length', 'normalize-space',
        'translate', 'boolean', 'not', 'true', 'false', 'number', 'sum', 'floor', 'ceiling', 'round',
    ];

    private const NODE_TESTS = ['node', 'text', 'comment', 'processing-instruction'];

    private const RENAMED = ['exists' => 'boolean'];

    private const IN_PHP = [
        'matches' => 'matches',
        'xs:decimal' => 'decimal',
        'string-to-codepoints' => 'codepoint',
    ];

    private const OPERATOR_NAMES = ['and', 'or', 'div', 'mod'];

    /**
     * @param array<string, string> $constants Lets declared on the schema or a pattern, by name
     */
    public function __construct(private readonly array $constants = [])
    {
        foreach ($constants as $name => $value) {
            if (!self::isLiteral(Lexer::tokenize($value))) {
                throw new UnsupportedRulesException(
                    "Variable \${$name} is declared outside a rule and is not a literal, which is not supported."
                );
            }
        }
    }

    /**
     * @param array<string, string> $lets Lets declared on the rule the expression belongs to, by name
     */
    public function translate(string $expression, array $lets = []): string
    {
        $tokens = $this->expand(Lexer::tokenize($expression), $lets, [], $expression);
        $tokens = self::expandSequences($tokens, $expression);
        $tokens = self::replaceFunctions($tokens, $expression);

        return Lexer::join(array_column($tokens, 1));
    }

    /**
     * Puts the value of each variable in its place. A rule's let is an
     * expression evaluated at the rule's node, so it can only be inlined
     * where that node is still the context: outside any predicate.
     *
     * @param list<Token> $tokens
     * @param array<string, string> $lets
     * @param list<string> $expanding
     *
     * @return list<Token>
     */
    private function expand(array $tokens, array $lets, array $expanding, string $expression): array
    {
        $out = [];
        $depth = 0;

        foreach ($tokens as $token) {
            [$type, $text] = $token;
            if ($type === Lexer::SYMBOL) {
                $depth += match ($text) {
                    '[' => 1,
                    ']' => -1,
                    default => 0,
                };
            }
            if ($type !== Lexer::VARIABLE) {
                $out[] = $token;

                continue;
            }

            $name = substr($text, 1);
            if (isset($lets[$name])) {
                if ($depth > 0) {
                    throw new UnsupportedRulesException("Variable {$text} is used inside a predicate: {$expression}");
                }
                if (in_array($name, $expanding, true)) {
                    throw new UnsupportedRulesException("Variable {$text} refers to itself.");
                }
                $value = $this->expand(Lexer::tokenize($lets[$name]), $lets, [...$expanding, $name], $lets[$name]);
                $value = self::isLiteral($value) ? $value : [[Lexer::SYMBOL, '('], ...$value, [Lexer::SYMBOL, ')']];
            } elseif (isset($this->constants[$name])) {
                $value = Lexer::tokenize($this->constants[$name]);
            } else {
                throw new UnsupportedRulesException("Unknown variable {$text} in expression: {$expression}");
            }

            $out = [...$out, ...$value];
        }

        return $out;
    }

    /**
     * XPath 2 compares a value with a list: f(x) = ('a', 'b'). XPath 1 has
     * no lists, so the comparison is written out: (f(x) = 'a' or f(x) = 'b').
     *
     * @param list<Token> $tokens
     *
     * @return list<Token>
     */
    private static function expandSequences(array $tokens, string $expression): array
    {
        for ($i = 0; $i < count($tokens); $i++) {
            if (!self::is($tokens[$i], '=', '!=') || !self::is($tokens[$i + 1] ?? null, '(')) {
                continue;
            }

            $items = [];
            $close = $i + 2;
            while (in_array($tokens[$close][0] ?? null, [Lexer::STRING, Lexer::NUMBER], true)) {
                $items[] = $tokens[$close];
                $close++;
                if (!self::is($tokens[$close] ?? null, ',')) {
                    break;
                }
                $close++;
            }
            if (count($items) < 2 || !self::is($tokens[$close] ?? null, ')')) {
                continue;
            }

            $start = self::operandStart($tokens, $i - 1, $expression);
            $operand = array_slice($tokens, $start, $i - $start);

            $comparison = [[Lexer::SYMBOL, '(']];
            foreach ($items as $n => $item) {
                if ($n > 0) {
                    $comparison[] = [Lexer::NAME, 'or'];
                }
                $comparison = [...$comparison, ...$operand, $tokens[$i], $item];
            }
            $comparison[] = [Lexer::SYMBOL, ')'];

            array_splice($tokens, $start, $close - $start + 1, $comparison);
            $i = $start + count($comparison) - 1;
        }

        return $tokens;
    }

    /**
     * Finds where the operand ending at $end begins. Only a function call or
     * a parenthesised expression standing on its own is accepted, because
     * anything else would need a full XPath parser to delimit.
     *
     * @param list<Token> $tokens
     */
    private static function operandStart(array $tokens, int $end, string $expression): int
    {
        $start = null;
        if (self::is($tokens[$end] ?? null, ')')) {
            $depth = 0;
            for ($i = $end; $i >= 0; $i--) {
                $depth += match (true) {
                    self::is($tokens[$i], ')') => 1,
                    self::is($tokens[$i], '(') => -1,
                    default => 0,
                };
                if ($depth === 0) {
                    $start = $i;
                    break;
                }
            }
        }
        if ($start !== null && $start > 0 && self::isFunctionName($tokens, $start - 1)) {
            $start--;
        }

        $before = $start === null || $start === 0 ? null : $tokens[$start - 1];
        $standsAlone = $before === null
            || self::is($before, '(', '[', ',')
            || ($before[0] === Lexer::NAME && in_array($before[1], ['and', 'or'], true));

        if ($start === null || !$standsAlone) {
            throw new UnsupportedRulesException("Unsupported comparison with a list in expression: {$expression}");
        }

        return $start;
    }

    /**
     * @param list<Token> $tokens
     *
     * @return list<Token>
     */
    private static function replaceFunctions(array $tokens, string $expression): array
    {
        $out = [];
        $calls = [];
        $skip = null;

        foreach ($tokens as $i => $token) {
            [$type, $text] = $token;

            if ($type === Lexer::SYMBOL && $text === '(') {
                $calls[] = $i > 0 && $tokens[$i - 1][0] === Lexer::NAME && !self::isOperator($tokens, $i - 1);
            } elseif ($type === Lexer::SYMBOL && $text === ')') {
                array_pop($calls);
            } elseif ($type === Lexer::SYMBOL && $text === ',' && end($calls) !== true) {
                throw new UnsupportedRulesException("Unsupported list in expression: {$expression}");
            }

            if ($i === $skip) {
                continue;
            }
            if (!self::isFunctionName($tokens, $i)) {
                $out[] = $token;
            } elseif (isset(self::IN_PHP[$text])) {
                if ($text === 'matches') {
                    self::checkPattern($tokens, $i);
                }
                $out[] = [Lexer::NAME, 'php:functionString'];
                $out[] = [Lexer::SYMBOL, '('];
                $out[] = [Lexer::STRING, "'" . Functions::class . '::' . self::IN_PHP[$text] . "'"];
                $out[] = [Lexer::SYMBOL, ','];
                $skip = $i + 1;
            } elseif (isset(self::RENAMED[$text])) {
                $out[] = [Lexer::NAME, self::RENAMED[$text]];
            } elseif (in_array($text, self::CORE_FUNCTIONS, true)) {
                $out[] = $token;
            } else {
                throw new UnsupportedRulesException("Unsupported function {$text}() in expression: {$expression}");
            }
        }

        return $out;
    }

    /**
     * A pattern written as a literal is checked while the rules are loaded
     * instead of when the first document reaches it.
     *
     * @param list<Token> $tokens
     */
    private static function checkPattern(array $tokens, int $function): void
    {
        $depth = 0;
        $comma = null;

        for ($i = $function + 1; $i < count($tokens); $i++) {
            if (self::is($tokens[$i], '(', '[')) {
                $depth++;
            } elseif (self::is($tokens[$i], ')', ']')) {
                $depth--;
            } elseif ($depth === 1 && $comma === null && self::is($tokens[$i], ',')) {
                $comma = $i;
            }
            if ($depth === 0) {
                break;
            }
        }

        if ($comma !== null && $i === $comma + 2 && $tokens[$comma + 1][0] === Lexer::STRING) {
            Functions::regex(substr($tokens[$comma + 1][1], 1, -1));
        }
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isFunctionName(array $tokens, int $i): bool
    {
        return $tokens[$i][0] === Lexer::NAME
            && self::is($tokens[$i + 1] ?? null, '(')
            && !in_array($tokens[$i][1], self::NODE_TESTS, true)
            && !self::isOperator($tokens, $i);
    }

    /**
     * "and", "or", "div" and "mod" are operators when they follow an operand,
     * and ordinary names otherwise.
     *
     * @param list<Token> $tokens
     */
    private static function isOperator(array $tokens, int $i): bool
    {
        if ($i < 1 || !in_array($tokens[$i][1], self::OPERATOR_NAMES, true)) {
            return false;
        }
        [$type, $text] = $tokens[$i - 1];

        return $type !== Lexer::SYMBOL || in_array($text, [')', ']', '.', '..', '*'], true);
    }

    /**
     * A string, a number, or a parenthesised list of them.
     *
     * @param list<Token> $tokens
     */
    private static function isLiteral(array $tokens): bool
    {
        $isValue = static fn (?array $token): bool => in_array($token[0] ?? null, [Lexer::STRING, Lexer::NUMBER], true);

        if (count($tokens) === 1) {
            return $isValue($tokens[0]);
        }
        $last = count($tokens) - 1;
        if ($last < 2 || $last % 2 === 1 || !self::is($tokens[0], '(') || !self::is($tokens[$last], ')')) {
            return false;
        }
        for ($i = 1; $i < $last; $i++) {
            if ($i % 2 === 1 ? !$isValue($tokens[$i]) : !self::is($tokens[$i], ',')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Token|null $token
     */
    private static function is(?array $token, string ...$symbols): bool
    {
        return $token !== null && $token[0] === Lexer::SYMBOL && in_array($token[1], $symbols, true);
    }
}
