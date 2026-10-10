<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Support;

/**
 * The documents the validator is tested with. Each is a valid document or
 * another case with a few changes applied, so that a case shows exactly
 * what it breaks. All data is made up; the UITs are the samples printed in
 * ANAF's rules file.
 *
 * What each case must produce is not written here: it is recorded in
 * tests/golden/rules.json by a reference Schematron processor (make golden).
 */
final class Cases
{
    public const NS = 'mfp:anaf:dgti:eTransport:declaratie:v2';

    private const UIT = '3V0P0L0P0T3JUW46';
    private const UIT_2 = '4C0U0C0J0W3DDQ92';

    private const ROOT = '/x:eTransport';
    private const NOTIFICATION = '//x:notificare';
    private const GOODS = '//x:bunuriTransportate';
    private const PARTNER = '//x:partenerComercial';
    private const TRANSPORT = '//x:dateTransport';
    private const START = '//x:locStartTraseuRutier';
    private const END = '//x:locFinalTraseuRutier';
    private const DOCUMENT = '//x:documenteTransport';

    private const NATIONAL = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <eTransport xmlns="mfp:anaf:dgti:eTransport:declaratie:v2" codDeclarant="10000001" refDeclarant="REF-1">
          <notificare codTipOperatiune="30">
            <bunuriTransportate codScopOperatiune="101" codTarifar="94036010" denumireMarfa="Mobilier din lemn" cantitate="12.50" codUnitateMasura="H87" greutateNeta="480.00" greutateBruta="510.25" valoareLeiFaraTva="18250.00"/>
            <partenerComercial codTara="RO" cod="10000002" denumire="Partener Exemplu SRL"/>
            <dateTransport nrVehicul="B100ABC" nrRemorca1="B200ABC" codTaraOrgTransport="RO" codOrgTransport="10000003" denumireOrgTransport="Transportator Exemplu SRL" dataTransport="2026-10-12"/>
            <locStartTraseuRutier>
              <locatie codJudet="12" denumireLocalitate="Cluj-Napoca" denumireStrada="Strada Exemplu" numar="1"/>
            </locStartTraseuRutier>
            <locFinalTraseuRutier>
              <locatie codJudet="40" denumireLocalitate="Bucuresti" denumireStrada="Bulevardul Exemplu" numar="2"/>
            </locFinalTraseuRutier>
            <documenteTransport tipDocument="30" numarDocument="F-100" dataDocument="2026-10-10"/>
          </notificare>
        </eTransport>
        XML;

    private const DELETION = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <eTransport xmlns="mfp:anaf:dgti:eTransport:declaratie:v2" codDeclarant="10000001">
          <stergere uit="3V0P0L0P0T3JUW46"/>
        </eTransport>
        XML;

    private const CONFIRMATION = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <eTransport xmlns="mfp:anaf:dgti:eTransport:declaratie:v2" codDeclarant="10000001">
          <confirmare uit="3V0P0L0P0T3JUW46" tipConfirmare="10"/>
        </eTransport>
        XML;

    private const VEHICLE_CHANGE = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <eTransport xmlns="mfp:anaf:dgti:eTransport:declaratie:v2" codDeclarant="10000001">
          <modifVehicul uit="3V0P0L0P0T3JUW46" nrVehicul="B300ABC" dataModificare="2026-10-12T08:30:00"/>
        </eTransport>
        XML;

    /**
     * @return array<string, string> Case name to XML
     */
    public static function all(): array
    {
        $documents = [];
        foreach (array_keys(self::definitions()) as $name) {
            $documents[$name] = self::build($name);
        }

        return $documents;
    }

    /**
     * Cases a reference XPath 2 processor cannot finish: it stops with an
     * error where this package reports rules. The list is what the package
     * is expected to report for each.
     *
     * @return array<string, list<string>>
     */
    public static function whereTheReferenceStops(): array
    {
        return [
            'stops: quantity is not a number' => ['BR-027'],
            'stops: gross weight is empty' => ['BR-020', 'BR-029', 'BR-218'],
            'stops: two documents have remarks' => [],
        ];
    }

