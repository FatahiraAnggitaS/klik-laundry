import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MapPin, Navigation, Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField } from '@/components/ui/form-field';
import { Pagination } from '@/components/ui/pagination';
import { SelectField } from '@/components/ui/select-field';
import type { OutletSearchFilters, OutletSummary, Paginated } from '@/types/operations';

interface Props { outlets: Paginated<OutletSummary>; filters: OutletSearchFilters }

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

export default function OutletIndex({ outlets, filters }: Props) {
    const { auth } = usePage<{ auth: { user: { role: string } | null } }>().props;
    const form = useForm({ q: filters.query ?? '', pricing_type: filters.pricingType ?? '', latitude: filters.latitude?.toString() ?? '', longitude: filters.longitude?.toString() ?? '' });
    const [locationError, setLocationError] = useState('');
    const search = () => form.get('/outlets', { preserveState: true, preserveScroll: true, only: ['outlets', 'filters'] });
    const locate = () => {
        setLocationError('');
        if (!navigator.geolocation) { setLocationError('Browser tidak mendukung geolokasi.'); return; }
        navigator.geolocation.getCurrentPosition((position) => {
            const latitude = position.coords.latitude.toString();
            const longitude = position.coords.longitude.toString();
            form.setData((data) => ({ ...data, latitude, longitude }));
            router.get('/outlets', { q: form.data.q, pricing_type: form.data.pricing_type, latitude, longitude }, { preserveState: true });
        }, () => setLocationError('Lokasi tidak diberikan. Anda tetap dapat mencari berdasarkan area.'));
    };

    return <main className="min-h-screen bg-canvas text-ink"><Head title="Cari outlet" /><header className="border-b border-line bg-brand-950 text-white"><div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5 sm:px-6"><Link href="/" className="text-lg font-black">Klik Laundry</Link><nav className="flex items-center gap-3 text-sm font-bold"><Link href={auth.user ? '/workspace' : '/login'}>{auth.user ? 'Workspace' : 'Masuk'}</Link>{auth.user?.role === 'customer' && <Link href="/customer/addresses" className="rounded-xl bg-accent px-3 py-2 text-brand-950">Alamat saya</Link>}</nav></div></header>
        <section className="bg-brand-950 px-4 pb-12 pt-8 text-white sm:px-6"><div className="mx-auto max-w-6xl"><p className="text-xs font-bold uppercase tracking-[.18em] text-accent">Discovery</p><h1 className="mt-2 max-w-2xl text-3xl font-black sm:text-5xl">Temukan laundry yang benar-benar melayani area Anda.</h1><p className="mt-3 max-w-xl text-sm text-white/70">Jarak adalah estimasi garis lurus. Tanpa izin lokasi, pencarian area tetap tersedia.</p>
            <form className="mt-7 grid gap-3 rounded-panel bg-white p-4 text-ink shadow-floating md:grid-cols-[1fr_220px_auto]" onSubmit={(event) => { event.preventDefault(); search(); }}><FormField label="Nama outlet atau area" name="q" value={form.data.q} onChange={(event) => form.setData('q', event.target.value)} placeholder="Contoh: Dago" /><SelectField label="Tipe harga" name="pricing_type" value={form.data.pricing_type} onChange={(event) => form.setData('pricing_type', event.target.value as '' | 'fixed' | 'per_kg')}><option value="">Semua paket</option><option value="fixed">Fixed</option><option value="per_kg">Per kilogram</option></SelectField><Button type="submit" className="self-end" disabled={form.processing}><Search className="size-4" />Cari</Button></form>
            <div className="mt-3 flex flex-wrap items-center gap-3"><Button variant="secondary" onClick={locate}><Navigation className="size-4" />{filters.usingLocation ? 'Perbarui lokasi' : 'Gunakan lokasi saya'}</Button>{filters.usingLocation && <span className="text-xs font-bold text-accent">Diurutkan dari jarak terdekat</span>}{locationError && <span role="alert" className="text-xs text-red-200">{locationError}</span>}</div></div></section>
        <section className="mx-auto max-w-6xl px-4 py-8 sm:px-6">{outlets.items.length === 0 ? <EmptyState title="Belum ada outlet yang cocok" description="Coba ubah area, tipe paket, atau gunakan lokasi yang berbeda." /> : <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">{outlets.items.map((outlet) => <article key={outlet.publicId} className="flex flex-col rounded-panel border border-line bg-surface p-5 shadow-panel"><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wider text-brand-600">{outlet.tenantName}</p><h2 className="mt-1 text-xl font-black">{outlet.name}</h2></div>{outlet.distanceKm !== null && <span className="rounded-full bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700">{outlet.distanceKm} km</span>}</div><p className="mt-3 flex gap-2 text-sm text-muted"><MapPin className="mt-0.5 size-4 shrink-0" />{outlet.area}, {outlet.city}</p><div className="mt-4 flex flex-wrap gap-2">{outlet.packages.slice(0, 3).map((pkg) => <span key={pkg.publicId} className="rounded-full bg-neutral-soft px-2.5 py-1 text-xs font-bold text-neutral">{pkg.name} · {money.format(pkg.unitPrice)}{pkg.pricingType === 'per_kg' ? '/kg' : ''}</span>)}</div><p className="mt-4 text-xs text-muted">Pickup {money.format(outlet.pickupFee)} · Delivery {money.format(outlet.deliveryFee)}</p><Link href={`/outlets/${outlet.publicId}`} className="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-950 px-4 text-sm font-bold text-white">Lihat detail</Link></article>)}</div>}<Pagination meta={outlets.meta} /></section></main>;
}
