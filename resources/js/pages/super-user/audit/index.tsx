import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Pagination } from '@/components/ui/pagination';
import type { AuditFilters, AuditPage, ProofCleanupSummary } from '@/types/privacy';

export default function AuditIndex({ audit, filters, proofCleanup }: { audit: AuditPage; filters: AuditFilters; proofCleanup: ProofCleanupSummary }) {
    const [form, setForm] = useState({
        action: filters.action ?? '', actor: filters.actor ?? '', tenant: filters.tenant ?? '',
        subject: filters.subject ?? '', from: filters.from ?? '', to: filters.to ?? '',
    });

    return <main className="min-h-screen bg-canvas px-4 py-8 text-ink sm:px-6">
        <div className="mx-auto max-w-6xl">
            <Link href="/workspace" className="text-sm font-bold text-brand-700">Kembali ke Workspace</Link>
            <header className="mt-5"><p className="text-xs font-bold uppercase tracking-[0.16em] text-muted">Super User</p><h1 className="mt-2 text-3xl font-black">Audit dan akses PII</h1><p className="mt-2 text-sm text-muted">Metadata aman saja; payload PII dan before/after tidak ditampilkan.</p></header>
            <section className="mt-6 grid gap-3 sm:grid-cols-3" aria-label="Status cleanup proof"><div className="rounded-xl border border-line bg-surface p-4"><p className="text-xs text-muted">Eligible</p><p className="mt-1 text-2xl font-black">{proofCleanup.eligible}</p></div><div className="rounded-xl border border-line bg-surface p-4"><p className="text-xs text-muted">Gagal/retry</p><p className="mt-1 text-2xl font-black text-red-700">{proofCleanup.failed}</p></div><div className="rounded-xl border border-line bg-surface p-4"><p className="text-xs text-muted">Terhapus</p><p className="mt-1 text-2xl font-black">{proofCleanup.deleted}</p></div></section>
            <form className="mt-6 grid gap-3 rounded-panel border border-line bg-surface p-5 shadow-panel sm:grid-cols-2 lg:grid-cols-3" onSubmit={(event) => { event.preventDefault(); router.get('/super-user/audit', form, { preserveState: true, replace: true }); }}>
                <FormField label="Action" name="action" value={form.action} onChange={(event) => setForm({ ...form, action: event.target.value })} />
                <FormField label="Actor public ID" name="actor" value={form.actor} onChange={(event) => setForm({ ...form, actor: event.target.value })} />
                <FormField label="Tenant public ID" name="tenant" value={form.tenant} onChange={(event) => setForm({ ...form, tenant: event.target.value })} />
                <FormField label="Subject public ID" name="subject" value={form.subject} onChange={(event) => setForm({ ...form, subject: event.target.value })} />
                <FormField label="Dari" name="from" type="date" value={form.from} onChange={(event) => setForm({ ...form, from: event.target.value })} />
                <FormField label="Sampai" name="to" type="date" value={form.to} onChange={(event) => setForm({ ...form, to: event.target.value })} />
                <Button type="submit">Terapkan filter</Button>
            </form>
            <section className="mt-6 overflow-hidden rounded-panel border border-line bg-surface shadow-panel">
                {audit.items.length === 0 ? <p className="p-8 text-center text-sm text-muted">Belum ada audit sesuai filter.</p> : <div className="overflow-x-auto"><table className="min-w-full text-left text-sm"><thead className="bg-canvas text-xs uppercase text-muted"><tr><th className="px-4 py-3">Waktu Jakarta</th><th className="px-4 py-3">Action</th><th className="px-4 py-3">Subject</th><th className="px-4 py-3">Actor/Tenant</th><th className="px-4 py-3">Alasan</th></tr></thead><tbody className="divide-y divide-line">{audit.items.map((item, index) => <tr key={`${item.source}-${item.occurredAt}-${index}`}><td className="whitespace-nowrap px-4 py-3">{new Date(item.occurredAt).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })}</td><td className="px-4 py-3 font-bold">{item.action}</td><td className="px-4 py-3"><span>{item.subjectType}</span><br/><code className="text-xs">{item.subjectId}</code>{item.subjectType === 'order' && <><br/><Link href={`/super-user/orders/${item.subjectId}/privacy`} className="text-xs font-bold text-brand-700">Lihat privacy</Link></>}</td><td className="px-4 py-3 text-xs"><div>{item.actorPublicId ?? 'system'}</div><div>{item.tenantPublicId ?? 'platform'}</div></td><td className="max-w-xs px-4 py-3 text-muted">{item.reasonRecorded ? 'Tercatat, isi disembunyikan' : 'Tidak ada'}</td></tr>)}</tbody></table></div>}
            </section>
            <Pagination meta={audit.meta} />
        </div>
    </main>;
}
