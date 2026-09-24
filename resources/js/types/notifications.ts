import type { Paginated } from '@/types/operations';

export interface NotificationItem { id: string; event: string; message: string; orderPublicId: string; taskPublicId: string | null; occurredAt: string; link: string; readAt: string | null; createdAt: string }
export interface NotificationSummary { unreadCount: number }
export interface RealtimeDomainEvent { notificationId: string; event: string; orderPublicId: string; taskPublicId: string | null; occurredAt: string; link: string }
export type PaginatedNotifications = Paginated<NotificationItem>;
