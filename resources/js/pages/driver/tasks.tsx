import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Bike, MapPin } from 'lucide-react';
import { NotificationLink } from '@/components/notification-link';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField } from '@/components/ui/form-field';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import { TextareaField } from '@/components/ui/textarea-field';
import type { DeliveryTask, DriverTaskPage } from '@/types/dispatch';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

function AssignedTask({ task }: { task: DeliveryTask }) {
    const complete = useForm<{ note: string; proof: File | null }>({ note: '', proof: null });
    return <article className="rounded-panel border border-line bg-surface p-5 shadow-panel"><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-bold text-brand-600">{task.orderNumber} · {task.type}</p><h3 className="mt-1 text-lg font-black">{task.outletName}</h3></div><StatusBadge tone={task.status === 'completed' ? 'green' : 'amber'}>{task.status}</StatusBadge></div><p className="mt-3 text-sm text-muted">{date.format(new Date(task.scheduledStartsAt))} · {task.area}</p>{task.contactName && <div className="mt-4 rounded-xl bg-brand-50 p-4"><p className="text-xs font-bold uppercase text-brand-700">Akses sementara Customer</p><p className="mt-1 font-black">{task.contactName} · {task.contactPhone}</p><p className="mt-1 text-sm text-muted">{task.address}</p></div>}{task.status === 'accepted' && <Button className="mt-4" onClick={() => router.post(`/driver/tasks/${task.publicId}/start`)}>Mulai task</Button>}{task.status === 'in_progress' && <form className="mt-4 space-y-3 border-t border-line pt-4" onSubmit={(event) => { event.preventDefault(); complete.post(`/driver/tasks/${task.publicId}/complete`, { forceFormData: true }); }}><TextareaField label="Catatan opsional" value={complete.data.note} error={complete.errors.note} onChange={(event) => complete.setData('note', event.target.value)} /><FormField label="Foto bukti opsional" type="file" accept="image/jpeg,image/png,image/webp" error={complete.errors.proof} onChange={(event) => complete.setData('proof', event.target.files?.[0] ?? null)} /><Button type="submit" disabled={complete.processing}>Selesaikan {task.type}</Button></form>}{task.hasProof && <a href={`/private-proofs/tasks/${task.publicId}`} className="mt-3 inline-block text-sm font-bold text-brand-700">Lihat bukti privat</a>}</article>;
}

export default function DriverTasks({ driver, offers, tasks }: DriverTaskPage) {
    const flash = usePage<{ flash?: { status?: string } }>().props.flash;
    const availability = useForm({ availability: driver.availability });

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6">
        <Head title="Task Driver" />
        <div className="mx-auto max-w-5xl">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div><p className="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Driver workspace</p><h1 className="mt-1 flex items-center gap-2 text-3xl font-black"><Bike className="size-7" />Halo, {driver.name}</h1><p className="mt-1 text-sm text-muted">Satu active task pada satu waktu. Tidak ada live GPS.</p></div>
                <form className="flex items-end gap-2" onSubmit={(event) => { event.preventDefault(); availability.patch('/driver/availability'); }}><SelectField label="Availability" value={availability.data.availability} onChange={(event) => availability.setData('availability', event.target.value as 'available' | 'unavailable')}><option value="available">Available</option><option value="unavailable">Unavailable</option></SelectField><Button type="submit" disabled={availability.processing}>Simpan</Button></form>
                <div className="flex items-center gap-2"><NotificationLink /><Button variant="ghost" onClick={() => router.post('/logout')}>Keluar</Button></div>
            </header>
            {flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}
            <section className="mt-8"><h2 className="text-xl font-black">Offer aktif</h2>{offers.length === 0 ? <div className="mt-3"><EmptyState title="Tidak ada offer" description="Aktifkan availability agar Tenant dapat menawarkan task baru." /></div> : <div className="mt-3 grid gap-4 md:grid-cols-2">{offers.map((offer) => <article key={offer.publicId} className="rounded-panel border border-line bg-surface p-5 shadow-panel"><p className="text-xs font-bold text-brand-600">Berakhir {date.format(new Date(offer.expiresAt))}</p><h3 className="mt-2 text-lg font-black">{offer.task.outletName}</h3><p className="mt-2 flex items-center gap-2 text-sm text-muted"><MapPin className="size-4" />{offer.task.area} · sekitar {offer.task.approximateDistanceKm} km</p><p className="mt-2 text-sm font-bold">{offer.task.type} · {money.format(offer.task.commissionAmount)}</p><p className="mt-2 text-xs text-muted">Alamat dan kontak lengkap baru tersedia setelah offer diterima.</p><div className="mt-4 flex gap-2"><Button onClick={() => router.post(`/driver/offers/${offer.publicId}/accept`)}>Terima</Button><Button variant="secondary" onClick={() => router.post(`/driver/offers/${offer.publicId}/reject`)}>Tolak</Button></div></article>)}</div>}</section>
            <section className="mt-8"><h2 className="text-xl font-black">Task saya</h2>{tasks.length === 0 ? <div className="mt-3"><EmptyState title="Belum ada task" description="Task yang diterima akan tampil di sini." /></div> : <div className="mt-3 grid gap-4 md:grid-cols-2">{tasks.map((task) => <AssignedTask key={task.publicId} task={task} />)}</div>}</section>
        </div>
    </main>;
}
