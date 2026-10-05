import { Button } from './button';
import { Dialog } from './dialog';

interface ConfirmDialogProps {
    confirmLabel?: string;
    description: string;
    loading?: boolean;
    onCancel: () => void;
    onConfirm: () => void;
    open: boolean;
    title: string;
    variant?: 'danger' | 'primary';
}

export function ConfirmDialog({ confirmLabel = 'Konfirmasi', description, loading = false, onCancel, onConfirm, open, title, variant = 'danger' }: ConfirmDialogProps) {
    return (
        <Dialog
            open={open}
            onClose={onCancel}
            title={title}
            description={description}
            footer={<><Button variant="secondary" onClick={onCancel} disabled={loading}>Batal</Button><Button variant={variant} onClick={onConfirm} loading={loading}>{confirmLabel}</Button></>}
        >
            <p className="text-sm leading-6 text-copy">Pastikan tindakan ini memang diperlukan. Perubahan akan diproses oleh server sesuai hak akses Anda.</p>
        </Dialog>
    );
}
