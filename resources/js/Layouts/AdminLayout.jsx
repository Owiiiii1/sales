import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    CalendarDays,
    ChartColumn,
    Contact,
    FileText,
    Globe,
    Home,
    LayoutGrid,
    LogOut,
    Settings,
    UserCircle2,
    Users,
    Wrench,
    Menu,
} from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

const primaryNavItems = [
    { route: 'dashboard', icon: Home },
    { route: 'customers.index', icon: Contact, activePattern: 'customers.*' },
    { route: 'orders.index', icon: LayoutGrid, activePattern: 'orders.*' },
    { route: 'calendar.index', icon: CalendarDays },
    { route: 'staff.index', icon: Users },
    { route: 'services.index', icon: Wrench, activePattern: 'services.*' },
];

const languageLabels = {
    uk: 'Українська',
    en: 'English',
    ru: 'Русский',
};

function navLabel(routeName, t) {
    if (routeName === 'dashboard') return t.home;
    if (routeName === 'customers.index') return t.customers;
    if (routeName === 'orders.index') return t.orders;
    if (routeName === 'calendar.index') return t.calendar;
    if (routeName === 'staff.index') return t.staff;
    if (routeName === 'services.index') return t.services;
    return routeName;
}

export default function AdminLayout({ title, children }) {
    const { auth, locale = 'en', owlAdmin = {} } = usePage().props;
    const user = auth?.user;
    const brandName = owlAdmin?.brand_name ?? 'Service Admin';
    const logoPath = owlAdmin?.logo_path ?? '/images/company-logo.svg';

    const ai = owlAdmin?.ai ?? {};
    const aiConnected = !!ai.connected;
    const aiBadgeText = ai?.status_label
        ?? (aiConnected
            ? `AI: connected — ${ai.provider_label ?? ai.provider ?? 'Unknown'} / ${ai.model ?? 'unknown'}`
            : 'AI: not connected');

    const telegram = owlAdmin?.telegram ?? {};
    const telegramStatus = telegram.status;
    let telegramBadgeText = 'Bot: not connected';
    let telegramBadgeClass = 'bg-red-100 text-red-700';

    if (telegramStatus === 'connected') {
        telegramBadgeText = telegram.status_label
            ?? `Bot: connected — @${telegram.bot_username ?? ''}`;
        telegramBadgeClass = 'bg-emerald-100 text-emerald-700';
    } else if (telegramStatus === 'incomplete') {
        telegramBadgeText = telegram.status_label ?? 'Bot: incomplete';
        telegramBadgeClass = 'bg-amber-100 text-amber-800';
    } else if (telegram.status_label) {
        telegramBadgeText = telegram.status_label;
    }

    const [statisticsOpen, setStatisticsOpen] = useState(route().current('statistics.*'));
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [mobileStatisticsOpen, setMobileStatisticsOpen] = useState(
        route().current('statistics.*'),
    );
    const [localeSwitching, setLocaleSwitching] = useState(false);

    const uiText = {
        en: {
            home: 'Home',
            customers: 'Customers',
            orders: 'Orders',
            calendar: 'Calendar',
            staff: 'Staff',
            services: 'Services',
            settings: 'Settings',
            logout: 'Logout',
            statistics: 'Statistics',
            logs: 'Logs',
            adminPanel: 'Admin Panel',
            profile: 'Profile',
            language: 'Language',
        },
        ru: {
            home: 'Главная',
            customers: 'Клиенты',
            orders: 'Заказы',
            calendar: 'Календарь',
            staff: 'Персонал',
            services: 'Услуги',
            settings: 'Настройки',
            logout: 'Выход',
            statistics: 'Статистика',
            logs: 'Логи',
            adminPanel: 'Панель администратора',
            profile: 'Профиль',
            language: 'Язык',
        },
        uk: {
            home: 'Головна',
            customers: 'Клієнти',
            orders: 'Замовлення',
            calendar: 'Календар',
            staff: 'Персонал',
            services: 'Послуги',
            settings: 'Налаштування',
            logout: 'Вийти',
            statistics: 'Статистика',
            logs: 'Логи',
            adminPanel: 'Панель адміністратора',
            profile: 'Профіль',
            language: 'Мова',
        },
    };

    const t = uiText[locale] ?? uiText.en;
    const currentLanguageLabel = languageLabels[locale] ?? languageLabels.en;

    const settingsActive =
        route().current('settings.*')
        || route().current('ai-settings.*')
        || route().current('app-settings.*');

    const switchLocale = (nextLocale) => {
        if (!nextLocale || nextLocale === locale || localeSwitching) {
            return;
        }

        setLocaleSwitching(true);
        router.post(
            route('settings.language.update'),
            { locale: nextLocale },
            {
                preserveScroll: true,
                onFinish: () => setLocaleSwitching(false),
            },
        );
    };

    const renderPrimaryNav = (mobile = false) =>
        primaryNavItems.map(({ route: routeName, icon: Icon, activePattern }) => {
            const active = activePattern
                ? route().current(activePattern)
                : route().current(routeName);

            return (
                <Link
                    key={`${mobile ? 'mobile-' : ''}${routeName}`}
                    href={route(routeName)}
                    onClick={() => mobile && setMobileMenuOpen(false)}
                    className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ${
                        active
                            ? 'border-l-2 border-white bg-white/10 text-white'
                            : 'text-slate-400 hover:bg-white/5 hover:text-white'
                    }`}
                >
                    <Icon className="h-4 w-4" />
                    <span>{navLabel(routeName, t)}</span>
                </Link>
            );
        });

    const renderStatistics = (mobile = false) => {
        const open = mobile ? mobileStatisticsOpen : statisticsOpen;
        const setOpen = mobile ? setMobileStatisticsOpen : setStatisticsOpen;

        return (
            <div>
                <button
                    type="button"
                    onClick={() => setOpen((prev) => !prev)}
                    className={`flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition ${
                        route().current('statistics.*')
                            ? 'border-l-2 border-white bg-white/10 text-white'
                            : 'text-slate-400 hover:bg-white/5 hover:text-white'
                    }`}
                >
                    <span className="flex items-center gap-3">
                        <ChartColumn className="h-4 w-4" />
                        <span>{t.statistics}</span>
                    </span>
                    <ChevronDown
                        className={`h-4 w-4 transition ${open ? 'rotate-180' : ''}`}
                    />
                </button>

                {open && (
                    <div className="mt-1 space-y-1 pl-4">
                        <Link
                            href={route('statistics.logs')}
                            onClick={() => mobile && setMobileMenuOpen(false)}
                            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ${
                                route().current('statistics.logs')
                                    ? 'bg-white/10 text-white'
                                    : 'text-slate-400 hover:bg-white/5 hover:text-white'
                            }`}
                        >
                            <FileText className="h-4 w-4" />
                            <span>{t.logs}</span>
                        </Link>
                    </div>
                )}
            </div>
        );
    };

    const renderSettingsLink = (mobile = false) => (
        <Link
            href={route('settings.index')}
            onClick={() => mobile && setMobileMenuOpen(false)}
            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition ${
                settingsActive
                    ? 'border-l-2 border-white bg-white/10 text-white'
                    : 'text-slate-400 hover:bg-white/5 hover:text-white'
            }`}
        >
            <Settings className="h-4 w-4" />
            <span>{t.settings}</span>
        </Link>
    );

    const poweredBy = (
        <div className="mt-auto border-t border-white/10 pt-4">
            <p className="px-3 text-xs text-slate-500">
                Powered by{' '}
                <a
                    href="https://owlsolutions.net"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="font-medium text-slate-300 transition hover:text-white"
                >
                    OwlSolutions
                </a>
            </p>
        </div>
    );

    return (
        <div className="h-screen overflow-hidden bg-slate-50 text-slate-900">
            <div className="flex h-screen">
                <aside className="fixed inset-y-0 left-0 hidden w-72 flex-col bg-slate-900 text-white shadow-2xl lg:flex">
                    <div className="px-6 py-6">
                        <div className="flex items-center gap-3">
                            <img
                                src={logoPath}
                                alt={brandName}
                                className="h-10 w-10 rounded-xl"
                            />
                            <div>
                                <p className="text-base font-semibold text-white">{brandName}</p>
                            </div>
                        </div>
                    </div>

                    <nav className="flex h-full flex-1 flex-col px-4 pb-6">
                        <div className="space-y-1.5">
                            {renderPrimaryNav(false)}

                            <div className="my-3 border-t border-white/10" />

                            {renderStatistics(false)}
                            {renderSettingsLink(false)}
                        </div>

                        {poweredBy}
                    </nav>
                </aside>

                <Sheet open={mobileMenuOpen} onOpenChange={setMobileMenuOpen}>
                    <SheetContent
                        side="left"
                        className="w-80 border-r-0 bg-slate-900 p-0 text-white data-[side=left]:w-80 data-[side=left]:sm:max-w-80"
                    >
                        <SheetHeader className="border-b border-white/10 px-6 py-6 text-left">
                            <SheetTitle className="text-base font-semibold text-white">
                                {brandName}
                            </SheetTitle>
                        </SheetHeader>

                        <nav className="flex h-full flex-1 flex-col px-4 pb-6 pt-4">
                            <div className="space-y-1.5">
                                {renderPrimaryNav(true)}

                                <div className="my-3 border-t border-white/10" />

                                {renderStatistics(true)}
                                {renderSettingsLink(true)}
                            </div>

                            {poweredBy}
                        </nav>
                    </SheetContent>
                </Sheet>

                <div className="flex min-w-0 flex-1 flex-col overflow-hidden lg:pl-72">
                    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/80 backdrop-blur-xl">
                        <div className="flex h-16 items-center justify-between px-4 sm:px-10">
                            <div className="flex items-center gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="h-9 w-9 lg:hidden"
                                    onClick={() => setMobileMenuOpen(true)}
                                    aria-label="Open menu"
                                >
                                    <Menu className="h-4 w-4" />
                                </Button>
                                <div className="text-sm font-semibold uppercase tracking-wide text-slate-700">
                                    {t.adminPanel}
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <span
                                    className={`hidden max-w-[280px] truncate rounded-full px-3 py-1 text-xs font-semibold lg:inline-flex ${
                                        aiConnected
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-red-100 text-red-700'
                                    }`}
                                    title={aiBadgeText}
                                >
                                    {aiBadgeText}
                                </span>

                                <span
                                    className={`hidden max-w-[280px] truncate rounded-full px-3 py-1 text-xs font-semibold md:inline-flex ${telegramBadgeClass}`}
                                    title={telegramBadgeText}
                                >
                                    {telegramBadgeText}
                                </span>

                                <div className="relative">
                                    <label htmlFor="admin-header-language" className="sr-only">
                                        {t.language}
                                    </label>
                                    <div className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 pr-8 text-sm font-medium text-slate-700 shadow-sm">
                                        <Globe className="h-4 w-4 text-slate-500" />
                                        <span className="hidden sm:inline">{currentLanguageLabel}</span>
                                        <ChevronDown className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                    <select
                                        id="admin-header-language"
                                        value={locale}
                                        disabled={localeSwitching}
                                        onChange={(e) => switchLocale(e.target.value)}
                                        className="absolute inset-0 h-9 w-full cursor-pointer opacity-0 disabled:cursor-wait"
                                    >
                                        <option value="uk">Українська</option>
                                        <option value="en">English</option>
                                        <option value="ru">Русский</option>
                                    </select>
                                </div>

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className="inline-flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-slate-700 outline-none transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-indigo-200"
                                            aria-label={t.profile}
                                        >
                                            {user?.avatar_path ? (
                                                <img
                                                    src={`/storage/${user.avatar_path}`}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <UserCircle2 className="h-5 w-5" />
                                            )}
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="min-w-40">
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href={route('profile.edit')}
                                                className="cursor-pointer"
                                            >
                                                <UserCircle2 className="h-4 w-4" />
                                                <span>{t.profile}</span>
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href={route('logout')}
                                                method="post"
                                                as="button"
                                                className="w-full cursor-pointer"
                                            >
                                                <LogOut className="h-4 w-4" />
                                                <span>{t.logout}</span>
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </header>

                    <main className="flex-1 overflow-y-auto p-4 sm:p-10">
                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h1 className="text-xl font-semibold text-slate-900">{title}</h1>
                            <div className="mt-4">{children}</div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    );
}
