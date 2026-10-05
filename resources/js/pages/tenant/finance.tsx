import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmButton } from '@/components/ui/confirm-button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField } from '@/components/ui/form-field';
import { InteractiveBarChart } from '@/components/ui/interactive-bar-chart';
import { Pagination } from '@/components/ui/pagination';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import { TextareaField } from '@/components/ui/textarea-field';
import { payoutStatusLabels, refundStatusLabels } from '@/lib/finance-labels';
import type { DriverPayout, TenantFinancePage } from '@/types/finance';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

function DriverPayoutCard({ payout }: { payout: DriverPayout }) {
    const finalize = useForm({ method: payout.totalAmount === 0 ? 'no_transfer_required' : 'bank_transfer', reference: '', note: '', reason: '' });
    const voidForm = useForm({ reason: '' });
    return <Card className="p-5">
        <div className="flex items-start justify-between gap-3">
            <div><Link href={`/finance/driver-payouts/${payout.publicId}`} className="font-black text-brand-700">{payout.batchReference}</Link><p className="mt-1 text-sm text-muted">{payout.driverName} · {money.format(payout.totalAmount)}</p></div>
            <StatusBadge tone={payout.status === 'finalized' ? 'green' : payout.status === 'pending_transfer' ? 'amber' : 'neutral'}>{payoutStatusLabels[payout.status]}</StatusBadge>
        </div>
        {payout.status === 'pending_transfer' && <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <form className="space-y-3" onSubmit={(event) => event.preventDefault()}>
                <SelectField label="Metode" value={finalize.data.method} onChange={(event) => finalize.setData('method', event.target.value)}><option value="bank_transfer">Transfer bank</option><option value="e_wallet">Dompet digital</option>{payout.totalAmount === 0 && <option value="no_transfer_required">Tidak perlu transfer</option>}</SelectField>
                <FormField label="Referensi transfer" value={finalize.data.reference} error={finalize.errors.reference} onChange={(event) => finalize.setData('reference', event.target.value)} />
                <TextareaField label="Alasan finalisasi" value={finalize.data.reason} error={finalize.errors.reason} onChange={(event) => finalize.setData('reason', event.target.value)} />
                <ConfirmButton title="Finalisasi pencairan Driver?" description="Pencairan akan menjadi final dan tidak dapat diubah." confirmLabel="Finalisasi" onConfirm={() => finalize.post(`/tenant/driver-payouts/${payout.publicId}/finalization`)} loading={finalize.processing} disabled={finalize.processing}>Finalisasi</ConfirmButton>
            </form>
            <form className="space-y-3" onSubmit={(event) => event.preventDefault()}>
                <TextareaField label="Alasan pembatalan" value={voidForm.data.reason} error={voidForm.errors.reason} onChange={(event) => voidForm.setData('reason', event.target.value)} />
                <ConfirmButton title="Batalkan pencairan Driver?" description="Pencairan dibatalkan dan komisi perlu diproses ulang." confirmLabel="Batalkan pencairan" onConfirm={() => voidForm.post(`/tenant/driver-payouts/${payout.publicId}/void`)} variant="secondary" loading={voidForm.processing} disabled={voidForm.processing}>Batalkan pencairan</ConfirmButton>
            </form>
        </div>}
    </Card>;
}

