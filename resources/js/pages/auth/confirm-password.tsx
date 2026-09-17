import { useForm } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function ConfirmPassword() {
    const form = useForm({ password: '' });
    return <AuthLayout title="Konfirmasi password" description="Laravel meminta konfirmasi sebelum pengaturan keamanan diubah.">
        <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); form.post('/user/confirm-password'); }}>
            <FormField label="Password" name="password" type="password" required value={form.data.password} error={form.errors.password} onChange={(e) => form.setData('password', e.target.value)} />
            <Button type="submit" className="w-full" disabled={form.processing}>Konfirmasi</Button>
        </form>
    </AuthLayout>;
}
