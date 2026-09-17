import type { InputHTMLAttributes } from 'react';

interface FormFieldProps extends InputHTMLAttributes<HTMLInputElement> {
    error?: string;
    label: string;
}

export function FormField({ error, id, label, className = '', ...props }: FormFieldProps) {
    const fieldId = id ?? props.name;

    return (
        <label className="block text-sm font-bold text-copy" htmlFor={fieldId}>
            {label}
            <input
                {...props}
                id={fieldId}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${fieldId}-error` : undefined}
                className={`mt-2 min-h-11 w-full rounded-xl border bg-white px-3.5 text-sm font-medium text-ink outline-none transition placeholder:text-subtle focus:border-brand-500 focus:ring-2 focus:ring-brand-100 ${error ? 'border-red-400' : 'border-line'} ${className}`}
            />
            {error && <span id={`${fieldId}-error`} className="mt-1.5 block text-xs font-semibold text-red-700">{error}</span>}
        </label>
    );
}
