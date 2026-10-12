<?php

declare(strict_types=1);

namespace slash197\eTransport\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use slash197\eTransport\Exception\ApiException;
use slash197\eTransport\Support\Json;

/**
 * Sends one request with the bearer token and decodes the JSON answer.
 * Only metadata is logged: declarations carry commercial data that does
 * not belong in logs.
 *
 * @internal
 */
final class Transport
{
    /**
     * @param string|\Closure(): string $token
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string|\Closure $token,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<mixed> The decoded body
     *
     * @throws ApiException When there is no response, an HTTP error or a body that is not JSON
     */
    public function send(string $method, string $url, ?string $xml = null): array
    {
        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . (is_string($this->token) ? $this->token : ($this->token)()))
            ->withHeader('Accept', 'application/json');

        if ($xml !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/xml')
                ->withBody($this->streamFactory->createStream($xml));
        }

        $endpoint = (string) strtok($url, '?');

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->warning('e-Transport request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            throw new ApiException("e-Transport request to {$endpoint} failed: {$exception->getMessage()}", previous: $exception);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $data = is_array($data) ? $data : null;

        $this->logger->info('e-Transport request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => $status,
            'trace_id' => $data === null ? null : Json::string($data, 'trace_id'),
        ]);

        if ($status >= 400 || $data === null) {
            $errors = $data === null ? [] : Json::errors($data);

            throw new ApiException(
                $status >= 400
                    ? "e-Transport request to {$endpoint} failed with HTTP {$status}" . ($errors === [] ? '.' : ': ' . implode(' | ', $errors))
                    : "e-Transport request to {$endpoint} was not answered with JSON.",
                $status,
                $body,
                $errors,
                $data === null ? null : Json::string($data, 'trace_id'),
            );
        }

        return $data;
    }
}
