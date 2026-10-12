<?php

declare(strict_types=1);

namespace slash197\eTransport;

/**
 * The kinds of message ANAF accepts, with the codes its message list uses for them.
 */
enum MessageType: string
{
    case Notificare = 'NOT';
    case Corectie = 'COR';
    case Stergere = 'DEL';
    case Confirmare = 'CON';
    case ModificareVehicul = 'MVH';
}
