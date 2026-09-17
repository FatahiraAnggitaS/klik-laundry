import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppLogo } from '@/components/app-logo';

interface AuthLayoutProps {
    children: ReactNode;
    description: string;
    title: string;
}

export function AuthLayout({ children, description, title }: AuthLayoutProps) {
    return (
        <main className="min-h-screen bg-canvas px-4 py-8 text-ink sm:px-6 lg:grid lg:grid-cols-[minmax(320px,0.8fr)_minmax(520px,1.2fr)] lg:gap-8 lg:p-8">
            <section className="hidden overflow-hidden rounded-[2rem] bg-brand-950 p-10 text-white lg:flex lg:flex-col">
                <AppLogo />
                <div className="my-auto max-w-md">
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-accent">Klik Laundry</p>
                    <h1 className="mt-4 text-4xl font-black tracking-[-0.04em]">Operasional laundry yang tertib sejak identitas pertama.</h1>
                    <p className="mt-5 text-sm leading-7 text-white/60">Session authentication, email verification, 2FA, dan isolation Tenant bekerja di server.</p>
                </div>
                <p className="text-xs text-white/40">Responsive web · Bahasa Indonesia · IDR</p>
            </section>

            <section className="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-xl items-center">
                <div className="w-full rounded-[1.75rem] border border-line bg-surface p-6 shadow-panel sm:p-9">
                    <Link href="/" className="inline-block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 lg:hidden">
                        <AppLogo />
                    </Link>
                    <h2 className="mt-7 text-3xl font-black tracking-[-0.035em]">{title}</h2>
                    <p className="mt-2 text-sm leading-6 text-muted">{description}</p>
                    <div className="mt-7">{children}</div>
                </div>
            </section>
        </main>
    );
}
