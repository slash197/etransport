<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation;

use slash197\eTransport\Exception\ETransportException;
use slash197\eTransport\Validation\Schematron\Schema;

/**
 * Checks an e-Transport document the way ANAF does on upload: against the
 * XML schema, and against the business rules ANAF publishes as a
 * Schematron file. Both files ship with the package.
 */
final class Validator
{
    public const SCHEMA = __DIR__ . '/../../resources/schema_ETR_v2_20230126.xsd';
    public const RULES = __DIR__ . '/../../resources/eTransport-validation_v.2.0.2_12082024.sch';

    private ?Schema $rules = null;

    /**
     * @param string $schemaFile Path to a different version of ANAF's XSD
     * @param string $rulesFile Path to a different version of ANAF's Schematron rules
     * @param bool $unreachableRules Whether to apply the rules that sit behind an earlier rule for the same
     *                               nodes, which a Schematron processor never reaches (BR-003 and BR-209)
     */
    public function __construct(
        private readonly string $schemaFile = self::SCHEMA,
        private readonly string $rulesFile = self::RULES,
        private readonly bool $unreachableRules = true,
    ) {
    }

    /**
     * Everything wrong with the document, from both the schema and the
     * rules. A document that is not well-formed XML is reported on its own.
     *
     * @throws \slash197\eTransport\Exception\UnsupportedRulesException When the rules file cannot be evaluated
     */
    public function validate(string $xml): Violations
    {
        if (!is_file($this->schemaFile)) {
            throw new ETransportException("Schema file not found: {$this->schemaFile}");
        }
        $this->rules ??= Schema::fromFile($this->rulesFile, $this->unreachableRules);

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();
            if (trim($xml) === '' || !$document->loadXML($xml, LIBXML_NONET)) {
                return new Violations(self::errors(Source::Xml) ?: [new Violation(Source::Xml, 'The document is empty.')]);
            }
            if ($document->doctype !== null) {
                return new Violations([new Violation(Source::Xml, 'A document type declaration is not allowed.')]);
            }

            $document->schemaValidate($this->schemaFile);

            return new Violations([...self::errors(Source::Schema), ...$this->rules->validate($document)]);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return list<Violation>
     */
    private static function errors(Source $source): array
    {
        $violations = [];
        foreach (libxml_get_errors() as $error) {
            // libxml writes names as {namespace}name; the namespace is always the same and only adds noise.
            $message = trim((string) preg_replace('/\{[^}]*\}/', '', $error->message));
            $violations[] = new Violation($source, $message, line: $error->line > 0 ? $error->line : null);
        }
        libxml_clear_errors();

        return $violations;
    }
}
