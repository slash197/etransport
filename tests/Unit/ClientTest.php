<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use slash197\eTransport\Client;
use slash197\eTransport\Environment;
use slash197\eTransport\Exception\ApiException;
use slash197\eTransport\Message;
use slash197\eTransport\MessageType;
use slash197\eTransport\Response\OutcomeState;
use slash197\eTransport\Tests\Support\Cases;
use slash197\eTransport\Tests\Support\FakeHttpClient;

/**
 * The responses are the examples of ANAF's API description, with made-up identifiers.
 */
final class ClientTest extends TestCase
{
    private const UIT = '3V0P0L0P0T3JUW46';
    private const TEST = 'https://api.anaf.ro/test/ETRANSPORT/ws/v1';

    private FakeHttpClient $http;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
    }

    public function testAMessageIsUploadedForItsDeclarant(): void
    {
        $this->http->respond([
            'dateResponse' => '202610121130',
            'ExecutionStatus' => 0,
            'index_incarcare' => 5001,
            'UIT' => self::UIT,
            'trace_id' => 'trace-1',
            'ref_declarant' => '',
        ]);
        $message = Message::stergere('10000001', self::UIT);

        $upload = $this->client()->upload($message);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::TEST . '/upload/ETRANSP/10000001/2', (string) $request->getUri());
        self::assertSame('Bearer token-1', $request->getHeaderLine('Authorization'));
        self::assertSame('application/xml', $request->getHeaderLine('Content-Type'));
        self::assertSame($message->toXml(), (string) $request->getBody());

        self::assertSame('5001', $upload->idIncarcare);
        self::assertSame(self::UIT, $upload->uit);
        self::assertSame('trace-1', $upload->traceId);
        self::assertSame(5001, $upload->raw['index_incarcare']);
    }

    public function testAnXmlDocumentIsUploadedForTheGivenTaxCode(): void
    {
        $this->http->respond(['ExecutionStatus' => 0, 'index_incarcare' => '5002']);
        $xml = Cases::all()['valid deletion'];

        $upload = (new Client($this->http, 'token-1'))->upload($xml, '10000009');

        self::assertSame('https://api.anaf.ro/prod/ETRANSPORT/ws/v1/upload/ETRANSP/10000009/2', (string) $this->http->last()->getUri());
        self::assertSame($xml, (string) $this->http->last()->getBody());
        self::assertNull($upload->uit);

        $this->expectException(\InvalidArgumentException::class);
        (new Client($this->http, 'token-1'))->upload($xml);
    }

    public function testTheTokenCanComeFromAFunction(): void
    {
        $this->http->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(['stare' => 'ok', 'ExecutionStatus' => 0]);
        $tokens = ['first', 'second'];
        $client = new Client($this->http, static function () use (&$tokens): string {
            return (string) array_shift($tokens);
        });

        $client->status('1');
        $client->status('1');

        self::assertSame('Bearer first', $this->http->requests[0]->getHeaderLine('Authorization'));
        self::assertSame('Bearer second', $this->http->requests[1]->getHeaderLine('Authorization'));
    }

    public function testAnUploadAnafDidNotTakeIsAnException(): void
    {
        $this->http->respond([
            'Errors' => [['errorMessage' => 'Nu aveti drept in SPV pentru CIF=10000001']],
            'dateResponse' => '202610121416',
            'ExecutionStatus' => 1,
            'trace_id' => 'trace-2',
        ]);

        try {
            $this->client()->upload(Message::stergere('10000001', self::UIT));
            self::fail('No exception.');
        } catch (ApiException $exception) {
            self::assertSame('e-Transport upload failed: Nu aveti drept in SPV pentru CIF=10000001', $exception->getMessage());
            self::assertSame(['Nu aveti drept in SPV pentru CIF=10000001'], $exception->errors);
            self::assertSame('trace-2', $exception->traceId);
            self::assertFalse($exception->isRetryable());
        }
    }

    public function testErrorTextsAreCleanedOfTheirWrapping(): void
    {
        $this->http->respond([
            'Errors' => [
                ['errorMessage' => 'E: validari globale; textEroare=[BR-027]-Cantitatea  NU TREBUIE sa ia valori negative; cod=1'],
                ['errorMessage' => "[BR-027]-Cantitatea NU TREBUIE\n sa ia valori negative"],
                ['errorMessage' => ''],
            ],
            'ExecutionStatus' => 1,
        ]);

        try {
            $this->client()->upload(Message::stergere('10000001', self::UIT));
            self::fail('No exception.');
        } catch (ApiException $exception) {
            self::assertSame(['Cantitatea NU TREBUIE sa ia valori negative'], $exception->errors);
        }
    }

    public function testAnHttpErrorIsAnException(): void
    {
        $this->http->respond(['timestamp' => '2026-10-12T12:21:09.512+00:00', 'status' => 404, 'error' => 'Not Found'], 404);

        try {
            $this->client()->status('1');
            self::fail('No exception.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->statusCode);
            self::assertSame(['Not Found'], $exception->errors);
            self::assertStringContainsString('"status":404', $exception->body);
            self::assertFalse($exception->isRetryable());
        }
    }

    public function testNoAnswerAndAnAnswerThatIsNotJsonAreExceptions(): void
    {
        $this->http->fail('Connection timed out')->respond('<html>Service unavailable</html>', 503)->respond('<html></html>');

        try {
            $this->client()->status('1');
            self::fail('No exception.');
        } catch (ApiException $exception) {
            self::assertNull($exception->statusCode);
            self::assertTrue($exception->isRetryable());
            self::assertStringContainsString('Connection timed out', $exception->getMessage());
        }

        try {
            $this->client()->status('1');
            self::fail('No exception.');
        } catch (ApiException $exception) {
            self::assertSame(503, $exception->statusCode);
            self::assertTrue($exception->isRetryable());
        }

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('not answered with JSON');
        $this->client()->status('1');
    }

    /**
     * @param array<mixed> $response
     */
    #[DataProvider('states')]
    public function testTheStatusOfAnUploadIsRead(array $response, bool $ok, bool $rejected, bool $processing): void
    {
        $this->http->respond($response);

        $status = $this->client()->status('0000005001');

        self::assertSame(self::TEST . '/stareMesaj/0000005001', (string) $this->http->last()->getUri());
        self::assertSame('GET', $this->http->last()->getMethod());
        self::assertSame([$ok, $rejected, $processing], [$status->isOk(), $status->isRejected(), $status->isProcessing()]);
    }

    /**
     * @return iterable<string, array{array<mixed>, bool, bool, bool}>
     */
    public static function states(): iterable
    {
        yield 'processed' => [['stare' => 'ok', 'ExecutionStatus' => 0], true, false, false];
        yield 'being processed' => [['stare' => 'in prelucrare', 'ExecutionStatus' => 0], false, false, true];
        yield 'rejected' => [
            ['Errors' => [['errorMessage' => 'UIT-ul nu poate fi identificat.']], 'stare' => 'nok', 'ExecutionStatus' => 1],
            false,
            true,
            false,
        ];
        yield 'XML not taken' => [['stare' => 'XML cu erori nepreluat de sistem', 'ExecutionStatus' => 0], false, true, false];
        yield 'a state this package does not know' => [['stare' => 'altceva', 'ExecutionStatus' => 0], false, false, true];
    }

    public function testARejectedStatusCarriesItsErrors(): void
    {
        $this->http->respond([
            'Errors' => [['errorMessage' => 'UIT-ul nu poate fi identificat.']],
            'stare' => 'nok',
            'ExecutionStatus' => 1,
            'trace_id' => 'trace-3',
        ]);

        $status = $this->client()->status('1');

        self::assertSame('nok', $status->stare);
        self::assertSame(['UIT-ul nu poate fi identificat.'], $status->errors);
        self::assertSame('trace-3', $status->traceId);
    }

    public function testAStatusCallThatFailedIsAnException(): void
    {
        $this->http->respond([
            'Errors' => [['errorMessage' => 'S-au facut deja 100 de verificari, pentru id-incarcare solicitat, in cursul zilei']],
            'ExecutionStatus' => 1,
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('S-au facut deja 100 de verificari');
        $this->client()->status('1');
    }

    public function testTheMessageListIsRead(): void
    {
        $this->http->respond(self::list());

        $messages = $this->client()->messages('10000001', 3);

        self::assertSame(self::TEST . '/lista/3/10000001', (string) $this->http->last()->getUri());
        self::assertCount(3, $messages);

        self::assertSame(MessageType::Notificare, $messages[0]->tip);
        self::assertTrue($messages[0]->isOk());
        self::assertSame(self::UIT, $messages[0]->uit);
        self::assertSame('10000001', $messages[0]->codDecl);
        self::assertSame('REF-1', $messages[0]->refDecl);
        self::assertSame('0000005001', $messages[0]->idIncarcare);
        self::assertSame('A', $messages[0]->sursa);
        self::assertSame('2026-10-12T14:40:08+03:00', $messages[0]->dataCreare?->format(DATE_ATOM));
        self::assertSame('30', $messages[0]->raw['tip_op']);
        self::assertSame([], $messages[0]->errors());

        self::assertSame(MessageType::Stergere, $messages[2]->tip);
        self::assertTrue($messages[2]->isError());
        self::assertNull($messages[2]->uit);
        self::assertSame(['Nu a fost identificat UIT-ul printre notificarile valide anterioare.'], $messages[2]->errors());
    }

    public function testAnEmptyMessageListIsEmptyAndAFailedCallIsAnException(): void
    {
        $this->http
            ->respond(['Errors' => [['errorMessage' => 'Nu exista mesaje in ultimele 60 zile']], 'ExecutionStatus' => 1])
            ->respond(['mesaje' => [], 'cui' => '10000001', 'ExecutionStatus' => 0])
            ->respond(['Errors' => [['errorMessage' => 'Numarul de zile trebuie sa fie intre 1 si 60']], 'ExecutionStatus' => 1]);

        foreach (['reported as an error', 'an empty list'] as $case) {
            self::assertSame([], $this->client()->messages('10000001'), $case);
        }
        self::assertSame(self::TEST . '/lista/60/10000001', (string) $this->http->last()->getUri());

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('intre 1 si 60');
        $this->client()->messages('10000001', 90);
    }

    public function testTransportsOfAnOrganiserAreRead(): void
    {
        $this->http->respond([[
            'id' => 123,
            'uit' => self::UIT,
            'cod_decl' => 10000001,
            'den_decl' => 'Declarant Exemplu SRL',
            'data_transp' => '2026-10-12T00:00:00',
            'data_exp_uit' => '2026-10-17T00:00:00',
            'ref_decl' => 'REF-1',
            'tr_cod' => '10000003',
            'tr_den' => 'Transportator Exemplu SRL',
            'nr_veh' => 'B100ABC',
            'loc_start' => ['tip_loc' => 'ADR', 'judet' => 'CJ'],
        ]]);

        $transports = $this->client()->info('10000003', uit: self::UIT, refDeclarant: 'REF 1');

        self::assertSame(self::TEST . '/info?cui_op=10000003&uit=' . self::UIT . '&ref_decl=REF+1', (string) $this->http->last()->getUri());
        self::assertCount(1, $transports);
        self::assertSame('10000001', $transports[0]->codDecl);
        self::assertSame('2026-10-17', $transports[0]->dataExpUit?->format('Y-m-d'));
        self::assertSame('B100ABC', $transports[0]->nrVeh);
        self::assertNull($transports[0]->nrRem1);
        self::assertSame('CJ', $transports[0]->raw['loc_start']['judet']);
    }

    public function testAnInfoCallWithoutAResultIsAnException(): void
    {
        $this->http->respond(['trace_id' => 'trace-4', 'dateResponse' => '202610121616', 'error' => 'Nu exista informatii pentru aceasta solicitare']);

        try {
            $this->client()->info('10000003');
            self::fail('No exception.');
        } catch (ApiException $exception) {
            self::assertSame(['Nu exista informatii pentru aceasta solicitare'], $exception->errors);
            self::assertSame('trace-4', $exception->traceId);
        }
    }

    public function testAMessageAnafIsStillProcessingHasNoOutcomeYet(): void
    {
        $this->http->respond(['stare' => 'in prelucrare', 'ExecutionStatus' => 0]);

        $outcome = $this->client()->outcome('5001', '10000001');

        self::assertSame(OutcomeState::Processing, $outcome->state);
        self::assertFalse($outcome->isFinal());
        self::assertCount(1, $this->http->requests);
    }

    public function testAMessageRejectedByTheStatusServiceIsRejected(): void
    {
        $this->http
            ->respond(['Errors' => [['errorMessage' => 'UIT-ul nu poate fi identificat.']], 'stare' => 'nok', 'ExecutionStatus' => 1])
            ->respond(['stare' => 'XML cu erori nepreluat de sistem', 'ExecutionStatus' => 0]);

        $outcome = $this->client()->outcome('5001', '10000001', uit: self::UIT);
        self::assertSame(OutcomeState::Rejected, $outcome->state);
        self::assertSame(['UIT-ul nu poate fi identificat.'], $outcome->errors);
        self::assertSame(self::UIT, $outcome->uit);
        self::assertTrue($outcome->isFinal());

        $outcome = $this->client()->outcome('5001', '10000001');
        self::assertSame(['XML cu erori nepreluat de sistem'], $outcome->errors);
        self::assertCount(2, $this->http->requests);
    }

    public function testAProcessedMessageIsLookedUpInTheMessageListByItsUploadId(): void
    {
        $this->http->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(self::list());

        $outcome = $this->client()->outcome('5001', '10000001');

        self::assertSame(self::TEST . '/lista/7/10000001', (string) $this->http->last()->getUri());
        self::assertSame(OutcomeState::Accepted, $outcome->state);
        self::assertSame(self::UIT, $outcome->uit);
        self::assertSame('0000005001', $outcome->listed?->idIncarcare);
        self::assertTrue($outcome->status->isOk());
    }

    public function testAMessageWithErrorsInTheListIsRejectedEvenWhenAValidOneHasItsUit(): void
    {
        $this->http->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(self::list());

        $outcome = $this->client()->outcome('5003', '10000001', MessageType::Corectie, self::UIT);

        self::assertSame(OutcomeState::Rejected, $outcome->state);
        self::assertSame(['Greutatea bruta TREBUIE sa fie mai mare decat greutatea neta.'], $outcome->errors);
        self::assertSame(MessageType::Corectie, $outcome->listed?->tip);
    }

    public function testAMessageIsFoundByUitOrReferenceWhenItsUploadIdIsNotListed(): void
    {
        $this->http
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(self::list())
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(self::list())
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(self::list())
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(self::list());

        // A correction that went through shows as the notification it corrected.
        $byUit = $this->client()->outcome('5009', '10000001', MessageType::Corectie, strtolower(self::UIT));
        self::assertSame(OutcomeState::Accepted, $byUit->state);
        self::assertSame('0000005001', $byUit->listed?->idIncarcare);

        $byReference = $this->client()->outcome('5009', '10000001', MessageType::Notificare, refDeclarant: 'ref-1');
        self::assertSame(OutcomeState::Accepted, $byReference->state);
        self::assertSame(self::UIT, $byReference->uit);

        // Without the type, the notification with the same UIT is all there is to go by.
        $untyped = $this->client()->outcome('5009', '10000001', uit: self::UIT);
        self::assertSame(MessageType::Notificare, $untyped->listed?->tip);

        // A vehicle change is not taken for the notification it changed.
        $vehicleChange = $this->client()->outcome('5009', '10000001', MessageType::ModificareVehicul, self::UIT);
        self::assertSame(OutcomeState::Processing, $vehicleChange->state);
        self::assertNull($vehicleChange->listed);
    }

    /**
     * ANAF does not list a deletion or a confirmation that went through.
     */
    #[DataProvider('unlisted')]
    public function testAMessageThatIsNotListedWhenValidIsAcceptedOnceProcessed(MessageType $type): void
    {
        $this->http
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(self::list())
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(['Errors' => [['errorMessage' => 'Nu exista mesaje in ultimele 7 zile']], 'ExecutionStatus' => 1])
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->fail()
            ->respond(['stare' => 'in prelucrare', 'ExecutionStatus' => 0]);

        $outcome = $this->client()->outcome('5009', '10000001', $type, self::UIT);
        self::assertSame(OutcomeState::Accepted, $outcome->state);
        self::assertNull($outcome->listed);
        self::assertSame(self::UIT, $outcome->uit);

        self::assertSame(OutcomeState::Accepted, $this->client()->outcome('5009', '10000001', $type)->state, 'an empty list');
        self::assertSame(OutcomeState::Processing, $this->client()->outcome('5009', '10000001', $type)->state, 'a list that cannot be read');
        self::assertSame(OutcomeState::Processing, $this->client()->outcome('5009', '10000001', $type)->state, 'not processed yet');
    }

    /**
     * @return iterable<string, array{MessageType}>
     */
    public static function unlisted(): iterable
    {
        yield 'deletion' => [MessageType::Stergere];
        yield 'confirmation' => [MessageType::Confirmare];
    }

    public function testAConfirmationIsFoundInTheRecordOfItsNotification(): void
    {
        $list = self::list();
        $list['mesaje'][0]['confirmare'] = ['tip_conf' => 10, 'obs' => 'Receptie', 'data_decl' => '2026-10-12T08:59:03', 'id_incarcare' => 5062319823];
        $this->http->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond($list);

        $outcome = $this->client()->outcome('5062319823', '10000001', MessageType::Confirmare);

        self::assertSame(OutcomeState::Accepted, $outcome->state);
        self::assertSame(MessageType::Notificare, $outcome->listed?->tip);
        self::assertSame(self::UIT, $outcome->uit);
    }

    public function testADeletionWithErrorsInTheListIsRejected(): void
    {
        $this->http->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(self::list());

        $outcome = $this->client()->outcome('5004', '10000001', MessageType::Stergere, self::UIT);

        self::assertSame(OutcomeState::Rejected, $outcome->state);
        self::assertSame(['Nu a fost identificat UIT-ul printre notificarile valide anterioare.'], $outcome->errors);
    }

    /**
     * ANAF's description shows ids and tax codes as text, zero-padded; its test environment sends numbers.
     */
    public function testARecordAsAnafReallySendsItIsRead(): void
    {
        $this->http->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(['mesaje' => [[
            'tip' => 'NOT',
            'stare' => 'OK',
            'uit' => self::UIT,
            'cod_decl' => 10000001,
            'ref_decl' => 'REF-1',
            'post_avarie' => 'N',
            'sursa' => 'A',
            'id_incarcare' => 5063704760,
            'data_creare' => '2026-10-12T08:45:02',
            'tip_op' => 30,
            'data_transp' => '2026-10-12T00:00:00',
        ]], 'ExecutionStatus' => 0]);

        $outcome = $this->client()->outcome('5063704760', '10000001', MessageType::Notificare);

        self::assertSame(OutcomeState::Accepted, $outcome->state);
        self::assertSame('5063704760', $outcome->listed?->idIncarcare);
        self::assertSame('10000001', $outcome->listed->codDecl);
        self::assertSame('2026-10-12T08:45:02+03:00', $outcome->listed->dataCreare?->format(DATE_ATOM));
    }

    public function testAMessageListThatCannotBeReadLeavesTheOutcomeOpen(): void
    {
        $this->http
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])
            ->respond(['Errors' => [['errorMessage' => 'S-au facut deja 1000 interogari de lista mesaje de catre utilizator in cursul zilei']], 'ExecutionStatus' => 1])
            ->respond(['stare' => 'ok', 'ExecutionStatus' => 0])
            ->fail();

        foreach (['a call that ANAF refuses', 'no answer'] as $case) {
            self::assertSame(OutcomeState::Processing, $this->client()->outcome('5001', '10000001')->state, $case);
        }
        self::assertCount(4, $this->http->requests);
    }

    #[DataProvider('ages')]
    public function testTheMessageListIsAskedForAsFarBackAsTheUploadIs(string $uploadedAt, int $days): void
    {
        $this->http->respond(['stare' => 'ok', 'ExecutionStatus' => 0])->respond(['mesaje' => [], 'ExecutionStatus' => 0]);

        $this->client()->outcome('5001', '10000001', uploadedAt: new \DateTimeImmutable($uploadedAt));

        self::assertSame(self::TEST . "/lista/{$days}/10000001", (string) $this->http->last()->getUri());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function ages(): iterable
    {
        yield 'just now' => ['2026-10-12 12:00:00', 1];
        yield 'an hour ago' => ['2026-10-12 11:00:00', 2];
        yield 'a day ago' => ['2026-10-11 12:00:00', 2];
        yield 'a day and a bit ago' => ['2026-10-11 11:00:00', 3];
        yield 'long ago' => ['2026-01-01 12:00:00', 60];
    }

    public function testAnUploadCarriesItsUitIntoTheOutcome(): void
    {
        $this->http
            ->respond(['ExecutionStatus' => 0, 'index_incarcare' => 5009, 'UIT' => self::UIT])
            ->respond(['stare' => 'in prelucrare', 'ExecutionStatus' => 0]);

        $client = $this->client();
        $outcome = $client->outcome($client->upload(Message::stergere('10000001', self::UIT)), '10000001');

        self::assertSame(self::TEST . '/stareMesaj/5009', (string) $this->http->last()->getUri());
        self::assertSame(self::UIT, $outcome->uit);
    }

    private function client(): Client
    {
        $clock = new class () implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-12 12:00:00');
            }
        };

        return new Client($this->http, 'token-1', Environment::Test, clock: $clock);
    }

    /**
     * @return array<mixed>
     */
    private static function list(): array
    {
        return [
            'mesaje' => [
                [
                    'tip' => 'NOT',
                    'stare' => 'OK',
                    'uit' => self::UIT,
                    'cod_decl' => '10000001',
                    'ref_decl' => 'REF-1',
                    'sursa' => 'A',
                    'id_incarcare' => '0000005001',
                    'data_creare' => '20261012144008',
                    'tip_op' => '30',
                    'data_transp' => '20261012000000',
                    'nr_veh' => 'B100ABC',
                ],
                [
                    'tip' => 'COR',
                    'stare' => 'ERR',
                    'uit' => self::UIT,
                    'cod_decl' => '10000001',
                    'sursa' => 'A',
                    'id_incarcare' => '0000005003',
                    'data_creare' => '20261012150000',
                    'mesaje' => [
                        ['tip' => 'WARN', 'mesaj' => 'Un avertisment.'],
                        ['tip' => 'ERR', 'mesaj' => 'Greutatea bruta TREBUIE sa fie mai mare decat greutatea neta.'],
                    ],
                ],
                [
                    'tip' => 'DEL',
                    'stare' => 'ERR',
                    'cod_decl' => '10000001',
                    'sursa' => 'A',
                    'id_incarcare' => '0000005004',
                    'data_creare' => '20261012160000',
                    'mesaje' => [['tip' => 'ERR', 'mesaj' => 'Nu a fost identificat UIT-ul printre notificarile valide anterioare.']],
                ],
            ],
            'serial' => 'XXXX',
            'cui' => '10000001',
            'titlu' => 'Lista Mesaje disponibile din ultimele 3 zile',
            'dateResponse' => '202610121242',
            'ExecutionStatus' => 0,
            'trace_id' => 'trace-5',
        ];
    }
}
