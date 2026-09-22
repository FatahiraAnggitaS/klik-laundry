import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { SelectField } from '@/components/ui/select-field';
import { StatusBadge } from '@/components/ui/status-badge';
import { TextareaField } from '@/components/ui/textarea-field';
import type { PaymentChannel } from '@/types/payments';

interface Props {
    maxServiceRadiusKm: number;
    paymentMaintenanceEnabled: boolean;
    paymentChannels: PaymentChannel[];
    version: number;
    updatedAt: string | null;
}

function ChannelControl({ channel }: { channel: PaymentChannel }) {
    const form = useForm({ active: !channel.isActive, reason: '' });

    return <form className="rounded-xl border border-line p-4" onSubmit={(event) => { event.preventDefault(); form.patch(`/super-user/payment-channels/${channel.code}`); }}>
        <div className="flex items-center justify-between gap-3"><div><h3 className="font-black">{channel.label}</h3><p className="text-xs text-muted">{channel.code} · {channel.category}</p></div><StatusBadge tone={channel.isActive ? 'green' : 'neutral'}>{channel.isActive ? 'aktif' : 'nonaktif'}</StatusBadge></div>
        <TextareaField className="mt-3" label="Alasan perubahan" value={form.data.reason} error={form.errors.reason} onChange={(event) => form.setData('reason', event.target.value)} />
        <Button className="mt-3" type="submit" variant="secondary" disabled={form.processing || form.data.reason.trim().length < 5}>{channel.isActive ? 'Nonaktifkan' : 'Aktifkan dan tandai terverifikasi'}</Button>
    </form>;
}

export default function PlatformSettings({ maxServiceRadiusKm, paymentMaintenanceEnabled, paymentChannels, version, updatedAt }: Props) {
    const { flash } = usePage<{ flash?: { status?: string } }>().props;
    const radius = useForm({ maximum_service_radius_km: maxServiceRadiusKm.toString(), reason: '' });
    const maintenance = useForm({ enabled: !paymentMaintenanceEnabled, reason: '' });

    return <main className="min-h-screen bg-canvas px-4 py-6 text-ink sm:px-6"><Head title="Pengaturan platform" /><div className="mx-auto max-w-5xl">
        <Link href="/workspace" className="inline-flex items-center gap-2 text-sm font-bold text-brand-700"><ArrowLeft className="size-4" />Workspace</Link>
        <header className="mt-4"><p className="text-xs font-bold uppercase tracking-wider text-brand-600">Super User</p><h1 className="mt-2 text-3xl font-black">Pengaturan platform</h1><p className="mt-2 text-sm text-muted">Radius, maintenance pembayaran, dan allowlist channel Duitku.</p></header>
        {flash?.status && <p className="mt-5 rounded-xl bg-success-soft p-4 text-sm font-bold text-success">{flash.status}</p>}
        <div className="mt-6 flex items-center gap-3 rounded-xl bg-brand-50 p-4"><ShieldCheck className="size-6 text-brand-700" /><div><p className="text-sm font-black">Konfirmasi sensitif wajib</p><p className="text-xs text-muted">Password + TOTP berlaku 15 menit.</p></div></div>
        <div className="mt-6 grid gap-6 lg:grid-cols-2">
            <form className="rounded-panel border border-line bg-surface p-6 shadow-panel" onSubmit={(event) => { event.preventDefault(); radius.patch('/super-user/platform-settings/service-radius'); }}><h2 className="text-xl font-black">Radius maksimum</h2><div className="mt-4 space-y-4"><FormField label="Radius maksimum (km)" type="number" min="1" max="100" value={radius.data.maximum_service_radius_km} error={radius.errors.maximum_service_radius_km} onChange={(event) => radius.setData('maximum_service_radius_km', event.target.value)} /><TextareaField label="Alasan perubahan" value={radius.data.reason} error={radius.errors.reason} onChange={(event) => radius.setData('reason', event.target.value)} /><Button type="submit" disabled={radius.processing}>Simpan radius</Button></div></form>
            <form className="rounded-panel border border-line bg-surface p-6 shadow-panel" onSubmit={(event) => { event.preventDefault(); maintenance.patch('/super-user/platform-settings/payment-maintenance'); }}><div className="flex items-center justify-between gap-3"><h2 className="text-xl font-black">Maintenance pembayaran</h2><StatusBadge tone={paymentMaintenanceEnabled ? 'amber' : 'green'}>{paymentMaintenanceEnabled ? 'aktif' : 'normal'}</StatusBadge></div><div className="mt-4 space-y-4"><SelectField label="Status baru" value={maintenance.data.enabled ? '1' : '0'} onChange={(event) => maintenance.setData('enabled', event.target.value === '1')}><option value="1">Aktifkan maintenance</option><option value="0">Nonaktifkan maintenance</option></SelectField><TextareaField label="Alasan perubahan" value={maintenance.data.reason} error={maintenance.errors.reason} onChange={(event) => maintenance.setData('reason', event.target.value)} /><Button type="submit" disabled={maintenance.processing || maintenance.data.reason.trim().length < 5}>Simpan maintenance</Button></div></form>
        </div>
        <section className="mt-6 rounded-panel border border-line bg-surface p-6 shadow-panel"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-xl font-black">Channel pembayaran</h2><p className="mt-1 text-sm text-muted">Semua channel default nonaktif sampai operator memverifikasinya.</p></div><Link href="/super-user/payments" className="text-sm font-bold text-brand-700">Buka rekonsiliasi</Link></div><div className="mt-4 grid gap-4 md:grid-cols-2">{paymentChannels.map((channel) => <ChannelControl key={channel.code} channel={channel} />)}</div></section>
        <p className="mt-6 text-xs text-muted">Versi {version}{updatedAt ? ` · diperbarui ${new Date(updatedAt).toLocaleString('id-ID')}` : ''} · <Link href="/identity/confirm-sensitive-action" className="font-bold text-brand-700">Perbarui konfirmasi sensitif</Link></p>
    </div></main>;
}
