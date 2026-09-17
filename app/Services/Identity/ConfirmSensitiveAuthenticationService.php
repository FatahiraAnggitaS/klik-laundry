<?php

namespace App\Services\Identity;

use App\Contracts\IdentityUser;
use App\Contracts\TransactionManagerInterface;
use App\DTOs\Audit\ActivityLogData;
use App\Gateways\Identity\SensitiveAuthenticationVerifierInterface;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmSensitiveAuthenticationService
{
    public function __construct(
        private SensitiveAuthenticationVerifierInterface $verifier,
        private ActivityLogRepositoryInterface $activityLogs,
        private TransactionManagerInterface $transactions,
    ) {}

    public function handle(IdentityUser $actor, string $password, string $code): void
    {
        $verified = $this->verifier->verify($actor, $password, $code);

        $this->transactions->run(function () use ($actor, $verified): void {
            $this->activityLogs->record(new ActivityLogData(
                tenantId: $actor->tenantId(),
                actorId: $actor->databaseId(),
                action: $verified ? 'identity.sensitive_authentication_confirmed' : 'identity.sensitive_authentication_failed',
                subjectType: 'user',
                subjectId: $actor->publicId(),
                after: ['verified' => $verified],
            ));
        });

        if (! $verified) {
            throw ValidationException::withMessages([
                'password' => 'Password atau kode authenticator tidak valid.',
            ]);
        }
    }
}
