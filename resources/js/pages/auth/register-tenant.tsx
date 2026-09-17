import { Link, useForm } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';

export default function RegisterTenant() {
    const form = useForm({ business_name: '', owner_name: '', email: '', phone: '', password: '', password_confirmation: '', outlet_name: '', outlet_address: '', city: '', area: '', latitude: '', longitude: '' });
    return <AuthLayout title="Daftarkan Tenant" description="Data usaha ditinjau manual. Dokumen legal tidak diminta pada MVP.">
        <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); form.post('/tenant/register'); }}>
            <FormField label="Nama usaha" name="business_name" required value={form.data.business_name} error={form.errors.business_name} onChange={(e) => form.setData('business_name', e.target.value)} />
            <FormField label="Nama owner" name="owner_name" required value={form.data.owner_name} error={form.errors.owner_name} onChange={(e) => form.setData('owner_name', e.target.value)} />
            <FormField label="Email owner" name="email" type="email" required value={form.data.email} error={form.errors.email} onChange={(e) => form.setData('email', e.target.value)} />
            <FormField label="Nomor telepon" name="phone" required value={form.data.phone} error={form.errors.phone} onChange={(e) => form.setData('phone', e.target.value)} />
            <FormField label="Password" name="password" type="password" required value={form.data.password} error={form.errors.password} onChange={(e) => form.setData('password', e.target.value)} />
            <FormField label="Konfirmasi password" name="password_confirmation" type="password" required value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
            <FormField label="Nama outlet awal" name="outlet_name" required value={form.data.outlet_name} error={form.errors.outlet_name} onChange={(e) => form.setData('outlet_name', e.target.value)} />
            <FormField label="Alamat outlet" name="outlet_address" required value={form.data.outlet_address} error={form.errors.outlet_address} onChange={(e) => form.setData('outlet_address', e.target.value)} />
            <FormField label="Kota" name="city" required value={form.data.city} error={form.errors.city} onChange={(e) => form.setData('city', e.target.value)} />
            <FormField label="Area" name="area" required value={form.data.area} error={form.errors.area} onChange={(e) => form.setData('area', e.target.value)} />
            <FormField label="Latitude" name="latitude" type="number" step="0.0000001" required value={form.data.latitude} error={form.errors.latitude} onChange={(e) => form.setData('latitude', e.target.value)} />
            <FormField label="Longitude" name="longitude" type="number" step="0.0000001" required value={form.data.longitude} error={form.errors.longitude} onChange={(e) => form.setData('longitude', e.target.value)} />
            <Button type="submit" className="w-full sm:col-span-2" disabled={form.processing}>Kirim pendaftaran</Button>
        </form>
        <Link href="/login" className="mt-6 block text-sm font-semibold text-brand-700">Kembali ke login</Link>
    </AuthLayout>;
}
