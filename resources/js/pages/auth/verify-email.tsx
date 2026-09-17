import { router } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';

export default function VerifyEmail() {
    return <AuthLayout title="Verifikasi email" description="Buka tautan yang kami kirim sebelum mengakses workspace.">
        <div className="space-y-3"><Button className="w-full" onClick={() => router.post('/email/verification-notification')}>Kirim ulang email</Button><Button variant="secondary" className="w-full" onClick={() => router.post('/logout')}>Keluar</Button></div>
    </AuthLayout>;
}
