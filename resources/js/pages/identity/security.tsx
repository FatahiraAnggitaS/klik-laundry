import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ConfirmButton } from '@/components/ui/confirm-button';
import { FormField } from '@/components/ui/form-field';
import type { IdentitySummary } from '@/types/identity';
import type { AccountClosureReadiness } from '@/types/privacy';

export default function Security({ identity, accountClosure }: { identity: IdentitySummary; accountClosure: AccountClosureReadiness | null }) {
    const [qrCode, setQrCode] = useState<string | null>(null);
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
    const [code, setCode] = useState('');
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const closure = useForm({ confirmation: '' });

    const loadQr = async () => {
        const response = await fetch('/user/two-factor-qr-code', { headers: { Accept: 'application/json' } });
        if (response.ok) {
            const data: { svg: string } = await response.json();
            setQrCode(`data:image/svg+xml;charset=utf-8,${encodeURIComponent(data.svg)}`);
        }
    };

    const loadRecoveryCodes = async () => {
        const response = await fetch('/user/two-factor-recovery-codes', { headers: { Accept: 'application/json' } });
        if (response.ok) setRecoveryCodes(await response.json() as string[]);
    };

    return <main className="min-h-screen bg-canvas px-4 py-8 text-ink sm:px-6">
        <div className="mx-auto max-w-3xl">
            <Link href="/workspace" className="text-sm font-bold text-brand-700">← Workspace</Link>
            <section className="mt-5 rounded-panel border border-line bg-surface p-6 shadow-panel sm:p-8">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-muted">Security</p>
                <h1 className="mt-2 text-3xl font-black">Two-factor authentication</h1>
                <p className="mt-2 text-sm text-muted">{identity.twoFactorRequired ? 'Wajib untuk role Anda.' : 'Opsional untuk role Anda.'}</p>
                <p className="mt-3 text-sm text-muted">Konfirmasi password diperlukan sebelum setup, melihat recovery code, atau menonaktifkan 2FA.</p>
                <Link href="/user/confirm-password" className="mt-3 inline-block text-sm font-bold text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">Konfirmasi password</Link>
                {!identity.twoFactorEnabled ? <div className="mt-7 space-y-4">
                    <Button onClick={() => router.post('/user/two-factor-authentication', {}, { onSuccess: loadQr })}>Mulai setup 2FA</Button>
                    {qrCode && <img className="size-48 rounded-xl border border-line" src={qrCode} alt="QR code setup authenticator" />}
                    {qrCode && <div className="flex max-w-sm gap-2"><input className="min-h-11 flex-1 rounded-xl border border-line px-3" value={code} onChange={(e) => setCode(e.target.value)} inputMode="numeric" aria-label="Kode TOTP" /><Button onClick={() => router.post('/user/confirmed-two-factor-authentication', { code })}>Konfirmasi</Button></div>}
                </div> : <div className="mt-7 space-y-4">
                    <p className="rounded-xl bg-success-soft p-4 text-sm font-bold text-success">2FA aktif.</p>
                    <Button variant="secondary" onClick={loadRecoveryCodes}>Tampilkan recovery codes</Button>
                    {recoveryCodes.length > 0 && <ul className="grid gap-2 rounded-xl border border-line bg-canvas p-4 font-mono text-sm sm:grid-cols-2">{recoveryCodes.map((item) => <li key={item}>{item}</li>)}</ul>}
                    <div className="flex flex-wrap gap-2"><ConfirmButton variant="secondary" title="Buat ulang recovery codes?" description="Recovery codes lama tidak dapat dipakai setelah penggantian." confirmLabel="Buat ulang" onConfirm={() => router.post('/user/two-factor-recovery-codes', {}, { onSuccess: loadRecoveryCodes })}>Buat ulang recovery codes</ConfirmButton><ConfirmButton variant="danger" title="Nonaktifkan 2FA?" description="Akun akan kehilangan perlindungan autentikasi dua faktor." confirmLabel="Nonaktifkan 2FA" onConfirm={() => router.delete('/user/two-factor-authentication')}>Nonaktifkan 2FA</ConfirmButton></div>
                </div>}
            </section>
            <section className="mt-5 rounded-panel border border-line bg-surface p-6 shadow-panel sm:p-8">
                <h2 className="text-xl font-black">Ubah password</h2>
                <p className="mt-2 text-sm text-muted">Semua sesi lain akan dicabut setelah password berubah.</p>
                <form className="mt-6 space-y-4" onSubmit={(event) => { event.preventDefault(); password.put('/user/password', { onSuccess: () => password.reset() }); }}>
                    <FormField label="Password saat ini" name="current_password" type="password" autoComplete="current-password" value={password.data.current_password} error={password.errors.current_password} onChange={(event) => password.setData('current_password', event.target.value)} />
                    <FormField label="Password baru" name="password" type="password" autoComplete="new-password" value={password.data.password} error={password.errors.password} onChange={(event) => password.setData('password', event.target.value)} />
                    <FormField label="Konfirmasi password baru" name="password_confirmation" type="password" autoComplete="new-password" value={password.data.password_confirmation} error={password.errors.password_confirmation} onChange={(event) => password.setData('password_confirmation', event.target.value)} />
                    <Button type="submit" loading={password.processing} disabled={password.processing}>Perbarui password</Button>
                </form>
            </section>
            {accountClosure && <section className="mt-5 rounded-panel border border-red-200 bg-surface p-6 shadow-panel sm:p-8">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-red-700">Zona sensitif</p>
                <h2 className="mt-2 text-xl font-black">Tutup dan anonimkan akun</h2>
                <p className="mt-2 text-sm text-muted">Profil dan address book akan dihapus permanen. Snapshot transaksi minimum tetap disimpan untuk kewajiban finansial dan legal.</p>
                {!accountClosure.canClose && <ul className="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-800">
                    <li>Order aktif: {accountClosure.blockers.activeOrders}</li>
                    <li>Pembayaran pending: {accountClosure.blockers.pendingPayments}</li>
                    <li>Refund aktif: {accountClosure.blockers.activeRefunds}</li>
                </ul>}
                <div className="mt-5 space-y-4">
                    <FormField label='Ketik "TUTUP AKUN"' name="confirmation" value={closure.data.confirmation} error={closure.errors.confirmation} onChange={(event) => closure.setData('confirmation', event.target.value)} disabled={!accountClosure.canClose} />
                    <p className="text-xs text-muted">Recent sensitive authentication wajib. Setelah berhasil, semua sesi dicabut dan tindakan tidak dapat dibatalkan.</p>
                    <ConfirmButton variant="danger" title="Tutup dan anonimkan akun?" description="Profil dan address book dihapus permanen; tindakan ini tidak dapat dibatalkan." confirmLabel="Tutup akun" onConfirm={() => closure.delete('/identity/account')} loading={closure.processing} disabled={!accountClosure.canClose || closure.processing || closure.data.confirmation !== 'TUTUP AKUN'}>Tutup akun</ConfirmButton>
                </div>
            </section>}
        </div>
    </main>;
}
