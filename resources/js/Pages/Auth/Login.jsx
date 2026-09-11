import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { useT } from '@/i18n';

export default function Login({ status, canResetPassword }) {
    const t = useT();
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout>
            <Head title={t('login.title')} />
            <div className="mb-6">
                <Link href={route('home')} className="text-sm font-medium text-indigo-700 hover:text-indigo-600">
                    {t('login.backToAnalyzer')}
                </Link>
            </div>

            <div className="space-y-8">
                <div className="space-y-2">
                    <h2 className="text-3xl font-semibold text-slate-900">{t('login.welcome')}</h2>
                    <p className="text-sm text-slate-500">{t('login.subtitle')}</p>
                </div>

                {status && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-2">
                        <label htmlFor="email" className="text-sm font-medium text-slate-700">{t('login.email')}</label>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="block h-11 w-full rounded-lg border border-slate-300 px-3 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            placeholder={t('login.emailPlaceholder')}
                            autoComplete="username"
                            required
                        />
                        {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <label htmlFor="password" className="text-sm font-medium text-slate-700">{t('login.password')}</label>
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-xs font-medium text-indigo-600 transition hover:text-indigo-500"
                                >
                                    {t('login.forgotPassword')}
                                </Link>
                            )}
                        </div>
                        <div className="relative">
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="block h-11 w-full rounded-lg border border-slate-300 px-3 pr-20 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                placeholder={t('login.passwordPlaceholder')}
                                autoComplete="current-password"
                                required
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((prev) => !prev)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 rounded px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                                aria-label={showPassword ? t('login.hide') : t('login.show')}
                            >
                                {showPassword ? t('login.hide') : t('login.show')}
                            </button>
                        </div>
                        {errors.password && <p className="text-sm text-red-600">{errors.password}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <span>{processing ? t('login.signingIn') : t('login.signIn')}</span>
                        <ArrowRight className="h-4 w-4" />
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}
