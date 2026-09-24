import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { ArrowLeft, CalendarClock, CreditCard, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import { TextareaField } from '@/components/ui/textarea-field';
import type { OrderPageProps } from '@/types/orders';
import type { RealtimeDomainEvent } from '@/types/notifications';
import { getEcho } from '@/lib/echo';
import { NotificationLink } from '@/components/notification-link';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'full', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

export default function OrderShow({ order, viewer, availablePickupSlots, availableDeliverySlots, canMutate, canMarkReady, canScheduleDelivery }: OrderPageProps) {
    const { flash, auth } = usePage<{ flash?: { status?: string }; auth: { user: { publicId: string } } }>().props;
    usePoll(15000, { only: ['order', 'canMutate', 'canMarkReady', 'canScheduleDelivery', 'availablePickupSlots', 'availableDeliverySlots', 'notifications'] }, { autoStart: !['completed', 'cancelled'].includes(order.fulfillmentStatus) });
    useEffect(() => {
        const echo = getEcho();
        if (!echo) return;
        const channel = echo.private(`users.${auth.user.publicId}`);
        channel.listen('.domain.activity', (event: RealtimeDomainEvent) => {
            if (event.orderPublicId === order.publicId) router.reload({ only: ['order', 'canMutate', 'canMarkReady', 'canScheduleDelivery', 'availablePickupSlots', 'availableDeliverySlots', 'notifications'] });
        });
        return () => { echo.leave(`users.${auth.user.publicId}`); };
    }, [auth.user.publicId, order.publicId]);
    const base = viewer === 'tenant_owner' ? '/tenant/orders' : '/orders';
    const schedule = useForm({ pickup_slot_public_id: '', pickup_date: '', reason: '' });
    const cancellation = useForm({ reason: '' });
    const delivery = useForm({ delivery_slot_public_id: '', delivery_date: '', reason: '' });
    const canPay = viewer === 'customer' && order.fulfillmentStatus === 'awaiting_payment' && order.paymentStatus !== 'paid' && order.grandTotal !== null;
    const chooseSlot = (value: string) => {
        const [slot, day] = value.split('|');
        schedule.setData({ ...schedule.data, pickup_slot_public_id: slot, pickup_date: day });
    };
    const chooseDeliverySlot = (value: string) => {
        const [slot, day] = value.split('|');
        delivery.setData({ ...delivery.data, delivery_slot_public_id: slot, delivery_date: day });
    };

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title={order.orderNumber} /><div className="mx-auto max-w-6xl">
        <header className="flex flex-wrap items-start justify-between gap-4"><div><Link href={base} className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Daftar order</Link><p className="mt-4 text-xs font-bold text-brand-600">{order.orderNumber}</p><h1 className="mt-1 text-3xl font-black">{order.outletName}</h1></div><div className="flex flex-wrap gap-2"><NotificationLink /><StatusBadge tone={order.fulfillmentStatus === 'cancelled' ? 'neutral' : order.fulfillmentStatus === 'completed' ? 'green' : 'amber'}>{order.fulfillmentStatus.replaceAll('_', ' ')}</StatusBadge>{canPay && <Link href={`/orders/${order.publicId}/payments/create`} className="inline-flex items-center gap-2 rounded-xl bg-brand-950 px-4 py-2 text-sm font-bold text-white"><CreditCard className="size-4" />Bayar</Link>}<Link href={`${base}/${order.publicId}/receipt`} className="inline-flex items-center gap-2 rounded-xl border border-line bg-white px-4 py-2 text-sm font-bold"><Printer className="size-4" />Receipt order</Link></div></header>
        {flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}
        <div className="mt-6 grid gap-6 lg:grid-cols-[1.35fr_.65fr]">
            <section className="space-y-5">
                <div className="rounded-panel border border-line bg-surface p-6 shadow-panel"><h2 className="text-xl font-black">Ringkasan</h2><dl className="mt-4 grid gap-4 sm:grid-cols-2"><div><dt className="text-xs text-muted">Paket</dt><dd className="font-bold">{order.item.packageName}</dd></div><div><dt className="text-xs text-muted">Pickup</dt><dd className="font-bold">{date.format(new Date(order.pickupStartsAt))}</dd></div><div><dt className="text-xs text-muted">Pembayaran</dt><dd className="font-bold">{order.paymentStatus}</dd></div><div><dt className="text-xs text-muted">Total</dt><dd className="font-black">{order.grandTotal !== null ? money.format(order.grandTotal) : order.estimatedGrandTotal !== null ? `Estimasi ${money.format(order.estimatedGrandTotal)}` : 'Menunggu berat final'}</dd></div></dl>{order.isEstimate && <p className="mt-4 rounded-xl bg-warning-soft p-3 text-sm text-warning">Nilai per-kg masih estimasi. Tagihan final dibuat setelah berat dikonfirmasi outlet.</p>}</div>
                <div className="rounded-panel border border-line bg-surface p-6"><h2 className="text-xl font-black">Alamat</h2><div className="mt-4 grid gap-3 sm:grid-cols-2">{order.addresses.map((address) => <article key={address.type} className="rounded-xl bg-canvas p-4"><p className="text-xs font-bold uppercase text-brand-600">{address.type}</p><h3 className="mt-1 font-black">{address.contactName} · {address.contactPhone}</h3><p className="mt-2 text-sm text-muted">{address.address}, {address.area}, {address.city}</p></article>)}</div></div>
                <div className="rounded-panel border border-line bg-surface p-6"><h2 className="text-xl font-black">Timeline</h2><ol className="mt-4 space-y-4 border-l-2 border-brand-100 pl-5">{order.statusHistory.map((item) => <li key={`${item.to}-${item.occurredAt}`}><p className="font-bold">{item.to.replaceAll('_', ' ')}</p><p className="text-xs text-muted">{date.format(new Date(item.occurredAt))}{item.reason ? ` · ${item.reason}` : ''}</p></li>)}</ol></div>
            </section>
            <aside className="space-y-5">{canMutate && <>
                <form className="rounded-panel border border-line bg-surface p-5" onSubmit={(event) => { event.preventDefault(); schedule.patch(`${base}/${order.publicId}/pickup-schedule`); }}><h2 className="flex items-center gap-2 font-black"><CalendarClock className="size-5" />Ubah pickup</h2><div className="mt-4 space-y-3"><SelectField label="Jadwal baru" value={schedule.data.pickup_slot_public_id ? `${schedule.data.pickup_slot_public_id}|${schedule.data.pickup_date}` : ''} error={schedule.errors.pickup_slot_public_id} onChange={(event) => chooseSlot(event.target.value)}><option value="">Pilih jadwal</option>{availablePickupSlots.map((slot) => <option key={`${slot.slotPublicId}-${slot.startsAt}`} value={`${slot.slotPublicId}|${slot.startsAt.slice(0, 10)}`}>{date.format(new Date(slot.startsAt))}</option>)}</SelectField>{viewer === 'tenant_owner' && <TextareaField label="Alasan" value={schedule.data.reason} error={schedule.errors.reason} onChange={(event) => schedule.setData('reason', event.target.value)} />}<Button type="submit" disabled={schedule.processing || !schedule.data.pickup_slot_public_id}>Simpan jadwal</Button></div></form>
                <form className="rounded-panel border border-line bg-surface p-5" onSubmit={(event) => { event.preventDefault(); if (window.confirm('Batalkan order ini?')) cancellation.post(`${base}/${order.publicId}/cancellation`); }}><h2 className="font-black">Batalkan order</h2><p className="mt-2 text-sm text-muted">Hanya tersedia sebelum pickup diterima dan sebelum pembayaran berhasil.</p>{viewer === 'tenant_owner' && <div className="mt-3"><TextareaField label="Alasan" value={cancellation.data.reason} error={cancellation.errors.reason} onChange={(event) => cancellation.setData('reason', event.target.value)} /></div>}<Button type="submit" variant="ghost" className="mt-3" disabled={cancellation.processing}>Batalkan</Button></form>
            </>}
            {canMarkReady && <div className="rounded-panel border border-line bg-surface p-5"><h2 className="font-black">Selesai diproses</h2><p className="mt-2 text-sm text-muted">Customer akan diminta memilih jadwal delivery maksimal tujuh hari.</p><Button className="mt-4" onClick={() => router.post(`/tenant/orders/${order.publicId}/ready-for-delivery`)}>Tandai siap delivery</Button></div>}
            {canScheduleDelivery && <form className="rounded-panel border border-line bg-surface p-5" onSubmit={(event) => { event.preventDefault(); delivery.patch(`${base}/${order.publicId}/delivery-schedule`); }}><h2 className="flex items-center gap-2 font-black"><CalendarClock className="size-5" />Jadwal delivery</h2><div className="mt-4 space-y-3"><SelectField label="Slot delivery" value={delivery.data.delivery_slot_public_id ? `${delivery.data.delivery_slot_public_id}|${delivery.data.delivery_date}` : ''} error={delivery.errors.delivery_slot_public_id} onChange={(event) => chooseDeliverySlot(event.target.value)}><option value="">Pilih jadwal</option>{availableDeliverySlots.map((slot) => <option key={`${slot.slotPublicId}-${slot.startsAt}`} value={`${slot.slotPublicId}|${slot.startsAt.slice(0, 10)}`}>{date.format(new Date(slot.startsAt))}</option>)}</SelectField>{viewer === 'tenant_owner' && <TextareaField label="Alasan koordinasi" value={delivery.data.reason} error={delivery.errors.reason} onChange={(event) => delivery.setData('reason', event.target.value)} />}<Button type="submit" disabled={delivery.processing || !delivery.data.delivery_slot_public_id}>Simpan delivery</Button></div></form>}
            <div className="rounded-panel border border-line bg-surface p-5"><h2 className="font-black">Indicator aktif</h2>{order.indicators.filter((item) => item.resolvedAt === null).length === 0 ? <p className="mt-2 text-sm text-muted">Tidak ada keterlambatan aktif.</p> : <ul className="mt-3 space-y-2">{order.indicators.filter((item) => item.resolvedAt === null).map((item) => <li key={item.type} className="rounded-xl bg-warning-soft p-3 text-sm font-bold text-warning">{item.type.replaceAll('_', ' ')}</li>)}</ul>}</div></aside>
        </div>
    </div></main>;
}
