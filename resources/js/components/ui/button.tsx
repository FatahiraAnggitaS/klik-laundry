import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { LoaderCircle } from 'lucide-react';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    children: ReactNode;
    loading?: boolean;
    loadingLabel?: string;
    variant?: ButtonVariant;
}

const variants: Record<ButtonVariant, string> = {
    primary: 'bg-brand-600 text-white shadow-[0_12px_30px_rgba(2,132,199,0.2)] hover:bg-brand-700',
    secondary: 'border border-line bg-surface text-copy hover:border-line-strong hover:bg-brand-50',
    ghost: 'text-muted hover:bg-brand-50 hover:text-ink',
    danger: 'bg-danger text-white shadow-sm hover:brightness-110',
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button({ children, className = '', disabled, loading = false, loadingLabel = 'Memproses…', type = 'button', variant = 'primary', ...props }, ref) {
    return (
        <button
            ref={ref}
            type={type}
            disabled={disabled || loading}
            aria-busy={loading}
            className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:pointer-events-none disabled:opacity-50 ${variants[variant]} ${className}`}
            {...props}
        >
            {loading && <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />}
            {loading ? loadingLabel : children}
        </button>
    );
});
