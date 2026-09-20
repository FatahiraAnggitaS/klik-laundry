import type { AvailableSlot, Paginated } from '@/types/operations';

export type FulfillmentStatus = 'awaiting_payment' | 'awaiting_pickup' | 'pickup_assigned' | 'picked_up' | 'awaiting_weight' | 'processing' | 'ready_for_delivery' | 'delivery_assigned' | 'out_for_delivery' | 'completed' | 'cancelled';
export type PaymentStatus = 'unpaid' | 'pending' | 'paid' | 'failed' | 'expired';

export interface OrderItemSnapshot { packageName: string; packageDescription: string | null; pricingType: 'fixed' | 'per_kg'; unitPrice: number; minimumQuantity: number | null; minimumWeightGrams: number | null; estimatedDurationMinutes: number; quantity: number | null; estimatedWeightGrams: number | null; estimatedBillableWeightGrams: number | null; actualWeightGrams: number | null; billableWeightGrams: number | null }
export interface OrderAddressSnapshot { type: 'pickup' | 'delivery'; label: string; contactName: string; contactPhone: string; address: string; city: string; area: string; latitude: number | null; longitude: number | null }
export interface OrderHistoryItem { from: FulfillmentStatus | null; to: FulfillmentStatus; reason: string | null; occurredAt: string }
export interface OrderScheduleHistoryItem { type: string; oldStartsAt: string; oldEndsAt: string; newStartsAt: string; newEndsAt: string; reason: string | null; occurredAt: string }
export interface OrderIndicator { type: 'delayed' | 'pickup_delayed' | 'delivery_delayed' | 'awaiting_customer'; context: Record<string, string | number | null> | null; detectedAt: string; resolvedAt: string | null }
export interface OrderSummary { publicId: string; orderNumber: string; pricingType: 'fixed' | 'per_kg'; fulfillmentStatus: FulfillmentStatus; paymentStatus: PaymentStatus; pickupStartsAt: string; pickupEndsAt: string; deliveryStartsAt: string | null; deliveryEndsAt: string | null; itemsSubtotal: number | null; estimatedItemsSubtotal: number | null; pickupFee: number; deliveryFee: number; grandTotal: number | null; estimatedGrandTotal: number | null; outletName: string; tenantName: string; customerName: string; item: OrderItemSnapshot; indicators: OrderIndicator[]; createdAt: string; isEstimate: boolean }
export interface OrderDetail extends OrderSummary { estimatedReadyAt: string | null; readyAt: string | null; completedAt: string | null; cancelledAt: string | null; cancellationReason: string | null; addresses: OrderAddressSnapshot[]; statusHistory: OrderHistoryItem[]; scheduleHistory: OrderScheduleHistoryItem[] }
export interface OrderPageProps { order: OrderDetail; viewer: 'customer' | 'tenant_owner'; receipt: boolean; availablePickupSlots: AvailableSlot[]; canMutate: boolean }
export type PaginatedOrders = Paginated<OrderSummary>;
