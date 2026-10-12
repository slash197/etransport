<?php

declare(strict_types=1);

namespace slash197\eTransport\Response;

/**
 * What ANAF's stareMesaj service says about an upload.
 */
final readonly class Status
{
    /**
     * @param string       $stare  ANAF's own wording: "ok", "nok", "in prelucrare", "XML cu erori nepreluat de sistem"
     * @param list<string> $errors
     * @param array<mixed> $raw    The decoded response body
     */
    public function __construct(
        public string $stare,
        public array $errors,
        public ?string $traceId,
        public array $raw = [],
    ) {
    }

    /**
     * ANAF processed the message and found it valid. Client::outcome()
     * also looks up its record in the message list.
     */
    public function isOk(): bool
    {
        return strtolower($this->stare) === 'ok';
    }

    public function isRejected(): bool
    {
        $stare = strtolower($this->stare);

        return $stare === 'nok' || str_contains($stare, 'xml') || str_contains($stare, 'ero');
    }

    public function isProcessing(): bool
    {
        return !$this->isOk() && !$this->isRejected();
    }
}
