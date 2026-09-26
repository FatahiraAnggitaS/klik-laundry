import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { StatusBadge } from '@/components/ui/status-badge';
import type { DriverPayout, PayoutItem, TenantPayout } from '@/types/finance';

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

function formatKey(key: string) {
    return key.replaceAll('_', ' ').replace(/([a-z])([A-Z])/g, '$1 $2');
}

function formatValue(key: string, value: number | string | null) {
    if (value === null) {
        return '—';
    }

    if (typeof value === 'number' && key.toLowerCase().includes('amount')) {
        return money.format(value);
    }

    return String(value);
}

function SourceList({ title, items }: { title: string; items: PayoutItem[] }) {
    if (items.length === 0) {
        return <EmptyState title={`Tidak ada ${title.toLowerCase()}`} description="Batch ini tidak memiliki sumber pada kategori tersebut." />;
    }

    return (
        <section>
            <h2 className="text-xl font-black">{title}</h2>
            <div className="mt-3 space-y-3">
                {items.map((item, index) => (
                    <Card key={`${title}-${index}`} className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        {Object.entries(item).map(([key, value]) => (
                            <div key={key}>
                                <p className="text-xs font-bold uppercase tracking-wide text-muted">{formatKey(key)}</p>
                                <p className="mt-1 break-words font-semibold">{formatValue(key, value)}</p>
                            </div>
                        ))}
                    </Card>
                ))}
            </div>
        </section>
    );
}

function Summary({ payout }: { payout: TenantPayout | DriverPayout }) {
    const amount = 'netAmount' in payout ? payout.netAmount : payout.totalAmount;

    return (
        <Card className="mt-6 p-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-sm text-muted">Batch reference</p>
                    <p className="font-black">{payout.batchReference}</p>
                    <p className="mt-3 text-3xl font-black">{money.format(amount)}</p>
                </div>
                <StatusBadge tone={payout.status === 'finalized' ? 'green' : payout.status === 'pending_transfer' ? 'amber' : 'neutral'}>
                    {payout.status}
                </StatusBadge>
            </div>
            <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt className="text-xs font-bold uppercase text-muted">Cutoff</dt><dd className="mt-1 font-semibold">{payout.cutoffAt}</dd></div>
                <div><dt className="text-xs font-bold uppercase text-muted">Metode</dt><dd className="mt-1 font-semibold">{payout.transferMethod ?? 'Belum ditetapkan'}</dd></div>
                <div><dt className="text-xs font-bold uppercase text-muted">Referensi</dt><dd className="mt-1 break-words font-semibold">{payout.externalReference ?? 'Belum tersedia'}</dd></div>
                <div><dt className="text-xs font-bold uppercase text-muted">Finalisasi</dt><dd className="mt-1 font-semibold">{payout.finalizedAt ?? 'Belum final'}</dd></div>
            </dl>
            {payout.voidReason && <p className="mt-4 rounded-xl bg-warning-soft p-4 text-sm text-warning"><span className="font-bold">Alasan void:</span> {payout.voidReason}</p>}
        </Card>
    );
}

function isTenantPayout(payout: TenantPayout | DriverPayout): payout is TenantPayout {
    return 'payments' in payout;
}

export default function PayoutDetail({ kind, payout }: { kind: 'tenant' | 'driver'; payout: TenantPayout | DriverPayout }) {
    const tenantPayout = kind === 'tenant' && isTenantPayout(payout);

    return (
        <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6">
            <Head title={`Detail payout ${payout.batchReference}`} />
            <div className="mx-auto max-w-6xl">
                <Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                    <ArrowLeft className="size-4" />Workspace
                </Link>
                <h1 className="mt-3 text-3xl font-black">Detail payout {tenantPayout ? 'Tenant' : 'Driver'}</h1>
                <p className="mt-1 text-sm text-muted">Sumber batch bersifat server-selected dan read-only.</p>
                <Summary payout={payout} />

                {isTenantPayout(payout) ? (
                    <div className="mt-7 space-y-7">
                        <Card className="p-5">
                            <h2 className="text-xl font-black">Snapshot rekening</h2>
                            <p className="mt-2 text-sm text-muted">{payout.bankName} · {payout.maskedAccountNumber}</p>
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                <p>Gross <strong>{money.format(payout.grossAmount)}</strong></p>
                                <p>Fee <strong>{money.format(payout.feeAmount)}</strong></p>
                                <p>Adjustment <strong>{money.format(payout.adjustmentAmount)}</strong></p>
                            </div>
                        </Card>
                        <SourceList title="Payment sources" items={payout.payments} />
                        <SourceList title="Adjustment sources" items={payout.adjustments} />
                    </div>
                ) : (
                    <div className="mt-7 space-y-7">
                        <Card className="p-5">
                            <h2 className="text-xl font-black">Driver</h2>
                            <p className="mt-2 font-semibold">{payout.driverName}</p>
                            {payout.note && <p className="mt-2 text-sm text-muted">{payout.note}</p>}
                        </Card>
                        <SourceList title="Commission sources" items={payout.items} />
                    </div>
                )}
            </div>
        </main>
    );
}
