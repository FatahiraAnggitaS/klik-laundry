import { Link, useForm } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <AuthLayout title="Masuk" description="Gunakan akun terverifikasi untuk membuka workspace sesuai role.">
            <form className="space-y-5" onSubmit={(event) => { event.preventDefault(); form.post('/login'); }}>
                <FormField label="Email" name="email" type="email" autoComplete="email" required value={form.data.email} error={form.errors.email} onChange={(event) => form.setData('email', event.target.value)} />
                <FormField label="Password" name="password" type="password" autoComplete="current-password" required value={form.data.password} error={form.errors.password} onChange={(event) => form.setData('password', event.target.value)} />
                <label className="flex items-center gap-2 text-sm text-muted"><input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} /> Ingat saya</label>
                <Button type="submit" className="w-full" disabled={form.processing}>{form.processing ? 'Memproses…' : 'Masuk'}</Button>
            </form>
            <div className="mt-6 flex flex-wrap justify-between gap-3 text-sm font-semibold"><Link href="/forgot-password" className="text-brand-700">Lupa password?</Link><Link href="/register" className="text-brand-700">Daftar Customer</Link></div>
            <Link href="/tenant/register" className="mt-3 block text-sm font-semibold text-brand-700">Daftarkan usaha laundry →</Link>
        </AuthLayout>
    );
}