export default function TenantFinance({ summary, payments, refunds, tenantPayouts, driverPayouts, drivers, outlets, filters }: TenantFinancePage) {
    const { flash } = usePage<{ flash?: { status?: string } }>().props;
    const [from, setFrom] = useState(filters.from); const [to, setTo] = useState(filters.to); const [outlet, setOutlet] = useState(filters.outlet ?? '');
    const refund = useForm({ payment_public_id: '', reason: '' });
    const driverPayout = useForm({ driver_public_id: drivers[0]?.publicId ?? '', cutoff: filters.to });
    const exportUrl = `/tenant/finance/export?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&outlet=${encodeURIComponent(outlet)}`;
    const metrics = [['Total pembayaran lunas', summary.grossPaid], ['Biaya Duitku aktual', summary.gatewayFeeActual], ['Komisi Driver', summary.driverCommission], ['Penyesuaian keuangan', summary.financialAdjustment], ['Bersih operasional', summary.netOperational], ['Pergerakan dana penyelesaian', summary.settlementMovement]] as const;
    const chartData = metrics.flatMap(([label, value]) => value === null ? [] : [{ label, value }]);

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Keuangan Tenant" /><div className="mx-auto max-w-7xl"><header className="flex flex-wrap items-end justify-between gap-4"><div><Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Workspace</Link><h1 className="mt-2 text-3xl font-black">Keuangan Tenant</h1><p className="mt-2 text-sm text-muted">Semua waktu laporan memakai WIB. Biaya Duitku yang belum diketahui tidak dianggap nol.</p></div><a href={exportUrl} className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-950 px-4 text-sm font-bold text-white"><Download className="size-4" />Unduh CSV</a></header>{flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}
        <form className="mt-6 grid gap-3 rounded-panel border border-line bg-surface p-5 sm:grid-cols-2 xl:grid-cols-4" onSubmit={(event) => { event.preventDefault(); router.get('/tenant/finance', { from, to, outlet: outlet || undefined }, { preserveState: true, replace: true, only: ['summary', 'payments', 'filters'] }); }}><FormField label="Dari" type="date" value={from} onChange={(event) => setFrom(event.target.value)} /><FormField label="Sampai" type="date" value={to} onChange={(event) => setTo(event.target.value)} /><SelectField label="Outlet" value={outlet} onChange={(event) => setOutlet(event.target.value)}><option value="">Semua outlet</option>{outlets.map((item) => <option key={item.publicId} value={item.publicId}>{item.name}</option>)}</SelectField><Button className="self-end" type="submit">Terapkan</Button></form>
        {!summary.isFinal && <p role="status" className="mt-4 rounded-xl bg-warning-soft p-4 text-sm font-bold text-warning">{summary.unknownFeeCount} pembayaran belum memiliki biaya Duitku aktual. Nilai bersih belum final.</p>}
        <section className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{metrics.map(([label, value]) => <Card key={label} className="p-5"><p className="text-xs font-bold uppercase tracking-wide text-muted">{label}</p><p className="mt-2 text-2xl font-black">{value === null ? 'Belum final' : money.format(value)}</p></Card>)}</section>
        <div className="mt-5"><InteractiveBarChart title="Komposisi keuangan periode" data={chartData} formatValue={(value) => money.format(value)} /></div>
        <div className="mt-6 grid gap-6 xl:grid-cols-2"><section><h2 className="text-xl font-black">Pembayaran terbaru</h2>{payments.length === 0 ? <EmptyState title="Belum ada pembayaran" description="Pembayaran lunas pada periode ini akan muncul di sini." /> : <div className="mt-3 space-y-3">{payments.map((payment) => <Card key={payment.publicId} className="p-4"><div className="flex justify-between gap-3"><div><p className="font-bold">{payment.orderNumber} · {payment.outletName}</p><p className="mt-1 text-xs text-muted">Bruto {money.format(payment.grossAmount)} · Biaya Duitku {payment.feeAmount === null ? 'belum direkonsiliasi' : money.format(payment.feeAmount)}</p></div><StatusBadge tone={payment.feeFinal ? 'green' : 'amber'}>{payment.feeFinal ? 'Biaya final' : 'Biaya belum final'}</StatusBadge></div></Card>)}</div>}</section>
        <section><h2 className="text-xl font-black">Ajukan pengembalian dana penuh</h2><form className="mt-3 space-y-3 rounded-panel border border-line bg-surface p-5" onSubmit={(event) => event.preventDefault()}><FormField label="ID pembayaran lunas" value={refund.data.payment_public_id} error={refund.errors.payment_public_id} onChange={(event) => refund.setData('payment_public_id', event.target.value)} /><TextareaField label="Alasan" value={refund.data.reason} error={refund.errors.reason} onChange={(event) => refund.setData('reason', event.target.value)} /><ConfirmButton title="Ajukan pengembalian dana penuh?" description="Permintaan untuk pembayaran ini akan masuk antrean peninjauan Super User." confirmLabel="Ajukan pengembalian dana" onConfirm={() => refund.post('/tenant/refunds', { onSuccess: () => refund.reset() })} loading={refund.processing} disabled={refund.processing}>Ajukan pengembalian dana</ConfirmButton></form><div className="mt-3 space-y-2">{refunds.items.map((item) => <Link key={item.publicId} href={`/finance/refunds/${item.publicId}`} className="block rounded-xl border border-line bg-surface p-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"><span className="font-bold">{item.orderNumber} · {money.format(item.amount)}</span><span className="ml-2 text-xs text-muted">{refundStatusLabels[item.status]}</span></Link>)}</div></section></div>
        <section className="mt-8"><h2 className="text-xl font-black">Pencairan komisi Driver</h2><form className="mt-3 grid gap-3 rounded-panel border border-line bg-surface p-5 sm:grid-cols-3" onSubmit={(event) => event.preventDefault()}><SelectField label="Driver" value={driverPayout.data.driver_public_id} error={driverPayout.errors.driver_public_id} onChange={(event) => driverPayout.setData('driver_public_id', event.target.value)}><option value="">Pilih Driver</option>{drivers.map((driver) => <option key={driver.publicId} value={driver.publicId}>{driver.name}</option>)}</SelectField><FormField label="Tanggal batas" type="date" value={driverPayout.data.cutoff} error={driverPayout.errors.cutoff} onChange={(event) => driverPayout.setData('cutoff', event.target.value)} /><ConfirmButton className="self-end" title="Buat pencairan komisi Driver?" description="Semua komisi yang memenuhi syarat hingga tanggal batas dipilih otomatis oleh sistem." confirmLabel="Buat pencairan" onConfirm={() => driverPayout.post('/tenant/driver-payouts')} loading={driverPayout.processing} disabled={driverPayout.processing}>Buat pencairan</ConfirmButton></form><div className="mt-4 grid gap-4 lg:grid-cols-2">{driverPayouts.map((payout) => <DriverPayoutCard key={payout.publicId} payout={payout} />)}</div></section>
        <section className="mt-8"><h2 className="text-xl font-black">Pencairan Tenant</h2><p className="mt-2 text-sm text-muted">Informasi ini hanya dapat dilihat. Transfer dicatat oleh Super User.</p><div className="mt-4 grid gap-4 lg:grid-cols-2">{tenantPayouts.map((payout) => <Card key={payout.publicId} className="p-5"><div className="flex justify-between gap-3"><div><Link href={`/finance/tenant-payouts/${payout.publicId}`} className="font-black text-brand-700">{payout.batchReference}</Link><p className="mt-1 text-sm text-muted">{payout.bankName} · {payout.maskedAccountNumber}</p></div><StatusBadge tone={payout.status === 'finalized' ? 'green' : payout.status === 'pending_transfer' ? 'amber' : 'neutral'}>{payoutStatusLabels[payout.status]}</StatusBadge></div><p className="mt-3 text-2xl font-black">{money.format(payout.netAmount)}</p></Card>)}</div></section>
        <Pagination meta={refunds.meta} pageName="refunds" />
    </div></main>;
}
