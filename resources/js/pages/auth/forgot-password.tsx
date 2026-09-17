import { Link, useForm } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function ForgotPassword() {
    const form = useForm({ email: '' });
    return <AuthLayout title="Reset password" description="Kami mengirim tautan reset jika email terdaftar.">
        <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); form.post('/forgot-password'); }}>
            <FormField label="Email" name="email" type="email" required value={form.data.email} error={form.errors.email} onChange={(e) => form.setData('email', e.target.value)} />
            <Button type="submit" className="w-full" disabled={form.processing}>Kirim tautan reset</Button>
        </form><Link href="/login" className="mt-6 block text-sm font-semibold text-brand-700">Kembali ke login</Link>
    </AuthLayout>;
}
