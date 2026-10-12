<?php

declare(strict_types=1);

namespace slash197\eTransport\Exception;

/**
 * A request that ANAF did not answer as expected: no response at all, an
 * HTTP error, or a response that reports the call itself as failed (no
 * right to the tax code, an unknown upload id, a daily limit reached).
 *
 * A message that ANAF received and then rejected is not an exception; it
 * is reported by the status and the outcome of the upload.
 */
class ApiException extends ETransportException
{
    /**
     * @param list<string> $errors  ANAF's own error texts, when it gave any
     * @param string|null  $traceId ANAF's id for the call, to quote when asking ANAF about it
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly string $body = '',
        public readonly array $errors = [],
        public readonly ?string $traceId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    /**
     * Tells a transient failure (no response at all, 5xx, 408, 429) from one
     * that will fail the same way again.
     */
    public function isRetryable(): bool
    {
        return $this->statusCode === null
            || $this->statusCode >= 500
            || in_array($this->statusCode, [408, 429], true);
    }
}
