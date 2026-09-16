interface AppLogoProps {
    compact?: boolean;
}

export function AppLogo({ compact = false }: AppLogoProps) {
    return (
        <div className="flex items-center gap-3" aria-label="Klik Laundry">
            <span className="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-[14px] bg-accent text-brand-950 shadow-[0_10px_28px_rgba(217,255,116,0.18)]">
                <span className="absolute -right-1 -top-1 size-5 rounded-full border-[5px] border-brand-950/15" />
                <span className="text-lg font-black tracking-[-0.08em]">KL</span>
            </span>
            {!compact && (
                <span>
                    <span className="block text-[15px] font-bold tracking-[-0.02em] text-white">Klik Laundry</span>
                    <span className="mt-0.5 block text-[10px] font-semibold uppercase tracking-[0.2em] text-white/45">
                        Clean operations
                    </span>
                </span>
            )}
        </div>
    );
}
