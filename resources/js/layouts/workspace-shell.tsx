import { Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, LogOut, Menu, ShieldCheck, X, type LucideIcon, CircleDollarSign, ClipboardList, Gauge, MapPin, PackageCheck, Route, Settings, Store, Truck, UserRoundCog, UsersRound, WalletCards } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { AppLogo } from '@/components/app-logo';
import { NotificationLink } from '@/components/notification-link';
import { Button } from '@/components/ui/button';
import { Toast } from '@/components/ui/toast';
import { ThemeToggle } from '@/theme';
import type { RoleKey } from '@/types/dashboard';
import type { SharedPageProps } from '@/types/shared';

interface NavigationItem {
    href: string;
    icon: LucideIcon;
    label: string;
    match?: string[];
}

const common: NavigationItem[] = [
    { href: '/workspace', icon: Gauge, label: 'Workspace' },
    { href: '/identity/security', icon: ShieldCheck, label: 'Keamanan' },
];

const navigation: Record<RoleKey, NavigationItem[]> = {
    customer: [
        common[0],
        { href: '/outlets', icon: MapPin, label: 'Cari Outlet' },
        { href: '/orders', icon: ClipboardList, label: 'Order', match: ['/orders', '/payments'] },
        { href: '/customer/addresses', icon: Store, label: 'Alamat' },
        ...common.slice(1),
    ],
    tenant_owner: [
        common[0],
        { href: '/tenant/orders', icon: ClipboardList, label: 'Order', match: ['/tenant/orders'] },
        { href: '/tenant/operations', icon: PackageCheck, label: 'Operasional' },
        { href: '/tenant/dispatch', icon: Route, label: 'Dispatch' },
        { href: '/tenant/drivers', icon: Truck, label: 'Driver' },
        { href: '/tenant/payments', icon: WalletCards, label: 'Pembayaran' },
        { href: '/tenant/finance', icon: CircleDollarSign, label: 'Keuangan', match: ['/tenant/finance', '/finance'] },
        ...common.slice(1),
    ],
    driver: [
        common[0],
        { href: '/driver/tasks', icon: Truck, label: 'Tugas' },
        { href: '/driver/finance', icon: CircleDollarSign, label: 'Komisi', match: ['/driver/finance', '/finance'] },
        ...common.slice(1),
    ],
    super_user: [
        common[0],
        { href: '/super-user/tenants', icon: UsersRound, label: 'Tenant' },
        { href: '/super-user/payments', icon: WalletCards, label: 'Pembayaran' },
        { href: '/super-user/finance', icon: CircleDollarSign, label: 'Keuangan', match: ['/super-user/finance', '/finance'] },
        { href: '/super-user/platform-settings', icon: Settings, label: 'Platform' },
        { href: '/super-user/audit', icon: UserRoundCog, label: 'Audit', match: ['/super-user/audit', '/super-user/orders'] },
        ...common.slice(1),
    ],
};

const roleLabels: Record<RoleKey, string> = {
    customer: 'Customer',
    tenant_owner: 'Tenant Owner',
    driver: 'Driver',
    super_user: 'Super User',
};

function isActive(path: string, item: NavigationItem): boolean {
    const matches = item.match ?? [item.href];
    return matches.some((match) => path === match || (match !== '/workspace' && path.startsWith(`${match}/`)));
}

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const page = usePage<SharedPageProps>();
    const user = page.props.auth.user;
    if (user === null) return null;

    return (
        <div className="flex h-full flex-col">
            <Link href="/workspace" onClick={onNavigate} className="rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <AppLogo />
            </Link>
            <div className="mt-7 rounded-2xl border border-white/10 bg-white/[0.06] p-3 text-white">
                <p className="truncate text-sm font-black">{user.name}</p>
                <p className="mt-0.5 text-xs text-white/65">{roleLabels[user.role]}</p>
            </div>
            <nav aria-label="Navigasi workspace" className="mt-7 flex-1 overflow-y-auto">
                <p className="px-3 text-[10px] font-black uppercase tracking-[0.18em] text-white/40">Menu utama</p>
                <ul className="mt-3 space-y-1">
                    {navigation[user.role].map((item) => {
                        const active = isActive(page.url.split('?')[0], item);
                        const Icon = item.icon;
                        return <li key={item.href}><Link href={item.href} onClick={onNavigate} aria-current={active ? 'page' : undefined} className={`group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ${active ? 'bg-surface text-ink shadow-lg' : 'text-white/65 hover:bg-white/10 hover:text-white'}`}><Icon className={`size-[18px] ${active ? 'text-brand-600' : ''}`} aria-hidden="true" /><span className="flex-1">{item.label}</span>{active && <ChevronRight className="size-4 text-brand-500" aria-hidden="true" />}</Link></li>;
                    })}
                </ul>
            </nav>
            <Button variant="ghost" className="mt-5 justify-start text-white/65 hover:bg-white/10 hover:text-white" onClick={() => router.post('/logout')}>
                <LogOut className="size-4" />Keluar
            </Button>
        </div>
    );
}

export function WorkspaceShell({ children }: { children: ReactNode }) {
    const { flash } = usePage<SharedPageProps>().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const mobileDialog = useRef<HTMLDialogElement>(null);
    const menuButton = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        const dialog = mobileDialog.current;
        if (dialog === null) return;
        if (mobileOpen && !dialog.open) dialog.showModal();
        if (!mobileOpen && dialog.open) dialog.close();
    }, [mobileOpen]);

    const closeMobile = () => {
        setMobileOpen(false);
        window.setTimeout(() => menuButton.current?.focus(), 0);
    };

    return (
        <div className="min-h-screen bg-canvas text-ink">
            <aside className="sidebar-glow fixed inset-y-0 left-0 z-30 hidden w-[272px] overflow-hidden bg-brand-950 p-5 lg:block"><div className="relative h-full"><Sidebar /></div></aside>
            <dialog ref={mobileDialog} aria-label="Menu workspace" onCancel={(event) => { event.preventDefault(); closeMobile(); }} onClose={() => setMobileOpen(false)} className="m-0 h-dvh w-[min(86vw,320px)] max-w-none border-0 bg-brand-950 p-5 shadow-2xl backdrop:bg-brand-950/70 lg:hidden">
                <Button variant="ghost" className="absolute right-3 top-3 z-10 size-10 px-0 text-white/70 hover:bg-white/10 hover:text-white" aria-label="Tutup menu" onClick={closeMobile}><X className="size-5" /></Button>
                <Sidebar onNavigate={closeMobile} />
            </dialog>
            <div className="lg:pl-[272px]">
                <header className="sticky top-0 z-20 border-b border-line/80 bg-canvas/90 backdrop-blur-xl">
                    <div className="flex h-16 items-center gap-3 px-4 sm:h-[72px] sm:px-6 lg:px-8 xl:px-10">
                        <Button ref={menuButton} variant="secondary" className="size-10 px-0 lg:hidden" aria-label="Buka menu" onClick={() => setMobileOpen(true)}><Menu className="size-5" /></Button>
                        <span className="text-sm font-black tracking-[-0.02em] lg:hidden">Klik Laundry</span>
                        <div className="ml-auto flex items-center gap-2">
                            <ThemeToggle compact />
                            <NotificationLink />
                        </div>
                    </div>
                </header>
                <div className="workspace-content page-enter mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8 xl:px-10">{children}</div>
            </div>
            <Toast message={flash?.status} />
        </div>
    );
}
