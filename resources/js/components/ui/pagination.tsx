import { Link } from '@inertiajs/react';
import type { PaginationMeta } from '@/types/operations';

export function Pagination({ meta, pageName = 'page' }: { meta: PaginationMeta; pageName?: string }) {
    if (meta.lastPage <= 1) return null;
    const url = new URL(window.location.href);
    const href = (page: number) => { const next = new URL(url); next.searchParams.set(pageName, page.toString()); return `${next.pathname}${next.search}`; };

    return <nav aria-label="Pagination" className="mt-6 flex items-center justify-between gap-4"><Link href={href(meta.currentPage - 1)} preserveScroll className={`rounded-xl border border-line bg-white px-4 py-2 text-sm font-bold ${meta.currentPage === 1 ? 'pointer-events-none opacity-40' : ''}`}>Sebelumnya</Link><span className="text-sm text-muted">Halaman {meta.currentPage} dari {meta.lastPage}</span><Link href={href(meta.currentPage + 1)} preserveScroll className={`rounded-xl border border-line bg-white px-4 py-2 text-sm font-bold ${meta.currentPage === meta.lastPage ? 'pointer-events-none opacity-40' : ''}`}>Berikutnya</Link></nav>;
}
