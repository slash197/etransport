<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

use slash197\eTransport\Support\Xml;

/**
 * One kind of goods being transported.
 */
final readonly class BunuriTransportate
{
    /**
     * @param string                $codUnitateMasura  UN/ECE Recommendation 20 or 21 unit code, such as "KGM" or "H87"
     * @param string|int|float      $greutateBruta     In kilograms
     * @param string|null           $codTarifar        Combined Nomenclature code of 4, 6 or 8 digits
     * @param string|int|float|null $greutateNeta      In kilograms
     * @param string|int|float|null $valoareLeiFaraTva
     */
    public function __construct(
        public ScopOperatiune|int $codScopOperatiune,
        public string $denumireMarfa,
        public string|int|float $cantitate,
        public string $codUnitateMasura,
        public string|int|float $greutateBruta,
        public ?string $codTarifar = null,
        public string|int|float|null $greutateNeta = null,
        public string|int|float|null $valoareLeiFaraTva = null,
        public ?string $refDeclarant = null,
    ) {
    }

    /**
     * @internal
     */
    public function toXml(\DOMDocument $document): \DOMElement
    {
        return Xml::element($document, 'bunuriTransportate', [
            'codScopOperatiune' => Xml::code($this->codScopOperatiune),
            'denumireMarfa' => $this->denumireMarfa,
            'cantitate' => Xml::decimal($this->cantitate),
            'codUnitateMasura' => $this->codUnitateMasura,
            'greutateBruta' => Xml::decimal($this->greutateBruta),
        ], [
            'codTarifar' => $this->codTarifar,
            'greutateNeta' => Xml::decimal($this->greutateNeta),
            'valoareLeiFaraTva' => Xml::decimal($this->valoareLeiFaraTva),
            'refDeclarant' => $this->refDeclarant,
        ]);
    }
}
