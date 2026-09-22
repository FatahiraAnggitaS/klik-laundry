import type { OrderSummary } from '@/types/orders';
import type { Paginated } from '@/types/operations';

export interface PaymentChannel {
    code: string;
    label: string;
    category: string;
    isActive?: boolean;
    verifiedAt?: string | null;
}

export interface PaymentAttempt {
    publicId: string;
    orderPublicId: string;
    orderNumber: string;
    merchantOrderId: string;
    providerReference: string | null;
    channelCode: string;
    channelLabel: string;
    amount: number;
    status: string;
    reconciliation: string;
    paymentUrl?: string | null;
    feeAmount: number | null;
    expiresAt: string;
    paidAt: string | null;
}

export interface PaymentCreatePage {
    order: OrderSummary;
    channels: PaymentChannel[];
    activeAttempt: PaymentAttempt | null;
}

export interface PaymentShowPage {
    payment: PaymentAttempt;
}

export interface PaymentFilters {
    status: string | null;
    reconciliation: string | null;
    query: string | null;
}

export interface TenantPaymentPage {
    items: PaymentAttempt[];
    meta: Paginated<PaymentAttempt>['meta'];
    filters: PaymentFilters;
}

export interface ReconciliationPayment extends PaymentAttempt {
    tenantName: string;
}

export interface ReconciliationPage {
    items: ReconciliationPayment[];
    meta: Paginated<ReconciliationPayment>['meta'];
    filters: PaymentFilters;
}
