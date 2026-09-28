<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Privacy\AccountClosureReadinessData;
use App\DTOs\Privacy\OrderPrivacyData;
use App\DTOs\Privacy\PiiRevealGrantData;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\PiiAccessEvent;
use App\Enums\RefundStatus;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\PiiAccessGrant;
use App\Models\PiiAccessLog;
use App\Models\User;
use App\Repositories\Contracts\PrivacyRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentPrivacyRepository implements PrivacyRepositoryInterface
{
    public function accountClosureReadiness(int $customerId): AccountClosureReadinessData
    {
        $activeOrders = Order::query()->where('customer_id', $customerId)
            ->whereNotIn('fulfillment_status', [FulfillmentStatus::Completed, FulfillmentStatus::Cancelled])->count();
        $pendingPayments = DB::table('payments')->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.customer_id', $customerId)->where('payments.status', PaymentStatus::Pending->value)->count();
        $activeRefunds = DB::table('refund_requests')->join('orders', 'orders.id', '=', 'refund_requests.order_id')
            ->where('orders.customer_id', $customerId)
            ->whereIn('refund_requests.status', [RefundStatus::Submitted->value, RefundStatus::Approved->value])->count();

        return new AccountClosureReadinessData($activeOrders, $pendingPayments, $activeRefunds);
    }

    public function anonymizeCustomer(int $customerId, string $pseudonymousEmail, string $passwordHash): void
    {
        $user = User::query()->lockForUpdate()->findOrFail($customerId);
        $originalEmail = $user->email;

        DB::table('customer_addresses')->where('customer_id', $customerId)->delete();
        DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();
        DB::table('sessions')->where('user_id', $customerId)->delete();
        DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $customerId)->delete();

        $user->forceFill([
            'name' => 'Pengguna ditutup',
            'email' => $pseudonymousEmail,
            'phone' => null,
            'password' => $passwordHash,
            'email_verified_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => null,
            'status' => UserStatus::Closed,
            'status_reason' => 'Akun ditutup oleh Customer.',
            'closed_at' => now(),
            'anonymized_at' => now(),
            'auth_version' => $user->auth_version + 1,
        ])->save();
    }

    public function findOrderPrivacy(string $orderPublicId): ?OrderPrivacyData
    {
        $order = Order::query()->with(['customer:id,public_id,name,email,phone', 'addresses'])->where('public_id', $orderPublicId)->first();
        if ($order === null) {
            return null;
        }

        return new OrderPrivacyData(
            orderId: (int) $order->id,
            customerId: (int) $order->customer_id,
            orderPublicId: $order->public_id,
            orderNumber: $order->order_number,
            customerName: $order->customer->name,
            customerEmail: $order->customer->email,
            customerPhone: $order->customer->phone,
            addresses: $order->addresses->map(fn ($address): array => [
                'type' => $address->type->value,
                'contactName' => $address->contact_name,
                'contactPhone' => $address->contact_phone,
                'address' => $address->address,
                'area' => $address->area,
                'city' => $address->city,
            ])->all(),
        );
    }

    public function createGrant(int $actorId, int $orderId, int $customerId, int $authVersion, string $sessionHash, string $reason, string $expiresAt): PiiRevealGrantData
    {
        $grant = PiiAccessGrant::query()->create([
            'public_id' => (string) Str::ulid(),
            'actor_id' => $actorId,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'actor_auth_version' => $authVersion,
            'session_hash' => $sessionHash,
            'reason' => $reason,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return $this->mapGrant($grant);
    }

    public function findActiveGrant(int $actorId, int $orderId, int $authVersion, string $sessionHash, string $now): ?PiiRevealGrantData
    {
        $grant = PiiAccessGrant::query()
            ->where('actor_id', $actorId)->where('order_id', $orderId)
            ->where('actor_auth_version', $authVersion)->where('session_hash', $sessionHash)
            ->whereNull('revoked_at')->where('expires_at', '>', $now)
            ->latest('id')->first();

        return $grant === null ? null : $this->mapGrant($grant);
    }

    public function revokeStaleGrants(int $actorId, int $authVersion, string $now): int
    {
        return $this->closeGrants(
            PiiAccessGrant::query()->where('actor_id', $actorId)->where('actor_auth_version', '!=', $authVersion),
            $now,
            PiiAccessEvent::Revoked,
            100,
        );
    }

    public function expireGrants(string $now, int $limit): int
    {
        return $this->closeGrants(
            PiiAccessGrant::query()->where('expires_at', '<=', $now),
            $now,
            PiiAccessEvent::Expired,
            $limit,
        );
    }

    public function recordAccess(?int $grantId, int $actorId, int $orderId, int $customerId, PiiAccessEvent $event): void
    {
        PiiAccessLog::query()->create([
            'grant_id' => $grantId,
            'actor_id' => $actorId,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'event' => $event,
            'occurred_at' => now(),
        ]);
    }

    private function mapGrant(PiiAccessGrant $grant): PiiRevealGrantData
    {
        return new PiiRevealGrantData(
            (int) $grant->id,
            $grant->public_id,
            (int) $grant->actor_id,
            (int) $grant->order_id,
            (int) $grant->customer_id,
            $grant->expires_at->toIso8601String(),
        );
    }

    /** @param Builder<PiiAccessGrant> $query */
    private function closeGrants(Builder $query, string $closedAt, PiiAccessEvent $event, int $limit): int
    {
        $grants = $query->whereNull('revoked_at')->orderBy('id')->limit($limit)->lockForUpdate()->get();

        foreach ($grants as $grant) {
            $grant->forceFill(['revoked_at' => $closedAt])->save();
            $this->recordAccess(
                (int) $grant->id,
                (int) $grant->actor_id,
                (int) $grant->order_id,
                (int) $grant->customer_id,
                $event,
            );
        }

        return $grants->count();
    }
}
