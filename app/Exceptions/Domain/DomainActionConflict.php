<?php

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

final class DomainActionConflict extends DomainException
{
    public function __construct(string $internalMessage, string $safeMessage)
    {
        parent::__construct($internalMessage, $safeMessage, Response::HTTP_CONFLICT);
    }
}
