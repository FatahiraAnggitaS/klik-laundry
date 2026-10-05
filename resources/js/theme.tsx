/* eslint-disable react-refresh/only-export-components */
import { Laptop, Moon, Sun } from 'lucide-react';
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

export type ThemePreference = 'system' | 'light' | 'dark';

interface ThemeContextValue {
    preference: ThemePreference;
    resolved: 'light' | 'dark';
    setPreference: (preference: ThemePreference) => void;
}

const storageKey = 'klik-laundry-theme';
const ThemeContext = createContext<ThemeContextValue | null>(null);

function storedPreference(): ThemePreference {
    try {
        const value = window.localStorage.getItem(storageKey);

        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
}

function resolveTheme(preference: ThemePreference): 'light' | 'dark' {
    return preference === 'system'
        ? window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
        : preference;
}

function applyTheme(preference: ThemePreference): 'light' | 'dark' {
    const resolved = resolveTheme(preference);
    document.documentElement.dataset.theme = resolved;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', resolved === 'dark' ? '#071521' : '#F4F8FC');

    return resolved;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [preference, setPreferenceState] = useState<ThemePreference>(storedPreference);
    const [resolved, setResolved] = useState<'light' | 'dark'>(() => resolveTheme(storedPreference()));

    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const sync = () => setResolved(applyTheme(preference));
        sync();

        if (preference === 'system') media.addEventListener('change', sync);

        return () => media.removeEventListener('change', sync);
    }, [preference]);

    const setPreference = (next: ThemePreference) => {
        try {
            if (next === 'system') window.localStorage.removeItem(storageKey);
            else window.localStorage.setItem(storageKey, next);
        } catch {
            // Theme still applies for this page when storage is unavailable.
        }

        setPreferenceState(next);
    };

    return <ThemeContext value={{ preference, resolved, setPreference }}>{children}</ThemeContext>;
}

export function useTheme(): ThemeContextValue {
    const theme = useContext(ThemeContext);
    if (theme === null) throw new Error('useTheme must be used inside ThemeProvider.');

    return theme;
}

const options: Array<{ label: string; value: ThemePreference; icon: typeof Sun }> = [
    { label: 'Sistem', value: 'system', icon: Laptop },
    { label: 'Terang', value: 'light', icon: Sun },
    { label: 'Gelap', value: 'dark', icon: Moon },
];

export function ThemeToggle({ compact = false }: { compact?: boolean }) {
    const { preference, setPreference } = useTheme();

    return (
        <fieldset className="inline-flex rounded-xl border border-line bg-surface p-1 shadow-sm" aria-label="Pilih tema">
            <legend className="sr-only">Tema tampilan</legend>
            {options.map(({ label, value, icon: Icon }) => (
                <button
                    key={value}
                    type="button"
                    aria-pressed={preference === value}
                    title={`Tema ${label.toLowerCase()}`}
                    onClick={() => setPreference(value)}
                    className={`inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg px-2.5 text-xs font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ${preference === value ? 'bg-brand-600 text-white shadow-sm' : 'text-muted hover:bg-brand-50 hover:text-ink'}`}
                >
                    <Icon className="size-4" aria-hidden="true" />
                    {!compact && <span className="hidden sm:inline">{label}</span>}
                </button>
            ))}
        </fieldset>
    );
}
