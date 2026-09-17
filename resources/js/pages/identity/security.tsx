import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import type { IdentitySummary } from '@/types/identity';

export default function Security({ identity }: { identity: IdentitySummary }) {
    const [qrCode, setQrCode] = useState<string | null>(null);
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
    const [code, setCode] = useState('');
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

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
                    <div className="flex flex-wrap gap-2"><Button variant="secondary" onClick={() => router.post('/user/two-factor-recovery-codes', {}, { onSuccess: loadRecoveryCodes })}>Buat ulang recovery codes</Button><Button variant="ghost" onClick={() => router.delete('/user/two-factor-authentication')}>Nonaktifkan 2FA</Button></div>
                </div>}
            </section>
            <section className="mt-5 rounded-panel border border-line bg-surface p-6 shadow-panel sm:p-8">
                <h2 className="text-xl font-black">Ubah password</h2>
                <p className="mt-2 text-sm text-muted">Semua sesi lain akan dicabut setelah password berubah.</p>
                <form className="mt-6 space-y-4" onSubmit={(event) => { event.preventDefault(); password.put('/user/password', { onSuccess: () => password.reset() }); }}>
                    <FormField label="Password saat ini" name="current_password" type="password" autoComplete="current-password" value={password.data.current_password} error={password.errors.current_password} onChange={(event) => password.setData('current_password', event.target.value)} />
                    <FormField label="Password baru" name="password" type="password" autoComplete="new-password" value={password.data.password} error={password.errors.password} onChange={(event) => password.setData('password', event.target.value)} />
                    <FormField label="Konfirmasi password baru" name="password_confirmation" type="password" autoComplete="new-password" value={password.data.password_confirmation} error={password.errors.password_confirmation} onChange={(event) => password.setData('password_confirmation', event.target.value)} />
                    <Button type="submit" disabled={password.processing}>Perbarui password</Button>
                </form>
            </section>
        </div>
    </main>;
}
