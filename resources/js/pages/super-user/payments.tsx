import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField } from '@/components/ui/form-field';
import { Pagination } from '@/components/ui/pagination';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import type { ReconciliationPage } from '@/types/payments';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

export default function PaymentReconciliation({ items, meta, filters }: ReconciliationPage) {
    const { flash } = usePage<{ flash?: { status?: string } }>().props;
    const [query, setQuery] = useState(filters.query ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [reconciliation, setReconciliation] = useState(filters.reconciliation ?? '');
    const applyFilters = () => router.get('/super-user/payments', { query: query || undefined, status: status || undefined, reconciliation: reconciliation || undefined }, { preserveState: true, replace: true });

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Rekonsiliasi pembayaran" /><div className="mx-auto max-w-6xl">
        <header><Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Workspace</Link><h1 className="mt-2 text-3xl font-black">Rekonsiliasi pembayaran</h1><p className="mt-1 text-sm text-muted">Inquiry selalu manual; callback tetap sumber konfirmasi otomatis.</p></header>
        {flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}
        <form className="mt-6 grid gap-3 rounded-panel border border-line bg-surface p-5 md:grid-cols-4" onSubmit={(event) => { event.preventDefault(); applyFilters(); }}><FormField label="Order / merchant ID" value={query} onChange={(event) => setQuery(event.target.value)} /><SelectField label="Status" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">Semua status</option>{['pending', 'paid', 'failed', 'expired'].map((value) => <option key={value}>{value}</option>)}</SelectField><SelectField label="Rekonsiliasi" value={reconciliation} onChange={(event) => setReconciliation(event.target.value)}><option value="">Semua state</option>{['not_required', 'needs_inquiry', 'matched', 'mismatch'].map((value) => <option key={value}>{value}</option>)}</SelectField><Button className="self-end" type="submit">Terapkan filter</Button></form>
        {items.length === 0 ? <div className="mt-6"><EmptyState title="Tidak ada payment" description="Ubah filter atau tunggu customer membuat tagihan." /></div> : <div className="mt-6 grid gap-4 lg:grid-cols-2">{items.map((payment) => <Card key={payment.publicId} className="p-5"><div className="flex items-start justify-between gap-3"><div><p className="font-black">{payment.orderNumber} · {payment.tenantName}</p><p className="mt-1 text-xs text-muted">{payment.merchantOrderId} · {payment.channelLabel}</p></div><StatusBadge tone={payment.status === 'paid' ? 'green' : payment.status === 'pending' ? 'amber' : 'neutral'}>{payment.status}</StatusBadge></div><dl className="mt-4 grid grid-cols-2 gap-3 text-xs"><div><dt className="text-muted">Nominal</dt><dd className="font-bold">{money.format(payment.amount)}</dd></div><div><dt className="text-muted">Fee aktual</dt><dd className="font-bold">{payment.feeAmount === null ? 'Belum tersedia' : money.format(payment.feeAmount)}</dd></div><div><dt className="text-muted">Referensi</dt><dd className="break-all font-bold">{payment.providerReference ?? '-'}</dd></div><div><dt className="text-muted">Rekonsiliasi</dt><dd className="font-bold">{payment.reconciliation}</dd></div></dl><Button className="mt-4" variant="secondary" onClick={() => router.post(`/super-user/payments/${payment.publicId}/inquiry`)}><RefreshCw className="size-4" />Inquiry manual</Button></Card>)}</div>}
        <Pagination meta={meta} />
        <p className="mt-4 text-xs text-muted">Inquiry membutuhkan konfirmasi sensitif aktif. <Link href="/identity/confirm-sensitive-action" className="font-bold text-brand-700">Konfirmasi sekarang</Link></p>
    </div></main>;
}
