import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { StatusBadge } from '@/components/ui/status-badge';
import type { DriverFinancePage } from '@/types/finance';

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function DriverFinance({ summary, commissions, payouts, filters }: DriverFinancePage) {
    const query = `from=${encodeURIComponent(filters.from)}&to=${encodeURIComponent(filters.to)}`;

    return (
        <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6">
            <Head title="Komisi Driver" />
            <div className="mx-auto max-w-5xl">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <Link href="/driver/tasks" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700">
                            <ArrowLeft className="size-4" />Tugas
                        </Link>
                        <h1 className="mt-2 text-3xl font-black">Komisi Driver</h1>
                        <p className="text-sm text-muted">Komisi earned tidak dibalik oleh refund Customer.</p>
                    </div>
                    <a href={`/driver/finance/export?${query}`} className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-950 px-4 text-sm font-bold text-white">
                        <Download className="size-4" />Export CSV
                    </a>
                </header>

                <section className="mt-6 grid gap-3 sm:grid-cols-3">
                    <Card className="p-5"><p className="text-xs text-muted">Total earned</p><p className="mt-2 text-2xl font-black">{money.format(summary.earnedAmount)}</p></Card>
                    <Card className="p-5"><p className="text-xs text-muted">Sudah paid</p><p className="mt-2 text-2xl font-black">{money.format(summary.paidAmount)}</p></Card>
                    <Card className="p-5"><p className="text-xs text-muted">Jumlah task</p><p className="mt-2 text-2xl font-black">{summary.earnedCount}</p></Card>
                </section>

                <section className="mt-7">
                    <h2 className="text-xl font-black">Riwayat komisi</h2>
                    {commissions.length === 0 ? (
                        <EmptyState title="Belum ada komisi" description="Komisi muncul setelah task selesai." />
                    ) : (
                        <div className="mt-3 space-y-3">
                            {commissions.map((item) => (
                                <Card key={item.publicId} className="flex items-center justify-between gap-3 p-4">
                                    <div><p className="font-bold">{item.orderNumber} · {item.taskType}</p><p className="text-xs text-muted">{new Date(item.earnedAt).toLocaleString('id-ID')}</p></div>
                                    <div className="text-right"><p className="font-black">{money.format(item.amount)}</p><StatusBadge tone={item.status === 'paid' ? 'green' : 'amber'}>{item.status}</StatusBadge></div>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>

                <section className="mt-7">
                    <h2 className="text-xl font-black">Batch payout</h2>
                    {payouts.length === 0 ? (
                        <EmptyState title="Belum ada payout" description="Batch payout yang memuat komisi Anda akan muncul di sini." />
                    ) : (
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {payouts.map((payout) => (
                                <Card key={payout.publicId} className="p-4">
                                    <Link href={`/finance/driver-payouts/${payout.publicId}`} className="font-black text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                                        {payout.batchReference}
                                    </Link>
                                    <p className="mt-1 text-sm text-muted">{money.format(payout.totalAmount)} · {payout.status}</p>
                                </Card>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </main>
    );
}
