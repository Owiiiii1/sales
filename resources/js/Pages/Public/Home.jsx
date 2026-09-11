import ShortAnalysisReport from '@/Components/Public/ShortAnalysisReport';
import CancelProcessingModal from '@/Components/Public/CancelProcessingModal';
import ProcessingProgress, { uploadingProgress } from '@/Components/Public/ProcessingProgress';
import TranscriptModal, { TranscriptReadyCard } from '@/Components/Public/TranscriptModal';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useT } from '@/i18n';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const GENERIC_CONTEXT = 'generic';
const TERMINAL_STATUSES = ['analysis_pending', 'completed', 'failed', 'cancelled'];

export default function PublicHome({ upload = {}, companies = [], employees = [] }) {
    const t = useT();
    const { auth } = usePage().props;
    const maxMb = upload.max_audio_size_mb ?? 200;
    const accept = upload.accept ?? '.mp3,.wav,.m4a,.mp4,.ogg,.webm';
    const pollInterval = upload.poll_interval_ms ?? 3000;
    const uploadAvailable = upload.available !== false;
    const unavailableMessage = upload.unavailable_message ?? t('home.unavailable');
    const inputRef = useRef(null);
    const pollRef = useRef(null);
    const resultAnchorRef = useRef(null);
    const companyIdRef = useRef('');
    const employeeIdRef = useRef('');

    const [dragOver, setDragOver] = useState(false);
    const [file, setFile] = useState(null);
    const [uiStatus, setUiStatus] = useState('idle');
    const [error, setError] = useState('');
    const [result, setResult] = useState(null);
    const [companyId, setCompanyId] = useState('');
    const [employeeId, setEmployeeId] = useState('');
    const [transcriptOpen, setTranscriptOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelling, setCancelling] = useState(false);

    companyIdRef.current = companyId;
    employeeIdRef.current = employeeId;

    const selectedCompany = useMemo(
        () => companies.find((company) => String(company.id) === String(companyId)) ?? null,
        [companies, companyId],
    );

    const employeesForCompany = useMemo(
        () => employees.filter((employee) => String(employee.company_id) === String(companyId)),
        [employees, companyId],
    );

    const selectedEmployee = useMemo(
        () => employeesForCompany.find((employee) => String(employee.id) === String(employeeId)) ?? null,
        [employeesForCompany, employeeId],
    );

    const contextSelected = companyId !== '';
    const isGenericContext = companyId === GENERIC_CONTEXT;

    const stopPolling = () => {
        if (pollRef.current) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }
    };

    useEffect(() => () => stopPolling(), []);

    useEffect(() => {
        if (uiStatus !== 'uploading') {
            return;
        }

        resultAnchorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, [uiStatus]);

    const assignFile = (nextFile) => {
        setFile(nextFile);
        setError('');
        setResult(null);
        setUiStatus('idle');
        stopPolling();
    };

    const changeCompany = (value) => {
        setCompanyId(value);
        setEmployeeId('');
    };

    const startPolling = (token) => {
        stopPolling();
        pollRef.current = setInterval(async () => {
            try {
                const response = await fetch(route('analysis.status', token), {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    return;
                }
                const payload = await response.json();
                setUiStatus(payload.status);

                if (payload.status !== 'uploaded' && payload.status !== 'processing') {
                    const full = await fetch(route('analysis.show', token), {
                        headers: { Accept: 'application/json' },
                    });
                    if (full.ok) {
                        setResult(await full.json());
                    } else {
                        setResult((current) => ({ ...(current ?? {}), ...payload }));
                    }
                } else {
                    setResult((current) => ({ ...(current ?? {}), ...payload }));
                }

                if (TERMINAL_STATUSES.includes(payload.status)) {
                    stopPolling();
                }
            } catch {
                // Keep the last known state; the next tick retries.
            }
        }, pollInterval);
    };

    const cancelProcessing = async () => {
        const token = result?.public_token;
        if (!token) {
            return;
        }

        setCancelling(true);
        try {
            const response = await fetch(route('analysis.cancel', token), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => ({}));
            if (response.ok) {
                setResult(payload);
                setUiStatus(payload.status || 'cancelled');
                stopPolling();
            } else if (response.status === 409 && payload.status) {
                setUiStatus(payload.status);
                if (payload.progress) {
                    setResult((current) => ({ ...(current ?? {}), ...payload }));
                }
            }
        } finally {
            setCancelling(false);
            setCancelOpen(false);
        }
    };

    const submit = async (nextFile) => {
        const audio = nextFile ?? file;
        if (!contextSelected) {
            setError(t('home.selectContextFirst'));
            return;
        }
        if (!audio) {
            setError(t('home.chooseAudio'));
            return;
        }

        const data = new FormData();
        data.append('audio', audio);
        data.append('locale', t.locale);
        if (companyIdRef.current && companyIdRef.current !== GENERIC_CONTEXT) {
            data.append('company_id', companyIdRef.current);
            if (employeeIdRef.current) {
                data.append('employee_id', employeeIdRef.current);
            }
        }

        setError('');
        setUiStatus('uploading');
        stopPolling();

        try {
            const response = await fetch(route('analyze.store'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: data,
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = payload.errors?.audio?.[0]
                    || payload.errors?.company_id?.[0]
                    || payload.errors?.employee_id?.[0]
                    || payload.message
                    || t('home.uploadFailed');
                setError(message);
                setUiStatus('failed');
                return;
            }

            setResult(payload);
            setFile(audio);
            setUiStatus(payload.status || 'uploaded');

            if (payload.status === 'uploaded' || payload.status === 'processing' || payload.status === 'analyzing' || payload.status === 'transcribed') {
                startPolling(payload.public_token);
            }
        } catch {
            setError(t('home.uploadFailed'));
            setUiStatus('failed');
        }
    };

    const statusLabel = t.status(uiStatus);
    const progress = uiStatus === 'uploading' ? uploadingProgress() : result?.progress;

    return (
        <PublicLayout>
            <Head title={t('home.title')} />

            <div className="mx-auto max-w-5xl px-6 py-16 sm:py-20">
                <section className="text-center">
                    <p className="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">{t('home.kicker')}</p>
                    <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                        {t('home.title')}
                    </h1>
                    <p className="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">
                        {t('home.subtitle')}
                    </p>
                </section>

                <section className="mt-12 rounded-3xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-left">
                            <span className="mb-1.5 block text-sm font-semibold text-slate-800">{t('home.company')}</span>
                            <select
                                value={companyId}
                                onChange={(event) => changeCompany(event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900"
                            >
                                <option value="">{t('home.selectContext')}</option>
                                <option value={GENERIC_CONTEXT}>{t('home.genericOption')}</option>
                                {companies.map((company) => (
                                    <option key={company.id} value={company.id}>{company.name}</option>
                                ))}
                            </select>
                            {auth?.user && (
                                <Link href={route('companies.index')} className="mt-2 inline-block text-xs font-medium text-indigo-700 hover:text-indigo-500">
                                    {t('home.manageCompanies')}
                                </Link>
                            )}
                        </label>
                        <label className="block text-left">
                            <span className="mb-1.5 block text-sm font-semibold text-slate-800">{t('home.employee')}</span>
                            <select
                                value={employeeId}
                                disabled={!selectedCompany}
                                onChange={(event) => setEmployeeId(event.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
                            >
                                <option value="">{t('home.noEmployee')}</option>
                                {employeesForCompany.map((employee) => (
                                    <option key={employee.id} value={employee.id}>{employee.name}</option>
                                ))}
                            </select>
                        </label>
                    </div>

                    {contextSelected && (
                        <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left">
                            {isGenericContext ? (
                                <>
                                    <p className="text-sm font-semibold text-slate-900">{t('home.genericMode')}</p>
                                    <p className="mt-1 text-sm leading-6 text-slate-600">{t('home.genericSummaryMethod')}</p>
                                    <p className="mt-1 text-sm leading-6 text-slate-600">{t('home.genericSummaryRules')}</p>
                                </>
                            ) : (
                                <>
                                    <p className="text-sm font-semibold text-slate-900">
                                        {t('home.companyMode', { name: selectedCompany?.name ?? '' })}
                                    </p>
                                    {selectedCompany?.knowledge_completeness !== null && selectedCompany?.knowledge_completeness !== undefined && (
                                        <p className="mt-1 text-sm leading-6 text-slate-600">
                                            {t('home.knowledge', { percent: selectedCompany.knowledge_completeness })}
                                        </p>
                                    )}
                                    {selectedCompany?.scorecard_name && (
                                        <p className="mt-1 text-sm leading-6 text-slate-600">
                                            {t('home.scorecardName', { name: selectedCompany.scorecard_name })}
                                        </p>
                                    )}
                                    {selectedEmployee && (
                                        <p className="mt-1 text-sm leading-6 text-slate-600">
                                            {t('home.summaryEmployee', { name: selectedEmployee.name })}
                                        </p>
                                    )}
                                </>
                            )}
                        </div>
                    )}

                    <div
                        className={`mt-6 rounded-2xl border-2 border-dashed p-6 transition sm:p-8 ${
                            dragOver && uploadAvailable ? 'border-indigo-400 bg-indigo-50/60' : 'border-slate-200'
                        }`}
                        onDragOver={(event) => {
                            event.preventDefault();
                            if (!uploadAvailable) {
                                return;
                            }
                            setDragOver(true);
                        }}
                        onDragLeave={() => setDragOver(false)}
                        onDrop={(event) => {
                            event.preventDefault();
                            setDragOver(false);
                            if (!uploadAvailable) {
                                return;
                            }
                            const dropped = event.dataTransfer.files?.[0];
                            if (dropped) {
                                assignFile(dropped);
                            }
                        }}
                    >
                        <div className="flex flex-col items-center text-center">
                            <p className="text-lg font-semibold text-slate-900">
                                {uploadAvailable ? t('home.drop') : unavailableMessage}
                            </p>
                            <p className="mt-2 text-sm text-slate-500">
                                {uploadAvailable
                                    ? t('home.formats', { mb: maxMb })
                                    : t('home.tryLater')}
                            </p>
                            {file && (
                                <p className="mt-3 text-sm font-medium text-slate-700">{file.name}</p>
                            )}
                            {uiStatus !== 'idle' && uiStatus !== 'failed' && uiStatus !== 'cancelled' && (
                                <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-indigo-600">{statusLabel}</p>
                            )}
                            <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
                                <button
                                    type="button"
                                    disabled={!uploadAvailable}
                                    onClick={() => inputRef.current?.click()}
                                    className="rounded-full border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {t('home.chooseFile')}
                                </button>
                                <button
                                    type="button"
                                    disabled={!uploadAvailable || !contextSelected}
                                    onClick={() => submit()}
                                    className="rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {t('home.analyze')}
                                </button>
                            </div>
                            {!contextSelected && uploadAvailable && (
                                <p className="mt-3 text-sm text-slate-500">{t('home.selectContextFirst')}</p>
                            )}
                            <input
                                ref={inputRef}
                                type="file"
                                accept={accept}
                                className="hidden"
                                onChange={(event) => assignFile(event.target.files?.[0] ?? null)}
                            />
                            {error && <p className="mt-4 text-sm text-red-600">{error}</p>}
                        </div>
                    </div>
                </section>

                <div ref={resultAnchorRef} className="mt-10 scroll-mt-8 space-y-6">
                    {progress && uiStatus !== 'idle' && (
                        <ProcessingProgress
                            progress={progress}
                            message={result?.message}
                            error={result?.error}
                            onStop={() => setCancelOpen(true)}
                        />
                    )}
                    {result?.transcript && (
                        <TranscriptReadyCard transcript={result.transcript} onOpen={() => setTranscriptOpen(true)} />
                    )}
                    {uiStatus === 'completed' && result?.report ? (
                        <ShortAnalysisReport
                            report={result.report}
                            analysisMode={result.analysis_mode}
                            companyName={result.company_name}
                            employeeName={result.employee_name}
                            fullReportUrl={result.full_report_url || (result.public_token ? route('analysis.full', result.public_token) : null)}
                        />
                    ) : null}
                </div>
            </div>
            <TranscriptModal
                open={transcriptOpen}
                onOpenChange={setTranscriptOpen}
                transcript={result?.transcript}
            />
            <CancelProcessingModal
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                onConfirm={cancelProcessing}
                busy={cancelling}
            />
        </PublicLayout>
    );
}
