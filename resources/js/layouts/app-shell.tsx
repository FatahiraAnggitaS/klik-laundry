import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { AppIcon } from '@/components/app-icon';
import { AppLogo } from '@/components/app-logo';
import { RoleSwitcher } from '@/components/dashboard/role-switcher';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/theme';
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
                <p className="px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-white/70">Workspace</p>
                <ul className="mt-3 space-y-1">
                    {navigation.map((item, index) => (
                        <li key={item.label}>
                            <a
                                href={item.href}
                                onClick={onNavigate}
                                aria-current={index === 0 ? 'page' : undefined}
                                className={`group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ${
                                    index === 0
                                        ? 'bg-surface text-ink shadow-[0_10px_24px_rgba(0,0,0,0.1)]'
                                        : 'text-white/58 hover:bg-white/[0.07] hover:text-white'
                                }`}
                            >
                                <AppIcon
                                    name={item.icon}
                                    className={`size-[18px] ${index === 0 ? 'text-brand-600' : 'text-white/65 group-hover:text-white/80'}`}
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
                        <span className="block truncate text-sm font-bold text-white">Anggita Saputri</span>
                        <span className="block truncate text-[11px] text-white/65">{activeRole.label}</span>
                    </span>
                    <AppIcon name="chevron-down" className="size-4 text-white/35" />
                </div>
            </div>
        </div>
    );
}

export function AppShell({ activeRole, roles, navigation, children }: AppShellProps) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const mobileDialog = useRef<HTMLDialogElement>(null);
    const menuButton = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        const dialog = mobileDialog.current;
        if (dialog === null) return;
        if (mobileMenuOpen && !dialog.open) dialog.showModal();
        if (!mobileMenuOpen && dialog.open) dialog.close();
    }, [mobileMenuOpen]);

    const closeMobileMenu = () => {
        setMobileMenuOpen(false);
        window.setTimeout(() => menuButton.current?.focus(), 0);
    };

    return (
        <div className="min-h-screen bg-canvas text-ink">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-[272px] bg-brand-950 p-5 lg:block">
                <div className="sidebar-glow absolute inset-0 overflow-hidden" aria-hidden="true" />
                <div className="relative h-full">
                    <Sidebar activeRole={activeRole} roles={roles} navigation={navigation} />
                </div>
            </aside>

            <dialog ref={mobileDialog} aria-label="Navigasi utama" onCancel={(event) => { event.preventDefault(); closeMobileMenu(); }} onClose={() => setMobileMenuOpen(false)} className="m-0 h-dvh w-[min(86vw,320px)] max-w-none border-0 bg-brand-950 p-5 shadow-2xl backdrop:bg-brand-950/70 lg:hidden">
                        <Button
                            variant="ghost"
                            className="absolute right-3 top-3 size-10 px-0 text-white/65 hover:bg-white/10 hover:text-white"
                            aria-label="Tutup menu"
                            onClick={closeMobileMenu}
                        >
                            <AppIcon name="close" className="size-5" />
                        </Button>
                        <Sidebar
                            activeRole={activeRole}
                            roles={roles}
                            navigation={navigation}
                            onNavigate={closeMobileMenu}
                        />
            </dialog>

            <div className="lg:pl-[272px]">
                <header className="sticky top-0 z-20 border-b border-line/80 bg-canvas/90 backdrop-blur-xl">
                    <div className="flex h-16 items-center gap-3 px-4 sm:h-[72px] sm:px-6 lg:px-8 xl:px-10">
                        <Button
                            ref={menuButton}
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

                        <div className="ml-auto flex items-center gap-2">
                            <span className="hidden items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-xs font-bold text-muted sm:flex">
                                <span className="size-2 rounded-full bg-success shadow-[0_0_0_4px_rgba(35,122,82,0.12)]" />
                                Sistem normal
                            </span>
                            <ThemeToggle compact />
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
