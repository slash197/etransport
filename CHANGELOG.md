# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). While the version is 0.x, a minor release may contain breaking changes.

## [Unreleased]

### Added

- `Validation\Validator`, which checks a document against ANAF's XML schema and against ANAF's Schematron business rules in plain PHP, with no XSLT processor or Java. Violations carry the rule id, the location in the document and ANAF's message.
- ANAF's schema and rules file, shipped unmodified in `resources/`.
- `BR-003` and `BR-209`, which a Schematron processor never reaches in ANAF's file, are checked as well. `new Validator(unreachableRules: false)` turns that off.
