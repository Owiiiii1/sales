import AdminLayout from '@/Layouts/AdminLayout';
import AnalysisReport from '@/Components/Public/AnalysisReport';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useT } from '@/i18n';

function formatDuration(seconds, t) {
    if (seconds === null || seconds === undefined) {
        return t('common.dash');
    }
    const mins = Math.floor(Number(seconds) / 60);
    const secs = Number(seconds) % 60;
    return `${mins}:${String(secs).padStart(2, '0')}`;
}

function statusClass(status) {
    if (status === 'completed' || status === 'transcribed' || status === 'analysis_pending') return 'bg-emerald-100 text-emerald-800';
    if (status === 'uploaded') return 'bg-indigo-100 text-indigo-800';
    if (status === 'processing' || status === 'analyzing') return 'bg-amber-100 text-amber-800';
    if (status === 'failed') return 'bg-red-100 text-red-800';
    return 'bg-slate-100 text-slate-700';
}

export default function CallsShow({ call, companies = [], employees = [] }) {
    const t = useT();
    const { errors = {} } = usePage().props;
    const form = useForm({
        company_id: call.company_id ?? '',
        employee_id: call.employee_id ?? '',
        recorded_at: call.recorded_at ? call.recorded_at.slice(0, 16) : '',
    });
    const [showSnapshot, setShowSnapshot] = useState(false);

    const employeesForCompany = useMemo(
        () => employees.filter((employee) => String(employee.company_id) === String(form.data.company_id)),
        [employees, form.data.company_id],
    );

    return (
        <AdminLayout title={t('calls.callTitle', { id: call.id })}>
            <Head title={t('calls.callTitle', { id: call.id })} />

            <div className="space-y-6">
                <section className="app-widget p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('calls.callId')}</p>
                            <h2 className="mt-1 text-xl font-semibold text-slate-900">#{call.id}</h2>
                            <span className={`mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${statusClass(call.status)}`}>
                                {t.status(call.status)}
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('calls.index')} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">
                                {t('calls.back')}
                            </Link>
                            {call.download_url && (
                                <a href={call.download_url} className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">
                                    {t('calls.download')}
                                </a>
                            )}
                            {call.can_retry_transcription && (
                                <button
                                    type="button"
                                    className="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-700"
                                    onClick={() => router.post(route('calls.transcribe', call.id))}
                                >
                                    {t('calls.retryTranscription')}
                                </button>
                            )}
                            {call.can_run_analysis && (
                                <button
                                    type="button"
                                    disabled={call.analysis_ready === false}
                                    title={call.analysis_ready === false ? call.analysis_unavailable_message : undefined}
                                    className="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    onClick={() => {
                                        if (call.analysis_ready === false) {
                                            return;
                                        }
                                        router.post(route('calls.analyze', call.id));
                                    }}
                                >
                                    {t('calls.runAnalysis')}
                                </button>
                            )}
                            {call.can_rerun_analysis && (
                                <button
                                    type="button"
                                    disabled={call.analysis_ready === false}
                                    title={call.analysis_ready === false ? call.analysis_unavailable_message : undefined}
                                    className="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    onClick={() => {
                                        if (call.analysis_ready === false) {
                                            return;
                                        }
                                        router.post(route('calls.analyze', call.id));
                                    }}
                                >
                                    {t('calls.rerunAnalysis')}
                                </button>
                            )}
                            <button
                                type="button"
                                className="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700"
                                onClick={() => {
                                    if (window.confirm(t('calls.deleteConfirm'))) {
                                        router.delete(route('calls.destroy', call.id));
                                    }
                                }}
                            >
                                {t('common.delete')}
                            </button>
                        </div>
                    </div>

                    {call.analysis_ready === false && (call.can_run_analysis || call.can_rerun_analysis) && call.analysis_unavailable_message && (
                        <p className="mt-3 text-sm text-amber-800">{call.analysis_unavailable_message}</p>
                    )}
                    {errors.call ? <p className="mt-3 text-sm text-red-600">{errors.call}</p> : null}

                    <dl className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                        <Item label={t('common.company')} value={call.company_name} />
                        <Item label={t('common.employee')} value={call.employee_name} />
                        <Item label={t('calls.source')} value={call.source} />
                        <Item label={t('common.filename')} value={call.original_filename} />
                        <Item label={t('calls.mime')} value={call.mime_type} />
                        <Item label={t('calls.fileSize')} value={call.file_size_label} />
                        <Item label={t('common.duration')} value={formatDuration(call.duration_seconds, t)} />
                        <Item label={t('calls.recordedAt')} value={t.date(call.recorded_at)} />
                        <Item label={t('calls.uploadedAt')} value={t.date(call.created_at)} />
                        <Item label={t('calls.uploadedBy')} value={call.uploaded_by_name} />
                        <Item label={t('calls.processingStarted')} value={t.date(call.processing_started_at)} />
                        <Item label={t('calls.processingCompleted')} value={t.date(call.processing_completed_at)} />
                    </dl>

                    {call.status === 'failed' && call.error_message && (
                        <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{call.error_message}</p>
                    )}
                </section>

                <section className="app-widget p-4">
                    <h3 className="text-base font-semibold text-slate-900">{t('calls.audio')}</h3>
                    {call.has_audio ? (
                        <audio className="mt-4 w-full" controls preload="metadata" src={call.audio_url}>
                            {t('calls.audioUnsupported')}
                        </audio>
                    ) : (
                        <p className="mt-3 text-sm text-slate-500">{t('calls.audioMissing')}</p>
                    )}
                </section>

                <section className="app-widget p-4">
                    <h3 className="text-base font-semibold text-slate-900">{t('calls.editDetails')}</h3>
                    <p className="mt-1 text-sm text-slate-500">{t('calls.editHint')}</p>
                    <form
                        className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.patch(route('calls.update', call.id), { preserveScroll: true });
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
                            label={t('common.employee')}
                            value={form.data.employee_id}
                            error={form.errors.employee_id}
                            onChange={(value) => form.setData('employee_id', value)}
                            options={[{ value: '', label: t('calls.noEmployee') }, ...employeesForCompany.map((employee) => ({ value: employee.id, label: employee.full_name }))]}
                        />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-600">{t('calls.recordedAt')}</label>
                            <input
                                type="datetime-local"
                                value={form.data.recorded_at}
                                onChange={(e) => form.setData('recorded_at', e.target.value)}
                                className="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                            />
                            {form.errors.recorded_at && <p className="mt-1 text-sm text-red-600">{form.errors.recorded_at}</p>}
                        </div>
                        <div className="md:col-span-2 flex justify-end">
                            <button type="submit" disabled={form.processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                                {t('common.saveChanges')}
                            </button>
                        </div>
                    </form>
                </section>

                <section className="app-widget p-4">
                    <h3 className="text-base font-semibold text-slate-900">{t('calls.transcript')}</h3>
                    {call.status === 'processing' && (
                        <div className="mt-4 flex items-center gap-3 text-sm text-slate-600">
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600" />
                            {t('calls.transcribing')}
                        </div>
                    )}
                    {call.status === 'uploaded' && !call.transcript && (
                        <p className="mt-3 text-sm text-slate-500">{t('calls.queued')}</p>
                    )}
                    {call.status === 'failed' && !call.transcript && (
                        <p className="mt-3 text-sm text-red-700">{call.error_message || t('calls.transcriptionFailed')}</p>
                    )}
                    {call.transcript && (
                        <div className="mt-4 space-y-4">
                            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                                <Item label={t('calls.provider')} value={call.transcript.provider_label} />
                                <Item label={t('calls.model')} value={call.transcript.model_label} />
                                <Item label={t('calls.detectedLanguage')} value={call.transcript.language ? call.transcript.language.toUpperCase() : null} />
                                <Item label={t('common.duration')} value={formatDuration(call.transcript.duration_seconds, t)} />
                            </dl>
                            {call.transcript.segments?.length > 0 ? (
                                <div className="space-y-3">
                                    {call.transcript.segments.map((segment, index) => (
                                        <div key={`${segment.speaker}-${segment.start_seconds}-${index}`}>
                                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                {segment.start_label} {segment.speaker_label}
                                            </p>
                                            <p className="mt-1 text-sm leading-6 text-slate-800">{segment.text}</p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="whitespace-pre-wrap text-sm leading-6 text-slate-800">{call.transcript.text}</p>
                            )}
                        </div>
                    )}
                </section>

                <section className="app-widget p-4">
                    <h3 className="text-base font-semibold text-slate-900">{t('calls.analysis')}</h3>
                    {call.status === 'analyzing' && (
                        <div className="mt-4 flex items-center gap-3 text-sm text-slate-600">
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600" />
                            {t('calls.analyzing')}
                        </div>
                    )}
                    {call.status === 'analysis_pending' && !call.analysis && (
                        <p className="mt-3 text-sm text-slate-600">{t('calls.analysisNotConfigured')}</p>
                    )}
                    {call.status === 'transcribed' && !call.analysis && (
                        <p className="mt-3 text-sm text-slate-600">{t('calls.transcriptReady')}</p>
                    )}
                    {call.analysis && (
                        <div className="mt-4 space-y-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('calls.analysisContext')}</p>
                                <dl className="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                                    <Item label={t('common.company')} value={call.analysis_context?.company} />
                                    <Item label={t('companies.scorecard')} value={call.analysis_context?.scorecard_name} />
                                    <Item label={t('calls.schemaVersion')} value={call.analysis_context?.schema_version} />
                                    <Item label={t('calls.companyContextUsed')} value={call.analysis_context?.company_context_used ? t('common.yes') : t('common.no')} />
                                </dl>
                            </div>
                            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                                <Item label={t('calls.provider')} value={call.analysis.provider_label} />
                                <Item label={t('calls.model')} value={call.analysis.model} />
                                <Item label={t('calls.schemaVersion')} value={call.analysis.schema_version} />
                                <Item label={t('calls.completed')} value={t.date(call.analysis.completed_at)} />
                            </dl>
                            {call.analysis.speaker_roles?.length > 0 && (
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('calls.speakerRoles')}</p>
                                    <ul className="mt-2 text-sm text-slate-800">
                                        {call.analysis.speaker_roles.map((role) => (
                                            <li key={role.speaker}>{role.speaker_label}: {t.enum('role', role.role)}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            <AnalysisReport status="completed" report={call.analysis} />
                            {call.analysis.context_snapshot && (
                                <div className="rounded-xl border border-slate-200 p-4">
                                    <button type="button" className="text-sm font-medium text-indigo-700" onClick={() => setShowSnapshot((open) => !open)}>
                                        {showSnapshot ? t('calls.hideSnapshot') : t('calls.showSnapshot')}
                                    </button>
                                    {showSnapshot && (
                                        <pre className="mt-3 max-h-80 overflow-auto whitespace-pre-wrap text-xs text-slate-700">
                                            {JSON.stringify(call.analysis.context_snapshot, null, 2)}
                                        </pre>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}

function Item({ label, value }) {
    const t = useT();
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 text-slate-800">{value || t('common.dash')}</dd>
        </div>
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
