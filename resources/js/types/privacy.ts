import type { PaginationMeta } from '@/types/operations';

export interface AccountClosureReadiness {
    canClose: boolean;
    blockers: {
        activeOrders: number;
        pendingPayments: number;
        activeRefunds: number;
    };
}

export interface PiiRevealGrant {
    publicId: string;
    expiresAt: string;
}

export interface OrderPrivacy {
    orderPublicId: string;
    orderNumber: string;
    revealed: boolean;
    customer: { name: string; email: string; phone: string | null };
    addresses: Array<{ type: string; contactName: string; contactPhone: string; address: string; area: string; city: string }>;
}

export interface AuditLogItem {
    source: 'activity' | 'pii_access';
    action: string;
    subjectType: string;
    subjectId: string;
    reasonRecorded: boolean;
    actorPublicId: string | null;
    tenantPublicId: string | null;
    occurredAt: string;
}

export interface PiiAccessLog extends AuditLogItem {
    source: 'pii_access';
}

export interface AuditFilters {
    action: string | null;
    actor: string | null;
    tenant: string | null;
    subject: string | null;
    from: string | null;
    to: string | null;
}

export interface AuditPage {
    items: AuditLogItem[];
    meta: PaginationMeta;
}

export interface ProofCleanupSummary {
    eligible: number;
    failed: number;
    deleted: number;
}

export interface OperationalReadiness {
    status: 'ready' | 'unavailable';
}
