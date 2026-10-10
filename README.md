# eTransport

[![CI](https://github.com/slash197/etransport/actions/workflows/ci.yml/badge.svg)](https://github.com/slash197/etransport/actions/workflows/ci.yml)

PHP package for [RO e-Transport](https://etransport.mfinante.gov.ro), ANAF's system for declaring the road transport of goods in Romania.

> **Status: in development.** Nothing is released yet. Validation works; the message builder and the client are not written.

- **Local validation without Java.** ANAF publishes its business rules as a Schematron file that needs an XSLT 2 processor. This package evaluates the same file in plain PHP, so a declaration can be checked before it is sent.
- **Typed messages** (planned). Notifications, corrections, deletions, vehicle changes and confirmations built from PHP objects.
- **A client for the web services** (planned). Upload, message status, message list and transporter info, over any PSR-18 HTTP client.

It does not obtain ANAF OAuth tokens; it takes one you already have.

## Validation

```php
use slash197\eTransport\Validation\Validator;

$violations = (new Validator())->validate($xml);

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

[`tests/Support/Cases.php`](tests/Support/Cases.php) holds about 120 made-up documents: valid ones of every message type, and at least one that breaks each rule. A reference Schematron processor, Saxon with [SchXslt](https://github.com/schxslt/schxslt), is run over them and its findings are recorded in [`tests/golden/rules.json`](tests/golden/rules.json). The tests require this package to report the same rules, at the same nodes, with the same messages. `make golden` records them again.

### What to know

- **Two rules can never fail.** `BR-003` (UIT format) and `BR-209` (organiser's tax code on national transport) sit in ANAF's file behind an earlier rule for the same attribute, and Schematron applies only the first rule that matches a node. The reference processor never reports them and neither does this package. The UIT format is still checked by the schema.
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
