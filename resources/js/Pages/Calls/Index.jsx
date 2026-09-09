import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

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

function statusClass(status) {
    if (status === 'completed' || status === 'transcribed' || status === 'analysis_pending') return 'bg-emerald-100 text-emerald-800';
    if (status === 'uploaded') return 'bg-indigo-100 text-indigo-800';
    if (status === 'processing' || status === 'analyzing') return 'bg-amber-100 text-amber-800';
    if (status === 'failed') return 'bg-red-100 text-red-800';
    return 'bg-slate-100 text-slate-700';
}

export default function CallsIndex({
    calls = [],
    companies = [],
    employees = [],
    statuses = [],
    filters = {},
}) {
    const { errors } = usePage().props;
    const employeesForFilter = employees.filter((employee) => (
        !filters.company_id || String(employee.company_id) === String(filters.company_id)
    ));

    const applyFilters = (next) => {
        const query = {};
        if (next.company_id) query.company_id = next.company_id;
        if (next.employee_id) query.employee_id = next.employee_id;
        if (next.status) query.status = next.status;
        router.get(route('calls.index'), query, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Calls">
            <Head title="Calls" />

            <div className="space-y-6">
                {errors?.call && <p className="text-sm text-red-600">{errors.call}</p>}

                <section className="app-widget p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-base font-semibold text-slate-900">Calls</h2>
                            <p className="mt-1 text-sm text-slate-500">Upload audio to transcribe and analyze a sales call.</p>
                        </div>
                        <Link
                            href={route('calls.create')}
                            className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white"
                        >
                            Upload Call
                        </Link>
                    </div>

                    <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <select
                            value={filters.company_id ?? ''}
                            onChange={(e) => applyFilters({ ...filters, company_id: e.target.value, employee_id: '' })}
                            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                        >
                            <option value="">All companies</option>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>{company.name}</option>
                            ))}
                        </select>
                        <select
                            value={filters.employee_id ?? ''}
                            onChange={(e) => applyFilters({ ...filters, employee_id: e.target.value })}
                            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                        >
                            <option value="">All employees</option>
                            {employeesForFilter.map((employee) => (
                                <option key={employee.id} value={employee.id}>{employee.full_name}</option>
                            ))}
                        </select>
                        <select
                            value={filters.status ?? ''}
                            onChange={(e) => applyFilters({ ...filters, status: e.target.value })}
                            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                        >
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>{status}</option>
                            ))}
                        </select>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>ID</Th>
                                    <Th>Company</Th>
                                    <Th>Employee</Th>
                                    <Th>Filename</Th>
                                    <Th>Status</Th>
                                    <Th>Duration</Th>
                                    <Th>File size</Th>
                                    <Th>Recorded</Th>
                                    <Th>Created</Th>
                                    <Th>Actions</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {calls.map((call) => (
                                    <tr key={call.id} className="hover:bg-slate-50">
                                        <Td>{call.id}</Td>
                                        <Td>{call.company_name || '—'}</Td>
                                        <Td>{call.employee_name || '—'}</Td>
                                        <Td>{call.original_filename || '—'}</Td>
                                        <Td>
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${statusClass(call.status)}`}>
                                                {call.status}
                                            </span>
                                        </Td>
                                        <Td>{formatDuration(call.duration_seconds)}</Td>
                                        <Td>{call.file_size_label || '—'}</Td>
                                        <Td>{formatDate(call.recorded_at)}</Td>
                                        <Td>{formatDate(call.created_at)}</Td>
                                        <Td>
                                            <div className="flex gap-3">
                                                <Link href={route('calls.show', call.id)} className="text-indigo-700">View</Link>
                                                <button
                                                    type="button"
                                                    className="text-red-700"
                                                    onClick={() => {
                                                        if (window.confirm('Delete this call and its audio file?')) {
                                                            router.delete(route('calls.destroy', call.id), { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                                {calls.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-8 text-center text-slate-500" colSpan={10}>
                                            No calls uploaded yet
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

function Th({ children }) {
    return <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{children}</th>;
}

function Td({ children }) {
    return <td className="px-4 py-3 text-slate-700">{children}</td>;
}
