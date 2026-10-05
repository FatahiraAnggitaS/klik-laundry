import { X } from 'lucide-react';
import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Button } from './button';

interface DialogProps {
    children: ReactNode;
    description?: string;
    footer?: ReactNode;
    onClose: () => void;
    open: boolean;
    title: string;
    variant?: 'center' | 'drawer';
}

export function Dialog({ children, description, footer, onClose, open, title, variant = 'center' }: DialogProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const titleId = useId();
    const descriptionId = useId();

    useEffect(() => {
        const dialog = dialogRef.current;
        if (dialog === null) return;

        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby={titleId}
            aria-describedby={description ? descriptionId : undefined}
            onCancel={(event) => { event.preventDefault(); onClose(); }}
            onClose={onClose}
            className={`m-0 max-h-dvh w-full border border-line bg-surface p-0 text-ink shadow-floating backdrop:bg-brand-950/70 ${variant === 'drawer' ? 'ml-auto h-dvh max-w-xl rounded-l-panel' : 'm-auto max-w-lg rounded-panel'}`}
        >
            <div className="flex min-h-full flex-col">
                <header className="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                    <div>
                        <h2 id={titleId} className="text-xl font-black">{title}</h2>
                        {description && <p id={descriptionId} className="mt-1 text-sm leading-6 text-muted">{description}</p>}
                    </div>
                    <Button variant="ghost" className="size-10 shrink-0 px-0" aria-label="Tutup dialog" onClick={onClose}>
                        <X className="size-5" />
                    </Button>
                </header>
                <div className="flex-1 overflow-y-auto px-5 py-5 sm:px-6">{children}</div>
                {footer && <footer className="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-4 sm:px-6">{footer}</footer>}
            </div>
        </dialog>
    );
}
