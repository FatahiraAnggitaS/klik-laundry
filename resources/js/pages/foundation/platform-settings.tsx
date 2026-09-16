import { Head, Link } from '@inertiajs/react';
import { AppIcon } from '@/components/app-icon';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import type { PlatformSettingsPageProps } from '@/types/foundation';

export default function PlatformSettings({
    maxServiceRadiusKm,
    paymentMaintenanceEnabled,
    version,
    updatedAt,
}: PlatformSettingsPageProps) {
    const updatedLabel = updatedAt
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'long',
              timeStyle: 'short',
              timeZone: 'Asia/Jakarta',
          }).format(new Date(updatedAt))
        : 'Belum tercatat';

    return (
        <main className="min-h-screen bg-canvas px-4 py-8 text-copy sm:px-6 lg:px-8 lg:py-12">
            <Head title="Foundation · Platform settings" />

            <div className="mx-auto max-w-5xl">
                <Link
                    href="/"
                    className="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-bold text-brand-700 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
                >
                    <AppIcon name="arrow-left" className="size-4" />
                    Kembali ke dashboard
                </Link>

                <section className="mt-5 overflow-hidden rounded-3xl bg-brand-900 p-6 text-white shadow-floating sm:p-8">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-[11px] font-black uppercase tracking-[0.18em] text-accent">Milestone 1</p>
                        <span className="rounded-full border border-white/10 bg-white/[0.07] px-2.5 py-1 text-[10px] font-bold text-white/65">
                            data persistence
                        </span>
                    </div>
                    <h1 className="mt-5 max-w-3xl text-3xl font-black tracking-[-0.045em] sm:text-5xl">
                        Reference vertical slice
                    </h1>
                    <p className="mt-4 max-w-2xl text-sm leading-6 text-white/65 sm:text-base">
                        Nilai di halaman ini dibaca dari SQLite melalui boundary Service dan Repository. Schema yang sama diuji terhadap PostgreSQL di CI.
                    </p>
                </section>

                <div className="mt-5 grid gap-5 md:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
                    <Card className="p-6 sm:p-7">
                        <p className="text-xs font-black uppercase tracking-[0.15em] text-muted">Kebijakan area layanan</p>
                        <div className="mt-6 flex items-end gap-3">
                            <strong className="text-6xl font-black tracking-[-0.06em] text-brand-700">{maxServiceRadiusKm}</strong>
                            <span className="pb-2 text-lg font-bold text-muted">km maksimum</span>
                        </div>
                        <p className="mt-5 max-w-xl text-sm leading-6 text-muted">
                            Batas global ini kelak membatasi radius setiap outlet. Perubahan nilai dan authorization baru masuk pada milestone operasional terkait.
                        </p>
                    </Card>

                    <Card className="p-6 sm:p-7">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[0.15em] text-muted">Konfigurasi</p>
                                <h2 className="mt-2 text-xl font-black tracking-[-0.03em] text-ink">Status platform</h2>
                            </div>
                            <StatusBadge tone={paymentMaintenanceEnabled ? 'amber' : 'green'}>
                                {paymentMaintenanceEnabled ? 'Maintenance' : 'Normal'}
                            </StatusBadge>
                        </div>
                        <dl className="mt-6 space-y-4 border-t border-line pt-5 text-sm">
                            <div className="flex items-center justify-between gap-4">
                                <dt className="text-muted">Versi record</dt>
                                <dd className="font-black text-copy">v{version}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Terakhir diperbarui</dt>
                                <dd className="mt-1 font-bold text-copy">{updatedLabel}</dd>
                            </div>
                        </dl>
                    </Card>
                </div>

                <Card className="mt-5 p-5 sm:p-6">
                    <h2 className="text-base font-black text-ink">Boundary yang terbukti</h2>
                    <p className="mt-2 text-sm leading-6 text-muted">
                        Form Request → Controller → Service → Repository Interface → Eloquent Repository → Model → DTO → Inertia.
                    </p>
                </Card>
            </div>
        </main>
    );
}
