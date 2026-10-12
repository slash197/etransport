<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

use slash197\eTransport\Support\Xml;

/**
 * A transport of goods: what a notification declares and what a correction replaces.
 */
final readonly class Notificare
{
    /**
     * @param list<BunuriTransportate>   $bunuriTransportate   At least one
     * @param list<DocumentTransport>    $documenteTransport   At least one
     * @param list<NotificareAnterioara> $notificareAnterioara
     */
    public function __construct(
        public TipOperatiune|int $codTipOperatiune,
        public array $bunuriTransportate,
        public PartenerComercial $partenerComercial,
        public DateTransport $dateTransport,
        public LocTraseuRutier $locStartTraseuRutier,
        public LocTraseuRutier $locFinalTraseuRutier,
        public array $documenteTransport,
        public array $notificareAnterioara = [],
    ) {
    }

    /**
     * @param string|null $corectie UIT of the notification this one replaces
     *
     * @internal
     */
    public function toXml(\DOMDocument $document, ?string $corectie = null): \DOMElement
    {
        $element = Xml::element($document, 'notificare', ['codTipOperatiune' => Xml::code($this->codTipOperatiune)]);

        if ($corectie !== null) {
            $element->appendChild(Xml::element($document, 'corectie', ['uit' => $corectie]));
        }
        foreach ($this->bunuriTransportate as $bunuri) {
            $element->appendChild($bunuri->toXml($document));
        }
        $element->appendChild($this->partenerComercial->toXml($document));
        $element->appendChild($this->dateTransport->toXml($document));
        $element->appendChild($this->locStartTraseuRutier->toXml($document, 'locStartTraseuRutier'));
        $element->appendChild($this->locFinalTraseuRutier->toXml($document, 'locFinalTraseuRutier'));
        foreach ($this->documenteTransport as $documentTransport) {
            $element->appendChild($documentTransport->toXml($document));
        }
        foreach ($this->notificareAnterioara as $anterioara) {
            $element->appendChild($anterioara->toXml($document));
        }

        return $element;
    }
}
