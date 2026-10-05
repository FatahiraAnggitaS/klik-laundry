import { CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export function Toast({ message }: { message?: string | null }) {
    const [dismissedMessage, setDismissedMessage] = useState<string | null>(null);

    useEffect(() => {
        if (!message) return;

        const timeout = window.setTimeout(() => setDismissedMessage(message), 5000);
        return () => window.clearTimeout(timeout);
    }, [message]);

    if (!message || message === dismissedMessage) return null;

    return (
        <div role="status" aria-live="polite" className="fixed bottom-4 right-4 z-[80] flex max-w-[calc(100vw-2rem)] items-start gap-3 rounded-xl border border-success/25 bg-surface p-4 text-sm text-copy shadow-floating">
            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" aria-hidden="true" />
            <span className="font-semibold">{message}</span>
            <button type="button" onClick={() => setDismissedMessage(message)} className="rounded-md text-muted hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" aria-label="Tutup notifikasi">
                <X className="size-4" />
            </button>
        </div>
    );
}
