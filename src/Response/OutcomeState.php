<?php

declare(strict_types=1);

namespace slash197\eTransport\Response;

enum OutcomeState: string
{
    /** ANAF has not said yet; ask again later. */
    case Processing = 'processing';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
