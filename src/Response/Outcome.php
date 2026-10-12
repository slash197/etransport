<?php

declare(strict_types=1);

namespace slash197\eTransport\Response;

/**
 * Where an uploaded message stands, from the status of the upload and,
 * once ANAF has processed it, from its record in the message list.
 */
final readonly class Outcome
{
    /**
     * @param string|null        $uit    The UIT of the transport, once known
     * @param list<string>       $errors Why ANAF rejected the message
     * @param ListedMessage|null $listed The record of the message list the outcome was read from
     */
    public function __construct(
        public OutcomeState $state,
        public ?string $uit,
        public array $errors,
        public Status $status,
        public ?ListedMessage $listed = null,
    ) {
    }

    public function isFinal(): bool
    {
        return $this->state !== OutcomeState::Processing;
    }
}
