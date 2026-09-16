import { Link } from '@inertiajs/react';
import { AppIcon } from '@/components/app-icon';
import type { ActiveRole, RoleOption } from '@/types/dashboard';

interface RoleSwitcherProps {
    activeRole: ActiveRole;
    roles: RoleOption[];
}

export function RoleSwitcher({ activeRole, roles }: RoleSwitcherProps) {
    return (
        <details className="group relative">
            <summary className="flex cursor-pointer list-none items-center justify-between rounded-2xl border border-white/10 bg-white/[0.06] px-3 py-3 text-left transition hover:bg-white/[0.1] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <span className="min-w-0">
                    <span className="block text-[10px] font-bold uppercase tracking-[0.16em] text-white/45">Preview sebagai</span>
                    <span className="mt-1 block truncate text-sm font-bold text-white">{activeRole.label}</span>
                </span>
                <AppIcon name="chevron-down" className="size-4 text-white/50 transition group-open:rotate-180" />
            </summary>

            <div className="absolute left-0 top-[calc(100%+0.5rem)] z-30 w-full overflow-hidden rounded-2xl border border-line bg-surface p-1.5 text-ink shadow-2xl">
                {roles.map((role) => (
                    <Link
                        key={role.value}
                        href={`/preview/${role.value}`}
                        prefetch
                        className={`block rounded-xl px-3 py-2.5 transition hover:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 ${
                            role.value === activeRole.value ? 'bg-brand-50' : ''
                        }`}
                    >
                        <span className="block text-sm font-bold">{role.label}</span>
                        <span className="mt-0.5 block text-xs text-muted">{role.description}</span>
                    </Link>
                ))}
            </div>
        </details>
    );
}
