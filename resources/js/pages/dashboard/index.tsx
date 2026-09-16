import { Head } from '@inertiajs/react';
import { AppIcon } from '@/components/app-icon';
import { MetricCard } from '@/components/dashboard/metric-card';
import { MilestoneCard } from '@/components/dashboard/milestone-card';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { AppShell } from '@/layouts/app-shell';
import type { DashboardPageProps } from '@/types/dashboard';

export default function Dashboard({ activeRole, roles, navigation, hero, metrics, focus, workItems, milestones }: DashboardPageProps) {
    return (
        <AppShell activeRole={activeRole} roles={roles} navigation={navigation}>
            <Head title={`${activeRole.label} dashboard`} />

            <section className="relative overflow-hidden rounded-3xl bg-brand-900 p-5 text-white shadow-floating sm:p-7 lg:p-8">
                <div className="hero-grid absolute inset-0 opacity-40" aria-hidden="true" />
                <div className="absolute -right-12 -top-16 size-56 rounded-full bg-accent/15 blur-2xl" aria-hidden="true" />
                <div className="relative max-w-3xl">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-accent">{hero.eyebrow}</p>
                        <span className="rounded-full border border-white/10 bg-white/[0.07] px-2.5 py-1 text-[10px] font-bold text-white/60">
                            UI preview - tanpa database
                        </span>
                    </div>
                    <h1 className="mt-5 max-w-2xl text-[clamp(2rem,4vw,3.7rem)] font-black leading-[1.02] tracking-[-0.055em] text-balance">
                        {hero.title}
                    </h1>
                    <p className="mt-4 max-w-xl text-sm leading-6 text-white/63 sm:text-base">{hero.description}</p>
                    <div className="mt-7 flex flex-col gap-3 sm:flex-row">
                        <a
                            href="#work-queue"
                            className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-accent px-4 text-sm font-black text-brand-950 transition hover:bg-accent-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-900 sm:w-auto"
                        >
                            {hero.primaryAction}
                            <AppIcon name="arrow-up-right" className="size-4" />
                        </a>
                        <a
                            href="#milestone"
                            className="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-white/14 bg-white/[0.06] px-4 text-sm font-bold text-white transition hover:bg-white/[0.11] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto"
                        >
                            {hero.secondaryAction}
                        </a>
                    </div>
                </div>
            </section>

            <section aria-labelledby="metrics-heading" className="mt-5">
                <h2 id="metrics-heading" className="sr-only">Ringkasan metrik</h2>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {metrics.map((metric) => (
                        <MetricCard key={metric.label} metric={metric} />
                    ))}
                </div>
            </section>

            <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(300px,0.72fr)]">
                <Card className="relative overflow-hidden bg-brand-50 p-5 sm:p-6">
                    <div className="absolute right-0 top-0 size-52 translate-x-16 -translate-y-16 rounded-full border-[36px] border-white/45" aria-hidden="true" />
                    <div className="relative flex h-full flex-col">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-[11px] font-black uppercase tracking-[0.16em] text-brand-600">{focus.label}</p>
                                <h2 className="mt-3 text-2xl font-black tracking-[-0.04em] text-ink sm:text-[28px]">{focus.title}</h2>
                                <p className="mt-3 max-w-xl text-sm leading-6 text-muted">{focus.description}</p>
                            </div>
                            <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-surface text-brand-600 shadow-sm">
                                <AppIcon name={focus.icon} className="size-5" />
                            </span>
                        </div>

                        <div className="mt-8">
                            <div className="mb-2 flex items-center justify-between gap-4 text-xs font-bold">
                                <span className="text-muted">Progress alur</span>
                                <span className="text-brand-700">{focus.progress}%</span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-white">
                                <div className="h-full rounded-full bg-brand-500" style={{ width: `${focus.progress}%` }} />
                            </div>
                        </div>

                        <div className="mt-6 flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p className="flex items-center gap-2 text-xs font-semibold text-muted">
                                <AppIcon name="clock" className="size-4 text-brand-600" />
                                {focus.meta}
                            </p>
                            <a
                                href="#work-queue"
                                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-brand-900 px-4 text-sm font-bold text-white transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2"
                            >
                                {focus.action}
                                <AppIcon name="arrow-up-right" className="size-4" />
                            </a>
                        </div>
                    </div>
                </Card>

                <MilestoneCard milestones={milestones} role={activeRole.value} />
            </div>

            <section id="work-queue" className="mt-5 scroll-mt-24" aria-labelledby="work-heading">
                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-3 border-b border-line px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.15em] text-muted">Live workspace</p>
                            <h2 id="work-heading" className="mt-1.5 text-xl font-black tracking-[-0.03em] text-ink">
                                Aktivitas yang perlu dipantau
                            </h2>
                        </div>
                        <a
                            href="#overview"
                            className="inline-flex items-center gap-1.5 self-start rounded-lg px-2 py-1.5 text-sm font-bold text-brand-600 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 sm:self-auto"
                        >
                            Lihat semua
                            <AppIcon name="arrow-up-right" className="size-4" />
                        </a>
                    </div>

                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-line bg-canvas text-[10px] font-bold uppercase tracking-[0.13em] text-subtle">
                                    <th className="px-6 py-3.5">Referensi</th>
                                    <th className="px-6 py-3.5">Detail</th>
                                    <th className="px-6 py-3.5">Status</th>
                                    <th className="px-6 py-3.5 text-right">Nilai / waktu</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {workItems.map((item) => (
                                    <tr key={item.id} className="group transition hover:bg-brand-50/50">
                                        <td className="whitespace-nowrap px-6 py-4 text-xs font-black tracking-[0.02em] text-brand-600">{item.id}</td>
                                        <td className="px-6 py-4">
                                            <p className="text-sm font-bold text-copy">{item.title}</p>
                                            <p className="mt-1 text-xs text-subtle">{item.subtitle}</p>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4">
                                            <StatusBadge tone={item.statusTone}>{item.status}</StatusBadge>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm font-bold text-copy">{item.meta}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="divide-y divide-line md:hidden">
                        {workItems.map((item) => (
                            <article key={item.id} className="p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <p className="text-[11px] font-black tracking-[0.04em] text-brand-600">{item.id}</p>
                                    <StatusBadge tone={item.statusTone}>{item.status}</StatusBadge>
                                </div>
                                <p className="mt-3 text-sm font-bold text-copy">{item.title}</p>
                                <p className="mt-1 text-xs leading-5 text-subtle">{item.subtitle}</p>
                                <p className="mt-3 text-sm font-black text-copy">{item.meta}</p>
                            </article>
                        ))}
                    </div>
                </Card>
            </section>

            <footer className="mt-8 flex flex-col gap-2 border-t border-line py-5 text-xs text-subtle sm:flex-row sm:items-center sm:justify-between">
                <p>Scaffolding Klik Laundry - data pada halaman ini hanya fixture UI.</p>
                <p>Asia/Jakarta - IDR - mobile-first</p>
            </footer>
        </AppShell>
    );
}
