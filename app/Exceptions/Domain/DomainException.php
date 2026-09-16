<?php

namespace App\Exceptions\Domain;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    public function __construct(
        string $internalMessage,
        private readonly string $safeMessage,
        private readonly int $httpStatus,
    ) {
        parent::__construct($internalMessage);
    }

    public function safeMessage(): string
    {
        return $this->safeMessage;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
