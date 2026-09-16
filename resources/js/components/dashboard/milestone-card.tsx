import { Link } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import type { Milestone, RoleKey } from '@/types/dashboard';

interface MilestoneCardProps {
    milestones: Milestone[];
    role: RoleKey;
}

export function MilestoneCard({ milestones, role }: MilestoneCardProps) {
    return (
        <Card id="milestone" className="p-5 sm:p-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-muted">Project foundation</p>
                    <h2 className="mt-2 text-lg font-black tracking-[-0.025em] text-ink">Roadmap implementasi</h2>
                </div>
                <span className="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-bold text-brand-700">M1 · active</span>
            </div>

            <ol className="mt-6 space-y-0">
                {milestones.map((milestone, index) => (
                    <li key={milestone.title} className="relative flex gap-3 pb-5 last:pb-0">
                        {index < milestones.length - 1 && (
                            <span className="absolute left-[7px] top-4 h-full w-px bg-line" aria-hidden="true" />
                        )}
                        <span
                            className={`relative mt-1 size-[15px] shrink-0 rounded-full border-[3px] ${
                                milestone.status === 'current'
                                    ? 'border-accent bg-brand-700'
                                    : milestone.status === 'blocked'
                                      ? 'border-warning bg-warning-soft'
                                    : 'border-line bg-surface'
                            }`}
                        />
                        <div>
                            <p className="text-sm font-bold text-copy">{milestone.title}</p>
                            <p className="mt-1 text-xs leading-5 text-muted">{milestone.description}</p>
                        </div>
                    </li>
                ))}
            </ol>

            <Link
                href={`/milestone-0/wireflows/${role}`}
                className="mt-6 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-line bg-brand-50 px-4 text-sm font-bold text-brand-700 transition hover:border-line-strong hover:bg-brand-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
            >
                Buka wireflow Milestone 0
            </Link>
            <Link
                href="/foundation/platform-settings"
                className="mt-2 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-brand-900 px-4 text-sm font-bold text-white transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2"
            >
                Buka reference slice Milestone 1
            </Link>
        </Card>
    );
}
