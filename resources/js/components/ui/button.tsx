import type { ButtonHTMLAttributes, ReactNode } from 'react';

type ButtonVariant = 'primary' | 'secondary' | 'ghost';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    children: ReactNode;
    variant?: ButtonVariant;
}

const variants: Record<ButtonVariant, string> = {
    primary: 'bg-accent text-brand-950 shadow-[0_12px_30px_rgba(124,150,51,0.16)] hover:bg-accent-soft',
    secondary: 'border border-line bg-surface text-copy hover:border-line-strong hover:bg-brand-50',
    ghost: 'text-muted hover:bg-brand-50 hover:text-ink',
};

export function Button({ children, className = '', type = 'button', variant = 'primary', ...props }: ButtonProps) {
    return (
        <button
            type={type}
            className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 ${variants[variant]} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}
