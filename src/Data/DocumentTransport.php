<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

use slash197\eTransport\Support\Xml;

/**
 * A document that accompanies the goods.
 */
final readonly class DocumentTransport
{
    /**
     * @param string|null $observatii ANAF requires it when the type is TipDocument::Altele
     */
    public function __construct(
        public TipDocument|int $tipDocument,
        public \DateTimeInterface $dataDocument,
        public ?string $numarDocument = null,
        public ?string $observatii = null,
    ) {
    }

    /**
     * @internal
     */
    public function toXml(\DOMDocument $document): \DOMElement
    {
        return Xml::element($document, 'documenteTransport', [
            'tipDocument' => Xml::code($this->tipDocument),
            'dataDocument' => $this->dataDocument->format('Y-m-d'),
        ], [
            'numarDocument' => $this->numarDocument,
            'observatii' => $this->observatii,
        ]);
    }
}
