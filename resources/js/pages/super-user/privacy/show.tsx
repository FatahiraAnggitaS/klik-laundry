import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TextareaField } from '@/components/ui/textarea-field';
import type { OrderPrivacy, PiiRevealGrant } from '@/types/privacy';

export default function PrivacyShow({ privacy, grant }: { privacy: OrderPrivacy; grant: PiiRevealGrant | null }) {
    const form = useForm({ reason: '' });

    return <main className="min-h-screen bg-canvas px-4 py-8 text-ink sm:px-6">
        <div className="mx-auto max-w-4xl">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Link href="/super-user/audit" className="text-sm font-bold text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">← Audit</Link>
                <span className={`rounded-full px-3 py-1 text-xs font-bold ${privacy.revealed ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700'}`}>{privacy.revealed ? 'PII terbuka sementara' : 'PII disamarkan'}</span>
            </div>
            <section className="mt-5 rounded-panel border border-line bg-surface p-6 shadow-panel sm:p-8">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-muted">Order {privacy.orderNumber}</p>
                <h1 className="mt-2 text-3xl font-black">Akses data Customer</h1>
                {grant && <p className="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-800">Grant berakhir {new Date(grant.expiresAt).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })}. Setiap pembukaan halaman dicatat.</p>}
                {!grant && <p className="mt-3 rounded-xl bg-slate-100 p-3 text-sm text-slate-700">Tidak ada grant aktif. Akses ditolak atau grant sebelumnya telah kedaluwarsa.</p>}
                <dl className="mt-6 grid gap-4 rounded-xl border border-line p-4 sm:grid-cols-3">
                    <div><dt className="text-xs text-muted">Nama</dt><dd className="mt-1 font-bold">{privacy.customer.name}</dd></div>
                    <div><dt className="text-xs text-muted">Email</dt><dd className="mt-1 break-all font-bold">{privacy.customer.email}</dd></div>
                    <div><dt className="text-xs text-muted">Telepon</dt><dd className="mt-1 font-bold">{privacy.customer.phone ?? '—'}</dd></div>
                </dl>
                <div className="mt-5 space-y-3">{privacy.addresses.map((address) => <article key={address.type} className="rounded-xl border border-line p-4"><p className="text-xs font-bold uppercase text-muted">{address.type}</p><p className="mt-2 font-bold">{address.contactName} · {address.contactPhone}</p><p className="mt-1 text-sm text-muted">{address.address}, {address.area}, {address.city}</p></article>)}</div>
            </section>
            {!privacy.revealed && <section className="mt-5 rounded-panel border border-line bg-surface p-6 shadow-panel sm:p-8">
                <h2 className="text-xl font-black">Ajukan reveal 15 menit</h2>
                <p className="mt-2 text-sm text-muted">Hanya untuk investigasi order ini. Sensitive authentication dan alasan minimal 10 karakter wajib.</p>
                <form className="mt-5 space-y-4" onSubmit={(event) => { event.preventDefault(); form.post(`/super-user/orders/${privacy.orderPublicId}/pii-reveals`); }}>
                    <TextareaField label="Alasan akses" name="reason" value={form.data.reason} error={form.errors.reason} onChange={(event) => form.setData('reason', event.target.value)} maxLength={500} />
                    <Button type="submit" loading={form.processing} disabled={form.processing}>Berikan akses sementara</Button>
                </form>
            </section>}
        </div>
    </main>;
}
