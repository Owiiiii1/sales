import { Link } from '@inertiajs/react';

export default function PublicLayout({ children }) {
    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <header className="border-b border-slate-200/80 bg-white/90 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between px-6">
                    <Link href={route('home')} className="text-base font-semibold tracking-tight text-slate-900">
                        Sales Analyzer
                    </Link>
                    <Link
                        href={route('login')}
                        className="rounded-full px-3 py-1.5 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                    >
                        Admin
                    </Link>
                </div>
            </header>
            <main>{children}</main>
        </div>
    );
}
