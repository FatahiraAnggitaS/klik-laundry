import type { HTMLAttributes, ReactNode } from 'react';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
    children: ReactNode;
}

export function Card({ children, className = '', ...props }: CardProps) {
    return (
        <div
            className={`rounded-panel border border-line bg-surface shadow-panel ${className}`}
            {...props}
        >
            {children}
        </div>
    );
}
