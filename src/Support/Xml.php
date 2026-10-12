<?php

declare(strict_types=1);

namespace slash197\eTransport\Support;

/**
 * @internal
 */
final class Xml
{
    public const NS = 'mfp:anaf:dgti:eTransport:declaratie:v2';

    /**
     * A required attribute is written even when it is empty, so that
     * validation reports it under its own name.
     *
     * @param array<string, string>      $required
     * @param array<string, string|null> $optional Left out when null or empty
     */
    public static function element(\DOMDocument $document, string $name, array $required, array $optional = []): \DOMElement
    {
        $element = $document->createElementNS(self::NS, $name);
        foreach ($required as $attribute => $value) {
            $element->setAttribute($attribute, $value);
        }
        foreach ($optional as $attribute => $value) {
            if ($value !== null && $value !== '') {
                $element->setAttribute($attribute, $value);
            }
        }

        return $element;
    }

    /**
     * @return ($code is null ? null : string)
     */
    public static function code(\BackedEnum|int|null $code): ?string
    {
        return $code === null ? null : (string) ($code instanceof \BackedEnum ? $code->value : $code);
    }

    /**
     * A float is written with the two decimals ANAF allows; text is passed
     * on as it is, for validation to judge.
     *
     * @return ($value is null ? null : string)
     */
    public static function decimal(string|int|float|null $value): ?string
    {
        return is_float($value) ? number_format($value, 2, '.', '') : ($value === null ? null : (string) $value);
    }
}
