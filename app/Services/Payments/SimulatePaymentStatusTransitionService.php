<?php

namespace App\Services\Payments;

use App\Enums\SandboxPaymentStatus;

final class SimulatePaymentStatusTransitionService
{
    public function apply(SandboxPaymentStatus $current, SandboxPaymentStatus $incoming): SandboxPaymentStatus
    {
        if ($current !== SandboxPaymentStatus::Pending || $current === $incoming) {
            return $current;
        }

        return $incoming;
    }
}
