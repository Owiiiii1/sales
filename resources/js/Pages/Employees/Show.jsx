import AdminLayout from '@/Layouts/AdminLayout';
import { AnalyticsFilters, AnalyticsSections } from '@/Components/Analytics/Board';
import { Head, Link, router } from '@inertiajs/react';
import { useT } from '@/i18n';

export default function EmployeeShow({ employee, filters = { period: 'last_30' }, analytics = {} }) {
    const t = useT();

    return (
        <AdminLayout title={employee.full_name}>
            <Head title={employee.full_name} />
            <div className="space-y-6">
                <section className="app-widget p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('common.employee')}</p>
                            <h2 className="mt-1 text-xl font-semibold text-slate-900">{employee.full_name}</h2>
                            <p className="mt-2 text-sm text-slate-600">
                                {employee.position || t('employees.noPosition')} · {employee.company_name || t('employees.noCompany')} · {employee.is_active ? t('common.active') : t('common.inactive')}
                            </p>
                        </div>
                        <Link href={route('employees.index')} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">
                            {t('employees.back')}
                        </Link>
                    </div>
                </section>
                <AnalyticsFilters
                    filters={filters}
                    action={(params) => router.get(route('employees.show', employee.id), params, { preserveState: true, preserveScroll: true })}
                />
                <AnalyticsSections analytics={{ ...analytics, hide_public: true }} variant="employee" />
            </div>
        </AdminLayout>
    );
}
