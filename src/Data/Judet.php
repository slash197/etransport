<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

enum Judet: int
{
    case Alba = 1;
    case Arad = 2;
    case Arges = 3;
    case Bacau = 4;
    case Bihor = 5;
    case BistritaNasaud = 6;
    case Botosani = 7;
    case Brasov = 8;
    case Braila = 9;
    case Buzau = 10;
    case CarasSeverin = 11;
    case Cluj = 12;
    case Constanta = 13;
    case Covasna = 14;
    case Dambovita = 15;
    case Dolj = 16;
    case Galati = 17;
    case Gorj = 18;
    case Harghita = 19;
    case Hunedoara = 20;
    case Ialomita = 21;
    case Iasi = 22;
    case Ilfov = 23;
    case Maramures = 24;
    case Mehedinti = 25;
    case Mures = 26;
    case Neamt = 27;
    case Olt = 28;
    case Prahova = 29;
    case SatuMare = 30;
    case Salaj = 31;
    case Sibiu = 32;
    case Suceava = 33;
    case Teleorman = 34;
    case Timis = 35;
    case Tulcea = 36;
    case Vaslui = 37;
    case Valcea = 38;
    case Vrancea = 39;
    case Bucuresti = 40;
    case Calarasi = 51;
    case Giurgiu = 52;

    private const ABBREVIATIONS = [
        'AB' => self::Alba,
        'AR' => self::Arad,
        'AG' => self::Arges,
        'BC' => self::Bacau,
        'BH' => self::Bihor,
        'BN' => self::BistritaNasaud,
        'BT' => self::Botosani,
        'BV' => self::Brasov,
        'BR' => self::Braila,
        'BZ' => self::Buzau,
        'CS' => self::CarasSeverin,
        'CJ' => self::Cluj,
        'CT' => self::Constanta,
        'CV' => self::Covasna,
        'DB' => self::Dambovita,
        'DJ' => self::Dolj,
        'GL' => self::Galati,
        'GJ' => self::Gorj,
        'HR' => self::Harghita,
        'HD' => self::Hunedoara,
        'IL' => self::Ialomita,
        'IS' => self::Iasi,
        'IF' => self::Ilfov,
        'MM' => self::Maramures,
        'MH' => self::Mehedinti,
        'MS' => self::Mures,
        'NT' => self::Neamt,
        'OT' => self::Olt,
        'PH' => self::Prahova,
        'SM' => self::SatuMare,
        'SJ' => self::Salaj,
        'SB' => self::Sibiu,
        'SV' => self::Suceava,
        'TR' => self::Teleorman,
        'TM' => self::Timis,
        'TL' => self::Tulcea,
        'VS' => self::Vaslui,
        'VL' => self::Valcea,
        'VN' => self::Vrancea,
        'B' => self::Bucuresti,
        'CL' => self::Calarasi,
        'GR' => self::Giurgiu,
    ];

    /**
     * The county for what an address usually carries: the two-letter
     * abbreviation ("CJ", "B") or ANAF's number, also as text ("12", "012").
     */
    public static function tryFromCode(string $code): ?self
    {
        $code = strtoupper(trim($code));
        if ($code !== '' && ctype_digit($code)) {
            return self::tryFrom((int) $code);
        }

        return self::ABBREVIATIONS[$code] ?? null;
    }
}
