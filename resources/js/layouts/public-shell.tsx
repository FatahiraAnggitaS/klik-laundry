import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppLogo } from '@/components/app-logo';
import { ThemeToggle } from '@/theme';
import type { SharedPageProps } from '@/types/shared';

export function PublicShell({ children }: { children: ReactNode }) {
    const user = usePage<SharedPageProps>().props.auth.user;

    return (
        <div className="min-h-screen bg-canvas text-ink">
            <header className="sticky top-0 z-30 border-b border-line/70 bg-canvas/90 backdrop-blur-xl">
                <div className="mx-auto flex h-[72px] max-w-7xl items-center gap-4 px-4 sm:px-6">
                    <Link href="/" className="rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"><AppLogo inverted={false} /></Link>
                    <nav className="ml-auto flex items-center gap-2 text-sm font-bold" aria-label="Navigasi publik">
                        <Link href="/outlets" className="hidden rounded-xl px-3 py-2 text-copy transition hover:bg-brand-50 sm:inline-flex">Cari outlet</Link>
                        <Link href={user ? '/workspace' : '/login'} className="rounded-xl bg-brand-600 px-4 py-2.5 text-white transition hover:bg-brand-700">{user ? 'Workspace' : 'Masuk'}</Link>
                        <ThemeToggle compact />
                    </nav>
                </div>
            </header>
            <div className="page-enter">{children}</div>
        </div>
    );
}
