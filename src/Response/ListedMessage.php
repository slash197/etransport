<?php

declare(strict_types=1);

namespace slash197\eTransport\Response;

use slash197\eTransport\MessageType;
use slash197\eTransport\Support\Json;

/**
 * One record of ANAF's message list: the final state of a valid
 * notification or vehicle change, or a message of any kind that had errors.
 *
 * Only the fields that identify the record and tell its state are
 * properties; everything ANAF sent is in $raw.
 */
final readonly class ListedMessage
{
    /**
     * @param MessageType|null                         $tip         NOT or MVH for a valid record, any type for one with errors
     * @param string|null                              $stare       "OK" or "ERR"
     * @param string|null                              $sursa       "A" for the API, "I" for ANAF's web application
     * @param list<array{tip: string, mesaj: string}> $mesaje      tip is "ERR", "WARN" or "INFO"
     * @param array<mixed>                             $raw
     */
    public function __construct(
        public ?MessageType $tip,
        public ?string $stare,
        public ?string $uit,
        public ?string $codDecl,
        public ?string $refDecl,
        public ?string $idIncarcare,
        public ?string $sursa,
        public ?\DateTimeImmutable $dataCreare,
        public array $mesaje = [],
        public array $raw = [],
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $mesaje = [];
        foreach (Json::objects($data, 'mesaje') as $mesaj) {
            $text = Json::string($mesaj, 'mesaj');
            if ($text !== null) {
                $mesaje[] = ['tip' => strtoupper(Json::string($mesaj, 'tip') ?? ''), 'mesaj' => $text];
            }
        }

        return new self(
            MessageType::tryFrom(strtoupper(Json::string($data, 'tip') ?? '')),
            Json::string($data, 'stare'),
            Json::string($data, 'uit') ?? Json::string($data, 'UIT'),
            Json::string($data, 'cod_decl'),
            Json::string($data, 'ref_decl'),
            Json::string($data, 'id_incarcare'),
            Json::string($data, 'sursa'),
            Json::date($data, 'data_creare'),
            $mesaje,
            $data,
        );
    }

    public function isOk(): bool
    {
        return strtoupper((string) $this->stare) === 'OK';
    }

    public function isError(): bool
    {
        return strtoupper((string) $this->stare) === 'ERR';
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        $errors = [];
        foreach ($this->mesaje as $mesaj) {
            if ($mesaj['tip'] === 'ERR') {
                $errors[] = $mesaj['mesaj'];
            }
        }

        return array_values(array_unique($errors));
    }
}
