import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) {
        return '—';
    }

    const mins = Math.floor(Number(seconds) / 60);
    const secs = Number(seconds) % 60;

    return `${mins}:${String(secs).padStart(2, '0')}`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export default function Dashboard({
    stats = {
        companies: 0,
        active_employees: 0,
        total_calls: 0,
        calls_completed: 0,
        calls_processing: 0,
        calls_failed: 0,
    },
    recentCalls = [],
}) {
    const cards = [
        { label: 'Companies', value: stats.companies },
        { label: 'Active Employees', value: stats.active_employees },
        { label: 'Total Calls', value: stats.total_calls },
        { label: 'Calls Completed', value: stats.calls_completed },
        { label: 'Calls Processing', value: stats.calls_processing },
        { label: 'Calls Failed', value: stats.calls_failed },
    ];

    return (
        <AdminLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="space-y-6">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {cards.map((card) => (
                        <div key={card.label} className="app-widget p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{card.label}</p>
                            <p className="mt-2 text-2xl font-semibold text-slate-900">{card.value}</p>
                        </div>
                    ))}
                </div>

                <section className="app-widget p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-base font-semibold text-slate-900">Recent Calls</h2>
                        <Link href={route('calls.index')} className="text-sm font-medium text-indigo-700">
                            View all
                        </Link>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Company</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Duration</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {recentCalls.map((call) => (
                                    <tr key={call.id}>
                                        <td className="px-4 py-3 text-slate-700">{call.company_name || '—'}</td>
                                        <td className="px-4 py-3 text-slate-700">{call.employee_name || '—'}</td>
                                        <td className="px-4 py-3 text-slate-700">{call.status}</td>
                                        <td className="px-4 py-3 text-slate-700">{formatDuration(call.duration_seconds)}</td>
                                        <td className="px-4 py-3 text-slate-700">
                                            <Link href={call.show_url || route('calls.show', call.id)} className="text-indigo-700">
                                                {formatDate(call.recorded_at || call.created_at)}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {recentCalls.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-5 text-slate-500" colSpan={5}>
                                            No calls yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}
