<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

use slash197\eTransport\Support\Xml;

/**
 * The vehicle, the transport organiser and the day the transport starts.
 */
final readonly class DateTransport
{
    /**
     * @param string      $nrVehicul           Registration number, capitals and digits only
     * @param string      $codTaraOrgTransport Two-letter country code of the transport organiser
     * @param string|null $codOrgTransport     Tax identification code of the transport organiser
     */
    public function __construct(
        public string $nrVehicul,
        public string $codTaraOrgTransport,
        public string $denumireOrgTransport,
        public \DateTimeInterface $dataTransport,
        public ?string $codOrgTransport = null,
        public ?string $nrRemorca1 = null,
        public ?string $nrRemorca2 = null,
    ) {
    }

    /**
     * @internal
     */
    public function toXml(\DOMDocument $document): \DOMElement
    {
        return Xml::element($document, 'dateTransport', [
            'nrVehicul' => $this->nrVehicul,
            'codTaraOrgTransport' => $this->codTaraOrgTransport,
            'denumireOrgTransport' => $this->denumireOrgTransport,
            'dataTransport' => $this->dataTransport->format('Y-m-d'),
        ], [
            'nrRemorca1' => $this->nrRemorca1,
            'nrRemorca2' => $this->nrRemorca2,
            'codOrgTransport' => $this->codOrgTransport,
        ]);
    }
}
