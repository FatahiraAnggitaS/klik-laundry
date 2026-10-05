export function Skeleton({ className = '' }: { className?: string }) {
    return <span aria-hidden="true" className={`block animate-pulse rounded-lg bg-neutral-soft ${className}`} />;
}
