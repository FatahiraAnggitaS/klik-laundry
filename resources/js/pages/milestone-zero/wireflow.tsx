import { Head, Link } from '@inertiajs/react';
import { AppIcon } from '@/components/app-icon';
import { AppLogo } from '@/components/app-logo';
import { Card } from '@/components/ui/card';
import type { RoleKey } from '@/types/dashboard';
import type { WireflowPageProps } from '@/types/wireflow';

function stepUrl(role: RoleKey, stepId: string): string {
    return `/milestone-0/wireflows/${role}?step=${encodeURIComponent(stepId)}`;
}

export default function WireflowPreview({
    activeRole,
    roles,
    wireflow,
    activeStep,
    activeStepIndex,
    previousStepId,
    nextStepId,
    riskNotices,
}: WireflowPageProps) {
    const progress = Math.round(((activeStepIndex + 1) / wireflow.steps.length) * 100);

    return (
        <div className="min-h-screen bg-canvas text-ink">
            <Head title={`Milestone 0 - ${activeRole.label} wireflow`} />

            <header className="sticky top-0 z-30 border-b border-line/80 bg-canvas/92 backdrop-blur-xl">
                <div className="mx-auto flex min-h-16 max-w-[1440px] items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-3 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                        <AppLogo compact />
                        <span className="hidden text-sm font-black tracking-[-0.02em] text-ink md:block">Klik Laundry</span>
                    </Link>
                    <span className="hidden h-7 w-px bg-line sm:block" aria-hidden="true" />
                    <div className="hidden sm:block">
                        <p className="text-xs font-black uppercase tracking-[0.15em] text-brand-600">Milestone 0</p>
                        <p className="text-xs text-muted">Risk validation & domain contract</p>
                    </div>
                    <Link
                        href={`/preview/${activeRole.value}`}
                        className="ml-auto inline-flex min-h-10 items-center gap-2 rounded-xl border border-line bg-surface px-3 text-sm font-bold text-copy transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
                    >
                        <AppIcon name="layout-dashboard" className="size-4" />
                        <span className="hidden sm:inline">Kembali ke dashboard</span>
                        <span className="sm:hidden">Dashboard</span>
                    </Link>
                </div>
            </header>

            <main className="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <section className="relative overflow-hidden rounded-3xl bg-brand-950 px-5 py-6 text-white shadow-floating sm:px-7 sm:py-8 lg:px-9">
                    <div className="hero-grid absolute inset-0 opacity-30" aria-hidden="true" />
                    <div className="relative grid gap-7 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-accent px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-brand-950">
                                    Wireflow interaktif
                                </span>
                                <span className="rounded-full border border-white/15 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-white/65">
                                    Fixture - tanpa database
                                </span>
                            </div>
                            <p className="mt-5 text-sm font-bold text-accent">{activeRole.label}</p>
                            <h1 className="mt-2 max-w-3xl text-3xl font-black tracking-[-0.045em] text-balance sm:text-4xl lg:text-5xl">
                                {wireflow.title}
                            </h1>
                            <p className="mt-4 max-w-2xl text-sm leading-6 text-white/65 sm:text-base">{wireflow.summary}</p>
                        </div>
                        <div className="rounded-2xl border border-white/10 bg-white/[0.065] p-4">
                            <div className="flex items-center justify-between text-xs font-bold">
                                <span className="text-white/55">Progress prototype</span>
                                <span className="text-accent">{progress}%</span>
                            </div>
                            <div className="mt-3 h-2 overflow-hidden rounded-full bg-white/10">
                                <div className="h-full rounded-full bg-accent" style={{ width: `${progress}%` }} />
                            </div>
                            <p className="mt-3 text-xs leading-5 text-white/55">
                                Langkah {activeStepIndex + 1} dari {wireflow.steps.length}. Tidak ada mutation atau transaksi nyata pada prototype ini.
                            </p>
                        </div>
                    </div>
                </section>

                <nav className="scrollbar-hidden mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Pilih wireflow berdasarkan role">
                    {roles.map((role) => (
                        <Link
                            key={role.value}
                            href={`/milestone-0/wireflows/${role.value}`}
                            prefetch
                            aria-current={role.value === activeRole.value ? 'page' : undefined}
                            className={`min-w-[180px] rounded-2xl border px-4 py-3 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 sm:min-w-0 sm:flex-1 ${
                                role.value === activeRole.value
                                    ? 'border-brand-600 bg-brand-50 shadow-sm'
                                    : 'border-line bg-surface hover:border-line-strong hover:bg-brand-50/50'
                            }`}
                        >
                            <span className="block text-sm font-black text-ink">{role.label}</span>
                            <span className="mt-1 block text-xs leading-5 text-muted">{role.description}</span>
                        </Link>
                    ))}
                </nav>

                <div className="mt-5 grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
                    <Card className="hidden self-start p-3 lg:block">
                        <p className="px-3 pb-3 pt-2 text-[10px] font-black uppercase tracking-[0.16em] text-muted">Alur {activeRole.label}</p>
                        <ol className="space-y-1">
                            {wireflow.steps.map((step, index) => (
                                <li key={step.id}>
                                    <Link
                                        href={stepUrl(activeRole.value, step.id)}
                                        prefetch
                                        aria-current={step.id === activeStep.id ? 'step' : undefined}
                                        className={`flex gap-3 rounded-xl px-3 py-3 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 ${
                                            step.id === activeStep.id ? 'bg-brand-900 text-white' : 'hover:bg-brand-50'
                                        }`}
                                    >
                                        <span
                                            className={`grid size-7 shrink-0 place-items-center rounded-lg text-[10px] font-black ${
                                                step.id === activeStep.id ? 'bg-accent text-brand-950' : 'bg-neutral-soft text-muted'
                                            }`}
                                        >
                                            {step.number}
                                        </span>
                                        <span>
                                            <span className="block text-sm font-bold">{step.title}</span>
                                            <span className={`mt-1 block text-xs ${step.id === activeStep.id ? 'text-white/55' : 'text-muted'}`}>
                                                {index < activeStepIndex ? 'Sudah ditinjau' : step.status}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ol>
                    </Card>

                    <div className="min-w-0">
                        <div className="scrollbar-hidden flex gap-2 overflow-x-auto pb-3 lg:hidden" aria-label="Tahapan wireflow">
                            {wireflow.steps.map((step) => (
                                <Link
                                    key={step.id}
                                    href={stepUrl(activeRole.value, step.id)}
                                    aria-current={step.id === activeStep.id ? 'step' : undefined}
                                    className={`whitespace-nowrap rounded-full border px-3 py-2 text-xs font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 ${
                                        step.id === activeStep.id ? 'border-brand-700 bg-brand-900 text-white' : 'border-line bg-surface text-muted'
                                    }`}
                                >
                                    {step.number} · {step.title}
                                </Link>
                            ))}
                        </div>

                        <Card className="overflow-hidden">
                            <div className="border-b border-line bg-brand-50 px-5 py-5 sm:px-7">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p className="text-[11px] font-black uppercase tracking-[0.16em] text-brand-600">
                                            Langkah {activeStep.number} · {activeStep.actor}
                                        </p>
                                        <h2 className="mt-2 text-2xl font-black tracking-[-0.035em] text-ink sm:text-3xl">{activeStep.title}</h2>
                                    </div>
                                    <span className="self-start rounded-full border border-brand-100 bg-surface px-3 py-1.5 text-xs font-black text-brand-700">
                                        {activeStep.status}
                                    </span>
                                </div>
                            </div>

                            <div className="grid gap-6 p-5 sm:p-7 xl:grid-cols-2">
                                <section aria-labelledby="actor-action-heading">
                                    <p className="text-[10px] font-black uppercase tracking-[0.16em] text-muted">Tindakan actor</p>
                                    <h3 id="actor-action-heading" className="sr-only">Tindakan actor</h3>
                                    <p className="mt-3 text-base font-bold leading-7 text-copy">{activeStep.trigger}</p>
                                </section>
                                <section aria-labelledby="system-outcome-heading">
                                    <p className="text-[10px] font-black uppercase tracking-[0.16em] text-muted">Respons sistem</p>
                                    <h3 id="system-outcome-heading" className="sr-only">Respons sistem</h3>
                                    <p className="mt-3 text-base font-bold leading-7 text-copy">{activeStep.systemOutcome}</p>
                                </section>
                            </div>

                            <div className="border-t border-line px-5 py-5 sm:px-7">
                                <div className="rounded-2xl border border-warning/20 bg-warning-soft p-4">
                                    <div className="flex items-start gap-3">
                                        <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-xl bg-surface text-warning">
                                            <AppIcon name="shield-alert" className="size-4" />
                                        </span>
                                        <div>
                                            <p className="text-xs font-black uppercase tracking-[0.13em] text-warning">Security & privacy guard</p>
                                            <p className="mt-2 text-sm leading-6 text-copy">{activeStep.privacy}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {activeStep.branches.length > 0 && (
                                <section className="border-t border-line px-5 py-5 sm:px-7" aria-labelledby="branch-heading">
                                    <p className="text-[10px] font-black uppercase tracking-[0.16em] text-muted">Cabang dan failure mode</p>
                                    <h3 id="branch-heading" className="sr-only">Cabang dan failure mode</h3>
                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        {activeStep.branches.map((branch) => (
                                            <article key={branch.label} className="rounded-2xl border border-line bg-canvas p-4">
                                                <p className="text-sm font-black text-ink">{branch.label}</p>
                                                <p className="mt-2 text-xs leading-5 text-muted">{branch.result}</p>
                                            </article>
                                        ))}
                                    </div>
                                </section>
                            )}

                            <div className="flex flex-col-reverse gap-3 border-t border-line bg-canvas px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                                {previousStepId ? (
                                    <Link
                                        href={stepUrl(activeRole.value, previousStepId)}
                                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 text-sm font-bold text-copy transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
                                    >
                                        <span aria-hidden="true">←</span> Langkah sebelumnya
                                    </Link>
                                ) : (
                                    <span className="hidden sm:block" />
                                )}
                                {nextStepId ? (
                                    <Link
                                        href={stepUrl(activeRole.value, nextStepId)}
                                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-900 px-4 text-sm font-bold text-white transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2"
                                    >
                                        Langkah berikutnya <span aria-hidden="true">→</span>
                                    </Link>
                                ) : (
                                    <Link
                                        href={`/milestone-0/wireflows/${roles[(roles.findIndex((role) => role.value === activeRole.value) + 1) % roles.length].value}`}
                                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-900 px-4 text-sm font-bold text-white transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2"
                                    >
                                        Tinjau role berikutnya <span aria-hidden="true">→</span>
                                    </Link>
                                )}
                            </div>
                        </Card>

                        <Card className="mt-5 p-5 sm:p-6">
                            <p className="text-[10px] font-black uppercase tracking-[0.16em] text-muted">Outcome kontrak</p>
                            <p className="mt-3 text-sm font-bold leading-6 text-copy">{wireflow.outcome}</p>
                        </Card>
                    </div>
                </div>

                <section className="mt-5 grid gap-3 md:grid-cols-2" aria-labelledby="blocker-heading">
                    <h2 id="blocker-heading" className="sr-only">Release blocker Milestone 0</h2>
                    {riskNotices.map((notice) => (
                        <article key={notice.title} className="rounded-2xl border border-warning/25 bg-warning-soft p-4 sm:p-5">
                            <div className="flex items-start gap-3">
                                <AppIcon name="shield-alert" className="mt-0.5 size-5 shrink-0 text-warning" />
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-black text-ink">{notice.title}</h3>
                                        <span className="rounded-full bg-surface px-2 py-1 text-[9px] font-black uppercase tracking-[0.12em] text-warning">
                                            {notice.severity}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-xs leading-5 text-muted">{notice.description}</p>
                                </div>
                            </div>
                        </article>
                    ))}
                </section>
            </main>
        </div>
    );
}
