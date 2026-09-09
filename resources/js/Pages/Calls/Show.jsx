import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

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
    if (status === 'completed' || status === 'transcribed') return 'bg-emerald-100 text-emerald-800';
    if (status === 'uploaded') return 'bg-indigo-100 text-indigo-800';
    if (status === 'processing') return 'bg-amber-100 text-amber-800';
    if (status === 'failed') return 'bg-red-100 text-red-800';
    return 'bg-slate-100 text-slate-700';
}

export default function CallsShow({ call, companies = [], employees = [] }) {
    const form = useForm({
        company_id: call.company_id ?? '',
        employee_id: call.employee_id ?? '',
        recorded_at: call.recorded_at ? call.recorded_at.slice(0, 16) : '',
    });

    const employeesForCompany = useMemo(
        () => employees.filter((employee) => String(employee.company_id) === String(form.data.company_id)),
        [employees, form.data.company_id],
    );

    return (
        <AdminLayout title={`Call #${call.id}`}>
            <Head title={`Call #${call.id}`} />

            <div className="space-y-6">
                <section className="app-widget p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Call ID</p>
                            <h2 className="mt-1 text-xl font-semibold text-slate-900">#{call.id}</h2>
                            <span className={`mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${statusClass(call.status)}`}>
                                {call.status}
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('calls.index')} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">
                                Back to calls
                            </Link>
                            {call.download_url && (
                                <a href={call.download_url} className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">
                                    Download
                                </a>
                            )}
                            {call.can_retry_transcription && (
                                <button
                                    type="button"
                                    className="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-700"
                                    onClick={() => router.post(route('calls.transcribe', call.id))}
                                >
                                    Retry transcription
                                </button>
                            )}
                            <button
                                type="button"
                                className="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700"
                                onClick={() => {
                                    if (window.confirm('Delete this call and its audio file?')) {
                                        router.delete(route('calls.destroy', call.id));
                                    }
                                }}
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    <dl className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                        <Item label="Company" value={call.company_name} />
                        <Item label="Employee" value={call.employee_name} />
                        <Item label="Source" value={call.source} />
                        <Item label="Original filename" value={call.original_filename} />
                        <Item label="MIME type" value={call.mime_type} />
                        <Item label="File size" value={call.file_size_label} />
                        <Item label="Duration" value={formatDuration(call.duration_seconds)} />
                        <Item label="Recorded at" value={formatDate(call.recorded_at)} />
                        <Item label="Uploaded at" value={formatDate(call.created_at)} />
                        <Item label="Uploaded by" value={call.uploaded_by_name} />
                        <Item label="Processing started" value={formatDate(call.processing_started_at)} />
                        <Item label="Processing completed" value={formatDate(call.processing_completed_at)} />
                    </dl>

                    {call.status === 'failed' && call.error_message && (
                        <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{call.error_message}</p>
                    )}
                </section>

                <section className="app-widget p-4">
                    <h3 className="text-base font-semibold text-slate-900">Audio</h3>
                    {call.has_audio ? (
                        <audio className="mt-4 w-full" controls preload="metadata" src={call.audio_url}>
                            Your browser does not support audio playback.
                        </audio>
                    ) : (
                        <p className="mt-3 text-sm text-slate-500">Audio file is not available.</p>
                    )}
                </section>

                <section className="app-widget p-4">
                    <h3 className="text-base font-semibold text-slate-900">Edit details</h3>
                    <p className="mt-1 text-sm text-slate-500">Company, employee, and recorded time can be updated. Audio cannot be replaced; delete the call and upload a new file instead.</p>
                    <form
                        className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.patch(route('calls.update', call.id), { preserveScroll: true });
                        }}
                    >
                        <Select
                            label="Company"
                            value={form.data.company_id}
                            error={form.errors.company_id}
                            onChange={(value) => form.setData({ ...form.data, company_id: value, employee_id: '' })}
                            options={[{ value: '', label: 'Select company' }, ...companies.map((company) => ({ value: company.id, label: company.name }))]}
                        />
                        <Select
                            label="Employee"
                            value={form.data.employee_id}
                            error={form.errors.employee_id}
                            onChange={(value) => form.setData('employee_id', value)}
                            options={[{ value: '', label: 'No employee' }, ...employeesForCompany.map((employee) => ({ value: employee.id, label: employee.full_name }))]}
                        />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-600">Recorded at</label>
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
                                Save changes
                            </button>
                        </div>
                    </form>
                </section>

                <section className="app-widget p-4">
                    <h3 className="text-base font-semibold text-slate-900">Transcript</h3>
                    {call.status === 'processing' && (
                        <div className="mt-4 flex items-center gap-3 text-sm text-slate-600">
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600" />
                            Transcribing this call…
                        </div>
                    )}
                    {call.status === 'uploaded' && !call.transcript && (
                        <p className="mt-3 text-sm text-slate-500">Queued for transcription.</p>
                    )}
                    {call.status === 'failed' && !call.transcript && (
                        <p className="mt-3 text-sm text-red-700">{call.error_message || 'Transcription failed. Please try again.'}</p>
                    )}
                    {call.transcript && (
                        <div className="mt-4 space-y-4">
                            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                                <Item label="Provider" value={call.transcript.provider_label} />
                                <Item label="Model" value={call.transcript.model_label} />
                                <Item label="Detected language" value={call.transcript.language ? call.transcript.language.toUpperCase() : null} />
                                <Item label="Duration" value={formatDuration(call.transcript.duration_seconds)} />
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
                    <h3 className="text-base font-semibold text-slate-900">AI Analysis</h3>
                    <p className="mt-2 text-sm text-slate-500">Not available yet</p>
                    <p className="mt-1 text-sm text-slate-400">AI analysis will appear here after processing.</p>
                </section>
            </div>
        </AdminLayout>
    );
}

function Item({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 text-slate-800">{value || '—'}</dd>
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
