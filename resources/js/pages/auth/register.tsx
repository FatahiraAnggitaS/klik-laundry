import { Link, useForm } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function Register() {
    const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });

    return <AuthLayout title="Daftar Customer" description="Nomor telepon digunakan sebagai kontak operasional dan tidak diverifikasi melalui OTP.">
        <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); form.post('/register'); }}>
            <FormField label="Nama lengkap" name="name" required value={form.data.name} error={form.errors.name} onChange={(e) => form.setData('name', e.target.value)} />
            <FormField label="Email" name="email" type="email" required value={form.data.email} error={form.errors.email} onChange={(e) => form.setData('email', e.target.value)} />
            <FormField label="Nomor telepon" name="phone" required value={form.data.phone} error={form.errors.phone} onChange={(e) => form.setData('phone', e.target.value)} />
            <FormField label="Password (minimal 12 karakter)" name="password" type="password" required value={form.data.password} error={form.errors.password} onChange={(e) => form.setData('password', e.target.value)} />
            <FormField label="Konfirmasi password" name="password_confirmation" type="password" required value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
            <Button type="submit" className="w-full" disabled={form.processing}>Buat akun</Button>
        </form>
        <Link href="/login" className="mt-6 block text-sm font-semibold text-brand-700">Sudah punya akun? Masuk</Link>
    </AuthLayout>;
}
