import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const DEFAULT_FORM = {
    company_id: '',
    employee_id: '',
    source: 'manual',
    original_filename: '',
    duration_seconds: '',
    status: 'pending',
    recorded_at: '',
};

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

export default function CallsIndex({ calls = [], companies = [], employees = [], statuses = [] }) {
    const [editing, setEditing] = useState(null);
    const createForm = useForm({ ...DEFAULT_FORM });
    const editForm = useForm({ ...DEFAULT_FORM });

    const employeesForCreate = useMemo(
        () => employees.filter((employee) => String(employee.company_id) === String(createForm.data.company_id)),
        [employees, createForm.data.company_id],
    );

    const employeesForEdit = useMemo(
        () => employees.filter((employee) => String(employee.company_id) === String(editForm.data.company_id)),
        [employees, editForm.data.company_id],
    );

    const startEdit = (call) => {
        setEditing(call);
        editForm.setData({
            company_id: call.company_id ?? '',
            employee_id: call.employee_id ?? '',
            source: call.source ?? 'manual',
            original_filename: call.original_filename ?? '',
            duration_seconds: call.duration_seconds ?? '',
            status: call.status ?? 'pending',
            recorded_at: call.recorded_at ? call.recorded_at.slice(0, 16) : '',
        });
        editForm.clearErrors();
    };

    return (
        <AdminLayout title="Calls">
            <Head title="Calls" />

            <div className="space-y-6">
                <section className="app-widget p-4">
                    <h2 className="text-base font-semibold text-slate-900">Create test call</h2>
                    <p className="mt-1 text-sm text-slate-500">Manual record only. File upload is not enabled in this phase.</p>
                    <form
                        className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            createForm.post(route('calls.store'), {
                                preserveScroll: true,
                                onSuccess: () => createForm.setData({ ...DEFAULT_FORM }),
                            });
                        }}
                    >
                        <Select
                            label="Company"
                            value={createForm.data.company_id}
                            error={createForm.errors.company_id}
                            onChange={(v) => createForm.setData({ ...createForm.data, company_id: v, employee_id: '' })}
                            options={[{ value: '', label: 'Select company' }, ...companies.map((c) => ({ value: c.id, label: c.name }))]}
                        />
                        <Select
                            label="Employee"
                            value={createForm.data.employee_id}
                            error={createForm.errors.employee_id}
                            onChange={(v) => createForm.setData('employee_id', v)}
                            options={[{ value: '', label: 'No employee' }, ...employeesForCreate.map((e) => ({ value: e.id, label: e.full_name }))]}
                        />
                        <Field label="Source" value={createForm.data.source} onChange={(v) => createForm.setData('source', v)} error={createForm.errors.source} />
                        <Field label="Filename" value={createForm.data.original_filename} onChange={(v) => createForm.setData('original_filename', v)} error={createForm.errors.original_filename} />
                        <Field label="Duration (seconds)" value={createForm.data.duration_seconds} onChange={(v) => createForm.setData('duration_seconds', v)} error={createForm.errors.duration_seconds} type="number" />
                        <Select
                            label="Status"
                            value={createForm.data.status}
                            error={createForm.errors.status}
                            onChange={(v) => createForm.setData('status', v)}
                            options={statuses.map((status) => ({ value: status, label: status }))}
                        />
                        <Field label="Recorded at" value={createForm.data.recorded_at} onChange={(v) => createForm.setData('recorded_at', v)} error={createForm.errors.recorded_at} type="datetime-local" />
                        <div className="md:col-span-2 flex justify-end">
                            <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                        </div>
                    </form>
                </section>

                <section className="app-widget p-4">
                    <h2 className="text-base font-semibold text-slate-900">Calls list</h2>
                    <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>ID</Th>
                                    <Th>Company</Th>
                                    <Th>Employee</Th>
                                    <Th>Source</Th>
                                    <Th>Filename</Th>
                                    <Th>Status</Th>
                                    <Th>Duration</Th>
                                    <Th>Recorded</Th>
                                    <Th>Created</Th>
                                    <Th>Actions</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {calls.map((call) => (
                                    <tr key={call.id}>
                                        <Td>{call.id}</Td>
                                        <Td>{call.company_name || '—'}</Td>
                                        <Td>{call.employee_name || '—'}</Td>
                                        <Td>{call.source}</Td>
                                        <Td>{call.original_filename || '—'}</Td>
                                        <Td>{call.status}</Td>
                                        <Td>{formatDuration(call.duration_seconds)}</Td>
                                        <Td>{formatDate(call.recorded_at)}</Td>
                                        <Td>{formatDate(call.created_at)}</Td>
                                        <Td>
                                            <div className="flex gap-3">
                                                <button type="button" className="text-indigo-700" onClick={() => startEdit(call)}>Edit</button>
                                                <button
                                                    type="button"
                                                    className="text-red-700"
                                                    onClick={() => {
                                                        if (window.confirm('Delete call?')) {
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
                                        <td className="px-4 py-5 text-slate-500" colSpan={10}>No calls yet.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {editing && (
                <Modal title="Edit call" onClose={() => setEditing(null)}>
                    <form
                        className="grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            editForm.patch(route('calls.update', editing.id), {
                                preserveScroll: true,
                                onSuccess: () => setEditing(null),
                            });
                        }}
                    >
                        <Select
                            label="Company"
                            value={editForm.data.company_id}
                            error={editForm.errors.company_id}
                            onChange={(v) => editForm.setData({ ...editForm.data, company_id: v, employee_id: '' })}
                            options={[{ value: '', label: 'Select company' }, ...companies.map((c) => ({ value: c.id, label: c.name }))]}
                        />
                        <Select
                            label="Employee"
                            value={editForm.data.employee_id}
                            error={editForm.errors.employee_id}
                            onChange={(v) => editForm.setData('employee_id', v)}
                            options={[{ value: '', label: 'No employee' }, ...employeesForEdit.map((e) => ({ value: e.id, label: e.full_name }))]}
                        />
                        <Field label="Source" value={editForm.data.source} onChange={(v) => editForm.setData('source', v)} error={editForm.errors.source} />
                        <Field label="Filename" value={editForm.data.original_filename} onChange={(v) => editForm.setData('original_filename', v)} error={editForm.errors.original_filename} />
                        <Field label="Duration (seconds)" value={editForm.data.duration_seconds} onChange={(v) => editForm.setData('duration_seconds', v)} error={editForm.errors.duration_seconds} type="number" />
                        <Select
                            label="Status"
                            value={editForm.data.status}
                            error={editForm.errors.status}
                            onChange={(v) => editForm.setData('status', v)}
                            options={statuses.map((status) => ({ value: status, label: status }))}
                        />
                        <Field label="Recorded at" value={editForm.data.recorded_at} onChange={(v) => editForm.setData('recorded_at', v)} error={editForm.errors.recorded_at} type="datetime-local" />
                        <div className="md:col-span-2 flex justify-end gap-2">
                            <button type="button" onClick={() => setEditing(null)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancel</button>
                            <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Update</button>
                        </div>
                    </form>
                </Modal>
            )}
        </AdminLayout>
    );
}

function Select({ label, value, onChange, options, error }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">{label}</label>
            <select value={value} onChange={(e) => onChange(e.target.value)} className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                {options.map((option) => (
                    <option key={`${option.value}-${option.label}`} value={option.value}>{option.label}</option>
                ))}
            </select>
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Field({ label, value, onChange, error, type = 'text' }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">{label}</label>
            <input type={type} value={value} onChange={(e) => onChange(e.target.value)} className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Modal({ title, children, onClose }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                    <button type="button" className="text-sm text-slate-500" onClick={onClose}>Close</button>
                </div>
                {children}
            </div>
        </div>
    );
}

function Th({ children }) {
    return <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{children}</th>;
}

function Td({ children }) {
    return <td className="px-4 py-3 text-slate-700">{children}</td>;
}
