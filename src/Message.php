<?php

declare(strict_types=1);

namespace slash197\eTransport;

use slash197\eTransport\Data\Notificare;
use slash197\eTransport\Data\TipConfirmare;
use slash197\eTransport\Support\Xml;

/**
 * One message for ANAF, described with typed objects and written as the
 * XML document ANAF takes.
 *
 * Every named constructor ends with the same two arguments: the
 * declarant's own reference for the message, and whether it is declared
 * after an outage of ANAF's system (declPostAvarie).
 */
final readonly class Message
{
    /**
     * @param \Closure(\DOMDocument): \DOMElement $body
     */
    private function __construct(
        public MessageType $type,
        public string $codDeclarant,
        public ?string $uit,
        public ?string $refDeclarant,
        public bool $declPostAvarie,
        private \Closure $body,
    ) {
    }

    /**
     * Declares a transport. ANAF answers with the UIT that identifies it.
     *
     * @param string $codDeclarant Tax identification code (CUI, CNP or NIF) of the declarant, digits only
     */
    public static function notificare(
        string $codDeclarant,
        Notificare $notificare,
        ?string $refDeclarant = null,
        bool $declPostAvarie = false,
    ): self {
        return new self(
            MessageType::Notificare,
            $codDeclarant,
            null,
            $refDeclarant,
            $declPostAvarie,
            static fn (\DOMDocument $document): \DOMElement => $notificare->toXml($document),
        );
    }

    /**
     * Replaces the whole content of a notification that was already declared.
     *
     * @param string $uit UIT of the notification to correct
     */
    public static function corectie(
        string $codDeclarant,
        string $uit,
        Notificare $notificare,
        ?string $refDeclarant = null,
        bool $declPostAvarie = false,
    ): self {
        return new self(
            MessageType::Corectie,
            $codDeclarant,
            $uit,
            $refDeclarant,
            $declPostAvarie,
            static fn (\DOMDocument $document): \DOMElement => $notificare->toXml($document, $uit),
        );
    }

    /**
     * Withdraws a notification.
     */
    public static function stergere(
        string $codDeclarant,
        string $uit,
        ?string $refDeclarant = null,
        bool $declPostAvarie = false,
    ): self {
        return new self(
            MessageType::Stergere,
            $codDeclarant,
            $uit,
            $refDeclarant,
            $declPostAvarie,
            static fn (\DOMDocument $document): \DOMElement => Xml::element($document, 'stergere', ['uit' => $uit]),
        );
    }

    /**
     * Confirms, in full or in part, or denies that the goods of a notification were received.
     */
    public static function confirmare(
        string $codDeclarant,
        string $uit,
        TipConfirmare|int $tipConfirmare,
        ?string $observatii = null,
        ?string $refDeclarant = null,
        bool $declPostAvarie = false,
    ): self {
        return new self(
            MessageType::Confirmare,
            $codDeclarant,
            $uit,
            $refDeclarant,
            $declPostAvarie,
            static fn (\DOMDocument $document): \DOMElement => Xml::element($document, 'confirmare', [
                'uit' => $uit,
                'tipConfirmare' => Xml::code($tipConfirmare),
            ], [
                'observatii' => $observatii,
            ]),
        );
    }

    /**
     * Changes the vehicle of a notification, for instance after a breakdown on the road.
     *
     * @param \DateTimeInterface $dataModificare When the vehicle changes; written as local time, without an offset
     */
    public static function modifVehicul(
        string $codDeclarant,
        string $uit,
        string $nrVehicul,
        \DateTimeInterface $dataModificare,
        ?string $nrRemorca1 = null,
        ?string $nrRemorca2 = null,
        ?string $observatii = null,
        ?string $refDeclarant = null,
        bool $declPostAvarie = false,
    ): self {
        return new self(
            MessageType::ModificareVehicul,
            $codDeclarant,
            $uit,
            $refDeclarant,
            $declPostAvarie,
            static fn (\DOMDocument $document): \DOMElement => Xml::element($document, 'modifVehicul', [
                'uit' => $uit,
                'nrVehicul' => $nrVehicul,
                'dataModificare' => $dataModificare->format('Y-m-d\TH:i:s'),
            ], [
                'nrRemorca1' => $nrRemorca1,
                'nrRemorca2' => $nrRemorca2,
                'observatii' => $observatii,
            ]),
        );
    }

    public function toXml(): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = Xml::element($document, 'eTransport', ['codDeclarant' => $this->codDeclarant], [
            'refDeclarant' => $this->refDeclarant,
            'declPostAvarie' => $this->declPostAvarie ? 'D' : null,
        ]);
        $root->appendChild(($this->body)($document));
        $document->appendChild($root);

        return (string) $document->saveXML();
    }
}
