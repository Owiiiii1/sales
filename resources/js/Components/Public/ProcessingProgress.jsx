import { AlertTriangle, Check, LoaderCircle, Square, X } from 'lucide-react';
import { useT } from '@/i18n';

const STEP_KEYS = [
    { key: 'uploaded', label: 'progress.uploaded' },
    { key: 'transcription', label: 'progress.transcription' },
    { key: 'preparing', label: 'progress.preparing' },
    { key: 'analysis', label: 'progress.analysis' },
    { key: 'complete', label: 'progress.completeStep' },
];

function headlineKey(headline) {
    if (headline === 'complete') {
        return 'progress.complete';
    }
    if (headline === 'stopped_analysis') {
        return 'progress.stoppedAnalysis';
    }
    if (headline === 'stopped') {
        return 'progress.stopped';
    }
    if (headline === 'failed') {
        return 'progress.failed';
    }
    if (headline === 'unavailable') {
        return 'progress.unavailable';
    }
    return 'progress.processing';
}

function StepIcon({ state }) {
    if (state === 'completed') {
        return (
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <Check className="h-4 w-4" />
            </span>
        );
    }
    if (state === 'active') {
        return (
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-700">
                <LoaderCircle className="h-4 w-4 animate-spin" />
            </span>
        );
    }
    if (state === 'failed') {
        return (
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-700">
                <X className="h-4 w-4" />
            </span>
        );
    }
    if (state === 'cancelled') {
        return (
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-slate-700">
                <Square className="h-3.5 w-3.5 fill-current" />
            </span>
        );
    }
    if (state === 'unavailable') {
        return (
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-800">
                <AlertTriangle className="h-4 w-4" />
            </span>
        );
    }

    return (
        <span className="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white">
            <span className="h-2 w-2 rounded-full bg-slate-300" />
        </span>
    );
}

function stepLabel(t, key, state) {
    if (key === 'analysis' && state === 'unavailable') {
        return t('progress.analysisUnavailable');
    }
    const item = STEP_KEYS.find((step) => step.key === key);
    return item ? t(item.label) : key;
}

export default function ProcessingProgress({ progress, message, error, onStop }) {
    const t = useT();

    if (!progress) {
        return null;
    }

    const steps = progress.steps ?? [];
    const compact = Boolean(progress.compact);
    const title = t(headlineKey(progress.headline));

    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-slate-900">{t('progress.title')}</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {title}
                        {progress.headline === 'complete' ? ' ✓' : ''}
                    </p>
                </div>
                {progress.cancellable && (
                    <button
                        type="button"
                        onClick={onStop}
                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        {t('progress.stop')}
                    </button>
                )}
            </div>

            {!compact && (
                <ol className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-2">
                    {steps.map((step, index) => (
                        <li key={step.key} className="flex min-w-0 flex-1 items-start gap-3 sm:flex-col sm:items-center sm:text-center">
                            <div className="flex items-center gap-3 sm:flex-col">
                                <StepIcon state={step.state} />
                                {index < steps.length - 1 && (
                                    <span className="hidden h-px w-full bg-slate-200 sm:block" />
                                )}
                            </div>
                            <p className={`text-sm font-medium ${step.state === 'pending' ? 'text-slate-400' : 'text-slate-800'}`}>
                                {stepLabel(t, step.key, step.state)}
                            </p>
                        </li>
                    ))}
                </ol>
            )}

            {progress.headline === 'unavailable' && (
                <p className="mt-4 text-sm text-slate-600">
                    {message || t('calls.analysisNotConfigured')}
                </p>
            )}
            {error && progress.headline === 'failed' && (
                <p className="mt-4 text-sm text-red-700">{error}</p>
            )}
        </section>
    );
}

export function uploadingProgress() {
    return {
        cancellable: false,
        transcript_available: false,
        compact: false,
        headline: 'processing',
        steps: [
            { key: 'uploaded', state: 'active' },
            { key: 'transcription', state: 'pending' },
            { key: 'preparing', state: 'pending' },
            { key: 'analysis', state: 'pending' },
            { key: 'complete', state: 'pending' },
        ],
    };
}
