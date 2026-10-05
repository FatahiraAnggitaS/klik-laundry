import type { ReactNode } from 'react';

type AlertTone = 'info' | 'success' | 'warning' | 'danger';

const tones: Record<AlertTone, string> = {
    info: 'border-info/25 bg-info-soft text-info',
    success: 'border-success/25 bg-success-soft text-success',
    warning: 'border-warning/25 bg-warning-soft text-warning',
    danger: 'border-danger/25 bg-danger-soft text-danger',
};

export function Alert({ children, className = '', tone = 'info' }: { children: ReactNode; className?: string; tone?: AlertTone }) {
    return <div role={tone === 'danger' ? 'alert' : 'status'} className={`rounded-xl border p-4 text-sm font-semibold ${tones[tone]} ${className}`}>{children}</div>;
}
