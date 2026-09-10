import AdminLayout from '@/Layouts/AdminLayout';
import { AnalyticsFilters, AnalyticsSections } from '@/Components/Analytics/Board';
import { Head, router } from '@inertiajs/react';

export default function Dashboard({
    filters = { period: 'last_30' },
    companies = [],
    employees = [],
    analytics = {},
}) {
    return (
        <AdminLayout title="Dashboard">
            <Head title="Dashboard" />
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
