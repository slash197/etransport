<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation\Schematron;

use slash197\eTransport\Exception\UnsupportedRulesException;

/**
 * The XPath 2 functions the rules use that XPath 1 lacks. They are called
 * from inside DOMXPath, which hands every argument over as a string.
 *
 * @internal
 */
final class Functions
{
    private const ESCAPES = ['.', '\\', '?', '*', '+', '{', '}', '(', ')', '[', ']', '|', '-', '^', '$', 'n', 'r', 't'];

    /** @var array<string, string> */
    private static array $compiled = [];

    public static function matches(string $input, string $pattern, string ...$flags): bool
    {
        if ($flags !== []) {
            throw new UnsupportedRulesException('matches() with flags is not supported.');
        }

        $result = preg_match(self::regex($pattern), $input);
        if ($result === false) {
            throw new UnsupportedRulesException("Regular expression could not be run: {$pattern}");
        }

        return $result === 1;
    }

    /**
     * string-to-codepoints(), for the single character the rules pass to it.
     */
    public static function codepoint(string $character): float
    {
        return mb_strlen($character) === 1 ? (float) mb_ord($character) : NAN;
    }

    /**
     * xs:decimal(). A value that is not a decimal becomes NaN, which fails
     * every comparison, where an XPath 2 processor would stop with an error.
     */
    public static function decimal(string $value): float
    {
        $value = trim($value, " \t\n\r");

        return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/D', $value) === 1 ? (float) $value : NAN;
    }

    /**
     * Turns an XPath regular expression into a PCRE one. The two differ in
     * what \d and \s match and in whether $ tolerates a trailing newline.
     */
    public static function regex(string $pattern): string
    {
        if (isset(self::$compiled[$pattern])) {
            return self::$compiled[$pattern];
        }

        $characters = mb_str_split($pattern);
        $count = count($characters);
        $inClass = false;
        $out = '';

        for ($i = 0; $i < $count; $i++) {
            $character = $characters[$i];
            $next = $characters[$i + 1] ?? '';

            if ($character === '\\') {
                $i++;
                $out .= match (true) {
                    $next === 'd' => '\p{Nd}',
                    $next === 'D' => '\P{Nd}',
                    $next === 's' => $inClass ? ' \t\n\r' : '[ \t\n\r]',
                    in_array($next, self::ESCAPES, true) => '\\' . $next,
                    default => throw new UnsupportedRulesException("Unsupported escape \\{$next} in regular expression: {$pattern}"),
                };
            } elseif ($character === '[') {
                if ($inClass) {
                    throw new UnsupportedRulesException("Unsupported character class in regular expression: {$pattern}");
                }
                $inClass = true;
                $out .= $character;
            } elseif ($character === ']') {
                $inClass = false;
                $out .= $character;
            } elseif ($character === '(' && $next === '?') {
                throw new UnsupportedRulesException("Unsupported group in regular expression: {$pattern}");
            } elseif ($character === '~') {
                $out .= '\~';
            } else {
                $out .= $character;
            }
        }

        $regex = '~' . $out . '~uD';
        set_error_handler(static fn (): bool => true);
        try {
            $valid = preg_match($regex, '') !== false;
        } finally {
            restore_error_handler();
        }
        if (!$valid) {
            throw new UnsupportedRulesException("Invalid regular expression: {$pattern}");
        }

        return self::$compiled[$pattern] = $regex;
    }
}
