# eTransport

[![CI](https://github.com/slash197/etransport/actions/workflows/ci.yml/badge.svg)](https://github.com/slash197/etransport/actions/workflows/ci.yml)

PHP package for [RO e-Transport](https://etransport.mfinante.gov.ro), ANAF's system for declaring the road transport of goods in Romania.

> **Status: in development.** Nothing is released yet. Messages and validation work; the client is not written.

- **Local validation without Java.** ANAF publishes its business rules as a Schematron file that needs an XSLT 2 processor. This package evaluates the same file in plain PHP, so a declaration can be checked before it is sent.
- **Typed messages.** Notifications, corrections, deletions, vehicle changes and confirmations built from PHP objects.
- **A client for the web services** (planned). Upload, message status, message list and transporter info, over any PSR-18 HTTP client.

It does not obtain ANAF OAuth tokens; it takes one you already have.

## Messages

```php
use slash197\eTransport\Data\BunuriTransportate;
use slash197\eTransport\Data\DateTransport;
use slash197\eTransport\Data\DocumentTransport;
use slash197\eTransport\Data\Judet;
use slash197\eTransport\Data\Locatie;
use slash197\eTransport\Data\LocTraseuRutier;
use slash197\eTransport\Data\Notificare;
use slash197\eTransport\Data\PartenerComercial;
use slash197\eTransport\Data\ScopOperatiune;
use slash197\eTransport\Data\TipDocument;
use slash197\eTransport\Data\TipOperatiune;
use slash197\eTransport\Message;

$message = Message::notificare('10000001', new Notificare(
    TipOperatiune::TransportNational,
    [new BunuriTransportate(ScopOperatiune::Comercializare, 'Mobilier din lemn', 12.5, 'H87', 510.25, codTarifar: '94036010')],
    new PartenerComercial('RO', 'Partener Exemplu SRL', '10000002'),
    new DateTransport('B100ABC', 'RO', 'Transportator Exemplu SRL', new DateTimeImmutable('2026-10-12'), codOrgTransport: '10000003'),
    LocTraseuRutier::locatie(new Locatie(Judet::Cluj, 'Cluj-Napoca', 'Strada Exemplu', '1')),
    LocTraseuRutier::locatie(new Locatie(Judet::Bucuresti, 'Bucuresti', 'Bulevardul Exemplu', '2')),
    [new DocumentTransport(TipDocument::AvizDeInsotire, new DateTimeImmutable('2026-10-10'), 'F-100')],
), refDeclarant: 'REF-1');

$xml = $message->toXml();
```

| Message | |
|---|---|
| `Message::notificare($codDeclarant, $notificare)` | Declares a transport |
| `Message::corectie($codDeclarant, $uit, $notificare)` | Replaces the content of a declared notification |
| `Message::stergere($codDeclarant, $uit)` | Withdraws a notification |
| `Message::confirmare($codDeclarant, $uit, TipConfirmare::Confirmat)` | Confirms or denies that the goods were received |
| `Message::modifVehicul($codDeclarant, $uit, $nrVehicul, $dataModificare)` | Changes the vehicle |

Every one also takes `refDeclarant`, your own reference for the message, and `declPostAvarie: true` for a declaration made after an outage of ANAF's system.

Properties are named as in ANAF's schema, so its field specification applies directly. The short code lists are enums (`TipOperatiune`, `ScopOperatiune`, `TipDocument`, `TipConfirmare`, `Judet`), and a plain number is accepted wherever one is, in case ANAF adds a code. `Judet::tryFromCode('CJ')` finds a county by its abbreviation. Border crossing points and customs offices are numbers from ANAF's lists; countries and units of measure are their usual codes.

A message is not checked when it is built. Pass it to the validator before sending it.

## Validation

```php
use slash197\eTransport\Validation\Validator;

$violations = (new Validator())->validate($message); // or an XML string

if (!$violations->isEmpty()) {
    foreach ($violations as $violation) {
        echo $violation->rule, ' ', $violation->location, ': ', $violation->message, PHP_EOL;
    }
}
```

```
BR-027 /eTransport/notificare/bunuriTransportate[2]/@cantitate: Cantitatea bunului transportat(BT-9) NU TREBUIE sa ia valori negative.
```

A document is checked against ANAF's XML schema and against ANAF's business rules, and everything found is returned at once. Each `Violation` has:

| Property | |
|---|---|
| `source` | `Source::Xml` (not well-formed), `Source::Schema` (breaks the XSD) or `Source::Rules` (breaks a business rule) |
| `message` | For a business rule, ANAF's own text for it, in Romanian, with the offending values filled in |
| `rule` | The id of the business rule, such as `BR-027`; `null` for the other sources |
| `location` | Path to the element or attribute a business rule failed at |
| `line` | Line in the document, when known |

`$violations->rules()` lists the ids of the broken rules.

### ANAF's files

The schema and the rules file ship with the package, unmodified, in [`resources/`](resources). Nothing in them is rewritten in PHP: the rules file is read and evaluated as it is. When ANAF publishes a new version, pass it in:

```php
$validator = new Validator(rulesFile: '/path/to/eTransport-validation_v.2.0.3.sch');
```

The rules are written in XPath 2 and PHP only has XPath 1, so each expression is translated when the file is loaded. The translator knows the constructs ANAF's file uses. If a new file uses anything else, loading it throws `UnsupportedRulesException` instead of applying the rules in part.

### How it is verified

[`tests/Support/Cases.php`](tests/Support/Cases.php) holds about 120 made-up documents: valid ones of every message type, and at least one that breaks each rule. A reference Schematron processor, Saxon with [SchXslt](https://github.com/schxslt/schxslt), is run over them and its findings are recorded in [`tests/golden/rules.json`](tests/golden/rules.json). The tests require this package to report the same rules, at the same nodes, with the same messages. The two rules the reference never reaches (see below) are checked on top of that. `make golden` records them again.

### What to know

- **Two rules are applied that ANAF's file leaves unreachable.** `BR-003` (UIT format) and `BR-209` (organiser's tax code on national transport) sit behind an earlier rule for the same attribute, and Schematron applies only the first rule that matches a node, so the reference processor never reports them. They are ANAF's rules all the same, so this package checks them along with the rule they sit behind. `new Validator(unreachableRules: false)` leaves them out and reports exactly what the reference processor does.
- **Some rules are looser than their text.** `BR-201` accepts any value containing `D`, and `BR-036` accepts thirteen digits when there is no decimal point. This is how the rules are written, and the reference processor agrees. The schema catches both.
- **Where an XPath 2 processor would stop, this package carries on.** A number that is not a number (`cantitate="abc"`) makes an XPath 2 processor abort the whole validation with an error. Here the rule that needed the number is reported as broken. Such documents already fail the schema.
- **It tells you what the published rules say, not what ANAF's server does.** The two should agree, but only an upload is proof.

## Requirements

- PHP 8.2 or newer, with the `dom`, `libxml` and `mbstring` extensions

## Development

Nothing needs to be installed on the host except Docker.

```sh
make build    # build the PHP image
make install  # composer install
make check    # composer validate, PHPStan, PHPUnit
make golden   # record again what the reference processor reports for the test cases
```

## Licence

MIT. See [LICENSE](LICENSE).
