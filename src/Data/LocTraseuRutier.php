<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

use slash197\eTransport\Support\Xml;

/**
 * Where the road journey in Romania starts or ends: an address, a border
 * crossing point or a customs office. Which of them an operation type
 * needs at each end is one of ANAF's business rules.
 */
final readonly class LocTraseuRutier
{
    /**
     * @param int|null $codPtf        Border crossing point, from Nomenclators::puncteTrecereFrontiera()
     * @param int|null $codBirouVamal Customs office, from Nomenclators::birouriVamale()
     */
    public function __construct(
        public ?Locatie $locatie = null,
        public ?int $codPtf = null,
        public ?int $codBirouVamal = null,
    ) {
    }

    public static function locatie(Locatie $locatie): self
    {
        return new self(locatie: $locatie);
    }

    public static function punctTrecereFrontiera(int $codPtf): self
    {
        return new self(codPtf: $codPtf);
    }

    public static function birouVamal(int $codBirouVamal): self
    {
        return new self(codBirouVamal: $codBirouVamal);
    }

    /**
     * @internal
     */
    public function toXml(\DOMDocument $document, string $name): \DOMElement
    {
        $element = Xml::element($document, $name, [], [
            'codPtf' => Xml::code($this->codPtf),
            'codBirouVamal' => Xml::code($this->codBirouVamal),
        ]);
        if ($this->locatie !== null) {
            $element->appendChild($this->locatie->toXml($document));
        }

        return $element;
    }
}
