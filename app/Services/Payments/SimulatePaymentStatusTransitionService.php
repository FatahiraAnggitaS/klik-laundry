<?php

namespace App\Services\Payments;

use App\Enums\SandboxPaymentStatus;

final class SimulatePaymentStatusTransitionService
{
    public function apply(SandboxPaymentStatus $current, SandboxPaymentStatus $incoming): SandboxPaymentStatus
    {
        if ($current === SandboxPaymentStatus::Paid || $current === $incoming) {
            return $current;
        }

        if ($incoming === SandboxPaymentStatus::Paid) {
            return SandboxPaymentStatus::Paid;
        }

        if ($current !== SandboxPaymentStatus::Pending) {
            return $current;
        }

        return $incoming;
    }
}
