import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCheck } from 'lucide-react';
import type { MouseEvent } from 'react';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Pagination } from '@/components/ui/pagination';
import type { NotificationItem, PaginatedNotifications } from '@/types/notifications';

const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

export default function NotificationIndex({ notifications }: { notifications: PaginatedNotifications }) {
    const { flash } = usePage<{ flash?: { status?: string } }>().props;
    const open = (event: MouseEvent<Element>, item: NotificationItem) => {
        if (item.readAt) return;

        event.preventDefault();
        router.patch(`/notifications/${item.id}/read`, {}, { onSuccess: () => router.visit(item.link) });
    };

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6">
        <Head title="Notifikasi" />
        <div className="mx-auto max-w-3xl">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Workspace</Link>
                    <h1 className="mt-4 text-3xl font-black">Notifikasi</h1>
                    <p className="mt-2 text-sm text-muted">Riwayat aktivitas aman tanpa alamat, nomor telepon, atau payment URL.</p>
                </div>
                <Button type="button" variant="ghost" onClick={() => router.post('/notifications/read-all')}><CheckCheck className="size-4" />Baca semua</Button>
            </header>
            {flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}
            {notifications.items.length === 0
                ? <div className="mt-8"><EmptyState title="Belum ada notifikasi" description="Pembaruan order dan task akan muncul di sini." /></div>
                : <ol className="mt-8 space-y-3">
                    {notifications.items.map((item) => <li key={item.id} className={`rounded-panel border p-5 ${item.readAt ? 'border-line bg-surface' : 'border-brand-200 bg-brand-50'}`}>
                        <Link href={item.link} onClick={(event) => open(event, item)} className="block rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <p className="font-black">{item.message}</p>
                            <p className="mt-2 text-xs text-muted">{date.format(new Date(item.occurredAt))}</p>
                        </Link>
                    </li>)}
                </ol>}
            <Pagination meta={notifications.meta} />
        </div>
    </main>;
}
