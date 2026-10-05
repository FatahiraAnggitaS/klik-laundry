import type { RoleKey } from './dashboard';
import type { ThemePreference } from '@/theme';

export interface SharedUser {
    name: string;
    publicId: string;
    role: RoleKey;
}

export interface SharedPageProps {
    [key: string]: unknown;
    app: { locale: string; name: string; timezone: string };
    auth: { user: SharedUser | null };
    theme: { defaultPreference: ThemePreference };
    flash?: { status?: string };
    notifications: { unreadCount: number };
    sensitiveAuthentication: { confirmed: boolean };
}
