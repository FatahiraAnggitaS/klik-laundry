import { Link } from '@inertiajs/react';
import type { PaginationMeta } from '@/types/operations';

export function Pagination({ meta, pageName = 'page' }: { meta: PaginationMeta; pageName?: string }) {
    if (meta.lastPage <= 1) return null;
    const url = new URL(window.location.href);
    const href = (page: number) => { const next = new URL(url); next.searchParams.set(pageName, page.toString()); return `${next.pathname}${next.search}`; };

    const controlClass = 'rounded-xl border border-line bg-surface px-4 py-2 text-sm font-bold';

    return <nav aria-label="Pagination" className="mt-6 flex items-center justify-between gap-4">{meta.currentPage === 1 ? <span aria-disabled="true" className={`${controlClass} text-muted`}>Sebelumnya</span> : <Link href={href(meta.currentPage - 1)} preserveScroll className={controlClass}>Sebelumnya</Link>}<span className="text-sm text-muted">Halaman {meta.currentPage} dari {meta.lastPage}</span>{meta.currentPage === meta.lastPage ? <span aria-disabled="true" className={`${controlClass} text-muted`}>Berikutnya</span> : <Link href={href(meta.currentPage + 1)} preserveScroll className={controlClass}>Berikutnya</Link>}</nav>;
}
