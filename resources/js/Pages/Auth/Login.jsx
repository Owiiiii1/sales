import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, ChevronDown, Globe } from 'lucide-react';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const { locale = 'en' } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);
    const [language, setLanguage] = useState(locale);

    const translations = {
        ru: {
            title: 'Вход в админ-панель',
            welcome: 'С возвращением',
            subtitle: 'Введите данные администратора для входа',
            email: 'Email',
            emailPlaceholder: 'name@company.com',
            password: 'Пароль',
            passwordPlaceholder: 'Введите пароль',
            forgotPassword: 'Забыли пароль?',
            signingIn: 'Входим...',
            signIn: 'Войти',
            show: 'Показать',
            hide: 'Скрыть',
        },
        en: {
            title: 'Admin Login',
            welcome: 'Welcome back',
            subtitle: 'Enter your administrator credentials to continue',
            email: 'Email address',
            emailPlaceholder: 'name@company.com',
            password: 'Password',
            passwordPlaceholder: 'Enter password',
            forgotPassword: 'Forgot password?',
            signingIn: 'Signing in...',
            signIn: 'Sign in',
            show: 'Show',
            hide: 'Hide',
        },
        uk: {
            title: 'Вхід в адмін-панель',
            welcome: 'З поверненням',
            subtitle: 'Введіть дані адміністратора для входу',
            email: 'Email',
            emailPlaceholder: 'name@company.com',
            password: 'Пароль',
            passwordPlaceholder: 'Введіть пароль',
            forgotPassword: 'Забули пароль?',
            signingIn: 'Входимо...',
            signIn: 'Увійти',
            show: 'Показати',
            hide: 'Сховати',
        },
    };

    const t = translations[language] ?? translations.en;
    const languageLabels = { ru: 'Русский', en: 'English', uk: 'Українська' };

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
            <Head title={t.title} />
            <div className="absolute right-6 top-6 sm:right-10 sm:top-10">
                <label htmlFor="language" className="sr-only">Language</label>
                <div className="relative">
                    <div className="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 pr-9 text-sm font-medium text-slate-700 shadow-sm">
                        <Globe className="h-4 w-4 text-slate-500" />
                        <span>{languageLabels[language] ?? languageLabels.en}</span>
                        <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                    </div>
                    <select
                        id="language"
                        value={language}
                        onChange={(e) => setLanguage(e.target.value)}
                        className="absolute inset-0 h-10 w-full cursor-pointer opacity-0"
                    >
                        <option value="ru">Русский</option>
                        <option value="en">English</option>
                        <option value="uk">Українська</option>
                    </select>
                </div>
            </div>

            <div className="space-y-8">
                <div className="space-y-2">
                    <h2 className="text-3xl font-semibold text-slate-900">{t.welcome}</h2>
                    <p className="text-sm text-slate-500">{t.subtitle}</p>
                </div>

                {status && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-2">
                        <label htmlFor="email" className="text-sm font-medium text-slate-700">{t.email}</label>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="block h-11 w-full rounded-lg border border-slate-300 px-3 text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            placeholder={t.emailPlaceholder}
                            autoComplete="username"
                            required
                        />
                        {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <label htmlFor="password" className="text-sm font-medium text-slate-700">{t.password}</label>
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-xs font-medium text-indigo-600 transition hover:text-indigo-500"
                                >
                                    {t.forgotPassword}
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
                                placeholder={t.passwordPlaceholder}
                                autoComplete="current-password"
                                required
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((prev) => !prev)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 rounded px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                                aria-label={showPassword ? t.hide : t.show}
                            >
                                {showPassword ? t.hide : t.show}
                            </button>
                        </div>
                        {errors.password && <p className="text-sm text-red-600">{errors.password}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <span>{processing ? t.signingIn : t.signIn}</span>
                        <ArrowRight className="h-4 w-4" />
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}
