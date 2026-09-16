<?php

namespace App\Exceptions\Domain;

use Symfony\Component\HttpFoundation\Response;

final class PlatformSettingsUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            internalMessage: 'The global platform settings record is missing.',
            safeMessage: 'Konfigurasi platform sedang tidak tersedia. Coba lagi beberapa saat.',
            httpStatus: Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
