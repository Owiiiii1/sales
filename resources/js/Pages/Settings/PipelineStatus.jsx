import { CheckCircle2, CircleAlert } from 'lucide-react';

function sourceLabel(source) {
    if (source === 'database') {
        return 'Database';
    }
    if (source === 'environment') {
        return 'Environment';
    }

    return 'Not configured';
}

function StatusRow({ label, ready, detail, source }) {
    return (
        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p className="text-sm font-medium text-slate-800">{label}</p>
                <p className="text-sm text-slate-600">{detail}</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {source ? (
                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                        {sourceLabel(source)}
                    </span>
                ) : null}
                <span
                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${
                        ready ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'
                    }`}
                >
                    {ready ? <CheckCircle2 className="h-3.5 w-3.5" /> : <CircleAlert className="h-3.5 w-3.5" />}
                    {ready ? 'Ready' : 'Not ready'}
                </span>
            </div>
        </div>
    );
}

function layerDetail(layer) {
    if (!layer) {
        return 'Not configured';
    }

    const name = layer.provider ? `${layer.provider}${layer.model ? ` / ${layer.model}` : ''}` : '';
    if (layer.ready) {
        return name ? `${name} — ${layer.message}` : layer.message;
    }

    return name ? `${name} — ${layer.message}` : layer.message;
}

export default function PipelineStatus({ pipeline = {} }) {
    const transcription = pipeline.transcription ?? {};
    const analysis = pipeline.analysis ?? {};
    const overallReady = !!pipeline.pipeline_ready;

    return (
        <section className="app-widget p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-slate-900">Analysis Pipeline</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Transcription and AI analysis must both be ready before the full pipeline can run.
                    </p>
                </div>
                <span
                    className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                        overallReady ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'
                    }`}
                >
                    {overallReady ? 'Pipeline Ready' : 'Pipeline not ready'}
                </span>
            </div>

            <div className="mt-4 space-y-4 divide-y divide-slate-100">
                <div className="pt-0">
                    <StatusRow
                        label="Transcription"
                        ready={!!transcription.ready}
                        detail={layerDetail(transcription)}
                        source={transcription.source}
                    />
                </div>
                <div className="pt-4">
                    <StatusRow
                        label="AI Analysis"
                        ready={!!analysis.ready}
                        detail={layerDetail(analysis)}
                    />
                </div>
            </div>
        </section>
    );
}
