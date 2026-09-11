import { router, usePage } from '@inertiajs/react';
import { ChevronDown, Globe } from 'lucide-react';
import { useState } from 'react';
import { useT } from '@/i18n';

const OPTIONS = [
    { value: 'uk', label: 'Українська' },
    { value: 'en', label: 'English' },
    { value: 'ru', label: 'Русский' },
];

export default function LanguageSwitcher({ id = 'language-switcher', compact = false }) {
    const { locale = 'en' } = usePage().props;
    const t = useT();
    const [switching, setSwitching] = useState(false);
    const current = OPTIONS.find((option) => option.value === locale) ?? OPTIONS[1];

    const switchLocale = (nextLocale) => {
        if (!nextLocale || nextLocale === locale || switching) {
            return;
        }

        setSwitching(true);
        router.post(
            route('locale.update'),
            { locale: nextLocale },
            {
                preserveScroll: true,
                onFinish: () => setSwitching(false),
            },
        );
    };

    return (
        <div className="relative">
            <label htmlFor={id} className="sr-only">{t('common.language')}</label>
            <div className={`inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white text-sm font-medium text-slate-700 shadow-sm ${compact ? 'h-9 px-3 pr-8' : 'h-10 px-3 pr-9'}`}>
                <Globe className="h-4 w-4 text-slate-500" />
                <span className={compact ? 'hidden sm:inline' : ''}>{current.label}</span>
                <ChevronDown className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            </div>
            <select
                id={id}
                value={locale}
                disabled={switching}
                onChange={(event) => switchLocale(event.target.value)}
                className={`absolute inset-0 w-full cursor-pointer opacity-0 disabled:cursor-wait ${compact ? 'h-9' : 'h-10'}`}
            >
                {OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                ))}
            </select>
        </div>
    );
}
