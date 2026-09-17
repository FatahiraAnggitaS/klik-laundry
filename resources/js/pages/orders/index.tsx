import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField } from '@/components/ui/form-field';
import { Pagination } from '@/components/ui/pagination';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import type { PaginatedOrders } from '@/types/orders';

interface Props { orders: PaginatedOrders; filters: { fulfillment_status: string | null; payment_status: string | null; query: string | null }; viewer: 'customer' | 'tenant_owner'; statusOptions: { value: string; label: string }[] }
const date = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });
const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

export default function OrdersIndex({ orders, filters, viewer, statusOptions }: Props) {
    const [query, setQuery] = useState(filters.query ?? '');
    const [status, setStatus] = useState(filters.fulfillment_status ?? '');
    const base = viewer === 'tenant_owner' ? '/tenant/orders' : '/orders';
    const apply = () => router.get(base, { query: query || undefined, fulfillment_status: status || undefined }, { preserveState: true, replace: true });
    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Order" /><div className="mx-auto max-w-6xl"><header><Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Workspace</Link><h1 className="mt-2 text-3xl font-black">{viewer === 'tenant_owner' ? 'Order Tenant' : 'Order saya'}</h1><p className="mt-1 text-sm text-muted">Status fulfillment dan pembayaran dipisahkan agar timeline jelas.</p></header><form className="mt-6 grid gap-3 rounded-panel border border-line bg-surface p-4 sm:grid-cols-[1fr_240px_auto]" onSubmit={(event) => { event.preventDefault(); apply(); }}><FormField label="Cari nomor order" value={query} onChange={(event) => setQuery(event.target.value)} /><SelectField label="Status fulfillment" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">Semua status</option>{statusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</SelectField><button className="self-end rounded-xl bg-brand-950 px-5 py-3 text-sm font-bold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">Terapkan</button></form>{orders.items.length === 0 ? <div className="mt-6"><EmptyState title="Belum ada order" description="Order yang sesuai filter akan tampil di sini." /></div> : <div className="mt-6 grid gap-4 md:grid-cols-2">{orders.items.map((order) => <Link key={order.publicId} href={`${base}/${order.publicId}`} className="rounded-panel border border-line bg-surface p-5 shadow-panel transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-bold text-brand-600">{order.orderNumber}</p><h2 className="mt-1 text-lg font-black">{order.outletName}</h2></div><StatusBadge tone={order.fulfillmentStatus === 'cancelled' ? 'neutral' : order.fulfillmentStatus === 'completed' ? 'green' : 'amber'}>{order.fulfillmentStatus.replaceAll('_', ' ')}</StatusBadge></div><p className="mt-4 text-sm text-muted">Pickup {date.format(new Date(order.pickupStartsAt))}</p><div className="mt-4 flex items-end justify-between"><span className="text-xs font-bold text-muted">Pembayaran: {order.paymentStatus}</span><span className="font-black">{order.grandTotal !== null ? money.format(order.grandTotal) : order.estimatedGrandTotal !== null ? `Estimasi ${money.format(order.estimatedGrandTotal)}` : 'Menunggu berat final'}</span></div></Link>)}</div>}<Pagination meta={orders.meta} /></div></main>;
}
