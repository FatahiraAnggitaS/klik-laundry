import type { CommissionLine, RefundRequest, TenantPayout } from '@/types/finance';

export const refundStatusLabels: Record<RefundRequest['status'], string> = {
    submitted: 'Diajukan',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    completed: 'Selesai',
};

export const payoutStatusLabels: Record<TenantPayout['status'], string> = {
    pending_transfer: 'Menunggu transfer',
    finalized: 'Final',
    voided: 'Dibatalkan',
};

export const commissionStatusLabels: Record<CommissionLine['status'], string> = {
    earned: 'Diperoleh',
    paid: 'Dibayar',
};

export const taskTypeLabels: Record<'pickup' | 'delivery', string> = {
    pickup: 'Penjemputan',
    delivery: 'Pengantaran',
};

const metricLabels: Record<string, string> = {
    grossPaid: 'Pembayaran Lunas',
    unknownFeeCount: 'Biaya Belum Final',
    reconciliationMismatchCount: 'Selisih Rekonsiliasi',
    activeRefundCount: 'Pengembalian Aktif',
    pendingTenantPayoutCount: 'Pencairan Tenant',
    pendingDriverPayoutCount: 'Pencairan Driver',
    payoutHoldCount: 'Pencairan Ditahan',
    unsettledAdjustmentAmount: 'Penyesuaian Tertunda',
};

const metricDescriptions: Record<string, string> = {
    grossPaid: 'Total nominal pembayaran berstatus lunas pada periode terpilih.',
    unknownFeeCount: 'Pembayaran lunas yang biaya Duitku-nya belum terkonfirmasi.',
    reconciliationMismatchCount: 'Pembayaran yang memiliki selisih rekonsiliasi.',
    activeRefundCount: 'Pengembalian dana yang diajukan atau disetujui tetapi belum selesai.',
    pendingTenantPayoutCount: 'Pencairan Tenant yang masih menunggu transfer.',
    pendingDriverPayoutCount: 'Pencairan Driver yang masih menunggu transfer.',
    payoutHoldCount: 'Tenant yang pencairannya sedang ditahan.',
    unsettledAdjustmentAmount: 'Total penyesuaian keuangan yang belum diselesaikan.',
};

export function financeMetricLabel(key: string): string {
    return metricLabels[key] ?? key;
}

export function financeMetricDescription(key: string): string {
    return metricDescriptions[key] ?? financeMetricLabel(key);
}

const sourceFieldLabels: Record<string, string> = {
    paymentPublicId: 'ID pembayaran',
    orderNumber: 'Nomor pesanan',
    grossAmount: 'Total bruto',
    feeAmount: 'Biaya Duitku',
    netAmount: 'Jumlah bersih',
    publicId: 'ID penyesuaian',
    type: 'Jenis penyesuaian',
    amount: 'Nominal',
    commissionPublicId: 'ID komisi',
    taskPublicId: 'ID tugas',
    taskType: 'Jenis tugas',
};

export function financeSourceFieldLabel(key: string): string {
    return sourceFieldLabels[key] ?? key.replaceAll('_', ' ').replace(/([a-z])([A-Z])/g, '$1 $2');
}

export function financeSourceValueLabel(key: string, value: string): string {
    if (key === 'taskType' && (value === 'pickup' || value === 'delivery')) return taskTypeLabels[value];
    if (key === 'type' && value === 'full_refund') return 'Pengembalian dana penuh';

    return value;
}

export function financeTransferMethodLabel(method: string | null): string {
    if (method === null) return 'Belum ditetapkan';

    return {
        bank_transfer: 'Transfer bank',
        e_wallet: 'Dompet digital',
        no_transfer_required: 'Tanpa transfer',
    }[method] ?? method;
}
