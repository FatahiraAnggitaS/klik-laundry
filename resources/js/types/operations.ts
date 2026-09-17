export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}

export interface Paginated<T> {
    items: T[];
    meta: PaginationMeta;
}

export type ResourceStatus = 'active' | 'archived' | 'draft';
export type PricingType = 'fixed' | 'per_kg';
export type SlotType = 'delivery' | 'pickup';

export interface OperatingHour {
    dayOfWeek: number;
    opensAt: string;
    closesAt: string;
}

export interface OutletSlot {
    publicId: string;
    type: SlotType;
    dayOfWeek: number;
    startsAt: string;
    endsAt: string;
    active: boolean;
}

export interface OutletBlackout {
    publicId: string;
    date: string;
    reason: string;
}

export interface ServicePackage {
    publicId: string;
    name: string;
    description: string | null;
    pricingType: PricingType;
    unitPrice: number;
    minimumQuantity: number | null;
    minimumWeightGrams: number | null;
    estimatedDurationMinutes: number;
    status: ResourceStatus;
}

export interface ReadinessBlocker {
    code: string;
    label: string;
}

export interface OutletReadiness {
    ready: boolean;
    blockers: ReadinessBlocker[];
}

export interface OutletSummary {
    publicId: string;
    name: string;
    city: string;
    area: string;
    pickupFee: number;
    deliveryFee: number;
    packages: ServicePackage[];
    tenantName: string | null;
    distanceKm: number | null;
}

export interface OutletDetail extends OutletSummary {
    contactPhone: string;
    address: string;
    serviceRadiusKm: number;
}

export interface OutletOperational extends OutletDetail {
    latitude: number;
    longitude: number;
    status: ResourceStatus;
    operatingHours: OperatingHour[];
    slots: OutletSlot[];
    blackouts: OutletBlackout[];
    readiness?: OutletReadiness;
}

export interface CustomerAddress {
    publicId: string;
    label: string;
    contactName: string;
    contactPhone: string;
    address: string;
    city: string;
    area: string;
    latitude: number;
    longitude: number;
    isDefault: boolean;
    locationConsentedAt: string;
}

export interface AddressCoverageItem {
    addressPublicId: string;
    distanceKm: number;
    withinRadius: boolean;
}

export interface AddressCoverage {
    pickup: AddressCoverageItem | null;
    delivery: AddressCoverageItem | null;
    ready: boolean;
}

export interface AvailableSlot {
    slotPublicId: string;
    type: SlotType;
    startsAt: string;
    endsAt: string;
}

export interface OutletSearchFilters {
    query: string | null;
    pricingType: PricingType | null;
    latitude: number | null;
    longitude: number | null;
    usingLocation: boolean;
}
