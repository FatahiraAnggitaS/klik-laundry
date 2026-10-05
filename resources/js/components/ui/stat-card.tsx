import type { ReactNode } from 'react';
import { Card } from './card';

export function StatCard({ icon, label, value, detail }: { detail?: string; icon?: ReactNode; label: string; value: ReactNode }) {
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between gap-3">
                <p className="text-xs font-black uppercase tracking-[0.1em] text-muted">{label}</p>
                {icon && <span className="grid size-9 place-items-center rounded-xl bg-brand-50 text-brand-600">{icon}</span>}
            </div>
            <p className="mt-3 text-2xl font-black tracking-[-0.03em] text-ink">{value}</p>
            {detail && <p className="mt-1 text-xs text-muted">{detail}</p>}
        </Card>
    );
}
