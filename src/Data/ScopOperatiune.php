<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

/**
 * Which purposes an operation type allows is one of ANAF's business rules,
 * and is checked by validation.
 */
enum ScopOperatiune: int
{
    case Comercializare = 101;
    case Productie = 201;
    case Gratuitati = 301;
    case EchipamentComercial = 401;
    case MijloaceFixe = 501;
    case ConsumPropriu = 601;
    case LivrareCuInstalare = 703;
    case TransferIntreGestiuni = 704;
    case BunuriPuseLaDispozitiaClientului = 705;
    case Leasing = 801;
    case BunuriInGarantie = 802;
    case OperatiuniScutite = 901;
    case InvestitieInCurs = 1001;
    case DonatiiAjutoare = 1101;
    case Altele = 9901;
    case AcelasiCuOperatiunea = 9999;
}
