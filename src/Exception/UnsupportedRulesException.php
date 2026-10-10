<?php

declare(strict_types=1);

namespace slash197\eTransport\Exception;

/**
 * The rules file uses something this package cannot evaluate. It is thrown
 * when the file is loaded, so that a new version of ANAF's rules is never
 * applied in part.
 */
class UnsupportedRulesException extends ETransportException
{
}
