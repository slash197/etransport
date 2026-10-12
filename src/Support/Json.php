<?php

declare(strict_types=1);

namespace slash197\eTransport\Support;

/**
 * Reads values out of ANAF's JSON, which writes the same field as text in
 * one service and as a number in another.
 *
 * @internal
 */
final class Json
{
    private const TIMEZONE = 'Europe/Bucharest';
    private const DATE_FORMATS = ['!YmdHis', '!YmdHi', '!Y-m-d\TH:i:s', '!Y-m-d\TH:i:s.v', '!Y-m-d H:i:s', '!Y-m-d', '!Ymd'];

    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * ANAF's dates carry no offset; they are Romanian time.
     *
     * @param array<mixed> $data
     */
    public static function date(array $data, string $key): ?\DateTimeImmutable
    {
        $value = self::string($data, $key);
        if ($value === null) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, new \DateTimeZone(self::TIMEZONE));
            if ($date !== false && $date->format(ltrim($format, '!')) === $value) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    /**
     * The error texts of a response, cleaned of what ANAF wraps them in.
     *
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public static function errors(array $data): array
    {
        $errors = [];
        foreach (self::objects($data, 'Errors') as $error) {
            $errors[] = self::clean(self::string($error, 'errorMessage') ?? '');
        }
        if ($errors === []) {
            $errors[] = self::clean(self::string($data, 'message') ?? self::string($data, 'error') ?? '');
        }

        return array_values(array_unique(array_filter($errors, static fn (string $error): bool => $error !== '')));
    }

    /**
     * ANAF sometimes returns an error as "...; textEroare=[BR-027]-The text; ...".
     */
    public static function clean(string $message): string
    {
        if (preg_match('/textEroare=([^;]+)/i', $message, $match) === 1) {
            $message = $match[1];
        }
        $message = (string) preg_replace('/^\s*\[[A-Z0-9-]+\]-\s*/', '', $message);

        return trim((string) preg_replace('/\s+/', ' ', $message));
    }
}
