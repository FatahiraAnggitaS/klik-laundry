import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import type { PaymentShowPage } from '@/types/payments';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

export default function PaymentShow({ payment }: PaymentShowPage) {
    const flash = usePage<{ flash?: { status?: string } }>().props.flash;

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Tagihan" /><div className="mx-auto max-w-3xl"><header><Link href={`/orders/${payment.orderPublicId}`} className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Kembali ke order</Link><h1 className="mt-2 text-3xl font-black">Tagihan {payment.orderNumber}</h1><p className="mt-1 text-sm text-muted">{payment.channelLabel} · {money.format(payment.amount)}</p></header>{flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}<Card className="mt-6 p-6"><div className="flex items-center justify-between gap-3"><StatusBadge tone={payment.status === 'paid' ? 'green' : payment.status === 'pending' ? 'amber' : 'neutral'}>{payment.status}</StatusBadge><p className="text-sm text-muted">Kedaluwarsa {date.format(new Date(payment.expiresAt))}</p></div>{payment.status === 'pending' && payment.paymentUrl !== null && <a href={payment.paymentUrl} target="_blank" rel="noreferrer" className="mt-5 inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand-900 px-5 text-sm font-bold text-white">Buka halaman bayar <ExternalLink className="size-4" /></a>}{payment.status === 'paid' && <Link href={`/payments/${payment.publicId}/receipt`} className="mt-5 inline-block text-sm font-bold text-brand-700">Lihat receipt</Link>}{payment.reconciliation === 'needs_inquiry' && <p className="mt-4 text-sm font-bold text-warning">Status sedang diverifikasi. Jangan buat tagihan baru.</p>}</Card></div></main>;
}
