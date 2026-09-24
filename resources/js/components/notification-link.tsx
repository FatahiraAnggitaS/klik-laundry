import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import type { NotificationSummary } from '@/types/notifications';

export function NotificationLink() {
    const { notifications } = usePage<{ notifications: NotificationSummary }>().props;
    return <Link href="/notifications" aria-label={`${notifications.unreadCount} notifikasi belum dibaca`} className="relative inline-flex size-10 items-center justify-center rounded-xl border border-line bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
        <Bell className="size-5" />
        {notifications.unreadCount > 0 && <span className="absolute -right-1 -top-1 min-w-5 rounded-full bg-brand-700 px-1 text-center text-[10px] font-black leading-5 text-white">{Math.min(notifications.unreadCount, 99)}</span>}
    </Link>;
}
