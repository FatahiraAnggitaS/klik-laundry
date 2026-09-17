import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function TwoFactorChallenge() {
    const [recovery, setRecovery] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });
    return <AuthLayout title="Verifikasi dua langkah" description={recovery ? 'Masukkan salah satu recovery code.' : 'Masukkan kode 6 digit dari aplikasi authenticator.'}>
        <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); form.post('/two-factor-challenge'); }}>
            {recovery ? <FormField label="Recovery code" name="recovery_code" required value={form.data.recovery_code} error={form.errors.recovery_code} onChange={(e) => form.setData('recovery_code', e.target.value)} /> : <FormField label="Kode authenticator" name="code" inputMode="numeric" required value={form.data.code} error={form.errors.code} onChange={(e) => form.setData('code', e.target.value)} />}
            <Button type="submit" className="w-full" disabled={form.processing}>Verifikasi</Button>
        </form>
        <button type="button" className="mt-5 text-sm font-semibold text-brand-700" onClick={() => setRecovery(!recovery)}>{recovery ? 'Gunakan kode authenticator' : 'Gunakan recovery code'}</button>
    </AuthLayout>;
}
