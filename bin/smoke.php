#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Calls ANAF's test environment. Needs ETRANSPORT_TOKEN and ETRANSPORT_CIF.
 *
 *   php bin/smoke.php                 reads the message list of the last day; changes nothing
 *   php bin/smoke.php --upload        declares a made-up national transport and follows its outcome
 *   php bin/smoke.php --upload=1      the same, for a transport that starts in so many days
 *   php bin/smoke.php --correct=UIT   replaces a notification with one of another quantity; --days=1 as at upload
 *   php bin/smoke.php --vehicle=UIT   changes the vehicle of a notification
 *   php bin/smoke.php --confirm=UIT   confirms that the goods of a notification were received
 *   php bin/smoke.php --delete=UIT    withdraws a notification
 *   php bin/smoke.php --info          lists the transports the tax code organises
 *
 * What ANAF answers is printed in full, since it describes the tax code's
 * own transports. The token is never printed.
 */

use GuzzleHttp\Client as Guzzle;
use slash197\eTransport\Client;
use slash197\eTransport\Data\BunuriTransportate;
use slash197\eTransport\Data\DateTransport;
use slash197\eTransport\Data\DocumentTransport;
use slash197\eTransport\Data\Judet;
use slash197\eTransport\Data\Locatie;
use slash197\eTransport\Data\LocTraseuRutier;
use slash197\eTransport\Data\Notificare;
use slash197\eTransport\Data\PartenerComercial;
use slash197\eTransport\Data\ScopOperatiune;
use slash197\eTransport\Data\TipConfirmare;
use slash197\eTransport\Data\TipDocument;
use slash197\eTransport\Data\TipOperatiune;
use slash197\eTransport\Environment;
use slash197\eTransport\Exception\ApiException;
use slash197\eTransport\Message;
use slash197\eTransport\PollSchedule;
use slash197\eTransport\Validation\Validator;

require dirname(__DIR__) . '/vendor/autoload.php';

$token = trim((string) getenv('ETRANSPORT_TOKEN'));
$cif = trim((string) getenv('ETRANSPORT_CIF'));
if ($token === '' || $cif === '') {
    fwrite(STDERR, "Set ETRANSPORT_TOKEN and ETRANSPORT_CIF in .env (see .env.example).\n");
    exit(2);
}

$client = new Client(new Guzzle(['timeout' => 60]), $token, Environment::Test);
$json = static fn (mixed $value): string => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$options = getopt('', ['upload::', 'days:', 'correct:', 'vehicle:', 'confirm:', 'delete:', 'info']);

try {
    $today = new DateTimeImmutable('now', new DateTimeZone('Europe/Bucharest'));
    $days = (int) ($options['upload'] ?? $options['days'] ?? 0);
    $notification = static fn (float $quantity): Notificare => new Notificare(
        TipOperatiune::TransportNational,
        [new BunuriTransportate(ScopOperatiune::Comercializare, 'Mobilier din lemn (test)', $quantity, 'H87', 510.25, codTarifar: '94036010', greutateNeta: 480, valoareLeiFaraTva: 18250)],
        new PartenerComercial('RO', 'Partener Exemplu SRL', $cif),
        new DateTransport('B100ABC', 'RO', 'Transportator Exemplu SRL', $today->modify("+{$days} days"), codOrgTransport: $cif),
        LocTraseuRutier::locatie(new Locatie(Judet::Cluj, 'Cluj-Napoca', 'Strada Exemplu', '1')),
        LocTraseuRutier::locatie(new Locatie(Judet::Bucuresti, 'Bucuresti', 'Bulevardul Exemplu', '2')),
        [new DocumentTransport(TipDocument::AvizDeInsotire, $today, 'TEST-1')],
    );
    $reference = 'SMOKE-' . $today->format('YmdHis');
    $uit = static fn (string $option): ?string => is_string($options[$option] ?? null) ? $options[$option] : null;

    if (isset($options['info'])) {
        $transports = $client->info($cif);
        echo 'Transports organised: ', count($transports), "\n";
        foreach ($transports as $transport) {
            echo $json($transport->raw), "\n";
        }

        exit(0);
    }

    $message = match (true) {
        isset($options['upload']) => Message::notificare($cif, $notification(12.5), $reference),
        $uit('correct') !== null => Message::corectie($cif, $uit('correct'), $notification(14), $reference),
        $uit('vehicle') !== null => Message::modifVehicul($cif, $uit('vehicle'), 'B300ABC', $today, 'B400ABC', observatii: 'Defectiune (test)', refDeclarant: $reference),
        $uit('confirm') !== null => Message::confirmare($cif, $uit('confirm'), TipConfirmare::Confirmat, 'Receptie (test)', $reference),
        $uit('delete') !== null => Message::stergere($cif, $uit('delete')),
        default => null,
    };

    if ($message === null) {
        $messages = $client->messages($cif, 1);
        echo 'Message list of the last day: ', count($messages), " records\n";
        foreach ($messages as $listed) {
            echo $json($listed->raw), "\n";
        }

        exit(0);
    }

    $violations = (new Validator())->validate($message);
    echo 'Local validation: ', $violations->isEmpty() ? 'no violations' : implode(', ', $violations->rules()), "\n";

    $upload = $client->upload($message);
    $uploadedAt = new DateTimeImmutable();
    echo 'Upload: ', $json($upload->raw), "\n";

    // Only the first two minutes or so; run the script again with nothing to see the list later.
    $schedule = new PollSchedule();
    for ($checks = 0; $checks < 5; $checks++) {
        $next = $schedule->nextCheck($uploadedAt, $checks);
        if ($next === null) {
            break;
        }
        sleep(max(0, $next->getTimestamp() - time()));

        $outcome = $client->outcome($upload, $cif, $message->type, refDeclarant: $message->refDeclarant, uploadedAt: $uploadedAt);
        echo 'Check ', $checks + 1, ': ', $outcome->state->value, ', stareMesaj says ', $json($outcome->status->raw), "\n";
        if ($outcome->listed !== null) {
            echo 'Listed as: ', $json($outcome->listed->raw), "\n";
        }
        if ($outcome->isFinal()) {
            echo 'Outcome: ', $outcome->state->value, ', UIT ', $outcome->uit ?? 'none', "\n";
            foreach ($outcome->errors as $error) {
                echo '  ', $error, "\n";
            }

            exit(0);
        }
    }

    echo "No final outcome yet.\n";
} catch (ApiException $exception) {
    echo 'FAILED: ', $exception->getMessage(), "\n";
    echo 'HTTP status ', $exception->statusCode ?? 'none', ', trace id ', $exception->traceId ?? 'none', "\n";
    echo $exception->body, "\n";
    exit(1);
}
