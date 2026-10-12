# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). While the version is 0.x, a minor release may contain breaking changes.

## [0.1.0] - 2026-10-12

First release.

### Added

- `Message`, which builds the five kinds of message (notificare, corectie, stergere, confirmare, modifVehicul) from typed objects in `Data\`, with enums for the short code lists.
- `Validation\Validator`, which checks a message or an XML document against ANAF's XML schema and against ANAF's Schematron business rules in plain PHP, with no XSLT processor or Java. Violations carry the rule id, the location in the document and ANAF's message.
- `BR-003` and `BR-209`, which a Schematron processor never reaches in ANAF's file, are checked as well. `new Validator(unreachableRules: false)` turns that off.
- ANAF's schema and rules file, shipped unmodified in `resources/`.
- `Client`, for ANAF's upload, stareMesaj, lista and info services over any PSR-18 HTTP client, and `Client::outcome()`, which tells whether an uploaded message was accepted or rejected.
- `PollSchedule`, which says when to ask for the outcome of an upload without exceeding ANAF's daily limit.
- `Nomenclators`, with ANAF's lists of border crossing points, customs offices and countries.

[0.1.0]: https://github.com/slash197/etransport/releases/tag/v0.1.0
