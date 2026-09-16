import { Head, Link } from '@inertiajs/react';

interface DomainErrorPageProps {
    status: number;
    message: string;
}

export default function DomainError({ status, message }: DomainErrorPageProps) {
    return (
        <main className="grid min-h-screen place-items-center bg-canvas px-5 py-10 text-copy">
            <Head title={`Error ${status}`} />
            <section className="w-full max-w-lg rounded-3xl border border-line bg-surface p-7 text-center shadow-floating sm:p-10">
                <p className="text-xs font-black uppercase tracking-[0.18em] text-brand-600">Error {status}</p>
                <h1 className="mt-4 text-3xl font-black tracking-[-0.04em] text-ink">Permintaan belum dapat diproses</h1>
                <p className="mt-4 text-sm leading-6 text-muted">{message}</p>
                <Link
                    href="/"
                    className="mt-7 inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-900 px-5 text-sm font-bold text-white transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2"
                >
                    Kembali ke dashboard
                </Link>
            </section>
        </main>
    );
}
