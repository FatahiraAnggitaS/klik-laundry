import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Route } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField } from '@/components/ui/form-field';
import { Pagination } from '@/components/ui/pagination';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import { TextareaField } from '@/components/ui/textarea-field';
import type { DeliveryTask, DispatchPage, DriverSummary } from '@/types/dispatch';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

function TaskCard({ task, drivers }: { task: DeliveryTask; drivers: DriverSummary[] }) {
    const reassign = useForm({ driver_public_id: '', reason: '' });
    const cancellation = useForm({ reason: '' });
    const weight = useForm<{ actual_grams: string; reason: string; proof: File | null }>({ actual_grams: '', reason: '', proof: null });
    const mutable = !['completed', 'cancelled'].includes(task.status);

    return <article className="rounded-panel border border-line bg-surface p-5 shadow-panel">
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div><p className="text-xs font-bold text-brand-600">{task.orderNumber} · {task.type}</p><h3 className="mt-1 text-lg font-black">{task.outletName}</h3><p className="mt-1 text-sm text-muted">{task.area} · {date.format(new Date(task.scheduledStartsAt))}</p></div>
            <StatusBadge tone={task.status === 'completed' ? 'green' : task.status === 'cancelled' ? 'neutral' : 'amber'}>{task.status}</StatusBadge>
        </div>
        <dl className="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt className="text-muted">Driver</dt><dd className="font-bold">{task.assigneeName ?? 'Belum ditugaskan'}</dd></div><div><dt className="text-muted">Komisi snapshot</dt><dd className="font-bold">{money.format(task.commissionAmount)}</dd></div></dl>
        {task.hasProof && <Link href={`/private-proofs/tasks/${task.publicId}`} className="mt-3 inline-block text-sm font-bold text-brand-700">Lihat bukti privat</Link>}
        {mutable && <>
            <form className="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); reassign.post(`/tenant/tasks/${task.publicId}/reassignment`); }}>
                <SelectField label="Driver pengganti" value={reassign.data.driver_public_id} error={reassign.errors.driver_public_id} onChange={(event) => reassign.setData('driver_public_id', event.target.value)}><option value="">Pilih Driver available</option>{drivers.filter((driver) => driver.status === 'active' && driver.availability === 'available').map((driver) => <option key={driver.publicId} value={driver.publicId}>{driver.name}</option>)}</SelectField>
                <TextareaField label="Alasan reassign" value={reassign.data.reason} error={reassign.errors.reason} onChange={(event) => reassign.setData('reason', event.target.value)} />
                <Button type="submit" variant="secondary" disabled={reassign.processing || !reassign.data.driver_public_id}>Reassign</Button>
            </form>
            <form className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]" onSubmit={(event) => { event.preventDefault(); if (window.confirm('Batalkan task sekaligus order ini?')) cancellation.post(`/tenant/tasks/${task.publicId}/cancellation`); }}>
                <TextareaField label="Alasan pembatalan task dan order" value={cancellation.data.reason} error={cancellation.errors.reason} onChange={(event) => cancellation.setData('reason', event.target.value)} />
                <Button type="submit" variant="ghost" className="self-end text-danger" disabled={cancellation.processing || cancellation.data.reason.trim().length < 5}>Batalkan task + order</Button>
            </form>
        </>}
        {task.orderStatus === 'awaiting_weight' && task.pricingType === 'per_kg' && <form className="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); weight.post(`/tenant/orders/${task.orderPublicId}/weight-confirmations`, { forceFormData: true }); }}>
            <FormField label="Berat aktual (gram)" type="number" min="1" max="1000000" value={weight.data.actual_grams} error={weight.errors.actual_grams} onChange={(event) => weight.setData('actual_grams', event.target.value)} />
            <FormField label="Foto timbang opsional" type="file" accept="image/jpeg,image/png,image/webp" error={weight.errors.proof} onChange={(event) => weight.setData('proof', event.target.files?.[0] ?? null)} />
            <div className="sm:col-span-2"><TextareaField label="Alasan (wajib untuk koreksi)" value={weight.data.reason} error={weight.errors.reason} onChange={(event) => weight.setData('reason', event.target.value)} /></div>
            <Button type="submit" disabled={weight.processing}>Konfirmasi berat</Button>
        </form>}
    </article>;
}

export default function TenantDispatch({ tenant, tasks, drivers, settings, eligibleOrders }: DispatchPage) {
    const flash = usePage<{ flash?: { status?: string } }>().props.flash;
    const offer = useForm({ order_public_id: '', driver_public_id: '', type: 'pickup' });

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Dispatch" /><div className="mx-auto max-w-6xl">
        <header className="flex flex-wrap items-start justify-between gap-4"><div><Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Workspace</Link><h1 className="mt-2 flex items-center gap-2 text-3xl font-black"><Route className="size-7" />Dispatch {tenant.name}</h1><p className="mt-1 text-sm text-muted">Satu Driver, satu offer aktif selama 10 menit.</p></div><div className="flex flex-wrap gap-2"><Link href="/tenant/drivers" className="rounded-xl border border-line bg-white px-4 py-3 text-sm font-bold">Kelola Driver</Link><Button variant="ghost" onClick={() => router.post('/logout')}>Keluar</Button></div></header>
        {flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}
        {(settings.pickupCommission === null || settings.deliveryCommission === null) && <p className="mt-5 rounded-xl bg-warning-soft p-4 text-sm font-bold text-warning">Konfigurasikan tarif komisi sebelum membuat offer.</p>}
        <form className="mt-6 rounded-panel border border-line bg-surface p-6 shadow-panel" onSubmit={(event) => { event.preventDefault(); router.post(`/tenant/orders/${offer.data.order_public_id}/driver-offers`, { driver_public_id: offer.data.driver_public_id, type: offer.data.type }, { onSuccess: () => offer.reset() }); }}>
            <h2 className="text-xl font-black">Buat offer pickup</h2><div className="mt-4 grid gap-4 md:grid-cols-3"><SelectField label="Order awaiting pickup" value={offer.data.order_public_id} onChange={(event) => offer.setData('order_public_id', event.target.value)}><option value="">Pilih order</option>{eligibleOrders.map((order) => <option key={order.publicId} value={order.publicId}>{order.orderNumber} · {order.outletName}</option>)}</SelectField><SelectField label="Driver available" value={offer.data.driver_public_id} onChange={(event) => offer.setData('driver_public_id', event.target.value)}><option value="">Pilih Driver</option>{drivers.filter((driver) => driver.status === 'active' && driver.availability === 'available').map((driver) => <option key={driver.publicId} value={driver.publicId}>{driver.name}</option>)}</SelectField><Button className="self-end" type="submit" disabled={offer.processing || !offer.data.order_public_id || !offer.data.driver_public_id || settings.pickupCommission === null}>Tawarkan task</Button></div>
        </form>
        <section className="mt-8"><h2 className="text-xl font-black">Task</h2>{tasks.items.length === 0 ? <div className="mt-3"><EmptyState title="Belum ada task" description="Pilih order dan Driver available untuk memulai dispatch." /></div> : <div className="mt-3 grid gap-4 lg:grid-cols-2">{tasks.items.map((task) => <TaskCard key={task.publicId} task={task} drivers={drivers} />)}</div>}<Pagination meta={tasks.meta} pageName="tasks" /></section>
    </div></main>;
}
