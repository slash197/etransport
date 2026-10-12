<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

use slash197\eTransport\Support\Xml;

/**
 * An address in Romania.
 */
final readonly class Locatie
{
    public function __construct(
        public Judet|int $codJudet,
        public string $denumireLocalitate,
        public string $denumireStrada,
        public ?string $numar = null,
        public ?string $bloc = null,
        public ?string $scara = null,
        public ?string $etaj = null,
        public ?string $apartament = null,
        public ?string $alteInfo = null,
        public ?string $codPostal = null,
    ) {
    }

    /**
     * @internal
     */
    public function toXml(\DOMDocument $document): \DOMElement
    {
        return Xml::element($document, 'locatie', [
            'codJudet' => Xml::code($this->codJudet),
            'denumireLocalitate' => $this->denumireLocalitate,
            'denumireStrada' => $this->denumireStrada,
        ], [
            'numar' => $this->numar,
            'bloc' => $this->bloc,
            'scara' => $this->scara,
            'etaj' => $this->etaj,
            'apartament' => $this->apartament,
            'alteInfo' => $this->alteInfo,
            'codPostal' => $this->codPostal,
        ]);
    }
}
