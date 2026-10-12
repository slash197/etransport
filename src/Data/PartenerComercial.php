<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

use slash197\eTransport\Support\Xml;

/**
 * The other party to the operation: the supplier or the recipient.
 */
final readonly class PartenerComercial
{
    /**
     * @param string      $codTara Two-letter country code; Greece is "EL"
     * @param string|null $cod     Tax identification code of the partner
     */
    public function __construct(
        public string $codTara,
        public string $denumire,
        public ?string $cod = null,
    ) {
    }

    /**
     * @internal
     */
    public function toXml(\DOMDocument $document): \DOMElement
    {
        return Xml::element($document, 'partenerComercial', [
            'codTara' => $this->codTara,
            'denumire' => $this->denumire,
        ], [
            'cod' => $this->cod,
        ]);
    }
}
