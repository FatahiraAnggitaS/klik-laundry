import {
    ArrowLeft,
    ArrowUpRight,
    Bell,
    ChevronDown,
    CircleHelp,
    Clock3,
    LayoutDashboard,
    MapPin,
    Menu,
    PackageCheck,
    Route,
    Scale,
    Search,
    Settings,
    ShieldAlert,
    ShieldCheck,
    ShoppingBag,
    Store,
    WalletCards,
    X,
    type LucideIcon,
    type LucideProps,
} from 'lucide-react';

const icons = {
    'arrow-left': ArrowLeft,
    'arrow-up-right': ArrowUpRight,
    bell: Bell,
    'chevron-down': ChevronDown,
    'circle-help': CircleHelp,
    clock: Clock3,
    'layout-dashboard': LayoutDashboard,
    'map-pin': MapPin,
    menu: Menu,
    'package-check': PackageCheck,
    route: Route,
    scale: Scale,
    search: Search,
    settings: Settings,
    'shield-alert': ShieldAlert,
    'shield-check': ShieldCheck,
    'shopping-bag': ShoppingBag,
    store: Store,
    'wallet-cards': WalletCards,
    close: X,
} satisfies Record<string, LucideIcon>;

export type IconName = keyof typeof icons;

interface AppIconProps extends LucideProps {
    name: IconName;
}

export function AppIcon({ name, ...props }: AppIconProps) {
    const Icon = icons[name];

    return <Icon aria-hidden="true" {...props} />;
}
