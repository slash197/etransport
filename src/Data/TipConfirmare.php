<?php

declare(strict_types=1);

namespace slash197\eTransport\Data;

enum TipConfirmare: int
{
    case Confirmat = 10;
    case ConfirmatPartial = 20;
    case Infirmat = 30;
}
