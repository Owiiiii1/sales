import AnalysisReport from '@/Components/Public/AnalysisReport';
import CallTranscript from '@/Components/Public/CallTranscript';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export default function PublicHome({ upload = {} }) {
    const maxMb = upload.max_audio_size_mb ?? 200;
    const accept = upload.accept ?? '.mp3,.wav,.m4a,.mp4,.ogg,.webm';
    const pollInterval = upload.poll_interval_ms ?? 3000;
    const uploadAvailable = upload.available !== false;
    const unavailableMessage = upload.unavailable_message ?? 'Audio analysis is temporarily unavailable.';
    const inputRef = useRef(null);
    const pollRef = useRef(null);

    const [dragOver, setDragOver] = useState(false);
    const [file, setFile] = useState(null);
    const [uiStatus, setUiStatus] = useState('idle');
    const [error, setError] = useState('');
    const [result, setResult] = useState(null);

    const stopPolling = () => {
        if (pollRef.current) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }
    };

    useEffect(() => () => stopPolling(), []);

    const assignFile = (nextFile) => {
        setFile(nextFile);
        setError('');
        setResult(null);
        setUiStatus('idle');
        stopPolling();
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
                setResult((current) => ({ ...(current ?? {}), ...payload }));
                if (payload.status === 'uploaded' || payload.status === 'processing' || payload.status === 'analyzing' || payload.status === 'transcribed') {
                    setUiStatus(payload.status);
                }
                if (payload.status === 'transcribed' || payload.status === 'analyzing' || payload.status === 'analysis_pending' || payload.status === 'completed' || payload.status === 'failed') {
                    const full = await fetch(route('analysis.show', token), {
                        headers: { Accept: 'application/json' },
                    });
                    if (full.ok) {
                        setResult(await full.json());
                    }
                }
                if (payload.status === 'analysis_pending' || payload.status === 'completed' || payload.status === 'failed') {
                    setUiStatus(payload.status);
                    stopPolling();
                }
            } catch {
                // Keep the last known state; the next tick retries.
            }
        }, pollInterval);
    };

    const submit = async (nextFile) => {
        const audio = nextFile ?? file;
        if (!audio) {
            setError('Please choose an audio file.');
            return;
        }

        const data = new FormData();
        data.append('audio', audio);

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
                const message = payload.errors?.audio?.[0] || payload.message || 'Upload failed. Please try again.';
                setError(message);
                setUiStatus('failed');
                return;
            }

            setResult(payload);
            setFile(audio);
            setUiStatus(payload.status || 'uploaded');

            if (payload.status === 'uploaded' || payload.status === 'processing' || payload.status === 'analyzing') {
                startPolling(payload.public_token);
            }
        } catch {
            setError('Upload failed. Please try again.');
            setUiStatus('failed');
        }
    };

    const statusLabel = {
        uploading: 'Uploading',
        uploaded: 'Queued',
        processing: 'Transcribing',
        transcribed: 'Transcribed',
        analysis_pending: 'Analysis pending',
        analyzing: 'Analyzing',
        completed: 'Completed',
        failed: 'Failed',
    }[uiStatus] ?? '';

    return (
        <PublicLayout>
            <Head title="Analyze your sales call" />

            <div className="mx-auto max-w-5xl px-6 py-16 sm:py-20">
                <section className="text-center">
                    <p className="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">Sales Analyzer</p>
                    <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                        Analyze your sales call
                    </h1>
                    <p className="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">
                        Upload a recording of the conversation and get an AI analysis of how the sale was conducted.
                    </p>
                </section>

                <section
                    className={`mt-12 rounded-3xl border-2 border-dashed bg-white p-8 shadow-sm transition sm:p-12 ${
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
                    {uiStatus === 'uploading' || uiStatus === 'processing' || uiStatus === 'analyzing' ? (
                        <div className="flex flex-col items-center gap-4 py-6 text-center">
                            <span className="h-12 w-12 animate-spin rounded-full border-4 border-indigo-100 border-t-indigo-600" />
                            <div>
                                <p className="text-lg font-semibold text-slate-900">{statusLabel}</p>
                                <p className="mt-1 text-sm text-slate-500">{file?.name || result?.original_filename}</p>
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center text-center">
                            <p className="text-lg font-semibold text-slate-900">
                                {uploadAvailable ? 'Drop your audio file here' : unavailableMessage}
                            </p>
                            <p className="mt-2 text-sm text-slate-500">
                                {uploadAvailable
                                    ? `MP3, WAV, M4A, MP4, OGG or WEBM. Maximum ${maxMb} MB.`
                                    : 'Please try again later.'}
                            </p>
                            {file && (
                                <p className="mt-3 text-sm font-medium text-slate-700">{file.name}</p>
                            )}
                            {uiStatus !== 'idle' && uiStatus !== 'failed' && (
                                <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-indigo-600">{statusLabel}</p>
                            )}
                            <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
                                <button
                                    type="button"
                                    disabled={!uploadAvailable}
                                    onClick={() => inputRef.current?.click()}
                                    className="rounded-full border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Choose file
                                </button>
                                <button
                                    type="button"
                                    disabled={!uploadAvailable}
                                    onClick={() => submit()}
                                    className="rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Analyze call
                                </button>
                            </div>
                            <input
                                ref={inputRef}
                                type="file"
                                accept={accept}
                                className="hidden"
                                onChange={(event) => assignFile(event.target.files?.[0] ?? null)}
                            />
                            {error && <p className="mt-4 text-sm text-red-600">{error}</p>}
                        </div>
                    )}
                </section>

                <div className="mt-10 space-y-6">
                    <AnalysisReport
                        status={uiStatus === 'idle' ? null : uiStatus}
                        report={result?.report}
                        message={result?.message}
                        error={result?.error}
                    />
                    {(uiStatus === 'transcribed' || uiStatus === 'analysis_pending' || uiStatus === 'analyzing' || uiStatus === 'completed' || uiStatus === 'failed') && result?.transcript && (
                        <CallTranscript transcript={result.transcript} />
                    )}
                </div>
            </div>
        </PublicLayout>
    );
}
