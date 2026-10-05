import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { refundStatusLabels } from '@/lib/finance-labels';
import type { RefundRequest } from '@/types/finance';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
export default function RefundDetail({ refund }: { refund: RefundRequest }) {
    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6">
        <Head title={`Pengembalian dana ${refund.orderNumber}`} />
        <div className="mx-auto max-w-3xl">
            <Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Workspace</Link>
            <Card className="mt-5 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase text-muted">Pengembalian dana penuh secara manual</p><h1 className="mt-1 text-2xl font-black">{refund.orderNumber}</h1></div><StatusBadge tone={refund.status === 'completed' ? 'green' : refund.status === 'rejected' ? 'neutral' : 'amber'}>{refundStatusLabels[refund.status]}</StatusBadge></div>
                <dl className="mt-6 grid gap-4 sm:grid-cols-2">
                    <div><dt className="text-xs text-muted">Nominal tetap</dt><dd className="mt-1 font-black">{money.format(refund.amount)}</dd></div>
                    <div><dt className="text-xs text-muted">ID pembayaran</dt><dd className="mt-1 break-words font-bold">{refund.paymentPublicId}</dd></div>
                    <div className="sm:col-span-2"><dt className="text-xs text-muted">Alasan Tenant</dt><dd className="mt-1 font-bold">{refund.reason}</dd></div>
                    {refund.reviewReason && <div className="sm:col-span-2"><dt className="text-xs text-muted">Alasan peninjauan</dt><dd className="mt-1 font-bold">{refund.reviewReason}</dd></div>}
                    <div><dt className="text-xs text-muted">Referensi transfer</dt><dd className="mt-1 font-bold">{refund.externalReference ?? '-'}</dd></div>
                    <div><dt className="text-xs text-muted">Selesai</dt><dd className="mt-1 font-bold">{refund.completedAt ? new Date(refund.completedAt).toLocaleString('id-ID') : '-'}</dd></div>
                </dl>
                <p className="mt-6 rounded-xl bg-brand-50 p-4 text-sm text-muted">Status pembayaran asli tetap lunas. Pengembalian dana yang selesai dicatat sebagai penyesuaian keuangan bernilai negatif.</p>
            </Card>
        </div>
    </main>;
}
