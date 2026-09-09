import { usePage } from '@inertiajs/react';

export default function AuthLayout({ children }) {
    const { owlAdmin = {} } = usePage().props;
    const brandName = owlAdmin.brand_name ?? 'Service Admin';
    const logoPath = owlAdmin.logo_path ?? '/images/company-logo.svg';

    return (
        <main className="min-h-screen bg-slate-50 text-slate-900">
            <div className="grid min-h-screen lg:grid-cols-2">
                <section className="relative hidden overflow-hidden bg-slate-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
                    <div
                        className="absolute inset-0 bg-cover bg-center"
                        style={{ backgroundImage: "url('/images/auth-abstract-bg.svg')" }}
                    />
                    <div className="absolute inset-0 bg-gradient-to-br from-slate-950/70 via-slate-900/60 to-slate-950/80" />

                    <div className="relative z-10 flex items-center gap-3">
                        <img src={logoPath} alt={brandName} className="h-9 w-9 rounded-lg bg-white/10 p-1" />
                        <span className="text-2xl font-semibold tracking-tight">{brandName}</span>
                    </div>

                    <div className="relative z-10 max-w-md space-y-4">
                        <h1 className="text-5xl font-semibold leading-tight">
                            Service admin workspace.
                        </h1>
                        <p className="text-base text-slate-300">
                            Secure access for your administration team.
                        </p>
                    </div>
                    <p className="relative z-10 text-xs uppercase tracking-[0.2em] text-slate-400">
                        {brandName}
                    </p>
                </section>

                <section className="relative flex items-center justify-center p-6 sm:p-10">
                    <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                        {children}
                    </div>
                </section>
            </div>
        </main>
    );
}
