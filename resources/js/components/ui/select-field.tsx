import type { ReactNode, SelectHTMLAttributes } from 'react';

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
    children: ReactNode;
    error?: string;
    label: string;
}

export function SelectField({ children, error, id, label, className = '', ...props }: SelectFieldProps) {
    const fieldId = id ?? props.name;

    return <label className="block text-sm font-bold text-copy" htmlFor={fieldId}>{label}<select {...props} id={fieldId} aria-invalid={Boolean(error)} className={`mt-2 min-h-11 w-full rounded-xl border bg-white px-3.5 text-sm font-medium outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 ${error ? 'border-red-400' : 'border-line'} ${className}`}>{children}</select>{error && <span className="mt-1.5 block text-xs font-semibold text-red-700">{error}</span>}</label>;
}