    /**
     * @return array<string, array{string, list<list<mixed>>}> Case name to the case or document it starts from, and the changes
     */
    private static function definitions(): array
    {
        $national = 'valid national transport';
        $acquisition = 'valid intra-community acquisition';
        $delivery = 'valid intra-community delivery';
        $import = 'valid import';
        $export = 'valid export';
        $deletion = 'valid deletion';
        $confirmation = 'valid confirmation';
        $vehicleChange = 'valid vehicle change';
        $correction = 'valid correction';

        $previous = ['add', self::NOTIFICATION, 'notificareAnterioara', ['uit' => self::UIT_2]];
        $startAt = static fn (string $attribute, string $code): array => [
            ['remove', self::START . '/x:locatie'],
            ['set', self::START, $attribute, $code],
        ];
        $endAt = static fn (string $attribute, string $code): array => [
            ['remove', self::END . '/x:locatie'],
            ['set', self::END, $attribute, $code],
        ];
        $goods = static fn (string $attribute, ?string $value): array => [['set', self::GOODS, $attribute, $value]];

        return [
            // Valid documents
            $national => [self::NATIONAL, []],
            $deletion => [self::DELETION, []],
            $confirmation => [self::CONFIRMATION, []],
            $vehicleChange => [self::VEHICLE_CHANGE, []],
            $acquisition => [$national, [
                ['set', self::NOTIFICATION, 'codTipOperatiune', '10'],
                ['set', self::PARTNER, 'codTara', 'DE'],
                ['set', self::PARTNER, 'cod', 'DE123456789'],
                ...$startAt('codPtf', '4'),
            ]],
            $delivery => [$national, [
                ['set', self::NOTIFICATION, 'codTipOperatiune', '20'],
                ['set', self::PARTNER, 'codTara', 'DE'],
                ['set', self::PARTNER, 'cod', 'DE123456789'],
                ...$endAt('codPtf', '4'),
            ]],
            $import => [$national, [
                ['set', self::NOTIFICATION, 'codTipOperatiune', '40'],
                ['set', self::GOODS, 'codScopOperatiune', '9999'],
                ['set', self::PARTNER, 'codTara', 'TR'],
                ['set', self::PARTNER, 'cod', 'TR1234567890'],
                ...$startAt('codBirouVamal', '132901'),
            ]],
            $export => [$national, [
                ['set', self::NOTIFICATION, 'codTipOperatiune', '50'],
                ['set', self::GOODS, 'codScopOperatiune', '9999'],
                ['set', self::PARTNER, 'codTara', 'TR'],
                ['set', self::PARTNER, 'cod', 'TR1234567890'],
                ...$endAt('codBirouVamal', '132901'),
            ]],
            $correction => [$national, [['add', self::NOTIFICATION, 'corectie', ['uit' => self::UIT], self::GOODS]]],
            'valid entry for storage' => [$acquisition, [
                ['set', self::NOTIFICATION, 'codTipOperatiune', '60'],
                ['set', self::GOODS, 'codScopOperatiune', '9999'],
                ['set', self::GOODS, 'codTarifar', null],
                ['set', self::GOODS, 'greutateNeta', null],
                ['set', self::GOODS, 'valoareLeiFaraTva', null],
            ]],
            'valid processing entry' => [$acquisition, [
                ['set', self::NOTIFICATION, 'codTipOperatiune', '12'],
                ['set', self::GOODS, 'codScopOperatiune', '9999'],
            ]],
            'valid with a previous notification' => [$acquisition, [$previous]],
            'valid after a system failure' => [$national, [['set', self::ROOT, 'declPostAvarie', 'D']]],
            'valid other document with remarks' => [$national, [
                ['set', self::DOCUMENT, 'tipDocument', '9999'],
                ['set', self::DOCUMENT, 'observatii', 'Aviz intern'],
            ]],
            'valid partner without a tax code' => [$national, [['set', self::PARTNER, 'cod', 'PF']]],
            'valid organiser without a tax code' => [$national, [['set', self::TRANSPORT, 'codOrgTransport', 'PF']]],
            'valid foreign organiser' => [$acquisition, [
                ['set', self::TRANSPORT, 'codTaraOrgTransport', 'DE'],
                ['set', self::TRANSPORT, 'codOrgTransport', 'DE999'],
            ]],
            'valid partner in Greece' => [$acquisition, [['set', self::PARTNER, 'codTara', 'EL']]],
            'valid partner in Kosovo' => [$import, [['set', self::PARTNER, 'codTara', 'XK']]],
            'valid value of zero' => [$national, $goods('valoareLeiFaraTva', '0')],
            'valid personal code as declarant' => [$national, [['set', self::ROOT, 'codDeclarant', '1900101123456']]],
            'valid two lines of goods' => [$national, [['add', self::NOTIFICATION, 'bunuriTransportate', [
                'codScopOperatiune' => '101',
                'codTarifar' => '4407',
                'denumireMarfa' => 'Cherestea',
                'cantitate' => '3',
                'codUnitateMasura' => 'MTQ',
                'greutateNeta' => '1500',
                'greutateBruta' => '1500',
                'valoareLeiFaraTva' => '4200.5',
            ], self::PARTNER]]],
            'valid two trailers' => [$national, [['set', self::TRANSPORT, 'nrRemorca2', 'B300ABC']]],

            // The message itself
            'BT-TY-005 two messages' => [$deletion, [['add', self::ROOT, 'confirmare', ['uit' => self::UIT, 'tipConfirmare' => '10']]]],
            'BT-TY-005 no message' => [$deletion, [['remove', '//x:stergere']]],
            'BR-002 declarant code starts with zero' => [$national, [['set', self::ROOT, 'codDeclarant', '0123']]],
            'BR-002 declarant code has a country prefix' => [$national, [['set', self::ROOT, 'codDeclarant', 'RO10000001']]],
            'BR-201 unknown post-failure flag' => [$national, [['set', self::ROOT, 'declPostAvarie', 'X']]],
            'BR-201 accepts any text containing D' => [$national, [['set', self::ROOT, 'declPostAvarie', 'ND']]],

            // UIT
            'BR-019 wrong check digits' => [$deletion, [['set', '//x:stergere', 'uit', '3V0P0L0P0T3JUW47']]],
            'BR-019 too short' => [$deletion, [['set', '//x:stergere', 'uit', 'ABC']]],
            'BR-019 lower case' => [$deletion, [['set', '//x:stergere', 'uit', '3v0p0l0p0t3juw46']]],
            'BR-019 forbidden letters with right check digits' => [$deletion, [['set', '//x:stergere', 'uit', 'OOOOOOOOOOOOOO06']]],
            'BR-019 in a correction' => [$correction, [['set', '//x:corectie', 'uit', '3V0P0L0P0T3JUW00']]],
            'BR-019 in a previous notification' => [$acquisition, [['add', self::NOTIFICATION, 'notificareAnterioara', ['uit' => '4C0U0C0J0W3DDQ00']]]],
            'BR-019 in a confirmation' => [$confirmation, [['set', '//x:confirmare', 'uit', '3V0P0L0P0T3JUW00']]],
            'BR-008 empty in a deletion' => [$deletion, [['set', '//x:stergere', 'uit', '']]],
            'BR-008 missing in a deletion' => [$deletion, [['set', '//x:stergere', 'uit', null]]],
            'BR-008 missing in a correction' => [$correction, [['set', '//x:corectie', 'uit', null]]],
            'BR-008 missing in a confirmation' => [$confirmation, [['set', '//x:confirmare', 'uit', null]]],
            'BR-008 missing in a vehicle change' => [$vehicleChange, [['set', '//x:modifVehicul', 'uit', null]]],
            'BR-008 blank in a previous notification' => [$acquisition, [['add', self::NOTIFICATION, 'notificareAnterioara', ['uit' => '  ']]]],

            // Vehicle change
            'BR-202 no change date' => [$vehicleChange, [['set', '//x:modifVehicul', 'dataModificare', null]]],
            'BR-203 change date without a time' => [$vehicleChange, [['set', '//x:modifVehicul', 'dataModificare', '2026-10-12']]],
            'BR-204 no vehicle' => [$vehicleChange, [['set', '//x:modifVehicul', 'nrVehicul', null]]],
            'BR-204 blank vehicle' => [$vehicleChange, [['set', '//x:modifVehicul', 'nrVehicul', ' ']]],
            'BR-031 vehicle in a vehicle change' => [$vehicleChange, [['set', '//x:modifVehicul', 'nrVehicul', 'b-1']]],

            // Operation type against partner, organiser and route
            'BR-004 acquisition from outside the EU' => [$acquisition, [['set', self::PARTNER, 'codTara', 'US']]],
            'BR-004 acquisition from Romania' => [$acquisition, [['set', self::PARTNER, 'codTara', 'RO']]],
            'BR-004 delivery to Romania' => [$delivery, [['set', self::PARTNER, 'codTara', 'RO']]],
            'BR-005 national transport with a foreign partner' => [$national, [['set', self::PARTNER, 'codTara', 'DE']]],
            'BR-006 import from the EU' => [$import, [['set', self::PARTNER, 'codTara', 'DE']]],
            'BR-006 export to Romania' => [$export, [['set', self::PARTNER, 'codTara', 'RO']]],
            'BR-007 partner code is not a Romanian tax code' => [$national, [['set', self::PARTNER, 'cod', 'X1']]],
            'BR-007 partner code missing' => [$national, [['set', self::PARTNER, 'cod', null]]],
            'BR-025 previous notification on national transport' => [$national, [$previous]],
            'BR-025 previous notification on import' => [$import, [$previous]],
            'BR-043 organiser code is not a Romanian tax code' => [$acquisition, [['set', self::TRANSPORT, 'codOrgTransport', 'ABC']]],
            'BR-043 organiser code missing' => [$acquisition, [['set', self::TRANSPORT, 'codOrgTransport', null]]],
            'BR-209 organiser code on national transport' => [$national, [['set', self::TRANSPORT, 'codOrgTransport', 'ABC']]],
            'BR-046 customs office on national transport' => [$national, $startAt('codBirouVamal', '132901')],
            'BR-212 border crossing at the start of national transport' => [$national, $startAt('codPtf', '4')],
            'BR-212 border crossing at the start of an export' => [$export, $startAt('codPtf', '4')],
            'BR-213 border crossing at the end of national transport' => [$national, $endAt('codPtf', '4')],
            'BR-213 border crossing at the end of an import' => [$import, $endAt('codPtf', '4')],
            'BR-210 start has a place and a customs office' => [$acquisition, [['set', self::START, 'codBirouVamal', '132901']]],
            'BR-210 start has nothing' => [$national, [['remove', self::START . '/x:locatie']]],
            'BR-211 end has a place and a customs office' => [$delivery, [['set', self::END, 'codBirouVamal', '132901']]],
            'BR-211 end has nothing' => [$national, [['remove', self::END . '/x:locatie']]],
            'BR-216 customs office at the start of an export' => [$export, $startAt('codBirouVamal', '132901')],
            'BR-217 customs office at the end of an import' => [$import, $endAt('codBirouVamal', '132901')],

            // Goods
            'BR-218 no gross weight' => [$national, $goods('greutateBruta', null)],
            'BR-020 gross weight below net weight' => [$national, $goods('greutateBruta', '100')],
            'BR-068 purpose not allowed for an acquisition' => [$acquisition, $goods('codScopOperatiune', '704')],
            'BR-069 purpose not allowed for a delivery' => [$delivery, $goods('codScopOperatiune', '201')],
            'BR-070 purpose not allowed for national transport' => [$national, $goods('codScopOperatiune', '201')],
            'BR-205 purpose not allowed for an import' => [$import, $goods('codScopOperatiune', '101')],
            'BR-205 purpose not allowed for a processing entry' => [$acquisition, [['set', self::NOTIFICATION, 'codTipOperatiune', '12']]],
            'BR-206 no tariff code' => [$national, $goods('codTarifar', null)],
            'BR-207 no net weight' => [$national, $goods('greutateNeta', null)],
            'BR-208 no value' => [$national, $goods('valoareLeiFaraTva', null)],
            'BR-027 quantity of zero' => [$national, $goods('cantitate', '0')],
            'BR-027 negative quantity' => [$national, $goods('cantitate', '-5')],
            'BR-035 quantity with three decimals' => [$national, $goods('cantitate', '1.234')],
            'BR-036 quantity with thirteen digits' => [$national, $goods('cantitate', '1234567890123.5')],
            'BR-036 accepts thirteen digits without a decimal point' => [$national, $goods('cantitate', '1234567890123')],
            'BR-028 net weight of zero' => [$national, $goods('greutateNeta', '0')],
            'BR-037 net weight with three decimals' => [$national, $goods('greutateNeta', '1.234')],
            'BR-038 net weight with thirteen digits' => [$national, $goods('greutateNeta', '1234567890123.5')],
            'BR-029 gross weight of zero' => [$national, $goods('greutateBruta', '0')],
            'BR-039 gross weight with three decimals' => [$national, $goods('greutateBruta', '600.123')],
            'BR-040 gross weight with thirteen digits' => [$national, $goods('greutateBruta', '1234567890123.5')],
            'BR-030 negative value' => [$national, $goods('valoareLeiFaraTva', '-1')],
            'BR-041 value with three decimals' => [$national, $goods('valoareLeiFaraTva', '1.234')],
            'BR-042 value with thirteen digits' => [$national, $goods('valoareLeiFaraTva', '1234567890123.5')],
            'BR-034 tariff code with two digits' => [$national, $goods('codTarifar', '12')],
            'BR-034 tariff code with five digits' => [$national, $goods('codTarifar', '12345')],
            'BR-034 tariff code with ten digits' => [$national, $goods('codTarifar', '1234567890')],
            'BR-034 tariff code with letters' => [$national, $goods('codTarifar', '9403601A')],
            'BR-027 on both of two lines' => ['valid two lines of goods', $goods('cantitate', '0')],

            // Vehicles and places
            'BR-031 vehicle in lower case' => [$national, [['set', self::TRANSPORT, 'nrVehicul', 'b-1']]],
            'BR-032 first trailer' => [$national, [['set', self::TRANSPORT, 'nrRemorca1', 'x']]],
            'BR-033 second trailer' => [$national, [['set', self::TRANSPORT, 'nrRemorca2', 'y']]],
            'BR-214 town name of one letter' => [$national, [['set', self::START . '/x:locatie', 'denumireLocalitate', 'X']]],
            'BR-214 town name too long' => [$national, [['set', self::END . '/x:locatie', 'denumireLocalitate', str_repeat('a', 101)]]],
            'BR-215 street name of one letter' => [$national, [['set', self::END . '/x:locatie', 'denumireStrada', 'Y']]],
            'BR-026 other document without remarks' => [$national, [['set', self::DOCUMENT, 'tipDocument', '9999']]],

            // Code lists
            'BR-CL-001 unknown country' => [$acquisition, [['set', self::PARTNER, 'codTara', 'ZZ']]],
            'BR-CL-001 Greece as GR' => [$acquisition, [['set', self::PARTNER, 'codTara', 'GR']]],
            'BR-CL-001 country in lower case' => [$national, [['set', self::PARTNER, 'codTara', 'ro']]],
            'BR-CL-010 unknown organiser country' => [$national, [['set', self::TRANSPORT, 'codTaraOrgTransport', 'ZZ']]],
            'BR-CL-002 unknown county' => [$national, [['set', self::START . '/x:locatie', 'codJudet', '41']]],
            'BR-CL-003 unknown unit of measure' => [$national, $goods('codUnitateMasura', 'QQQ')],
            'BR-CL-004 unknown operation type' => [$national, [['set', self::NOTIFICATION, 'codTipOperatiune', '99']]],
            'BR-CL-005 unknown purpose' => [$national, $goods('codScopOperatiune', '1234')],
            'BR-CL-006 unknown border crossing' => [$acquisition, [['set', self::START, 'codPtf', '999']]],
            'BR-CL-007 unknown customs office' => [$import, [['set', self::START, 'codBirouVamal', '1']]],
            'BR-CL-008 unknown confirmation type' => [$confirmation, [['set', '//x:confirmare', 'tipConfirmare', '99']]],
            'BR-CL-009 unknown document type' => [$national, [['set', self::DOCUMENT, 'tipDocument', '77']]],

            // See whereTheReferenceStops()
            'stops: quantity is not a number' => [$national, $goods('cantitate', 'abc')],
            'stops: gross weight is empty' => [$national, $goods('greutateBruta', '')],
            'stops: two documents have remarks' => [$national, [
                ['set', self::DOCUMENT, 'tipDocument', '9999'],
                ['set', self::DOCUMENT, 'observatii', 'Aviz intern'],
                ['add', self::NOTIFICATION, 'documenteTransport', ['tipDocument' => '10', 'dataDocument' => '2026-10-10', 'observatii' => 'CMR']],
            ]],
        ];
    }

