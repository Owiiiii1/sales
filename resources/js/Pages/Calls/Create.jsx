import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useT } from '@/i18n';

export default function CallsCreate({ companies = [], employees = [], upload = {} }) {
    const t = useT();
    const form = useForm({
        company_id: '',
        employee_id: '',
        recorded_at: '',
        audio: null,
    });

    const employeesForCompany = useMemo(
        () => employees.filter((employee) => String(employee.company_id) === String(form.data.company_id)),
        [employees, form.data.company_id],
    );

    const maxMb = upload.max_audio_size_mb ?? 200;
    const accept = upload.accept ?? '.mp3,.wav,.m4a,.mp4,.ogg,.webm';
    const formats = (upload.allowed_extensions || ['mp3', 'wav', 'm4a', 'mp4', 'ogg', 'webm']).join(', ');

    return (
        <AdminLayout title={t('calls.upload')}>
            <Head title={t('calls.upload')} />

            <section className="app-widget p-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-base font-semibold text-slate-900">{t('calls.upload')}</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {t('calls.uploadHint')}
                        </p>
                    </div>
                    <Link href={route('calls.index')} className="text-sm font-medium text-indigo-700">{t('calls.back')}</Link>
                </div>

                <form
                    className="mt-6 grid grid-cols-1 gap-3 md:grid-cols-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('calls.store'), {
                            forceFormData: true,
                        });
                    }}
                >
                    <Select
                        label={t('common.company')}
                        value={form.data.company_id}
                        error={form.errors.company_id}
                        onChange={(value) => form.setData({ ...form.data, company_id: value, employee_id: '' })}
                        options={[{ value: '', label: t('companies.selectCompany') }, ...companies.map((company) => ({ value: company.id, label: company.name }))]}
                    />
                    <Select
                        label={t('calls.employeeOptional')}
                        value={form.data.employee_id}
                        error={form.errors.employee_id}
                        onChange={(value) => form.setData('employee_id', value)}
                        options={[{ value: '', label: t('calls.noEmployee') }, ...employeesForCompany.map((employee) => ({ value: employee.id, label: employee.full_name }))]}
                    />
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-600">{t('calls.audioFile')}</label>
                        <input
                            type="file"
                            accept={accept}
                            onChange={(e) => form.setData('audio', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700"
                        />
                        <p className="mt-1 text-xs text-slate-500">
                            {t('calls.allowed', { formats, mb: maxMb })}
                        </p>
                        {form.errors.audio && <p className="mt-1 text-sm text-red-600">{form.errors.audio}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-600">{t('calls.recordedOptional')}</label>
                        <input
                            type="datetime-local"
                            value={form.data.recorded_at}
                            onChange={(e) => form.setData('recorded_at', e.target.value)}
                            className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        />
                        {form.errors.recorded_at && <p className="mt-1 text-sm text-red-600">{form.errors.recorded_at}</p>}
                    </div>
                    <div className="md:col-span-2 flex justify-end">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing ? t('common.uploading') : t('common.save')}
                        </button>
                    </div>
                </form>
            </section>
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
