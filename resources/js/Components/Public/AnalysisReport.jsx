const REPORT_SECTIONS = [
    { key: 'overall_score', title: 'Overall Score' },
    { key: 'summary', title: 'Summary' },
    { key: 'opening_rapport', title: 'Opening / Rapport' },
    { key: 'discovery_needs', title: 'Discovery & Needs' },
    { key: 'questions_listening', title: 'Questions & Listening' },
    { key: 'presentation_value', title: 'Presentation & Value' },
    { key: 'objections', title: 'Objections' },
    { key: 'pricing_negotiation', title: 'Pricing / Negotiation' },
    { key: 'closing_next_step', title: 'Closing & Next Step' },
    { key: 'strengths', title: 'Strengths' },
    { key: 'weaknesses', title: 'Weaknesses' },
    { key: 'missed_opportunities', title: 'Missed Opportunities' },
    { key: 'recommendations', title: 'Recommendations' },
    { key: 'suggested_responses', title: 'Better Phrases / Suggested Responses' },
];

function Spinner({ label }) {
    return (
        <div className="flex items-center gap-3 text-slate-600">
            <span className="h-5 w-5 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600" />
            <span className="text-sm font-medium">{label}</span>
        </div>
    );
}

export default function AnalysisReport({ status, report, message }) {
    if (status === 'uploading' || status === 'processing') {
        return (
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>
                <div className="mt-6">
                    <Spinner label={status === 'uploading' ? 'Uploading your call…' : 'Analyzing the conversation…'} />
                </div>
            </section>
        );
    }

    if (status === 'uploaded' && !report) {
        return (
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    {message || 'Call uploaded successfully. Analysis engine is not connected yet.'}
                </p>
            </section>
        );
    }

    if (status === 'failed') {
        return (
            <section className="rounded-3xl border border-red-100 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>
                <p className="mt-3 text-sm text-red-700">Analysis failed. Please try again.</p>
            </section>
        );
    }

    if (!report) {
        return null;
    }

    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Analysis report</h2>
            <div className="mt-6 space-y-6">
                {REPORT_SECTIONS.map((section) => {
                    const value = report[section.key];
                    if (value === undefined || value === null || value === '') {
                        return null;
                    }

                    return (
                        <div key={section.key}>
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{section.title}</h3>
                            <div className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-800">
                                {Array.isArray(value) ? value.join('\n') : String(value)}
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
