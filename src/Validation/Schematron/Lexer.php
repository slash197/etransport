<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation\Schematron;

use slash197\eTransport\Exception\UnsupportedRulesException;

/**
 * Splits an XPath expression into tokens. Whitespace is dropped.
 *
 * @internal
 *
 * @phpstan-type Token array{string, string}
 */
final class Lexer
{
    public const STRING = 'string';
    public const NUMBER = 'number';
    public const VARIABLE = 'variable';
    public const NAME = 'name';
    public const SYMBOL = 'symbol';

    private const NC_NAME = '[\p{L}_][\p{L}\p{N}_.\-]*';

    private const PATTERNS = [
        self::STRING => '\'[^\']*\'|"[^"]*"',
        self::NUMBER => '\d+(?:\.\d*)?|\.\d+',
        self::VARIABLE => '\$' . self::NC_NAME,
        self::NAME => self::NC_NAME . '(?::(?:\*|' . self::NC_NAME . '))?',
        self::SYMBOL => '//|::|!=|<=|>=|\.\.|[()\[\],|/=<>+\-*@.]',
    ];

    /**
     * @return list<Token>
     */
    public static function tokenize(string $expression): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($expression);

        while ($offset < $length) {
            if (preg_match('/\G\s+/u', $expression, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);

                continue;
            }

            foreach (self::PATTERNS as $type => $pattern) {
                if (preg_match('~\G(?:' . $pattern . ')~u', $expression, $match, 0, $offset) === 1) {
                    $tokens[] = [$type, $match[0]];
                    $offset += strlen($match[0]);

                    continue 2;
                }
            }

            throw new UnsupportedRulesException(
                'Unexpected "' . mb_substr(substr($expression, $offset), 0, 1) . "\" in expression: {$expression}"
            );
        }

        return $tokens;
    }

    /**
     * @param list<string> $tokens
     */
    public static function join(array $tokens): string
    {
        return str_replace(['@ ', ' :: '], ['@', '::'], implode(' ', $tokens));
    }
}
