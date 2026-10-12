<?php

declare(strict_types=1);

namespace slash197\eTransport;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use slash197\eTransport\Exception\ApiException;
use slash197\eTransport\Http\Transport;
use slash197\eTransport\Response\ListedMessage;
use slash197\eTransport\Response\Outcome;
use slash197\eTransport\Response\OutcomeState;
use slash197\eTransport\Response\Status;
use slash197\eTransport\Response\TransportInfo;
use slash197\eTransport\Response\Upload;
use slash197\eTransport\Support\Json;
use slash197\eTransport\Support\SystemClock;

/**
 * Client for the web services of RO e-Transport.
 */
final class Client
{
    private const STANDARD = 'ETRANSP';
    /** The version of the XML schema this package writes. */
    private const VERSION = 2;
    private const MAX_DAYS = 60;

    /** Which type a valid record of the message list has, for the type of message that was uploaded. */
    private const LISTED_AS = [
        MessageType::Notificare->value => MessageType::Notificare,
        MessageType::Corectie->value => MessageType::Notificare,
        MessageType::Stergere->value => MessageType::Stergere,
        MessageType::ModificareVehicul->value => MessageType::ModificareVehicul,
    ];

    private readonly Transport $transport;
    private readonly ClockInterface $clock;

    /**
     * @param ClientInterface           $httpClient Any PSR-18 client. Configure timeouts on it.
     * @param string|\Closure(): string $token      An ANAF OAuth access token, or a function that returns the current one
     * @param LoggerInterface|null      $logger     Receives request metadata, never message contents
     */
    public function __construct(
        ClientInterface $httpClient,
        string|\Closure $token,
        private readonly Environment $environment = Environment::Production,
        ?LoggerInterface $logger = null,
        ?ClockInterface $clock = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $factory = new Psr17Factory();
        $this->transport = new Transport(
            $httpClient,
            $requestFactory ?? $factory,
            $streamFactory ?? $factory,
            $token,
            $logger ?? new NullLogger(),
        );
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * Sends a message. The answer only confirms that ANAF received it;
     * {@see outcome()} tells what became of it.
     *
     * @param string|null $cif The tax code to upload for, digits only. A Message is uploaded for its declarant by default.
     *
     * @throws ApiException When ANAF did not take the message
     */
    public function upload(Message|string $message, ?string $cif = null): Upload
    {
        $cif ??= $message instanceof Message ? $message->codDeclarant : null;
        if ($cif === null) {
            throw new \InvalidArgumentException('An XML document needs the tax code to upload it for.');
        }

        $url = $this->url('upload', self::STANDARD, $cif, (string) self::VERSION);
        $data = $this->transport->send('POST', $url, $message instanceof Message ? $message->toXml() : $message);
        $id = Json::string($data, 'index_incarcare');

        if (Json::int($data, 'ExecutionStatus') !== 0 || $id === null) {
            throw $this->failure('upload', $data);
        }

        return new Upload(
            $id,
            Json::string($data, 'UIT') ?? ($message instanceof Message ? $message->uit : null),
            Json::string($data, 'trace_id'),
            $data,
        );
    }

    /**
     * Asks how far ANAF got with an upload. ANAF answers at most 100 times a day for one upload.
     *
     * @throws ApiException
     */
    public function status(Upload|string $idIncarcare): Status
    {
        $id = $idIncarcare instanceof Upload ? $idIncarcare->idIncarcare : $idIncarcare;
        $data = $this->transport->send('GET', $this->url('stareMesaj', $id));
        $stare = Json::string($data, 'stare');

        // A rejected message also has ExecutionStatus 1; without a state it is the call that failed.
        if ($stare === null) {
            throw $this->failure('stareMesaj', $data);
        }

        return new Status($stare, Json::errors($data), Json::string($data, 'trace_id'), $data);
    }

    /**
     * The message list of a tax code: the final state of its valid
     * notifications and vehicle changes, and every message that had
     * errors. ANAF does not sort it.
     *
     * @param int $days How far back to look, 1 to 60
     *
     * @return list<ListedMessage>
     *
     * @throws ApiException
     */
    public function messages(string $cif, int $days = self::MAX_DAYS): array
    {
        $data = $this->transport->send('GET', $this->url('lista', (string) $days, $cif));
        if (!array_is_list($data) && Json::int($data, 'ExecutionStatus') !== 0 && !isset($data['mesaje'])) {
            // ANAF reports a list with nothing in it as an error: "Nu exista mesaje in ultimele 60 zile".
            if (preg_match('/^Nu exista mesaje/i', Json::errors($data)[0] ?? '') === 1) {
                return [];
            }

            throw $this->failure('lista', $data);
        }

        $messages = [];
        foreach (array_is_list($data) ? array_filter($data, is_array(...)) : Json::objects($data, 'mesaje') as $record) {
            $messages[] = ListedMessage::fromArray($record);
        }

        return $messages;
    }

    /**
     * For transport organisers: the active notifications that name
     * $cuiOp as the organiser.
     *
     * @param string|null $cuiDecl     Only those declared by this tax code
     * @param string|null $refDeclarant Only those with this declarant's reference
     *
     * @return list<TransportInfo>
     *
     * @throws ApiException
     */
    public function info(string $cuiOp, ?string $cuiDecl = null, ?string $uit = null, ?string $refDeclarant = null): array
    {
        $query = http_build_query(array_filter([
            'cui_op' => $cuiOp,
            'cui_decl' => $cuiDecl,
            'uit' => $uit,
            'ref_decl' => $refDeclarant,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));

        $data = $this->transport->send('GET', $this->url('info') . '?' . $query);
        if (!array_is_list($data)) {
            throw $this->failure('info', $data);
        }

        return array_map(TransportInfo::fromArray(...), array_values(array_filter($data, is_array(...))));
    }

    /**
     * Tells what became of an upload. The status service only says that
     * ANAF processed the message; whether the declaration is valid is read
     * from the message list, where the upload is looked for by its id, then
     * by UIT, then by the declarant's reference.
     *
     * Call it again later while the outcome is not final.
     *
     * @param string                  $cif          The tax code the message was uploaded for
     * @param MessageType|null        $type         What was uploaded; keeps a deletion or vehicle change from being
     *                                              taken for the notification that has the same UIT
     * @param string|null             $uit          The UIT the message was about or was given at upload
     * @param \DateTimeInterface|null $uploadedAt   Limits how far back the message list is asked for
     *
     * @throws ApiException When the status cannot be asked for
     */
    public function outcome(
        Upload|string $idIncarcare,
        string $cif,
        ?MessageType $type = null,
        ?string $uit = null,
        ?string $refDeclarant = null,
        ?\DateTimeInterface $uploadedAt = null,
    ): Outcome {
        if ($idIncarcare instanceof Upload) {
            $uit ??= $idIncarcare->uit;
            $idIncarcare = $idIncarcare->idIncarcare;
        }

        $status = $this->status($idIncarcare);
        if ($status->isRejected()) {
            return new Outcome(OutcomeState::Rejected, $uit, $status->errors ?: [$status->stare], $status);
        }
        if (!$status->isOk()) {
            return new Outcome(OutcomeState::Processing, $uit, [], $status);
        }

        try {
            $listed = self::find($this->messages($cif, $this->days($uploadedAt)), $idIncarcare, $type, $uit, $refDeclarant);
        } catch (ApiException) {
            $listed = null;
        }

        return new Outcome(
            match (true) {
                $listed?->isOk() => OutcomeState::Accepted,
                $listed?->isError() => OutcomeState::Rejected,
                default => OutcomeState::Processing,
            },
            $uit ?? $listed?->uit,
            $listed?->isError() ? $listed->errors() : [],
            $status,
            $listed,
        );
    }

    /**
     * @param list<ListedMessage> $messages
     */
    private static function find(array $messages, string $idIncarcare, ?MessageType $type, ?string $uit, ?string $refDeclarant): ?ListedMessage
    {
        // ANAF pads the id with zeros in the list.
        $same = static fn (?string $a, ?string $b): bool => $a !== null && $b !== null && strtolower(trim($a)) === strtolower(trim($b));
        $id = ltrim($idIncarcare, '0');

        foreach ($messages as $message) {
            if ($message->idIncarcare !== null && ltrim($message->idIncarcare, '0') === $id) {
                return $message;
            }
        }

        // The record of a valid notification may carry the id of another upload, such as the one a correction replaced.
        if ($type !== null && !isset(self::LISTED_AS[$type->value])) {
            return null;
        }
        $expected = $type === null ? null : self::LISTED_AS[$type->value];

        foreach ([[$uit, 'uit'], [$refDeclarant, 'refDecl']] as [$wanted, $property]) {
            foreach ($messages as $message) {
                if ($same($wanted, $message->{$property}) && ($expected === null || $message->tip === $expected)) {
                    return $message;
                }
            }
        }

        return null;
    }

    private function days(?\DateTimeInterface $uploadedAt): int
    {
        if ($uploadedAt === null) {
            return 7;
        }

        $hours = abs($this->clock->now()->getTimestamp() - $uploadedAt->getTimestamp()) / 3600;

        return (int) min(self::MAX_DAYS, max(1, ceil($hours / 24) + 1));
    }

    /**
     * @param array<mixed> $data
     */
    private function failure(string $service, array $data): ApiException
    {
        $errors = Json::errors($data);

        return new ApiException(
            "e-Transport {$service} failed" . ($errors === [] ? '.' : ': ' . implode(' | ', $errors)),
            200,
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $errors,
            Json::string($data, 'trace_id'),
        );
    }

    private function url(string ...$segments): string
    {
        return $this->environment->apiUrl() . '/' . implode('/', array_map(rawurlencode(...), $segments));
    }
}
