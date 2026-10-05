import { useState, type ReactNode } from 'react';
import { Button, type ButtonProps } from './button';
import { ConfirmDialog } from './confirm-dialog';

interface ConfirmButtonProps extends Omit<ButtonProps, 'children' | 'onClick'> {
    children: ReactNode;
    confirmLabel?: string;
    description: string;
    onConfirm: () => void;
    title: string;
}

export function ConfirmButton({ children, confirmLabel, description, onConfirm, title, ...buttonProps }: ConfirmButtonProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button {...buttonProps} onClick={() => setOpen(true)}>{children}</Button>
            <ConfirmDialog
                open={open}
                title={title}
                description={description}
                confirmLabel={confirmLabel}
                onCancel={() => setOpen(false)}
                onConfirm={() => { setOpen(false); onConfirm(); }}
            />
        </>
    );
}
