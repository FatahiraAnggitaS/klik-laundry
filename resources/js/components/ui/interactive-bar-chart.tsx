import { useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card } from './card';

export interface ChartDatum {
    label: string;
    value: number;
}

interface InteractiveBarChartProps {
    data: ChartDatum[];
    formatValue?: (value: number) => string;
    title: string;
}

export function InteractiveBarChart({ data, formatValue = (value) => value.toLocaleString('id-ID'), title }: InteractiveBarChartProps) {
    const [reduceMotion, setReduceMotion] = useState(false);
    const [showBars, setShowBars] = useState(true);

    useEffect(() => {
        const media = window.matchMedia('(prefers-reduced-motion: reduce)');
        const sync = () => setReduceMotion(media.matches);
        sync();
        media.addEventListener('change', sync);
        return () => media.removeEventListener('change', sync);
    }, []);

    return (
        <Card className="p-5">
            <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-lg font-black">{title}</h2><button type="button" data-testid="chart-legend-toggle" aria-pressed={showBars} onClick={() => setShowBars((visible) => !visible)} className="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-xs font-bold text-copy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"><span className="size-3 rounded-sm bg-brand-500" aria-hidden="true" />Batang grafik</button></div>
            <div className="mt-4 h-72 w-full" role="img" aria-label={`${title}. ${data.map((item) => `${item.label}: ${formatValue(item.value)}`).join(', ')}`}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} accessibilityLayer margin={{ top: 8, right: 8, left: 8, bottom: 12 }}>
                        <CartesianGrid stroke="var(--color-line)" strokeDasharray="4 4" vertical={false} />
                        <XAxis dataKey="label" tick={{ fill: 'var(--color-muted)', fontSize: 11 }} axisLine={false} tickLine={false} />
                        <YAxis tick={{ fill: 'var(--color-muted)', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={(value) => formatValue(Number(value))} width={72} />
                        <Tooltip formatter={(value) => formatValue(Number(value))} contentStyle={{ background: 'var(--color-surface)', borderColor: 'var(--color-line)', borderRadius: 12, color: 'var(--color-ink)' }} />
                        <Bar dataKey="value" name="Nilai" fill="var(--color-brand-500)" radius={[8, 8, 2, 2]} hide={!showBars} isAnimationActive={!reduceMotion} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <dl className="sr-only">{data.map((item) => <div key={item.label}><dt>{item.label}</dt><dd>{formatValue(item.value)}</dd></div>)}</dl>
        </Card>
    );
}
