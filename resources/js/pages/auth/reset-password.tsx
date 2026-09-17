import { useForm } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
    const form = useForm({ email, token, password: '', password_confirmation: '' });
    return <AuthLayout title="Password baru" description="Setelah reset, seluruh sesi lama akan dicabut.">
        <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); form.post('/reset-password'); }}>
            <FormField label="Email" name="email" type="email" readOnly value={form.data.email} error={form.errors.email} />
            <FormField label="Password baru" name="password" type="password" required value={form.data.password} error={form.errors.password} onChange={(e) => form.setData('password', e.target.value)} />
            <FormField label="Konfirmasi password" name="password_confirmation" type="password" required value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
            <Button type="submit" className="w-full" disabled={form.processing}>Simpan password</Button>
        </form>
    </AuthLayout>;
}
