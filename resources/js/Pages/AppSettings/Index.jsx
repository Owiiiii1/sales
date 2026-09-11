import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { useT } from '@/i18n';

export default function AppSettingsIndex() {
    const t = useT();

    return (
        <AdminLayout title={t('appSettings.title')}>
            <Head title={t('appSettings.title')} />
            <div className="app-widget p-4">
                <p className="text-sm text-slate-700">{t('appSettings.body')}</p>
            </div>
        </AdminLayout>
    );
}
