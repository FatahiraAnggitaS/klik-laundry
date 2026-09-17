import { useForm } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function ConfirmSensitive() {
    const form = useForm({ password: '', code: '' });
    return <AuthLayout title="Konfirmasi tindakan sensitif" description="Masukkan password dan kode TOTP. Konfirmasi berlaku 15 menit.">
        <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); form.post('/identity/confirm-sensitive-action'); }}>
            <FormField label="Password" name="password" type="password" required value={form.data.password} error={form.errors.password} onChange={(e) => form.setData('password', e.target.value)} />
            <FormField label="Kode TOTP" name="code" inputMode="numeric" required value={form.data.code} error={form.errors.code} onChange={(e) => form.setData('code', e.target.value)} />
            <Button type="submit" className="w-full" disabled={form.processing}>Konfirmasi</Button>
        </form>
    </AuthLayout>;
}
