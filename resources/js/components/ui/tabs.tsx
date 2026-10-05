import type { ReactNode } from 'react';

export interface TabOption<T extends string> {
    label: string;
    value: T;
}

export function Tabs<T extends string>({ label, onChange, options, value, children }: { children: ReactNode; label: string; onChange: (value: T) => void; options: Array<TabOption<T>>; value: T }) {
    return (
        <div>
            <div role="tablist" aria-label={label} className="scrollbar-hidden flex gap-1 overflow-x-auto rounded-xl border border-line bg-surface p-1">
                {options.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        role="tab"
                        aria-selected={value === option.value}
                        onClick={() => onChange(option.value)}
                        className={`min-h-10 flex-1 whitespace-nowrap rounded-lg px-4 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ${value === option.value ? 'bg-brand-600 text-white shadow-sm' : 'text-muted hover:bg-brand-50 hover:text-ink'}`}
                    >
                        {option.label}
                    </button>
                ))}
            </div>
            <div role="tabpanel" className="mt-5">{children}</div>
        </div>
    );
}
