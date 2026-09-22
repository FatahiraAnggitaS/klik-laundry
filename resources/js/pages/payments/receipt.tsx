import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { PaymentShowPage } from '@/types/payments';

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

export default function PaymentReceipt({ payment }: PaymentShowPage) {
    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Receipt" /><div className="mx-auto max-w-2xl"><header className="print:hidden"><Link href={`/payments/${payment.publicId}`} className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Kembali ke tagihan</Link><h1 className="mt-2 text-3xl font-black">Receipt</h1></header><Card className="mt-6 p-6"><dl className="space-y-3 text-sm"><div className="flex justify-between gap-4"><dt className="text-muted">Nomor order</dt><dd className="font-black">{payment.orderNumber}</dd></div><div className="flex justify-between gap-4"><dt className="text-muted">Referensi</dt><dd className="font-bold">{payment.providerReference ?? '-'}</dd></div><div className="flex justify-between gap-4"><dt className="text-muted">Channel</dt><dd className="font-bold">{payment.channelLabel}</dd></div><div className="flex justify-between gap-4"><dt className="text-muted">Nominal</dt><dd className="font-black">{money.format(payment.amount)}</dd></div><div className="flex justify-between gap-4"><dt className="text-muted">Waktu bayar</dt><dd className="font-bold">{payment.paidAt === null ? '-' : date.format(new Date(payment.paidAt))}</dd></div><div className="flex justify-between gap-4"><dt className="text-muted">Status</dt><dd className="font-bold">{payment.status}</dd></div></dl><Button className="mt-6 print:hidden" variant="secondary" onClick={() => window.print()}><Printer className="size-4" />Cetak</Button></Card></div></main>;
}
