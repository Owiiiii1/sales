import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const DEFAULT_FORM = {
    company_id: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    position: '',
    external_id: '',
    is_active: true,
};

export default function EmployeesIndex({ employees = [], companies = [], filters = {} }) {
    const { errors } = usePage().props;
    const [editing, setEditing] = useState(null);
    const createForm = useForm({ ...DEFAULT_FORM });
    const editForm = useForm({ ...DEFAULT_FORM });

    const startEdit = (employee) => {
        setEditing(employee);
        editForm.setData({
            company_id: employee.company_id ?? '',
            first_name: employee.first_name ?? '',
            last_name: employee.last_name ?? '',
            email: employee.email ?? '',
            phone: employee.phone ?? '',
            position: employee.position ?? '',
            external_id: employee.external_id ?? '',
            is_active: !!employee.is_active,
        });
        editForm.clearErrors();
    };

    return (
        <AdminLayout title="Employees">
            <Head title="Employees" />

            <div className="space-y-6">
                {errors?.employee && <p className="text-sm text-red-600">{errors.employee}</p>}

                <section className="app-widget p-4">
                    <h2 className="text-base font-semibold text-slate-900">Create employee</h2>
                    <form
                        className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            createForm.post(route('employees.store'), {
                                preserveScroll: true,
                                onSuccess: () => createForm.setData({ ...DEFAULT_FORM }),
                            });
                        }}
                    >
                        <CompanySelect
                            value={createForm.data.company_id}
                            companies={companies}
                            error={createForm.errors.company_id}
                            onChange={(v) => createForm.setData('company_id', v)}
                        />
                        <Field label="First name" value={createForm.data.first_name} onChange={(v) => createForm.setData('first_name', v)} error={createForm.errors.first_name} />
                        <Field label="Last name" value={createForm.data.last_name} onChange={(v) => createForm.setData('last_name', v)} error={createForm.errors.last_name} />
                        <Field label="Email" value={createForm.data.email} onChange={(v) => createForm.setData('email', v)} error={createForm.errors.email} type="email" />
                        <Field label="Phone" value={createForm.data.phone} onChange={(v) => createForm.setData('phone', v)} error={createForm.errors.phone} />
                        <Field label="Position" value={createForm.data.position} onChange={(v) => createForm.setData('position', v)} error={createForm.errors.position} />
                        <Field label="External ID" value={createForm.data.external_id} onChange={(v) => createForm.setData('external_id', v)} error={createForm.errors.external_id} />
                        <div className="md:col-span-2 flex justify-end">
                            <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                        </div>
                    </form>
                </section>

                <section className="app-widget p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 className="text-base font-semibold text-slate-900">Employees list</h2>
                        <select
                            value={filters.company_id ?? ''}
                            onChange={(e) => {
                                const value = e.target.value;
                                router.get(route('employees.index'), value ? { company_id: value } : {}, {
                                    preserveState: true,
                                    preserveScroll: true,
                                });
                            }}
                            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                        >
                            <option value="">All companies</option>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>{company.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Name</Th>
                                    <Th>Company</Th>
                                    <Th>Position</Th>
                                    <Th>Email</Th>
                                    <Th>Status</Th>
                                    <Th>Actions</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {employees.map((employee) => (
                                    <tr key={employee.id}>
                                        <Td>{employee.full_name}</Td>
                                        <Td>{employee.company_name || '—'}</Td>
                                        <Td>{employee.position || '—'}</Td>
                                        <Td>{employee.email || '—'}</Td>
                                        <Td>{employee.is_active ? 'active' : 'inactive'}</Td>
                                        <Td>
                                            <div className="flex flex-wrap gap-3">
                                                <Link href={route('employees.show', employee.id)} className="text-indigo-700">View</Link>
                                                <button type="button" className="text-indigo-700" onClick={() => startEdit(employee)}>Edit</button>
                                                <button
                                                    type="button"
                                                    className="text-slate-700"
                                                    onClick={() => router.patch(route('employees.toggle', employee.id), {}, { preserveScroll: true })}
                                                >
                                                    {employee.is_active ? 'Deactivate' : 'Activate'}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="text-red-700"
                                                    onClick={() => {
                                                        if (window.confirm('Delete employee?')) {
                                                            router.delete(route('employees.destroy', employee.id), { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                                {employees.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-5 text-slate-500" colSpan={6}>No employees yet.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {editing && (
                <Modal title="Edit employee" onClose={() => setEditing(null)}>
                    <form
                        className="grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            editForm.patch(route('employees.update', editing.id), {
                                preserveScroll: true,
                                onSuccess: () => setEditing(null),
                            });
                        }}
                    >
                        <CompanySelect
                            value={editForm.data.company_id}
                            companies={companies}
                            error={editForm.errors.company_id}
                            onChange={(v) => editForm.setData('company_id', v)}
                        />
                        <Field label="First name" value={editForm.data.first_name} onChange={(v) => editForm.setData('first_name', v)} error={editForm.errors.first_name} />
                        <Field label="Last name" value={editForm.data.last_name} onChange={(v) => editForm.setData('last_name', v)} error={editForm.errors.last_name} />
                        <Field label="Email" value={editForm.data.email} onChange={(v) => editForm.setData('email', v)} error={editForm.errors.email} type="email" />
                        <Field label="Phone" value={editForm.data.phone} onChange={(v) => editForm.setData('phone', v)} error={editForm.errors.phone} />
                        <Field label="Position" value={editForm.data.position} onChange={(v) => editForm.setData('position', v)} error={editForm.errors.position} />
                        <Field label="External ID" value={editForm.data.external_id} onChange={(v) => editForm.setData('external_id', v)} error={editForm.errors.external_id} />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-600">Status</label>
                            <select
                                value={editForm.data.is_active ? '1' : '0'}
                                onChange={(e) => editForm.setData('is_active', e.target.value === '1')}
                                className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                            >
                                <option value="1">active</option>
                                <option value="0">inactive</option>
                            </select>
                        </div>
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

function CompanySelect({ value, companies, onChange, error }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">Company</label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >
                <option value="">Select company</option>
                {companies.map((company) => (
                    <option key={company.id} value={company.id}>{company.name}</option>
                ))}
            </select>
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Modal({ title, children, onClose }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div className="w-full max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
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

function Field({ label, value, onChange, error, type = 'text' }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">{label}</label>
            <input type={type} value={value} onChange={(e) => onChange(e.target.value)} className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm" />
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
