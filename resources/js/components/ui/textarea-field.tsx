import type { TextareaHTMLAttributes } from 'react';

interface TextareaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    error?: string;
    label: string;
}

export function TextareaField({ error, id, label, className = '', ...props }: TextareaFieldProps) {
    const fieldId = id ?? props.name;

    return <label className="block text-sm font-bold text-copy" htmlFor={fieldId}>{label}<textarea {...props} id={fieldId} aria-invalid={Boolean(error)} aria-describedby={error ? `${fieldId}-error` : undefined} className={`mt-2 min-h-28 w-full rounded-xl border bg-surface px-3.5 py-3 text-sm font-medium text-ink outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100 disabled:cursor-not-allowed disabled:opacity-60 ${error ? 'border-danger' : 'border-line'} ${className}`} />{error && <span id={`${fieldId}-error`} className="mt-1.5 block text-xs font-semibold text-danger">{error}</span>}</label>;
}
