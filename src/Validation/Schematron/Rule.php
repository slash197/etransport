<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation\Schematron;

/**
 * @internal
 */
final class Rule
{
    /**
     * @param string $context XPath 1 expression selecting the nodes the rule applies to
     * @param list<Assertion> $assertions
     */
    public function __construct(
        public readonly string $context,
        public readonly array $assertions,
    ) {
    }
}
