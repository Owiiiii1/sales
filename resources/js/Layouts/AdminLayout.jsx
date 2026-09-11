import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Home,
    LogOut,
    Phone,
    Settings,
    UserCircle2,
    Users,
    Menu,
} from 'lucide-react';
import { useState } from 'react';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
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
import { useT } from '@/i18n';

const primaryNavItems = [
    { route: 'dashboard', icon: Home, labelKey: 'nav.dashboard' },
    { route: 'companies.index', icon: Building2, activePattern: 'companies.*', labelKey: 'nav.companies' },
    { route: 'employees.index', icon: Users, activePattern: 'employees.*', labelKey: 'nav.employees' },
    { route: 'calls.index', icon: Phone, activePattern: 'calls.*', labelKey: 'nav.calls' },
];

export default function AdminLayout({ title, children }) {
    const { auth, owlAdmin = {} } = usePage().props;
    const t = useT();
    const user = auth?.user;
    const brandName = owlAdmin?.brand_name ?? 'Service Admin';
    const logoPath = owlAdmin?.logo_path ?? '/images/company-logo.svg';

    const ai = owlAdmin?.ai ?? {};
    const aiConnected = !!ai.connected;
    const aiBadgeText = aiConnected
        ? t('header.aiConnected', {
            provider: ai.provider_label ?? ai.provider ?? t('common.unknown'),
            model: ai.model ?? t('common.unknown'),
        })
        : t('header.aiDisconnected');

    const transcription = owlAdmin?.transcription ?? {};
    const transcriptionConnected = !!transcription.connected;
    const modelLabel = transcription.model === 'scribe_v2' ? 'Scribe v2' : (transcription.model || t('common.unknown'));
    const transcriptionBadgeText = transcriptionConnected
        ? t('header.sttConnected', { model: modelLabel })
        : t('header.sttDisconnected');

    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const settingsActive =
        route().current('settings.*')
        || route().current('ai-settings.*')
        || route().current('app-settings.*');

    const renderPrimaryNav = (mobile = false) =>
        primaryNavItems.map(({ route: routeName, icon: Icon, activePattern, labelKey }) => {
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
                    <span>{t(labelKey)}</span>
                </Link>
            );
        });

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
            <span>{t('nav.settings')}</span>
        </Link>
    );

    const poweredBy = (
        <div className="border-t border-white/10 pt-4">
            <Link
                href={route('home')}
                className="mb-3 block px-3 text-sm font-medium text-slate-300 transition hover:text-white"
            >
                {t('nav.backToPublic')}
            </Link>
            <p className="px-3 text-xs text-slate-500">
                {t('common.poweredBy')}{' '}
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
                        </div>

                        <div className="mt-auto space-y-1.5">
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
                            </div>

                            <div className="mt-auto space-y-1.5">
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
                                    aria-label={t('common.openMenu')}
                                >
                                    <Menu className="h-4 w-4" />
                                </Button>
                                <div className="text-sm font-semibold uppercase tracking-wide text-slate-700">
                                    {t('nav.adminPanel')}
                                </div>
                                <Link
                                    href={route('home')}
                                    className="text-sm font-medium text-indigo-700 hover:text-indigo-600"
                                >
                                    {t('nav.backToPublic')}
                                </Link>
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
                                    className={`hidden max-w-[280px] truncate rounded-full px-3 py-1 text-xs font-semibold md:inline-flex ${
                                        transcriptionConnected
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-red-100 text-red-700'
                                    }`}
                                    title={transcriptionBadgeText}
                                >
                                    {transcriptionBadgeText}
                                </span>

                                <LanguageSwitcher id="admin-header-language" compact />

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className="inline-flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-slate-700 outline-none transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-indigo-200"
                                            aria-label={t('nav.profile')}
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
                                                <span>{t('nav.profile')}</span>
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
                                                <span>{t('nav.logout')}</span>
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
