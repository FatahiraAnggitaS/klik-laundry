<?php

namespace App\Listeners;

use App\Events\DispatchLifecycleEvent;
use App\Events\UserActivityBroadcast;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;

final readonly class PersistAndBroadcastLifecycleNotification implements ShouldQueue
{
    public string $queue;

    public function __construct(private NotificationRepositoryInterface $notifications)
    {
        $this->queue = 'notifications';
    }

    public function handle(DispatchLifecycleEvent $event): void
    {
        $order = Order::query()->select(['id', 'public_id', 'customer_id', 'tenant_id'])->where('public_id', $event->orderPublicId)->first();
        if ($order === null) {
            return;
        }

        $recipientIds = array_values(array_unique(array_filter([
            $event->driverId,
            $event->customerId ?? (int) $order->customer_id,
            ...User::query()->where('tenant_id', $event->tenantId)->where('role', 'tenant_owner')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ])));

        foreach (User::query()->whereKey($recipientIds)->get(['id', 'public_id', 'role']) as $user) {
            $payload = $this->payload($event, $user);
            $notification = $this->notifications->store((int) $user->id, $event->eventId.':'.$user->id, $payload);
            if (! $notification['created']) {
                continue;
            }
            Event::dispatch(new UserActivityBroadcast($user->public_id, [
                'notificationId' => $notification['id'],
                'event' => $event->name,
                'orderPublicId' => $event->orderPublicId,
                'taskPublicId' => $event->taskPublicId,
                'occurredAt' => $event->occurredAt,
                'link' => $payload['link'],
            ]));
        }
    }

    /** @return array<string, string|null> */
    private function payload(DispatchLifecycleEvent $event, User $user): array
    {
        return [
            'event' => $event->name,
            'message' => $this->message($event->name),
            'orderPublicId' => $event->orderPublicId,
            'taskPublicId' => $event->taskPublicId,
            'occurredAt' => $event->occurredAt,
            'link' => match ($user->role->value) {
                'tenant_owner' => '/tenant/orders/'.$event->orderPublicId,
                'driver' => '/driver/tasks',
                default => '/orders/'.$event->orderPublicId,
            },
        ];
    }

    private function message(string $event): string
    {
        return match ($event) {
            'payment.paid' => 'Pembayaran telah terverifikasi.',
            'order.processing' => 'Laundry sedang diproses.',
            'order.ready_for_delivery' => 'Laundry siap dijadwalkan untuk delivery.',
            'order.delivery_scheduled' => 'Jadwal delivery diperbarui.',
            'order.out_for_delivery' => 'Laundry sedang diantar.',
            'order.completed' => 'Order telah selesai.',
            'driver_task.offered' => 'Task baru tersedia selama 10 menit.',
            'driver_task.accepted' => 'Driver menerima task.',
            'driver_task.rejected' => 'Driver menolak task.',
            'driver_task.offer_expired' => 'Offer Driver kedaluwarsa.',
            'driver_task.reassigned' => 'Task dialihkan ke Driver lain.',
            'driver_task.completed' => 'Task Driver selesai.',
            'order_indicator.pickup_delayed' => 'Pickup melewati jadwal dan perlu ditindaklanjuti.',
            'order_indicator.delayed' => 'Proses laundry melewati estimasi selesai.',
            'order_indicator.delivery_delayed' => 'Delivery melewati jadwal dan perlu ditindaklanjuti.',
            'order_indicator.awaiting_customer' => 'Jadwal delivery belum dipilih setelah tujuh hari.',
            default => 'Status order diperbarui.',
        };
    }
}
