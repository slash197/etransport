<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation\Schematron;

/**
 * @internal
 */
final class Assertion
{
    public const TEXT = 'text';
    public const SELECT = 'select';

    /**
     * @param string $test XPath 1 expression that must hold at the rule's node
     * @param list<array{self::TEXT|self::SELECT, string}> $message Literal text, and expressions whose value is put into it
     */
    public function __construct(
        public readonly string $id,
        public readonly string $test,
        public readonly array $message,
    ) {
    }
}
