# eTransport

[![CI](https://github.com/slash197/etransport/actions/workflows/ci.yml/badge.svg)](https://github.com/slash197/etransport/actions/workflows/ci.yml)

PHP package for [RO e-Transport](https://etransport.mfinante.gov.ro), ANAF's system for declaring the road transport of goods in Romania.

> **Status: in development.** Nothing is released yet and nothing here is usable.

Planned for the first release:

- **Local validation without Java.** ANAF publishes its business rules as a Schematron file that needs an XSLT 2 processor. This package evaluates the same rules in plain PHP, so a declaration can be checked before it is sent.
- **Typed messages.** Notifications, corrections, deletions, vehicle changes and confirmations built from PHP objects.
- **A client for the web services.** Upload, message status, message list and transporter info, over any PSR-18 HTTP client.

It does not obtain ANAF OAuth tokens; it takes one you already have.

## Requirements

- PHP 8.2 or newer, with the `dom`, `libxml` and `mbstring` extensions

## Development

Nothing needs to be installed on the host except Docker.

```sh
make build    # build the PHP image
make install  # composer install
make check    # composer validate, PHPStan, PHPUnit
```

## Licence

MIT. See [LICENSE](LICENSE).
