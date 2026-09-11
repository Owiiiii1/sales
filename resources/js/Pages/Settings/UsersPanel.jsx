import { useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { useT } from '@/i18n';

export default function UsersPanel() {
    const t = useT();
    const { users = [], auth } = usePage().props;
    const me = auth?.user;
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const editForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const deleteForm = useForm({ confirm: '' });

    const openEditModal = (user) => {
        setEditingUser(user);
        setShowDeleteConfirm(false);
        editForm.setData({
            name: user.name ?? '',
            email: user.email ?? '',
            password: '',
            password_confirmation: '',
        });
        editForm.clearErrors();
        deleteForm.reset();
        deleteForm.clearErrors();
    };

    const closeEditModal = () => {
        setEditingUser(null);
        setShowDeleteConfirm(false);
        editForm.reset();
        editForm.clearErrors();
        deleteForm.reset();
        deleteForm.clearErrors();
    };

    return (
        <div className="space-y-6">
            <div className="app-widget p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-base font-semibold text-slate-900">{t('settings.usersTitle')}</h2>
                        <p className="mt-1 text-sm text-slate-600">{t('settings.usersDescription')}</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowCreateModal(true)}
                        className="inline-flex h-10 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700"
                    >
                        <Plus className="h-4 w-4" />
                        {t('settings.addUser')}
                    </button>
                </div>

                <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold">{t('settings.colName')}</th>
                                <th className="px-4 py-3 text-left font-semibold">{t('settings.colEmail')}</th>
                                <th className="px-4 py-3 text-left font-semibold">{t('settings.colCreated')}</th>
                                <th className="px-4 py-3 text-left font-semibold">{t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {users.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-6 text-center text-sm text-slate-400">
                                        {t('settings.emptyUsers')}
                                    </td>
                                </tr>
                            ) : (
                                users.map((u) => (
                                    <tr key={u.id} className="hover:bg-slate-50/60">
                                        <td className="px-4 py-3 font-medium text-slate-900">{u.name}</td>
                                        <td className="px-4 py-3">{u.email}</td>
                                        <td className="px-4 py-3 text-slate-500">{t.date(u.created_at)}</td>
                                        <td className="px-4 py-3">
                                            <button
                                                type="button"
                                                onClick={() => openEditModal(u)}
                                                className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                                {t('common.edit')}
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
                    <div className="w-full max-w-xl rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="flex items-start justify-between">
                            <h3 className="text-base font-semibold text-slate-900">{t('settings.createUser')}</h3>
                            <button
                                type="button"
                                onClick={() => {
                                    setShowCreateModal(false);
                                    createForm.reset();
                                    createForm.clearErrors();
                                }}
                                className="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                createForm.post(route('settings.users.store'), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        createForm.reset();
                                        setShowCreateModal(false);
                                    },
                                });
                            }}
                            className="mt-4 space-y-4"
                        >
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <Field form={createForm} field="name" label={t('settings.fieldName')} />
                                <Field form={createForm} field="email" label={t('settings.colEmail')} type="email" />
                                <Field form={createForm} field="password" label={t('settings.fieldPassword')} type="password" />
                                <Field
                                    form={createForm}
                                    field="password_confirmation"
                                    label={t('settings.fieldPasswordConfirm')}
                                    type="password"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    {t('common.cancel')}
                                </button>
                                <button
                                    type="submit"
                                    disabled={createForm.processing}
                                    className="inline-flex h-10 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                >
                                    <Plus className="h-4 w-4" />
                                    {t('settings.createUserSubmit')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {editingUser && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
                    <div className="w-full max-w-xl rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="flex items-start justify-between">
                            <h3 className="text-base font-semibold text-slate-900">{t('settings.editUser')}</h3>
                            <button
                                type="button"
                                onClick={closeEditModal}
                                className="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                editForm.patch(route('settings.users.update', editingUser.id), {
                                    preserveScroll: true,
                                    onSuccess: closeEditModal,
                                });
                            }}
                            className="mt-4 space-y-4"
                        >
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <Field form={editForm} field="name" label={t('settings.fieldName')} />
                                <Field form={editForm} field="email" label={t('settings.colEmail')} type="email" />
                                <Field form={editForm} field="password" label={t('settings.fieldPassword')} type="password" />
                                <Field
                                    form={editForm}
                                    field="password_confirmation"
                                    label={t('settings.fieldPasswordConfirm')}
                                    type="password"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={closeEditModal}
                                    className="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    {t('common.cancel')}
                                </button>
                                <button
                                    type="submit"
                                    disabled={editForm.processing}
                                    className="inline-flex h-10 items-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                >
                                    <Pencil className="h-4 w-4" />
                                    {t('common.saveChanges')}
                                </button>
                            </div>
                        </form>

                        <div className="mt-5 border-t border-slate-200 pt-4">
                            {Number(editingUser.id) === Number(me?.id) ? (
                                <p className="text-sm text-slate-500">{t('settings.cannotDeleteSelf')}</p>
                            ) : !showDeleteConfirm ? (
                                <button
                                    type="button"
                                    onClick={() => setShowDeleteConfirm(true)}
                                    className="inline-flex h-10 items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 text-sm font-semibold text-red-700 transition hover:bg-red-100"
                                >
                                    <Trash2 className="h-4 w-4" />
                                    {t('settings.deleteUser')}
                                </button>
                            ) : (
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        if (deleteForm.data.confirm !== 'DELETE') {
                                            deleteForm.setError('confirm', t('settings.typeDelete'));
                                            return;
                                        }
                                        deleteForm.delete(route('settings.users.destroy', editingUser.id), {
                                            preserveScroll: true,
                                            onSuccess: closeEditModal,
                                        });
                                    }}
                                    className="space-y-3"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-red-700">{t('settings.deleteConfirmTitle')}</p>
                                        <p className="mt-1 text-sm text-slate-600">{t('settings.deleteConfirmHint')}</p>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-slate-600">
                                            {t('settings.deleteConfirmLabel')}
                                        </label>
                                        <input
                                            type="text"
                                            value={deleteForm.data.confirm}
                                            onChange={(e) => deleteForm.setData('confirm', e.target.value)}
                                            placeholder="DELETE"
                                            className="block h-11 w-full rounded-lg border border-red-200 px-3 text-sm shadow-sm focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-100"
                                        />
                                        <ErrorText message={deleteForm.errors.confirm} />
                                        <ErrorText message={deleteForm.errors.user_delete} />
                                    </div>
                                    <div className="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowDeleteConfirm(false);
                                                deleteForm.reset();
                                                deleteForm.clearErrors();
                                            }}
                                            className="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            {t('common.cancel')}
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={deleteForm.processing}
                                            className="inline-flex h-10 items-center gap-2 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                            {deleteForm.processing ? t('settings.deleting') : t('settings.deleteUser')}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function Field({ form, field, label, type = 'text' }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-600">{label}</label>
            <input
                type={type}
                value={form.data[field]}
                onChange={(e) => form.setData(field, e.target.value)}
                className="block h-11 w-full rounded-lg border border-slate-300 px-3 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            />
            <ErrorText message={form.errors[field]} />
        </div>
    );
}

function ErrorText({ message }) {
    if (!message) {
        return null;
    }

    return <p className="mt-2 text-sm text-red-600">{message}</p>;
}
