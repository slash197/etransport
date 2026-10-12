<?php

declare(strict_types=1);

namespace slash197\eTransport\Response;

use slash197\eTransport\Support\Json;

/**
 * An active notification that names a company as transport organiser, as
 * ANAF's info service describes it to that company.
 *
 * The route, the documents and the vehicle changes are in $raw.
 */
final readonly class TransportInfo
{
    /**
     * @param \DateTimeImmutable|null $dataExpUit From when the UIT counts as expired
     * @param array<mixed>            $raw
     */
    public function __construct(
        public ?string $uit,
        public ?string $codDecl,
        public ?string $denDecl,
        public ?string $refDecl,
        public ?\DateTimeImmutable $dataTransp,
        public ?\DateTimeImmutable $dataExpUit,
        public ?string $trCod,
        public ?string $trDen,
        public ?string $nrVeh,
        public ?string $nrRem1,
        public ?string $nrRem2,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Json::string($data, 'uit'),
            Json::string($data, 'cod_decl'),
            Json::string($data, 'den_decl'),
            Json::string($data, 'ref_decl'),
            Json::date($data, 'data_transp'),
            Json::date($data, 'data_exp_uit'),
            Json::string($data, 'tr_cod'),
            Json::string($data, 'tr_den'),
            Json::string($data, 'nr_veh'),
            Json::string($data, 'nr_rem1'),
            Json::string($data, 'nr_rem2'),
            $data,
        );
    }
}
