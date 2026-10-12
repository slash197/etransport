<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

enum TipDocument: int
{
    case Cmr = 10;
    case Factura = 20;
    case AvizDeInsotire = 30;
    case Altele = 9999;
}
