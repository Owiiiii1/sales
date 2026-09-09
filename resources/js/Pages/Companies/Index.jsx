import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const DEFAULT_FORM = {
    name: '',
    legal_name: '',
    website: '',
    industry: '',
    description: '',
    country: '',
    city: '',
    phone: '',
    email: '',
    is_active: true,
};

export default function CompaniesIndex({ companies = [] }) {
    const { errors } = usePage().props;
    const [editing, setEditing] = useState(null);
    const createForm = useForm({ ...DEFAULT_FORM });
    const editForm = useForm({ ...DEFAULT_FORM });

    const startEdit = (company) => {
        setEditing(company);
        editForm.setData({
            name: company.name ?? '',
            legal_name: company.legal_name ?? '',
            website: company.website ?? '',
            industry: company.industry ?? '',
            description: company.description ?? '',
            country: company.country ?? '',
            city: company.city ?? '',
            phone: company.phone ?? '',
            email: company.email ?? '',
            is_active: !!company.is_active,
        });
        editForm.clearErrors();
    };

    return (
        <AdminLayout title="Companies">
            <Head title="Companies" />

            <div className="space-y-6">
                {errors?.company && <p className="text-sm text-red-600">{errors.company}</p>}

                <section className="app-widget p-4">
                    <h2 className="text-base font-semibold text-slate-900">Create company</h2>
                    <form
                        className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            createForm.post(route('companies.store'), {
                                preserveScroll: true,
                                onSuccess: () => createForm.setData({ ...DEFAULT_FORM }),
                            });
                        }}
                    >
                        <Field label="Name" value={createForm.data.name} onChange={(v) => createForm.setData('name', v)} error={createForm.errors.name} />
                        <Field label="Legal name" value={createForm.data.legal_name} onChange={(v) => createForm.setData('legal_name', v)} error={createForm.errors.legal_name} />
                        <Field label="Website" value={createForm.data.website} onChange={(v) => createForm.setData('website', v)} error={createForm.errors.website} />
                        <Field label="Industry" value={createForm.data.industry} onChange={(v) => createForm.setData('industry', v)} error={createForm.errors.industry} />
                        <Field label="Country" value={createForm.data.country} onChange={(v) => createForm.setData('country', v)} error={createForm.errors.country} />
                        <Field label="City" value={createForm.data.city} onChange={(v) => createForm.setData('city', v)} error={createForm.errors.city} />
                        <Field label="Phone" value={createForm.data.phone} onChange={(v) => createForm.setData('phone', v)} error={createForm.errors.phone} />
                        <Field label="Email" value={createForm.data.email} onChange={(v) => createForm.setData('email', v)} error={createForm.errors.email} type="email" />
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-600">Description</label>
                            <textarea
                                value={createForm.data.description}
                                onChange={(e) => createForm.setData('description', e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            {createForm.errors.description && <p className="mt-1 text-sm text-red-600">{createForm.errors.description}</p>}
                        </div>
                        <div className="md:col-span-2 flex justify-end">
                            <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">
                                Save
                            </button>
                        </div>
                    </form>
                </section>

                <section className="app-widget p-4">
                    <h2 className="text-base font-semibold text-slate-900">Companies list</h2>
                    <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Name</Th>
                                    <Th>Industry</Th>
                                    <Th>Email</Th>
                                    <Th>Status</Th>
                                    <Th>Employees</Th>
                                    <Th>Actions</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {companies.map((company) => (
                                    <tr key={company.id}>
                                        <Td>{company.name}</Td>
                                        <Td>{company.industry || '—'}</Td>
                                        <Td>{company.email || '—'}</Td>
                                        <Td>{company.is_active ? 'active' : 'inactive'}</Td>
                                        <Td>{company.employees_count}</Td>
                                        <Td>
                                            <div className="flex flex-wrap gap-3">
                                                <button type="button" className="text-indigo-700" onClick={() => startEdit(company)}>Edit</button>
                                                <button
                                                    type="button"
                                                    className="text-slate-700"
                                                    onClick={() => router.patch(route('companies.toggle', company.id), {}, { preserveScroll: true })}
                                                >
                                                    {company.is_active ? 'Deactivate' : 'Activate'}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="text-red-700"
                                                    onClick={() => {
                                                        if (window.confirm('Delete company?')) {
                                                            router.delete(route('companies.destroy', company.id), { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                                {companies.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-5 text-slate-500" colSpan={6}>No companies yet.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {editing && (
                <Modal title="Edit company" onClose={() => setEditing(null)}>
                    <form
                        className="grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            editForm.patch(route('companies.update', editing.id), {
                                preserveScroll: true,
                                onSuccess: () => setEditing(null),
                            });
                        }}
                    >
                        <Field label="Name" value={editForm.data.name} onChange={(v) => editForm.setData('name', v)} error={editForm.errors.name} />
                        <Field label="Legal name" value={editForm.data.legal_name} onChange={(v) => editForm.setData('legal_name', v)} error={editForm.errors.legal_name} />
                        <Field label="Website" value={editForm.data.website} onChange={(v) => editForm.setData('website', v)} error={editForm.errors.website} />
                        <Field label="Industry" value={editForm.data.industry} onChange={(v) => editForm.setData('industry', v)} error={editForm.errors.industry} />
                        <Field label="Country" value={editForm.data.country} onChange={(v) => editForm.setData('country', v)} error={editForm.errors.country} />
                        <Field label="City" value={editForm.data.city} onChange={(v) => editForm.setData('city', v)} error={editForm.errors.city} />
                        <Field label="Phone" value={editForm.data.phone} onChange={(v) => editForm.setData('phone', v)} error={editForm.errors.phone} />
                        <Field label="Email" value={editForm.data.email} onChange={(v) => editForm.setData('email', v)} error={editForm.errors.email} type="email" />
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-600">Description</label>
                            <textarea
                                value={editForm.data.description}
                                onChange={(e) => editForm.setData('description', e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
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
            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            />
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
