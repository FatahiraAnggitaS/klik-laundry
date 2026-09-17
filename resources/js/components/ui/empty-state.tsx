interface EmptyStateProps {
    title: string;
    description: string;
}

export function EmptyState({ title, description }: EmptyStateProps) {
    return <div className="rounded-panel border border-dashed border-line-strong bg-surface p-8 text-center"><h3 className="font-black text-ink">{title}</h3><p className="mt-2 text-sm text-muted">{description}</p></div>;
}
