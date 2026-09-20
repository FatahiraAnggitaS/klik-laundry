import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { AuthLayout } from '@/layouts/auth-layout';

interface Props { invitation: { tenantName: string; emailMasked: string; expiresAt: string } }

export default function AcceptDriverInvitation({ invitation }: Props) {
    const form = useForm({ name: '', password: '', password_confirmation: '' });
    return <AuthLayout title="Aktifkan akun Driver" description={`Undangan ${invitation.tenantName} untuk ${invitation.emailMasked}.`}>
        <p className="mb-5 rounded-xl bg-warning-soft p-3 text-sm text-warning">Berlaku sampai {new Date(invitation.expiresAt).toLocaleString('id-ID')} dan hanya dapat digunakan sekali.</p>
        <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); form.post('/driver/invitation/accept'); }}>
            <FormField label="Nama lengkap" name="name" required value={form.data.name} error={form.errors.name} onChange={(event) => form.setData('name', event.target.value)} />
            <FormField label="Password (minimal 12 karakter)" name="password" type="password" required value={form.data.password} error={form.errors.password} onChange={(event) => form.setData('password', event.target.value)} />
            <FormField label="Konfirmasi password" name="password_confirmation" type="password" required value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} />
            <Button className="w-full" type="submit" disabled={form.processing}>Aktifkan akun</Button>
        </form>
    </AuthLayout>;
}
