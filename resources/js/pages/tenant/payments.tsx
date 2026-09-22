import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField } from '@/components/ui/form-field';
import { Pagination } from '@/components/ui/pagination';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import type { TenantPaymentPage } from '@/types/payments';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

export default function TenantPayments({ items, meta, filters }: TenantPaymentPage) {
    const [query, setQuery] = useState(filters.query ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [reconciliation, setReconciliation] = useState(filters.reconciliation ?? '');

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Pembayaran tenant" /><div className="mx-auto max-w-6xl">
        <header><Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Workspace</Link><h1 className="mt-2 text-3xl font-black">Pembayaran</h1><p className="mt-1 text-sm text-muted">Daftar read-only attempt, fee aktual, dan status rekonsiliasi Tenant.</p></header>
        <form className="mt-6 grid gap-3 rounded-panel border border-line bg-surface p-5 md:grid-cols-4" onSubmit={(event) => { event.preventDefault(); router.get('/tenant/payments', { query: query || undefined, status: status || undefined, reconciliation: reconciliation || undefined }, { preserveState: true, replace: true }); }}><FormField label="Order / merchant ID" value={query} onChange={(event) => setQuery(event.target.value)} /><SelectField label="Status" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">Semua status</option>{['pending', 'paid', 'failed', 'expired'].map((value) => <option key={value}>{value}</option>)}</SelectField><SelectField label="Rekonsiliasi" value={reconciliation} onChange={(event) => setReconciliation(event.target.value)}><option value="">Semua state</option>{['not_required', 'needs_inquiry', 'matched', 'mismatch'].map((value) => <option key={value}>{value}</option>)}</SelectField><Button className="self-end" type="submit">Terapkan filter</Button></form>
        {items.length === 0 ? <div className="mt-6"><EmptyState title="Belum ada pembayaran" description="Attempt muncul setelah customer membuat tagihan." /></div> : <div className="mt-6 grid gap-4 lg:grid-cols-2">{items.map((payment) => <Card key={payment.publicId} className="p-5"><div className="flex items-start justify-between gap-3"><div><p className="text-sm font-black">{payment.orderNumber} · {money.format(payment.amount)}</p><p className="mt-1 text-xs text-muted">{payment.channelLabel} · {payment.merchantOrderId}</p></div><StatusBadge tone={payment.status === 'paid' ? 'green' : payment.status === 'pending' ? 'amber' : 'neutral'}>{payment.status}</StatusBadge></div><dl className="mt-3 grid grid-cols-2 gap-2 text-xs text-muted"><div><dt>Referensi</dt><dd className="font-bold text-copy">{payment.providerReference ?? '-'}</dd></div><div><dt>Fee aktual</dt><dd className="font-bold text-copy">{payment.feeAmount === null ? 'Belum direkonsiliasi' : money.format(payment.feeAmount)}</dd></div><div><dt>Rekonsiliasi</dt><dd className="font-bold text-copy">{payment.reconciliation}</dd></div></dl></Card>)}</div>}
        <Pagination meta={meta} pageName="payments" />
    </div></main>;
}
