import type { ReactNode } from 'react';

interface PageHeaderProps {
    actions?: ReactNode;
    description?: string;
    eyebrow?: string;
    title: string;
}

export function PageHeader({ actions, description, eyebrow, title }: PageHeaderProps) {
    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="max-w-3xl">
                {eyebrow && <p className="text-xs font-black uppercase tracking-[0.16em] text-brand-600">{eyebrow}</p>}
                <h1 className="mt-1 text-3xl font-black tracking-[-0.035em] text-ink sm:text-4xl">{title}</h1>
                {description && <p className="mt-2 text-sm leading-6 text-muted">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </header>
    );
}
