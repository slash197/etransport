<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use slash197\eTransport\Data\BunuriTransportate;
use slash197\eTransport\Data\DateTransport;
use slash197\eTransport\Data\DocumentTransport;
use slash197\eTransport\Data\Judet;
use slash197\eTransport\Data\Locatie;
use slash197\eTransport\Data\LocTraseuRutier;
use slash197\eTransport\Data\Notificare;
use slash197\eTransport\Data\NotificareAnterioara;
use slash197\eTransport\Data\PartenerComercial;
use slash197\eTransport\Data\ScopOperatiune;
use slash197\eTransport\Data\TipConfirmare;
use slash197\eTransport\Data\TipDocument;
use slash197\eTransport\Data\TipOperatiune;
use slash197\eTransport\Message;
use slash197\eTransport\MessageType;
use slash197\eTransport\Validation\Validator;

/**
 * All data is made up; the UITs are the samples printed in ANAF's rules file.
 */
final class MessageTest extends TestCase
{
    private const UIT = '3V0P0L0P0T3JUW46';
    private const UIT_2 = '4C0U0C0J0W3DDQ92';

    public function testANotificationIsWrittenAsAnafExpectsIt(): void
    {
        $message = Message::notificare('10000001', self::national(), refDeclarant: 'REF-1');

        self::assertSame(MessageType::Notificare, $message->type);
        self::assertNull($message->uit);
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <eTransport xmlns="mfp:anaf:dgti:eTransport:declaratie:v2" codDeclarant="10000001" refDeclarant="REF-1">
              <notificare codTipOperatiune="30">
                <bunuriTransportate codScopOperatiune="101" denumireMarfa="Mobilier din lemn" cantitate="12.50" codUnitateMasura="H87" greutateBruta="510.25" codTarifar="94036010" greutateNeta="480" valoareLeiFaraTva="18250.00"/>
                <partenerComercial codTara="RO" denumire="Partener Exemplu SRL" cod="10000002"/>
                <dateTransport nrVehicul="B100ABC" codTaraOrgTransport="RO" denumireOrgTransport="Transportator Exemplu SRL" dataTransport="2026-10-12" nrRemorca1="B200ABC" codOrgTransport="10000003"/>
                <locStartTraseuRutier>
                  <locatie codJudet="12" denumireLocalitate="Cluj-Napoca" denumireStrada="Strada Exemplu" numar="1"/>
                </locStartTraseuRutier>
                <locFinalTraseuRutier>
                  <locatie codJudet="40" denumireLocalitate="Bucuresti" denumireStrada="Bulevardul Exemplu" numar="2"/>
                </locFinalTraseuRutier>
                <documenteTransport tipDocument="30" dataDocument="2026-10-10" numarDocument="F-100"/>
              </notificare>
            </eTransport>

            XML, $message->toXml());
    }

    #[DataProvider('messages')]
    public function testEveryKindOfMessagePassesValidation(Message $message, MessageType $type, ?string $uit): void
    {
        self::assertSame($type, $message->type);
        self::assertSame($uit, $message->uit);
        self::assertSame([], (new Validator())->validate($message)->all());
    }

    /**
     * @return iterable<string, array{Message, MessageType, string|null}>
     */
    public static function messages(): iterable
    {
        yield 'notification' => [Message::notificare('10000001', self::national()), MessageType::Notificare, null];
        yield 'notification after an outage' => [
            Message::notificare('10000001', self::national(), declPostAvarie: true),
            MessageType::Notificare,
            null,
        ];
        yield 'intra-community acquisition' => [
            Message::notificare('10000001', self::acquisition()),
            MessageType::Notificare,
            null,
        ];
        yield 'correction' => [Message::corectie('10000001', self::UIT, self::national()), MessageType::Corectie, self::UIT];
        yield 'deletion' => [Message::stergere('10000001', self::UIT, 'REF-2'), MessageType::Stergere, self::UIT];
        yield 'confirmation' => [
            Message::confirmare('10000001', self::UIT, TipConfirmare::ConfirmatPartial, 'Lipsesc doua colete'),
            MessageType::Confirmare,
            self::UIT,
        ];
        yield 'vehicle change' => [
            Message::modifVehicul('10000001', self::UIT, 'B300ABC', new \DateTimeImmutable('2026-10-12 08:30:00'), 'B400ABC'),
            MessageType::ModificareVehicul,
            self::UIT,
        ];
    }

    public function testACorrectionNamesTheNotificationItReplaces(): void
    {
        $xml = Message::corectie('10000001', self::UIT, self::national())->toXml();

        self::assertStringContainsString("<notificare codTipOperatiune=\"30\">\n    <corectie uit=\"" . self::UIT . "\"/>\n    <bunuriTransportate", $xml);
    }

    public function testTheSmallMessagesCarryTheirAttributes(): void
    {
        self::assertStringContainsString(
            '<eTransport xmlns="mfp:anaf:dgti:eTransport:declaratie:v2" codDeclarant="10000001" declPostAvarie="D">',
            Message::stergere('10000001', self::UIT, declPostAvarie: true)->toXml()
        );
        self::assertStringContainsString(
            '<confirmare uit="' . self::UIT . '" tipConfirmare="30" observatii="Marfa nu a sosit"/>',
            Message::confirmare('10000001', self::UIT, 30, 'Marfa nu a sosit')->toXml()
        );
        self::assertStringContainsString(
            '<modifVehicul uit="' . self::UIT . '" nrVehicul="B300ABC" dataModificare="2026-10-12T08:30:00" nrRemorca2="B500ABC" observatii="Defectiune"/>',
            Message::modifVehicul(
                '10000001',
                self::UIT,
                'B300ABC',
                new \DateTimeImmutable('2026-10-12 08:30:00', new \DateTimeZone('Europe/Bucharest')),
                nrRemorca2: 'B500ABC',
                observatii: 'Defectiune',
            )->toXml()
        );
    }

    public function testAnEmptyOptionalValueIsLeftOutAndAnEmptyRequiredOneIsReported(): void
    {
        $message = Message::notificare('10000001', new Notificare(
            TipOperatiune::TransportNational,
            [new BunuriTransportate(ScopOperatiune::Comercializare, '', 1, 'H87', 2, codTarifar: '')],
            new PartenerComercial('RO', 'Partener Exemplu SRL', '10000002'),
            self::transport(),
            LocTraseuRutier::locatie(new Locatie(Judet::Cluj, 'Cluj-Napoca', 'Strada Exemplu')),
            LocTraseuRutier::locatie(new Locatie(Judet::Bucuresti, 'Bucuresti', 'Bulevardul Exemplu')),
            [new DocumentTransport(TipDocument::AvizDeInsotire, new \DateTimeImmutable('2026-10-10'))],
        ));

        self::assertStringContainsString('denumireMarfa=""', $message->toXml());
        self::assertStringNotContainsString('codTarifar', $message->toXml());

        $violations = (new Validator())->validate($message);
        self::assertFalse($violations->isEmpty());
        self::assertStringContainsString('denumireMarfa', $violations->all()[0]->message);
    }

    public function testTextIsEscaped(): void
    {
        $xml = Message::confirmare('10000001', self::UIT, TipConfirmare::Confirmat, 'A & B "C" <d>')->toXml();

        self::assertStringContainsString('observatii="A &amp; B &quot;C&quot; &lt;d&gt;"', $xml);
        self::assertSame([], (new Validator())->validate($xml)->all());
    }

    public function testACountyIsFoundByAbbreviationOrNumber(): void
    {
        self::assertSame(Judet::Cluj, Judet::tryFromCode('CJ'));
        self::assertSame(Judet::Bucuresti, Judet::tryFromCode(' b '));
        self::assertSame(Judet::Cluj, Judet::tryFromCode('012'));
        self::assertSame(Judet::Giurgiu, Judet::tryFromCode('52'));
        self::assertNull(Judet::tryFromCode('XX'));
        self::assertNull(Judet::tryFromCode('45'));
        self::assertNull(Judet::tryFromCode(''));

        $abbreviations = (new \ReflectionClassConstant(Judet::class, 'ABBREVIATIONS'))->getValue();
        self::assertIsArray($abbreviations);
        self::assertEqualsCanonicalizing(Judet::cases(), array_values($abbreviations));
    }

    private static function national(): Notificare
    {
        return new Notificare(
            TipOperatiune::TransportNational,
            [new BunuriTransportate(
                ScopOperatiune::Comercializare,
                'Mobilier din lemn',
                12.5,
                'H87',
                '510.25',
                codTarifar: '94036010',
                greutateNeta: 480,
                valoareLeiFaraTva: 18250.0,
            )],
            new PartenerComercial('RO', 'Partener Exemplu SRL', '10000002'),
            self::transport(),
            LocTraseuRutier::locatie(new Locatie(Judet::Cluj, 'Cluj-Napoca', 'Strada Exemplu', '1')),
            LocTraseuRutier::locatie(new Locatie(Judet::Bucuresti, 'Bucuresti', 'Bulevardul Exemplu', '2')),
            [new DocumentTransport(TipDocument::AvizDeInsotire, new \DateTimeImmutable('2026-10-10'), 'F-100')],
        );
    }

    private static function acquisition(): Notificare
    {
        return new Notificare(
            TipOperatiune::AchizitieIntracomunitara,
            [new BunuriTransportate(ScopOperatiune::Comercializare, 'Mobilier din lemn', 3, 'H87', 120.4, codTarifar: '94036010', greutateNeta: 110, valoareLeiFaraTva: 4000)],
            new PartenerComercial('HU', 'Example Partner Kft', 'HU10000004'),
            self::transport(),
            LocTraseuRutier::punctTrecereFrontiera(2),
            LocTraseuRutier::locatie(new Locatie(Judet::Cluj, 'Cluj-Napoca', 'Strada Exemplu', '1')),
            [new DocumentTransport(TipDocument::Cmr, new \DateTimeImmutable('2026-10-10'), 'CMR-7')],
            [new NotificareAnterioara(self::UIT_2, 'Transport anterior')],
        );
    }

    private static function transport(): DateTransport
    {
        return new DateTransport(
            'B100ABC',
            'RO',
            'Transportator Exemplu SRL',
            new \DateTimeImmutable('2026-10-12'),
            codOrgTransport: '10000003',
            nrRemorca1: 'B200ABC',
        );
    }
}
