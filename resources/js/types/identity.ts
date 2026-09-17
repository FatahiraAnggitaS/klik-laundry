export interface IdentitySummary {
    publicId: string;
    name: string;
    email: string;
    phone: string | null;
    role: 'customer' | 'driver' | 'super_user' | 'tenant_owner';
    roleLabel: string;
    status: 'active' | 'closed' | 'suspended';
    emailVerified: boolean;
    twoFactorRequired: boolean;
    twoFactorEnabled: boolean;
}

export interface TenantApplication {
    id: number;
    publicId: string;
    name: string;
    slug: string;
    phone: string;
    onboardingStatus: 'approved' | 'pending' | 'rejected';
    operationalStatus: 'active' | 'closed' | 'inactive' | 'suspended';
    reviewReason: string | null;
    reviewedAt: string | null;
    closureRequested: boolean;
    payoutHold: boolean;
    payoutHoldReason: string | null;
    outletName: string;
    outletAddress: string;
    city: string;
    area: string;
    latitude: number;
    longitude: number;
    ownerPublicId: string | null;
    ownerName: string | null;
    ownerEmail: string | null;
    ownerStatus: string | null;
}

export interface PayoutAccount {
    publicId: string;
    tenantId: number;
    bankName: string;
    maskedHolderName: string;
    maskedAccountNumber: string;
    verificationStatus: 'pending' | 'rejected' | 'superseded' | 'verified';
    reviewReason: string | null;
    submittedAt: string;
    reviewedAt: string | null;
    tenantPublicId: string | null;
    tenantName: string | null;
}

export interface ActivityItem {
    action: string;
    subjectType: string;
    subjectId: string;
    reason: string | null;
    createdAt: string;
}

export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}
