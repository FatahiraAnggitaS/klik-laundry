import { Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ConfirmButton } from '@/components/ui/confirm-button';
import { FormField } from '@/components/ui/form-field';
import type { PaginationMeta, PayoutAccount, TenantApplication } from '@/types/identity';

function TenantActions({ tenant, sensitiveConfirmed }: { tenant: TenantApplication; sensitiveConfirmed: boolean }) {
    const form = useForm({ reason: '', decision: 'approved' });
    const send = (url: string, method: 'delete' | 'patch' | 'post') => form.submit(method, url, { preserveScroll: true });
    const review = (decision: 'approved' | 'rejected') => {
        form.transform((data) => ({ ...data, decision }));
        form.patch(`/super-user/tenants/${tenant.publicId}/review`, { preserveScroll: true });
    };

    return <div className="mt-4 space-y-3">
        <FormField label="Alasan tindakan" name={`reason-${tenant.publicId}`} value={form.data.reason} error={form.errors.reason} onChange={(event) => form.setData('reason', event.target.value)} />
        <div className="flex flex-wrap gap-2">
            {tenant.onboardingStatus === 'pending' && <><ConfirmButton title="Setujui Tenant?" description="Tenant dapat melanjutkan setup operasional setelah persetujuan." confirmLabel="Setujui" loading={form.processing} disabled={form.processing || !sensitiveConfirmed} onConfirm={() => review('approved')}>Setujui</ConfirmButton><ConfirmButton title="Tolak pendaftaran Tenant?" description="Alasan review akan dikirim ke pemilik Tenant." confirmLabel="Tolak" loading={form.processing} disabled={form.processing || !sensitiveConfirmed} variant="secondary" onConfirm={() => review('rejected')}>Tolak</ConfirmButton></>}
            {tenant.operationalStatus === 'active' && <ConfirmButton title="Suspend Tenant?" description="Tenant tidak dapat menerima order baru selama ditangguhkan." confirmLabel="Suspend" loading={form.processing} disabled={form.processing || !sensitiveConfirmed} variant="secondary" onConfirm={() => send(`/super-user/tenants/${tenant.publicId}/suspension`, 'post')}>Suspend</ConfirmButton>}
            {tenant.operationalStatus === 'suspended' && <Button loading={form.processing} disabled={form.processing || !sensitiveConfirmed} onClick={() => send(`/super-user/tenants/${tenant.publicId}/reactivation`, 'post')}>Aktifkan</Button>}
            {tenant.closureRequested && tenant.operationalStatus !== 'closed' && <ConfirmButton title="Tutup Tenant?" description="Penutupan menghentikan akses operasional Tenant." confirmLabel="Tutup Tenant" loading={form.processing} disabled={form.processing || !sensitiveConfirmed} variant="secondary" onConfirm={() => send(`/super-user/tenants/${tenant.publicId}/closure`, 'post')}>Tutup Tenant</ConfirmButton>}
            {!tenant.payoutHold && tenant.operationalStatus !== 'closed' && <ConfirmButton title="Tahan payout Tenant?" description="Payout baru akan ditahan sampai hold dilepas." confirmLabel="Set hold" loading={form.processing} disabled={form.processing || !sensitiveConfirmed} variant="ghost" onConfirm={() => send(`/super-user/tenants/${tenant.publicId}/payout-hold`, 'post')}>Set hold</ConfirmButton>}
            {tenant.payoutHold && <Button loading={form.processing} disabled={form.processing || !sensitiveConfirmed} variant="ghost" onClick={() => send(`/super-user/tenants/${tenant.publicId}/payout-hold`, 'delete')}>Lepas hold</Button>}
            {tenant.ownerPublicId && tenant.ownerStatus === 'active' && <ConfirmButton title="Suspend pemilik Tenant?" description="Akses pemilik akan dihentikan sampai akun diaktifkan kembali." confirmLabel="Suspend owner" loading={form.processing} disabled={form.processing || !sensitiveConfirmed} variant="ghost" onConfirm={() => send(`/super-user/users/${tenant.ownerPublicId}/suspension`, 'post')}>Suspend owner</ConfirmButton>}
            {tenant.ownerPublicId && tenant.ownerStatus === 'suspended' && <Button loading={form.processing} disabled={form.processing || !sensitiveConfirmed} variant="ghost" onClick={() => send(`/super-user/users/${tenant.ownerPublicId}/reactivation`, 'post')}>Aktifkan owner</Button>}
        </div>
    </div>;
}

