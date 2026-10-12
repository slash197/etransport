<?php

declare(strict_types=1);

namespace slash197\eTransport\Response;

/**
 * Proof that ANAF received a message. It says nothing about the outcome:
 * ANAF processes the message afterwards, see Client::outcome().
 */
final readonly class Upload
{
    /**
     * @param string       $idIncarcare ANAF's id for the upload ("index_incarcare"), which its status is asked by
     * @param string|null  $uit         The UIT ANAF assigned or the one the message was about
     * @param array<mixed> $raw         The decoded response body
     */
    public function __construct(
        public string $idIncarcare,
        public ?string $uit,
        public ?string $traceId,
        public array $raw = [],
    ) {
    }
}
