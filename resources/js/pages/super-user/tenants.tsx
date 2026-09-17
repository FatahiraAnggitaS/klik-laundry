import { Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import type { PaginationMeta, PayoutAccount, TenantApplication } from '@/types/identity';

function TenantActions({ tenant }: { tenant: TenantApplication }) {
    const form = useForm({ reason: '', decision: 'approved' });
    const send = (url: string, method: 'delete' | 'patch' | 'post') => form.submit(method, url, { preserveScroll: true });
    const review = (decision: 'approved' | 'rejected') => {
        form.transform((data) => ({ ...data, decision }));
        form.patch(`/super-user/tenants/${tenant.publicId}/review`, { preserveScroll: true });
    };

    return <div className="mt-4 space-y-3">
        <FormField label="Alasan tindakan" name={`reason-${tenant.publicId}`} value={form.data.reason} error={form.errors.reason} onChange={(event) => form.setData('reason', event.target.value)} />
        <div className="flex flex-wrap gap-2">
            {tenant.onboardingStatus === 'pending' && <><Button disabled={form.processing} onClick={() => review('approved')}>Setujui</Button><Button disabled={form.processing} variant="secondary" onClick={() => review('rejected')}>Tolak</Button></>}
            {tenant.operationalStatus === 'active' && <Button disabled={form.processing} variant="secondary" onClick={() => send(`/super-user/tenants/${tenant.publicId}/suspension`, 'post')}>Suspend</Button>}
            {tenant.operationalStatus === 'suspended' && <Button disabled={form.processing} onClick={() => send(`/super-user/tenants/${tenant.publicId}/reactivation`, 'post')}>Aktifkan</Button>}
            {tenant.closureRequested && tenant.operationalStatus !== 'closed' && <Button disabled={form.processing} variant="secondary" onClick={() => send(`/super-user/tenants/${tenant.publicId}/closure`, 'post')}>Tutup Tenant</Button>}
            {!tenant.payoutHold && tenant.operationalStatus !== 'closed' && <Button disabled={form.processing} variant="ghost" onClick={() => send(`/super-user/tenants/${tenant.publicId}/payout-hold`, 'post')}>Set hold</Button>}
            {tenant.payoutHold && <Button disabled={form.processing} variant="ghost" onClick={() => send(`/super-user/tenants/${tenant.publicId}/payout-hold`, 'delete')}>Lepas hold</Button>}
            {tenant.ownerPublicId && tenant.ownerStatus === 'active' && <Button disabled={form.processing} variant="ghost" onClick={() => send(`/super-user/users/${tenant.ownerPublicId}/suspension`, 'post')}>Suspend owner</Button>}
            {tenant.ownerPublicId && tenant.ownerStatus === 'suspended' && <Button disabled={form.processing} variant="ghost" onClick={() => send(`/super-user/users/${tenant.ownerPublicId}/reactivation`, 'post')}>Aktifkan owner</Button>}
        </div>
    </div>;
}

function PayoutReview({ account }: { account: PayoutAccount }) {
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
            <div className="flex gap-2"><Button disabled={form.processing} onClick={() => review('verified')}>Verifikasi</Button><Button disabled={form.processing} variant="secondary" onClick={() => review('rejected')}>Tolak</Button></div>
        </div>
    </article>;
}

interface TenantsProps {
    payoutAccounts: PayoutAccount[];
    tenants: { items: TenantApplication[]; meta: PaginationMeta };
}

export default function Tenants({ payoutAccounts, tenants }: TenantsProps) {
    const page = usePage<{ flash?: { status?: string } }>();

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6 lg:px-10">
        <div className="mx-auto max-w-6xl">
            <header className="flex items-center justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-muted">Super User</p><h1 className="mt-1 text-3xl font-black">Tenant review</h1></div><Link href="/workspace" className="text-sm font-bold text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">Workspace</Link></header>
            {page.props.flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{page.props.flash.status}</p>}

            <section className="mt-7" aria-labelledby="tenant-applications-title">
                <h2 id="tenant-applications-title" className="text-xl font-black">Pendaftaran Tenant</h2>
                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    {tenants.items.length === 0 ? <p className="rounded-panel border border-line bg-surface p-6 text-muted">Belum ada pendaftaran Tenant.</p> : tenants.items.map((tenant) => <article key={tenant.publicId} className="rounded-panel border border-line bg-surface p-6 shadow-panel"><div className="flex items-start justify-between gap-4"><div><h3 className="text-lg font-black">{tenant.name}</h3><p className="mt-1 text-sm text-muted">{tenant.ownerName} · {tenant.ownerEmail}</p></div><span className="rounded-full bg-neutral-soft px-3 py-1 text-xs font-bold text-neutral">{tenant.onboardingStatus}/{tenant.operationalStatus}</span></div><p className="mt-3 text-sm text-copy">{tenant.outletName}, {tenant.area}, {tenant.city}</p><TenantActions tenant={tenant} /></article>)}
                </div>
                <p className="mt-5 text-sm text-muted">Halaman {tenants.meta.currentPage} dari {tenants.meta.lastPage} · {tenants.meta.total} Tenant</p>
            </section>

            <section className="mt-10" aria-labelledby="payout-review-title">
                <h2 id="payout-review-title" className="text-xl font-black">Review rekening payout</h2>
                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    {payoutAccounts.length === 0 ? <p className="rounded-panel border border-line bg-surface p-6 text-muted">Tidak ada rekening yang menunggu review.</p> : payoutAccounts.map((account) => <PayoutReview key={account.publicId} account={account} />)}
                </div>
            </section>
        </div>
    </main>;
}
