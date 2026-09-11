import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useT } from '@/i18n';

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
    const t = useT();
    const { errors } = usePage().props;
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const createForm = useForm({ ...DEFAULT_FORM });
    const editForm = useForm({ ...DEFAULT_FORM });

    const openCreate = () => {
        createForm.setData({
            ...DEFAULT_FORM,
            company_id: filters.company_id ?? '',
        });
        createForm.clearErrors();
        setCreating(true);
    };

    const closeCreate = () => {
        setCreating(false);
        createForm.setData({ ...DEFAULT_FORM });
        createForm.clearErrors();
    };

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
        <AdminLayout title={t('employees.title')}>
            <Head title={t('employees.title')} />

            <div className="space-y-6">
                {errors?.employee && <p className="text-sm text-red-600">{errors.employee}</p>}

                <section className="app-widget p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 className="text-base font-semibold text-slate-900">{t('employees.title')}</h2>
                        <div className="flex flex-wrap items-center gap-2">
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
                                <option value="">{t('companies.allCompanies')}</option>
                                {companies.map((company) => (
                                    <option key={company.id} value={company.id}>{company.name}</option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={openCreate}
                                className="inline-flex h-10 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700"
                            >
                                <Plus className="h-4 w-4" />
                                {t('common.create')}
                            </button>
                        </div>
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>{t('common.name')}</Th>
                                    <Th>{t('common.company')}</Th>
                                    <Th>{t('common.position')}</Th>
                                    <Th>{t('common.email')}</Th>
                                    <Th>{t('common.status')}</Th>
                                    <Th>{t('common.actions')}</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {employees.map((employee) => (
                                    <tr
                                        key={employee.id}
                                        className="cursor-pointer hover:bg-slate-50/80"
                                        onClick={() => router.visit(route('employees.show', employee.id))}
                                    >
                                        <Td>{employee.full_name}</Td>
                                        <Td>{employee.company_name || t('common.dash')}</Td>
                                        <Td>{employee.position || t('common.dash')}</Td>
                                        <Td>{employee.email || t('common.dash')}</Td>
                                        <Td>{employee.is_active ? t('common.active') : t('common.inactive')}</Td>
                                        <Td>
                                            <div className="flex flex-wrap gap-3" onClick={(event) => event.stopPropagation()}>
                                                <button type="button" className="text-indigo-700" onClick={() => startEdit(employee)}>{t('common.edit')}</button>
                                                <button
                                                    type="button"
                                                    className="text-slate-700"
                                                    onClick={() => router.patch(route('employees.toggle', employee.id), {}, { preserveScroll: true })}
                                                >
                                                    {employee.is_active ? t('common.deactivate') : t('common.activate')}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="text-red-700"
                                                    onClick={() => {
                                                        if (window.confirm(t('employees.deleteConfirm'))) {
                                                            router.delete(route('employees.destroy', employee.id), { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    {t('common.delete')}
                                                </button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                                {employees.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-5 text-slate-500" colSpan={6}>{t('employees.empty')}</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {creating && (
                <Modal title={t('employees.createTitle')} onClose={closeCreate}>
                    <form
                        className="grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            createForm.post(route('employees.store'), {
                                preserveScroll: true,
                                onSuccess: closeCreate,
                            });
                        }}
                    >
                        <CompanySelect
                            value={createForm.data.company_id}
                            companies={companies}
                            error={createForm.errors.company_id}
                            onChange={(v) => createForm.setData('company_id', v)}
                        />
                        <Field label={t('employees.firstName')} value={createForm.data.first_name} onChange={(v) => createForm.setData('first_name', v)} error={createForm.errors.first_name} />
                        <Field label={t('employees.lastName')} value={createForm.data.last_name} onChange={(v) => createForm.setData('last_name', v)} error={createForm.errors.last_name} />
                        <Field label={t('common.email')} value={createForm.data.email} onChange={(v) => createForm.setData('email', v)} error={createForm.errors.email} type="email" />
                        <Field label={t('common.phone')} value={createForm.data.phone} onChange={(v) => createForm.setData('phone', v)} error={createForm.errors.phone} />
                        <Field label={t('common.position')} value={createForm.data.position} onChange={(v) => createForm.setData('position', v)} error={createForm.errors.position} />
                        <Field label={t('employees.externalId')} value={createForm.data.external_id} onChange={(v) => createForm.setData('external_id', v)} error={createForm.errors.external_id} />
                        <div className="md:col-span-2 flex justify-end gap-2">
                            <button type="button" onClick={closeCreate} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">{t('common.cancel')}</button>
                            <button type="submit" disabled={createForm.processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{t('common.create')}</button>
                        </div>
                    </form>
                </Modal>
            )}

            {editing && (
                <Modal title={t('employees.editTitle')} onClose={() => setEditing(null)}>
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
                        <Field label={t('employees.firstName')} value={editForm.data.first_name} onChange={(v) => editForm.setData('first_name', v)} error={editForm.errors.first_name} />
                        <Field label={t('employees.lastName')} value={editForm.data.last_name} onChange={(v) => editForm.setData('last_name', v)} error={editForm.errors.last_name} />
                        <Field label={t('common.email')} value={editForm.data.email} onChange={(v) => editForm.setData('email', v)} error={editForm.errors.email} type="email" />
                        <Field label={t('common.phone')} value={editForm.data.phone} onChange={(v) => editForm.setData('phone', v)} error={editForm.errors.phone} />
                        <Field label={t('common.position')} value={editForm.data.position} onChange={(v) => editForm.setData('position', v)} error={editForm.errors.position} />
                        <Field label={t('employees.externalId')} value={editForm.data.external_id} onChange={(v) => editForm.setData('external_id', v)} error={editForm.errors.external_id} />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-600">{t('common.status')}</label>
                            <select
                                value={editForm.data.is_active ? '1' : '0'}
                                onChange={(e) => editForm.setData('is_active', e.target.value === '1')}
                                className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                            >
                                <option value="1">{t('common.active')}</option>
                                <option value="0">{t('common.inactive')}</option>
                            </select>
                        </div>
                        <div className="md:col-span-2 flex justify-end gap-2">
                            <button type="button" onClick={() => setEditing(null)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">{t('common.cancel')}</button>
                            <button type="submit" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{t('common.update')}</button>
                        </div>
                    </form>
                </Modal>
            )}
        </AdminLayout>
    );
}

function CompanySelect({ value, companies, onChange, error }) {
    const t = useT();
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">{t('common.company')}</label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >
                <option value="">{t('companies.selectCompany')}</option>
                {companies.map((company) => (
                    <option key={company.id} value={company.id}>{company.name}</option>
                ))}
            </select>
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Modal({ title, children, onClose }) {
    const t = useT();
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div className="w-full max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                    <button type="button" className="text-sm text-slate-500" onClick={onClose}>{t('common.close')}</button>
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
