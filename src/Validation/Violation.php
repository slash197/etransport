<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation;

final class Violation
{
    /**
     * @param string $message For a business rule, ANAF's own text for the rule, in Romanian
     * @param string|null $rule The id of the business rule, such as "BR-027"
     * @param string|null $location Where the problem is, such as "/eTransport/notificare/bunuriTransportate[2]/@cantitate"
     */
    public function __construct(
        public readonly Source $source,
        public readonly string $message,
        public readonly ?string $rule = null,
        public readonly ?string $location = null,
        public readonly ?int $line = null,
    ) {
    }
}
