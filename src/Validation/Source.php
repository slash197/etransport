<?php

declare(strict_types=1);

namespace slash197\eTransport\Validation;

enum Source: string
{
    /** The document is not well-formed XML. */
    case Xml = 'xml';

    /** The document does not follow ANAF's XML schema. */
    case Schema = 'schema';

    /** The document breaks one of ANAF's business rules. */
    case Rules = 'rules';
}
