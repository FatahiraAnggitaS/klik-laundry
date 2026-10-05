import { useForm, usePage } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function ConfirmSensitive() {
    const form = useForm({ password: '', code: '' });
    const flash = usePage<{ flash?: { status?: string } }>().props.flash;

    return <AuthLayout title="Konfirmasi tindakan sensitif" description="Masukkan password dan kode TOTP. Konfirmasi berlaku 15 menit.">
        {flash?.status && <p className="rounded-xl bg-warning-soft p-4 text-sm font-bold text-warning">{flash.status}</p>}
        <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); form.post('/identity/confirm-sensitive-action'); }}>
            <FormField label="Password" name="password" type="password" required value={form.data.password} error={form.errors.password} onChange={(e) => form.setData('password', e.target.value)} />
            <FormField label="Kode TOTP" name="code" inputMode="numeric" required value={form.data.code} error={form.errors.code} onChange={(e) => form.setData('code', e.target.value)} />
            <Button type="submit" className="w-full" loading={form.processing} disabled={form.processing}>Konfirmasi</Button>
        </form>
    </AuthLayout>;
}
