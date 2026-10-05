interface AppLogoProps {
    compact?: boolean;
    inverted?: boolean;
}

export function AppLogo({ compact = false, inverted = true }: AppLogoProps) {
    return (
        <div className="flex items-center gap-3" aria-label="Klik Laundry">
            <span className="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-[14px] bg-gradient-to-br from-brand-500 to-accent text-white shadow-[0_10px_28px_rgba(14,165,233,0.28)]">
                <span className="absolute -right-1 -top-1 size-5 rounded-full border-[5px] border-white/25" />
                <span className="absolute -bottom-2 -left-2 size-5 rounded-full bg-white/15" />
                <span className="text-lg font-black tracking-[-0.08em]">KL</span>
            </span>
            {!compact && (
                <span>
                    <span className={`block text-[15px] font-bold tracking-[-0.02em] ${inverted ? 'text-white' : 'text-ink'}`}>Klik Laundry</span>
                    <span className={`mt-0.5 block text-[10px] font-semibold uppercase tracking-[0.2em] ${inverted ? 'text-white/65' : 'text-muted'}`}>
                        Clean operations
                    </span>
                </span>
            )}
        </div>
    );
}