function PayoutReview({ account, sensitiveConfirmed }: { account: PayoutAccount; sensitiveConfirmed: boolean }) {
    const form = useForm({ decision: 'verified', reason: '' });
    const review = (decision: 'rejected' | 'verified') => {
        form.transform((data) => ({ ...data, decision }));
        form.patch(`/super-user/payout-accounts/${account.publicId}/review`, { preserveScroll: true });
    };

    return <article className="rounded-panel border border-line bg-surface p-5 shadow-panel">
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div><h3 className="font-black">{account.tenantName}</h3><p className="mt-1 text-sm text-muted">{account.bankName} · {account.maskedHolderName} · {account.maskedAccountNumber}</p></div>
            <span className="rounded-full bg-warning-soft px-3 py-1 text-xs font-bold text-warning">pending</span>
        </div>
        <div className="mt-4 space-y-3">
            <FormField label="Alasan review" name={`payout-reason-${account.publicId}`} value={form.data.reason} error={form.errors.reason} onChange={(event) => form.setData('reason', event.target.value)} />
            <div className="flex gap-2"><ConfirmButton title="Verifikasi rekening payout?" description="Rekening ini akan dapat digunakan untuk payout Tenant." confirmLabel="Verifikasi" loading={form.processing} disabled={form.processing || !sensitiveConfirmed} onConfirm={() => review('verified')}>Verifikasi</ConfirmButton><ConfirmButton title="Tolak rekening payout?" description="Tenant perlu mengajukan rekening baru." confirmLabel="Tolak" loading={form.processing} disabled={form.processing || !sensitiveConfirmed} variant="secondary" onConfirm={() => review('rejected')}>Tolak</ConfirmButton></div>
        </div>
    </article>;
}

interface TenantsProps {
    payoutAccounts: PayoutAccount[];
    tenants: { items: TenantApplication[]; meta: PaginationMeta };
}

export default function Tenants({ payoutAccounts, tenants }: TenantsProps) {
    const page = usePage<{ flash?: { status?: string }; sensitiveAuthentication: { confirmed: boolean } }>();
    const sensitiveConfirmed = page.props.sensitiveAuthentication.confirmed;

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6 lg:px-10">
        <div className="mx-auto max-w-6xl">
            <header className="flex items-center justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-muted">Super User</p><h1 className="mt-1 text-3xl font-black">Tenant review</h1></div><Link href="/workspace" className="text-sm font-bold text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">Workspace</Link></header>
            {page.props.flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{page.props.flash.status}</p>}
            {!sensitiveConfirmed && <p className="mt-5 rounded-xl bg-warning-soft p-4 text-sm font-bold text-warning">Konfirmasi password dan TOTP sebelum review. <Link href="/identity/confirm-sensitive-action?return_to=/super-user/tenants" className="underline">Konfirmasi sekarang</Link>.</p>}

            <section className="mt-7" aria-labelledby="tenant-applications-title">
                <h2 id="tenant-applications-title" className="text-xl font-black">Pendaftaran Tenant</h2>
                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    {tenants.items.length === 0 ? <p className="rounded-panel border border-line bg-surface p-6 text-muted">Belum ada pendaftaran Tenant.</p> : tenants.items.map((tenant) => <article key={tenant.publicId} className="rounded-panel border border-line bg-surface p-6 shadow-panel"><div className="flex items-start justify-between gap-4"><div><h3 className="text-lg font-black">{tenant.name}</h3><p className="mt-1 text-sm text-muted">{tenant.ownerName} · {tenant.ownerEmail}</p></div><span className="rounded-full bg-neutral-soft px-3 py-1 text-xs font-bold text-neutral">{tenant.onboardingStatus}/{tenant.operationalStatus}</span></div><p className="mt-3 text-sm text-copy">{tenant.outletName}, {tenant.area}, {tenant.city}</p><TenantActions tenant={tenant} sensitiveConfirmed={sensitiveConfirmed} /></article>)}
                </div>
                <p className="mt-5 text-sm text-muted">Halaman {tenants.meta.currentPage} dari {tenants.meta.lastPage} · {tenants.meta.total} Tenant</p>
            </section>

            <section className="mt-10" aria-labelledby="payout-review-title">
                <h2 id="payout-review-title" className="text-xl font-black">Review rekening payout</h2>
                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    {payoutAccounts.length === 0 ? <p className="rounded-panel border border-line bg-surface p-6 text-muted">Tidak ada rekening yang menunggu review.</p> : payoutAccounts.map((account) => <PayoutReview key={account.publicId} account={account} sensitiveConfirmed={sensitiveConfirmed} />)}
                </div>
            </section>
        </div>
    </main>;
}
