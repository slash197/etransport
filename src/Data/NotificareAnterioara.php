<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

use slash197\eTransport\Support\Xml;

/**
 * An earlier notification whose goods this transport carries on.
 */
final readonly class NotificareAnterioara
{
    public function __construct(
        public string $uit,
        public ?string $observatii = null,
        public ?string $refDeclarant = null,
    ) {
    }

    /**
     * @internal
     */
    public function toXml(\DOMDocument $document): \DOMElement
    {
        return Xml::element($document, 'notificareAnterioara', [
            'uit' => $this->uit,
        ], [
            'observatii' => $this->observatii,
            'refDeclarant' => $this->refDeclarant,
        ]);
    }
}
