import { Link } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { AppIcon } from '@/components/app-icon';
import { AppLogo } from '@/components/app-logo';
import { RoleSwitcher } from '@/components/dashboard/role-switcher';
import { Button } from '@/components/ui/button';
import type { ActiveRole, NavigationItem, RoleOption } from '@/types/dashboard';

interface AppShellProps {
    activeRole: ActiveRole;
    roles: RoleOption[];
    navigation: NavigationItem[];
    children: ReactNode;
}

interface SidebarProps {
    activeRole: ActiveRole;
    navigation: NavigationItem[];
    roles: RoleOption[];
    onNavigate?: () => void;
}

function Sidebar({ activeRole, navigation, roles, onNavigate }: SidebarProps) {
    return (
        <div className="flex h-full flex-col">
            <Link href="/" onClick={onNavigate} className="rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <AppLogo />
            </Link>

            <div className="mt-7">
                <RoleSwitcher activeRole={activeRole} roles={roles} />
            </div>

            <nav className="mt-8 flex-1" aria-label="Navigasi utama">
                <p className="px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-white/35">Workspace</p>
                <ul className="mt-3 space-y-1">
                    {navigation.map((item, index) => (
                        <li key={item.label}>
                            <a
                                href={index === 0 ? '#overview' : '#work-queue'}
                                onClick={onNavigate}
                                aria-current={index === 0 ? 'page' : undefined}
                                className={`group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ${
                                    index === 0
                                        ? 'bg-surface text-brand-950 shadow-[0_10px_24px_rgba(0,0,0,0.1)]'
                                        : 'text-white/58 hover:bg-white/[0.07] hover:text-white'
                                }`}
                            >
                                <AppIcon
                                    name={item.icon}
                                    className={`size-[18px] ${index === 0 ? 'text-brand-600' : 'text-white/45 group-hover:text-white/80'}`}
                                    strokeWidth={2.1}
                                />
                                {item.label}
                            </a>
                        </li>
                    ))}
                </ul>
            </nav>

            <div className="mt-6 rounded-2xl border border-white/10 bg-white/[0.055] p-3">
                <div className="flex items-center gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-accent text-xs font-black text-brand-950">AP</span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-bold text-white">Alya Pratama</span>
                        <span className="block truncate text-[11px] text-white/45">{activeRole.label}</span>
                    </span>
                    <AppIcon name="chevron-down" className="size-4 text-white/35" />
                </div>
            </div>
        </div>
    );
}

export function AppShell({ activeRole, roles, navigation, children }: AppShellProps) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    return (
        <div className="min-h-screen bg-canvas text-ink">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-[272px] bg-brand-950 p-5 lg:block">
                <div className="sidebar-glow absolute inset-0 overflow-hidden" aria-hidden="true" />
                <div className="relative h-full">
                    <Sidebar activeRole={activeRole} roles={roles} navigation={navigation} />
                </div>
            </aside>

            {mobileMenuOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <button
                        type="button"
                        className="absolute inset-0 bg-brand-950/65 backdrop-blur-sm"
                        aria-label="Tutup navigasi"
                        onClick={() => setMobileMenuOpen(false)}
                    />
                    <aside
                        role="dialog"
                        aria-modal="true"
                        aria-label="Navigasi utama"
                        className="relative h-full w-[min(86vw,320px)] bg-brand-950 p-5 shadow-2xl"
                    >
                        <Button
                            variant="ghost"
                            className="absolute right-3 top-3 size-10 px-0 text-white/65 hover:bg-white/10 hover:text-white"
                            aria-label="Tutup menu"
                            onClick={() => setMobileMenuOpen(false)}
                        >
                            <AppIcon name="close" className="size-5" />
                        </Button>
                        <Sidebar
                            activeRole={activeRole}
                            roles={roles}
                            navigation={navigation}
                            onNavigate={() => setMobileMenuOpen(false)}
                        />
                    </aside>
                </div>
            )}

            <div className="lg:pl-[272px]">
                <header className="sticky top-0 z-20 border-b border-line/80 bg-canvas/90 backdrop-blur-xl">
                    <div className="flex h-16 items-center gap-3 px-4 sm:h-[72px] sm:px-6 lg:px-8 xl:px-10">
                        <Button
                            variant="secondary"
                            className="size-10 px-0 lg:hidden"
                            aria-label="Buka navigasi"
                            onClick={() => setMobileMenuOpen(true)}
                        >
                            <AppIcon name="menu" className="size-5" />
                        </Button>
                        <div className="lg:hidden">
                            <span className="text-sm font-black tracking-[-0.02em] text-ink">Klik Laundry</span>
                        </div>

                        <label className="ml-auto hidden w-full max-w-[360px] items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2.5 text-subtle shadow-sm md:flex lg:ml-0">
                            <AppIcon name="search" className="size-[17px]" />
                            <span className="sr-only">Cari</span>
                            <input
                                type="search"
                                placeholder="Cari order, outlet, atau customer..."
                                className="w-full bg-transparent text-sm text-copy outline-none placeholder:text-subtle"
                            />
                            <kbd className="rounded-md border border-line bg-canvas px-1.5 py-0.5 text-[10px] font-bold text-muted">Ctrl K</kbd>
                        </label>

                        <div className="ml-auto flex items-center gap-2 md:ml-0">
                            <span className="hidden items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-xs font-bold text-muted sm:flex">
                                <span className="size-2 rounded-full bg-success shadow-[0_0_0_4px_rgba(35,122,82,0.12)]" />
                                Sistem normal
                            </span>
                            <Button variant="secondary" className="relative size-10 px-0" aria-label="Notifikasi">
                                <AppIcon name="bell" className="size-[18px]" />
                                <span className="absolute right-2.5 top-2.5 size-1.5 rounded-full bg-orange-500 ring-2 ring-white" />
                            </Button>
                        </div>
                    </div>
                </header>

                <main id="overview" className="mx-auto w-full max-w-[1440px] px-4 py-5 sm:px-6 sm:py-7 lg:px-8 xl:px-10">
                    {children}
                </main>
            </div>
        </div>
    );
}
