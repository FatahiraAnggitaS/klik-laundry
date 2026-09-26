import type { PaginationMeta } from './identity';

export interface FinanceFilters { from: string; to: string; outlet?: string | null }
export interface FinanceSummary {
    grossPaid: number; gatewayFeeActual: number; unknownFeeCount: number;
    netAfterGatewayFee: number | null; driverCommission: number; financialAdjustment: number;
    netOperational: number | null; settlementMovement: number | null; isFinal: boolean;
}
export interface FinancePaymentLine {
    publicId: string; orderNumber: string; outletName: string; grossAmount: number;
    feeAmount: number | null; netAmount: number | null; paidAt: string | null; feeFinal: boolean;
}
export interface RefundRequest {
    publicId: string; orderNumber: string; paymentPublicId: string; status: 'submitted' | 'approved' | 'rejected' | 'completed';
    amount: number; reason: string; submittedAt: string; reviewReason: string | null; reviewedAt: string | null;
    transferMethod: string | null; externalReference: string | null; completedAt: string | null;
}
export interface PayoutItem { [key: string]: number | string | null }
export interface TenantPayout {
    publicId: string; batchReference: string; status: 'pending_transfer' | 'finalized' | 'voided'; cutoffAt: string;
    grossAmount: number; feeAmount: number; adjustmentAmount: number; netAmount: number; bankName: string;
    maskedAccountNumber: string; transferMethod: string | null; externalReference: string | null;
    finalizedAt: string | null; voidReason: string | null; payments: PayoutItem[]; adjustments: PayoutItem[];
}
export interface DriverPayout {
    publicId: string; batchReference: string; driverPublicId: string; driverName: string;
    status: 'pending_transfer' | 'finalized' | 'voided'; cutoffAt: string; totalAmount: number;
    transferMethod: string | null; externalReference: string | null; note: string | null;
    finalizedAt: string | null; voidReason: string | null; items: PayoutItem[];
}
export interface CommissionLine {
    publicId: string; orderNumber: string; taskPublicId: string; taskType: string; amount: number;
    status: 'earned' | 'paid'; earnedAt: string; paidAt: string | null;
}
export interface NamedPublicResource { publicId: string; name: string }
export interface RefundPage { items: RefundRequest[]; meta: PaginationMeta }
export interface TenantFinancePage {
    summary: FinanceSummary; payments: FinancePaymentLine[]; refunds: RefundPage;
    tenantPayouts: TenantPayout[]; driverPayouts: DriverPayout[]; drivers: NamedPublicResource[];
    outlets: NamedPublicResource[]; filters: FinanceFilters;
}
export interface DriverFinancePage {
    summary: { earnedAmount: number; earnedCount: number; paidAmount: number };
    commissions: CommissionLine[]; payouts: DriverPayout[]; filters: FinanceFilters;
}
export interface SupportFinancePage {
    metrics: Record<string, number>; refunds: RefundPage; tenantPayouts: TenantPayout[];
    tenants: NamedPublicResource[]; filters: FinanceFilters;
}
