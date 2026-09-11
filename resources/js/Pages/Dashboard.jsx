import AdminLayout from '@/Layouts/AdminLayout';
import { AnalyticsFilters, AnalyticsSections } from '@/Components/Analytics/Board';
import { Head, router } from '@inertiajs/react';
import { useT } from '@/i18n';

export default function Dashboard({
    filters = { period: 'last_30' },
    companies = [],
    employees = [],
    analytics = {},
}) {
    const t = useT();

    return (
        <AdminLayout title={t('nav.dashboard')}>
            <Head title={t('nav.dashboard')} />
            <div className="space-y-6">
                <AnalyticsFilters
                    filters={filters}
                    companies={companies}
                    employees={employees}
                    showCompany
                    showEmployee
                    action={(params) => router.get(route('dashboard'), params, { preserveState: true, preserveScroll: true })}
                />
                <AnalyticsSections analytics={{ ...analytics, hide_public: !!filters.company_id || !!filters.employee_id }} />
            </div>
        </AdminLayout>
    );
}
