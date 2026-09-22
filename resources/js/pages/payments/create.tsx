import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import type { PaymentCreatePage } from '@/types/payments';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

export default function PaymentCreate({ order, channels, activeAttempt }: PaymentCreatePage) {
    const flash = usePage<{ flash?: { status?: string } }>().props.flash;
    const form = useForm({ channel_code: '' });

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Bayar order" /><div className="mx-auto max-w-3xl"><header><Link href={`/orders/${order.publicId}`} className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Kembali ke order</Link><h1 className="mt-2 text-3xl font-black">Pembayaran {order.orderNumber}</h1><p className="mt-1 text-sm text-muted">{order.outletName} · {order.tenantName}</p></header>{flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}<Card className="mt-6 p-6"><div className="flex items-center justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.15em] text-muted">Total tagihan</p><p className="mt-1 text-3xl font-black">{order.grandTotal === null ? 'Menunggu timbang' : money.format(order.grandTotal)}</p></div><StatusBadge tone={order.paymentStatus === 'paid' ? 'green' : 'amber'}>{order.paymentStatus}</StatusBadge></div>{order.isEstimate && <p className="mt-3 text-sm text-muted">Paket per-kg ditagih setelah berat final dikonfirmasi outlet.</p>}</Card>{activeAttempt !== null ? <Card className="mt-5 p-6"><h2 className="text-xl font-black">Tagihan aktif</h2><p className="mt-2 text-sm text-muted">Selesaikan pembayaran sebelum kedaluwarsa. Satu order hanya punya satu tagihan aktif.</p><Link href={`/payments/${activeAttempt.publicId}`} className="mt-4 inline-block text-sm font-bold text-brand-700">Buka tagihan aktif</Link></Card> : channels.length === 0 ? <div className="mt-5"><EmptyState title="Channel tidak tersedia" description="Tidak ada channel pembayaran aktif. Coba lagi nanti." /></div> : <form className="mt-5 rounded-panel border border-line bg-surface p-6 shadow-panel" onSubmit={(event) => { event.preventDefault(); form.post(`/orders/${order.publicId}/payments`); }}><h2 className="text-xl font-black">Pilih channel</h2><div className="mt-4"><SelectField label="QRIS / E-Wallet" value={form.data.channel_code} error={form.errors.channel_code} onChange={(event) => form.setData('channel_code', event.target.value)}><option value="">Pilih channel</option>{channels.map((channel) => <option key={channel.code} value={channel.code}>{channel.label}</option>)}</SelectField></div><Button className="mt-4" type="submit" disabled={form.processing || order.grandTotal === null}>Buat tagihan 60 menit</Button></form>}</div></main>;
}
