function Spinner({ label }) {
    return (
        <div className="flex items-center gap-3 text-slate-600">
            <span className="h-5 w-5 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600" />
            <span className="text-sm font-medium">{label}</span>
        </div>
    );
}

function FindingList({ items }) {
    if (!items?.length) {
        return <p className="mt-2 text-sm text-slate-500">None noted.</p>;
    }

    return (
        <ul className="mt-2 space-y-3">
            {items.map((item, index) => (
                <li key={`${item.text}-${index}`} className="text-sm leading-6 text-slate-800">
                    <p>{item.text}</p>
                    {(item.quote || item.speaker_label || item.timestamp_label) && (
                        <p className="mt-1 text-xs text-slate-500">
                            {item.timestamp_label ? `${item.timestamp_label} ` : ''}
                            {item.speaker_label || ''}
                            {item.quote ? ` “${item.quote}”` : ''}
                        </p>
                    )}
                </li>
            ))}
        </ul>
    );
}

function SectionCard({ section }) {
    if (!section) {
        return null;
    }

    return (
        <div className="rounded-2xl border border-slate-200 p-4">
            <div className="flex items-start justify-between gap-3">
                <h3 className="text-sm font-semibold text-slate-900">{section.title}</h3>
                {section.applicable === false ? (
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-400">Not applicable</span>
                ) : (
                    <span className="text-sm font-semibold text-slate-900">{section.score ?? '—'}</span>
                )}
            </div>
            {section.summary && <p className="mt-2 text-sm leading-6 text-slate-700">{section.summary}</p>}
            {section.applicable !== false && (
                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Strengths</p>
                        <FindingList items={section.strengths} />
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Issues</p>
                        <FindingList items={section.issues} />
                    </div>
                </div>
            )}
        </div>
    );
}

function labelize(value) {
    if (!value) {
        return '—';
    }
    return String(value).replaceAll('_', ' ');
}

export default function AnalysisReport({ status, report, message, error }) {
    if (status === 'uploading' || status === 'processing' || status === 'analyzing') {
        const label = {
            uploading: 'Uploading your call…',
            processing: 'Transcribing your call…',
            analyzing: 'Analyzing your sales call…',
        }[status];

        return (
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>
                <div className="mt-6">
                    <Spinner label={label} />
                </div>
            </section>
        );
    }

    if (status === 'uploaded' && !report) {
        return (
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    {message || 'Your call is queued for transcription.'}
                </p>
            </section>
        );
    }

    if ((status === 'transcribed' || status === 'analysis_pending') && !report) {
        return (
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    {message || 'Transcription complete. AI analysis is not configured yet.'}
                </p>
            </section>
        );
    }

    if (status === 'failed') {
        return (
            <section className="rounded-3xl border border-red-100 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>
                <p className="mt-3 text-sm text-red-700">{error || message || 'Something went wrong. Please try again.'}</p>
            </section>
        );
    }

    if (!report) {
        return null;
    }

    const sections = report.sections || {};

    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>

            <div className="mt-6 flex items-end gap-3">
                <p className="text-5xl font-semibold tracking-tight text-slate-900">{report.overall_score ?? '—'}</p>
                <p className="pb-1 text-sm font-medium text-slate-500">Overall score</p>
            </div>

            {report.summary && (
                <div className="mt-6">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Summary</h3>
                    <p className="mt-2 text-sm leading-6 text-slate-800">{report.summary}</p>
                </div>
            )}

            <dl className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Call outcome</dt>
                    <dd className="mt-1 capitalize text-slate-800">{labelize(report.call_outcome)}</dd>
                </div>
                <div>
                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Customer intent</dt>
                    <dd className="mt-1 capitalize text-slate-800">{labelize(report.customer_intent)}</dd>
                </div>
            </dl>

            <div className="mt-8">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Strengths</h3>
                <FindingList items={report.strengths} />
            </div>
            <div className="mt-6">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Weaknesses</h3>
                <FindingList items={report.weaknesses} />
            </div>

            <div className="mt-8 space-y-4">
                <SectionCard section={sections.opening_rapport} />
                <SectionCard section={sections.discovery_needs} />
                <SectionCard section={sections.questions_listening} />
                <SectionCard section={sections.presentation_value} />
                <SectionCard section={sections.objections} />
                <SectionCard section={sections.pricing_negotiation} />
                <SectionCard section={sections.closing_next_step} />
            </div>

            <div className="mt-8">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Buying signals</h3>
                <FindingList items={report.buying_signals} />
            </div>
            <div className="mt-6">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Objections detected</h3>
                <FindingList items={report.objections_detected} />
            </div>
            <div className="mt-6">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Missed opportunities</h3>
                <FindingList items={report.missed_opportunities} />
            </div>
            <div className="mt-6">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Recommendations</h3>
                <FindingList items={report.recommendations} />
            </div>
            <div className="mt-6">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Better phrases</h3>
                {report.better_phrases?.length ? (
                    <ul className="mt-2 space-y-3">
                        {report.better_phrases.map((phrase, index) => (
                            <li key={`${phrase.suggested}-${index}`} className="text-sm leading-6 text-slate-800">
                                {phrase.original && <p className="text-slate-500">Instead of: {phrase.original}</p>}
                                <p>{phrase.suggested}</p>
                                {phrase.reason && <p className="text-xs text-slate-500">{phrase.reason}</p>}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="mt-2 text-sm text-slate-500">None noted.</p>
                )}
            </div>
            {report.next_step && (
                <div className="mt-6">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Next step</h3>
                    <p className="mt-2 text-sm leading-6 text-slate-800">{report.next_step}</p>
                </div>
            )}
        </section>
    );
}