    private static function build(string $name): string
    {
        [$from, $changes] = self::definitions()[$name];

        $document = new \DOMDocument();
        $document->loadXML(isset(self::definitions()[$from]) ? self::build($from) : $from);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', self::NS);

        foreach ($changes as $change) {
            $nodes = $xpath->query($change[1]);
            if ($nodes === false || $nodes->length === 0) {
                throw new \LogicException("Case \"{$name}\": nothing matches {$change[1]}.");
            }

            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                if ($change[0] === 'set' && $change[3] === null) {
                    $node->removeAttribute($change[2]);
                } elseif ($change[0] === 'set') {
                    $node->setAttribute($change[2], $change[3]);
                } elseif ($change[0] === 'remove') {
                    $node->parentNode?->removeChild($node);
                } elseif ($change[0] === 'add') {
                    $element = $document->createElementNS(self::NS, $change[2]);
                    foreach ($change[3] as $attribute => $value) {
                        $element->setAttribute($attribute, $value);
                    }
                    $before = isset($change[4]) ? $xpath->query($change[4], $node) : false;
                    $before = $before === false ? null : $before->item(0);
                    $node->insertBefore($element, $before instanceof \DOMNode ? $before : null);
                }
            }
        }

        return (string) $document->saveXML();
    }
}
