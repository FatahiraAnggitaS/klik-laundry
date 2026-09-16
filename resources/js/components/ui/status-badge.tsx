import type { Tone } from '@/types/dashboard';

interface StatusBadgeProps {
    children: string;
    tone: Tone;
}

const tones: Record<Tone, string> = {
    blue: 'bg-info-soft text-info',
    green: 'bg-success-soft text-success',
    amber: 'bg-warning-soft text-warning',
    violet: 'bg-violet-soft text-violet',
    neutral: 'bg-neutral-soft text-neutral',
};

export function StatusBadge({ children, tone }: StatusBadgeProps) {
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ${tones[tone]}`}>
            <span className="size-1.5 rounded-full bg-current opacity-70" />
            {children}
        </span>
    );
}
