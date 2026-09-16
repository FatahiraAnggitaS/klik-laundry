import type { IconName } from '@/components/app-icon';

export type RoleKey = 'customer' | 'tenant_owner' | 'driver' | 'super_user';
export type Tone = 'blue' | 'green' | 'amber' | 'violet' | 'neutral';

export interface ActiveRole {
    value: RoleKey;
    label: string;
}

export interface RoleOption extends ActiveRole {
    description: string;
}

export interface NavigationItem {
    label: string;
    icon: IconName;
}

export interface HeroContent {
    eyebrow: string;
    title: string;
    description: string;
    primaryAction: string;
    secondaryAction: string;
}

export interface Metric {
    label: string;
    value: string;
    change: string;
    tone: Tone;
    icon: IconName;
}

export interface FocusItem {
    label: string;
    title: string;
    description: string;
    meta: string;
    progress: number;
    action: string;
    icon: IconName;
}

export interface WorkItem {
    id: string;
    title: string;
    subtitle: string;
    status: string;
    statusTone: Tone;
    meta: string;
}

export interface Milestone {
    title: string;
    description: string;
    status: 'blocked' | 'current' | 'later';
}

export interface DashboardPageProps {
    activeRole: ActiveRole;
    roles: RoleOption[];
    navigation: NavigationItem[];
    hero: HeroContent;
    metrics: Metric[];
    focus: FocusItem;
    workItems: WorkItem[];
    milestones: Milestone[];
}
