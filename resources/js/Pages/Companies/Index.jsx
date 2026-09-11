import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useT } from '@/i18n';

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
    const t = useT();
    const { errors } = usePage().props;
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState(null);
    const createForm = useForm({ ...DEFAULT_FORM });
    const editForm = useForm({ ...DEFAULT_FORM });

    const openCreate = () => {
        createForm.setData({ ...DEFAULT_FORM });
        createForm.clearErrors();
        setCreating(true);
    };

    const closeCreate = () => {
        setCreating(false);
        createForm.setData({ ...DEFAULT_FORM });
        createForm.clearErrors();
    };

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
        <AdminLayout title={t('companies.title')}>
            <Head title={t('companies.title')} />

            <div className="space-y-6">
                {errors?.company && <p className="text-sm text-red-600">{errors.company}</p>}

                <section className="app-widget p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 className="text-base font-semibold text-slate-900">{t('companies.title')}</h2>
                        <button
                            type="button"
                            onClick={openCreate}
                            className="inline-flex h-10 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700"
                        >
                            <Plus className="h-4 w-4" />
                            {t('common.create')}
                        </button>
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>{t('common.name')}</Th>
                                    <Th>{t('common.industry')}</Th>
                                    <Th>{t('common.email')}</Th>
                                    <Th>{t('common.status')}</Th>
                                    <Th>{t('companies.employeesCount')}</Th>
                                    <Th>{t('common.actions')}</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {companies.map((company) => (
                                    <tr
                                        key={company.id}
                                        className="cursor-pointer hover:bg-slate-50/80"
                                        onClick={() => router.visit(route('companies.show', company.id))}
                                    >
                                        <Td>{company.name}</Td>
                                        <Td>{company.industry || '—'}</Td>
                                        <Td>{company.email || '—'}</Td>
                                        <Td>{company.is_active ? t('common.active') : t('common.inactive')}</Td>
                                        <Td>{company.employees_count}</Td>
                                        <Td>
                                            <div className="flex flex-wrap gap-3" onClick={(event) => event.stopPropagation()}>
                                                <button type="button" className="text-indigo-700" onClick={() => startEdit(company)}>{t('common.edit')}</button>
                                                <button
                                                    type="button"
                                                    className="text-slate-700"
                                                    onClick={() => router.patch(route('companies.toggle', company.id), {}, { preserveScroll: true })}
                                                >
                                                    {company.is_active ? t('common.deactivate') : t('common.activate')}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="text-red-700"
                                                    onClick={() => {
                                                        if (window.confirm(t('companies.deleteConfirm'))) {
                                                            router.delete(route('companies.destroy', company.id), { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    {t('common.delete')}
                                                </button>
                                            </div>
                                        </Td>
                                    </tr>
                                ))}
                                {companies.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-5 text-slate-500" colSpan={6}>{t('companies.empty')}</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {creating && (
                <Modal title={t('companies.createTitle')} onClose={closeCreate}>
                    <form
                        className="grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            createForm.post(route('companies.store'), {
                                preserveScroll: true,
                                onSuccess: closeCreate,
                            });
                        }}
                    >
                        <Field label={t('common.name')} value={createForm.data.name} onChange={(v) => createForm.setData('name', v)} error={createForm.errors.name} />
                        <Field label={t('companies.legalName')} value={createForm.data.legal_name} onChange={(v) => createForm.setData('legal_name', v)} error={createForm.errors.legal_name} />
                        <Field label={t('common.website')} value={createForm.data.website} onChange={(v) => createForm.setData('website', v)} error={createForm.errors.website} />
                        <Field label={t('common.industry')} value={createForm.data.industry} onChange={(v) => createForm.setData('industry', v)} error={createForm.errors.industry} />
                        <Field label={t('common.country')} value={createForm.data.country} onChange={(v) => createForm.setData('country', v)} error={createForm.errors.country} />
                        <Field label={t('common.city')} value={createForm.data.city} onChange={(v) => createForm.setData('city', v)} error={createForm.errors.city} />
                        <Field label={t('common.phone')} value={createForm.data.phone} onChange={(v) => createForm.setData('phone', v)} error={createForm.errors.phone} />
                        <Field label={t('common.email')} value={createForm.data.email} onChange={(v) => createForm.setData('email', v)} error={createForm.errors.email} type="email" />
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-600">{t('common.description')}</label>
                            <textarea
                                value={createForm.data.description}
                                onChange={(e) => createForm.setData('description', e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            {createForm.errors.description && <p className="mt-1 text-sm text-red-600">{createForm.errors.description}</p>}
                        </div>
                        <div className="md:col-span-2 flex justify-end gap-2">
                            <button type="button" onClick={closeCreate} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">{t('common.cancel')}</button>
                            <button type="submit" disabled={createForm.processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{t('common.create')}</button>
                        </div>
                    </form>
                </Modal>
            )}

            {editing && (
                <Modal title={t('companies.editTitle')} onClose={() => setEditing(null)}>
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
                        <Field label={t('common.name')} value={editForm.data.name} onChange={(v) => editForm.setData('name', v)} error={editForm.errors.name} />
                        <Field label={t('companies.legalName')} value={editForm.data.legal_name} onChange={(v) => editForm.setData('legal_name', v)} error={editForm.errors.legal_name} />
                        <Field label={t('common.website')} value={editForm.data.website} onChange={(v) => editForm.setData('website', v)} error={editForm.errors.website} />
                        <Field label={t('common.industry')} value={editForm.data.industry} onChange={(v) => editForm.setData('industry', v)} error={editForm.errors.industry} />
                        <Field label={t('common.country')} value={editForm.data.country} onChange={(v) => editForm.setData('country', v)} error={editForm.errors.country} />
                        <Field label={t('common.city')} value={editForm.data.city} onChange={(v) => editForm.setData('city', v)} error={editForm.errors.city} />
                        <Field label={t('common.phone')} value={editForm.data.phone} onChange={(v) => editForm.setData('phone', v)} error={editForm.errors.phone} />
                        <Field label={t('common.email')} value={editForm.data.email} onChange={(v) => editForm.setData('email', v)} error={editForm.errors.email} type="email" />
                        <div className="md:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-slate-600">{t('common.description')}</label>
                            <textarea
                                value={editForm.data.description}
                                onChange={(e) => editForm.setData('description', e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
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
