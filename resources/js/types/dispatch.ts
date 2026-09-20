import type { OrderSummary } from '@/types/orders';
import type { Paginated } from '@/types/operations';

export type DriverAvailability = 'available' | 'unavailable';
export type DriverTaskStatus = 'pending' | 'offered' | 'accepted' | 'in_progress' | 'completed' | 'cancelled';

export interface DriverSummary { id: number; tenantId: number; publicId: string; name: string; email: string; phone: string; status: string; availability: DriverAvailability }
export interface DriverInvitation { id: number; tenantId: number; publicId: string; email: string; phone: string; tenantName: string; expiresAt: string; acceptedAt: string | null; revokedAt: string | null }
export interface DriverCommissionSettings { pickupCommission: number | null; deliveryCommission: number | null }
export interface DriverTaskHistory { from: DriverTaskStatus | null; to: DriverTaskStatus; reason: string | null; occurredAt: string }
export interface DeliveryTask { publicId: string; orderPublicId: string; orderNumber: string; pricingType: 'fixed' | 'per_kg'; paymentStatus: string; orderStatus: string; type: 'pickup' | 'delivery'; status: DriverTaskStatus; assigneePublicId: string | null; assigneeName: string | null; commissionAmount: number; outletName: string; scheduledStartsAt: string; scheduledEndsAt: string; area: string; approximateDistanceKm?: number; contactName: string | null; contactPhone: string | null; address: string | null; note: string | null; hasProof: boolean; acceptedAt: string | null; startedAt: string | null; completedAt: string | null; history: DriverTaskHistory[] }
export interface DriverTaskOffer { publicId: string; status: string; expiresAt: string; task: DeliveryTask }
export interface TenantDriverPage { tenant: { name: string; canConfigure: boolean }; settings: DriverCommissionSettings; drivers: Paginated<DriverSummary>; invitations: DriverInvitation[] }
export interface DispatchPage { tenant: { name: string; operationalStatus: string }; tasks: Paginated<DeliveryTask>; drivers: DriverSummary[]; settings: DriverCommissionSettings; eligibleOrders: OrderSummary[] }
export interface DriverTaskPage { driver: DriverSummary; offers: DriverTaskOffer[]; tasks: DeliveryTask[] }
export interface WeightConfirmation { publicId: string; actualGrams: number; minimumGrams: number; billableGrams: number; itemsSubtotal: number; grandTotal: number; hasProof: boolean; confirmedAt: string }
