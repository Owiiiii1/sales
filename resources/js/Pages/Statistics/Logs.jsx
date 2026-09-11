import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { useT } from '@/i18n';

export default function StatisticsLogs() {
    const t = useT();

    return (
        <AdminLayout title={t('logs.title')}>
            <Head title={t('logs.title')} />
            <div className="app-widget p-4">
                <p className="text-sm text-slate-700">{t('logs.body')}</p>
            </div>
        </AdminLayout>
    );
}
