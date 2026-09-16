import { AppIcon } from '@/components/app-icon';
import { Card } from '@/components/ui/card';
import type { Metric, Tone } from '@/types/dashboard';

interface MetricCardProps {
    metric: Metric;
}

const iconTones: Record<Tone, string> = {
    blue: 'bg-info-soft text-info',
    green: 'bg-success-soft text-success',
    amber: 'bg-warning-soft text-warning',
    violet: 'bg-violet-soft text-violet',
    neutral: 'bg-neutral-soft text-neutral',
};

export function MetricCard({ metric }: MetricCardProps) {
    return (
        <Card className="group p-4 transition duration-300 hover:-translate-y-0.5 hover:border-line-strong hover:shadow-panel-hover sm:p-5">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-semibold text-muted">{metric.label}</p>
                    <p className="mt-2 text-[28px] font-black tracking-[-0.045em] text-ink">{metric.value}</p>
                </div>
                <span className={`grid size-10 place-items-center rounded-[14px] ${iconTones[metric.tone]}`}>
                    <AppIcon name={metric.icon} className="size-[18px]" strokeWidth={2.2} />
                </span>
            </div>
            <p className="mt-3 text-xs font-medium text-subtle">{metric.change}</p>
        </Card>
    );
}
