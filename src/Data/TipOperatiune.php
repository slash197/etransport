<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

enum TipOperatiune: int
{
    /** AIC */
    case AchizitieIntracomunitara = 10;
    /** LHI: operațiuni în sistem lohn (UE), intrare */
    case LohnIntrare = 12;
    /** SCI: stocuri la dispoziția clientului (call-off stock), intrare */
    case StocuriClientIntrare = 14;
    /** LIC */
    case LivrareIntracomunitara = 20;
    /** LHE: operațiuni în sistem lohn (UE), ieșire */
    case LohnIesire = 22;
    /** SCE: stocuri la dispoziția clientului (call-off stock), ieșire */
    case StocuriClientIesire = 24;
    /** TTN */
    case TransportNational = 30;
    /** IMP */
    case Import = 40;
    /** EXP */
    case Export = 50;
    /** DIN: tranzacție intracomunitară, intrare pentru depozitare/formare nou transport */
    case IntrareDepozitare = 60;
    /** DIE: tranzacție intracomunitară, ieșire după depozitare/formare nou transport */
    case IesireDepozitare = 70;
}
