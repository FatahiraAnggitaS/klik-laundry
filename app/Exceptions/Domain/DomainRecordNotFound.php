<?php

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

final class DomainRecordNotFound extends DomainException
{
    public function __construct(string $internalMessage = 'The requested domain record was not found.')
    {
        parent::__construct($internalMessage, 'Data yang diminta tidak ditemukan.', Response::HTTP_NOT_FOUND);
    }
}
