import { useT } from '@/i18n';

function Spinner({ label }) {
    return (
        <div className="flex items-center gap-3 text-slate-600">
            <span className="h-5 w-5 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-600" />
            <span className="text-sm font-medium">{label}</span>
        </div>
    );
}

function labelize(value) {
    if (!value) {
        return '—';
    }
    return String(value).replaceAll('_', ' ');
}

function na(value, fallback = 'N/A') {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }
    return value;
}

function bandFor(score) {
    if (score === null || score === undefined) {
        return null;
    }
    if (score >= 80) {
        return 'good';
    }
    if (score >= 60) {
        return 'warning';
    }
    return 'poor';
}

function toneClass(tone) {
    if (tone === 'positive' || tone === 'good') {
        return 'bg-emerald-50 text-emerald-800';
    }
    if (tone === 'warning') {
        return 'bg-amber-50 text-amber-800';
    }
    if (tone === 'critical' || tone === 'poor') {
        return 'bg-slate-100 text-slate-800';
    }
    return 'bg-slate-50 text-slate-700';
}

function timelineTone(type) {
    if (type === 'positive' || type === 'buying_signal') {
        return 'positive';
    }
    if (type === 'critical') {
        return 'critical';
    }
    if (type === 'warning' || type === 'objection' || type === 'missed_opportunity') {
        return 'warning';
    }
    return 'neutral';
}

function ScoreBar({ score, label }) {
    const t = useT();
    if (score === null || score === undefined) {
        return (
            <div>
                {label && <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>}
                <p className="mt-1 text-sm text-slate-500">{t('common.na')}</p>
            </div>
        );
    }

    const width = Math.max(0, Math.min(100, Number(score)));
    const band = bandFor(score);

    return (
        <div>
            {label && (
                <div className="flex items-baseline justify-between gap-2">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
                    <p className="text-sm font-semibold text-slate-900">{score}</p>
                </div>
            )}
            <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div
                    className={`h-full rounded-full ${band === 'good' ? 'bg-emerald-700' : band === 'warning' ? 'bg-amber-600' : 'bg-slate-500'}`}
                    style={{ width: `${width}%` }}
                />
            </div>
        </div>
    );
}

function Badge({ children, tone = 'neutral' }) {
    return <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${toneClass(tone)}`}>{children}</span>;
}

function Meta({ time, speaker, quote }) {
    if (!time && !speaker && !quote) {
        return null;
    }

    return (
        <p className="mt-1 text-xs text-slate-500">
            {time ? `${time} ` : ''}
            {speaker || ''}
            {quote ? ` “${quote}”` : ''}
        </p>
    );
}

function FindingList({ items }) {
    const t = useT();
    if (!items?.length) {
        return <p className="mt-2 text-sm text-slate-500">{t('common.noneNoted')}</p>;
    }

    return (
        <ul className="mt-2 space-y-3">
            {items.map((item, index) => (
                <li key={`${item.text || item.signal || item.explanation}-${index}`} className="text-sm leading-6 text-slate-800">
                    <p>{item.text || item.signal || item.explanation}</p>
                    <Meta time={item.timestamp_label} speaker={item.speaker_label} quote={item.quote} />
                </li>
            ))}
        </ul>
    );
}

function PracticeList({ items }) {
    const t = useT();
    if (!items?.length) {
        return <p className="mt-2 text-sm text-slate-500">{t('common.noneNoted')}</p>;
    }

    return (
        <ul className="mt-2 space-y-2">
            {items.map((item, index) => (
                <li key={`${item.text}-${index}`} className="text-sm leading-6 text-slate-800">
                    <p>{item.text}</p>
                    {item.why && <p className="text-xs text-slate-500">{item.why}</p>}
                </li>
            ))}
        </ul>
    );
}

function SectionCard({ section }) {
    const t = useT();
    if (!section) {
        return null;
    }

    return (
        <div className="rounded-2xl border border-slate-200 p-4">
            <div className="flex items-start justify-between gap-3">
                <h3 className="text-sm font-semibold text-slate-900">{section.title}</h3>
                {section.applicable === false ? (
                    <Badge>{t('common.notApplicable')}</Badge>
                ) : (
                    <span className="text-sm font-semibold text-slate-900">{section.score ?? t('common.na')}</span>
                )}
            </div>
            {section.summary && <p className="mt-2 text-sm leading-6 text-slate-700">{section.summary}</p>}
            {section.applicable !== false && (
                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.strengths')}</p>
                        <FindingList items={section.strengths} />
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.issues')}</p>
                        <FindingList items={section.issues} />
                    </div>
                </div>
            )}
        </div>
    );
}

function Collapsible({ title, children, defaultOpen = false }) {
    return (
        <details className="rounded-2xl border border-slate-200 bg-white p-4" open={defaultOpen || undefined}>
            <summary className="cursor-pointer list-none text-sm font-semibold text-slate-900 [&::-webkit-details-marker]:hidden">
                {title}
            </summary>
            <div className="mt-3">{children}</div>
        </details>
    );
}

function CallTimeline({ items }) {
    const t = useT();
    if (!items?.length) {
        return null;
    }

    return (
        <ol className="relative ms-2 space-y-4 border-s border-slate-200 ps-5">
            {items.map((item, index) => (
                <li key={`${item.title}-${index}`} className="relative">
                    <span className={`absolute -start-[23px] mt-1.5 h-2.5 w-2.5 rounded-full ${
                        timelineTone(item.type) === 'positive'
                            ? 'bg-emerald-700'
                            : timelineTone(item.type) === 'critical'
                                ? 'bg-slate-700'
                                : timelineTone(item.type) === 'warning'
                                    ? 'bg-amber-600'
                                    : 'bg-slate-400'
                    }`} />
                    <div className="flex flex-wrap items-center gap-2">
                        {item.timestamp_label && <span className="text-xs text-slate-500">{item.timestamp_label}</span>}
                        <Badge tone={timelineTone(item.type)}>{t.enum('timeline', item.type)}</Badge>
                    </div>
                    <p className="mt-1 text-sm font-medium text-slate-900">{item.title}</p>
                    {item.description && <p className="mt-1 text-sm leading-6 text-slate-700">{item.description}</p>}
                    <Meta time={null} speaker={item.speaker_label} quote={item.quote} />
                </li>
            ))}
        </ol>
    );
}

function SignalGroups({ signals }) {
    const t = useT();
    if (!signals) {
        return <p className="text-sm text-slate-500">{t('common.noneNoted')}</p>;
    }

    const groups = [
        ['positive_signals', t('report.signalsPositive')],
        ['buying_signals', t('report.signalsBuying')],
        ['trust_signals', t('report.signalsTrust')],
        ['hesitation_signals', t('report.signalsHesitation')],
        ['negative_signals', t('report.signalsNegative')],
        ['risk_signals', t('report.signalsRisk')],
    ];

    const nonempty = groups.filter(([key]) => signals[key]?.length);

    if (!nonempty.length) {
        return <p className="text-sm text-slate-500">{t('common.noneNoted')}</p>;
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {nonempty.map(([key, label]) => (
                <div key={key}>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
                    <FindingList items={signals[key]} />
                </div>
            ))}
        </div>
    );
}

function ModeBadge({ mode, companyName }) {
    const t = useT();

    if (mode !== 'generic' && mode !== 'company') {
        return null;
    }

    return (
        <p className="mt-2">
            <Badge tone={mode === 'company' ? 'good' : 'neutral'}>
                {mode === 'company'
                    ? t('report.companyBadge', { name: companyName || '' })
                    : t('report.genericBadge')}
            </Badge>
        </p>
    );
}

export default function AnalysisReport({ status, report, message, error, analysisMode, companyName }) {
    const t = useT();
    const dash = t('common.na');
    const badge = <ModeBadge mode={analysisMode} companyName={companyName} />;

    if (status === 'uploading' || status === 'processing' || status === 'analyzing') {
        const label = {
            uploading: t('report.uploading'),
            processing: t('report.transcribing'),
            analyzing: t('report.analyzing'),
        }[status];

        return (
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">{t('report.title')}</h2>
                {badge}
                <div className="mt-6">
                    <Spinner label={label} />
                </div>
            </section>
        );
    }

    if (status === 'uploaded' && !report) {
        return (
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">{t('report.title')}</h2>
                {badge}
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    {message || t('report.queued')}
                </p>
            </section>
        );
    }

    if ((status === 'transcribed' || status === 'analysis_pending') && !report) {
        return (
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">{t('report.title')}</h2>
                {badge}
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    {message || t('report.transcribed')}
                </p>
            </section>
        );
    }

    if (status === 'failed') {
        return (
            <section className="rounded-3xl border border-red-100 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">{t('report.title')}</h2>
                {badge}
                <p className="mt-3 text-sm text-red-700">{error || message || t('report.failed')}</p>
            </section>
        );
    }

    if (!report) {
        return null;
    }

    const exec = report.executive_summary;
    const sections = report.sections || {};
    const metrics = report.conversation_metrics || {};
    const isDeep = Boolean(exec);

    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">{t('report.title')}</h2>
            {badge}

            <div className="mt-6 flex flex-wrap items-end gap-8">
                <div>
                    <p className="text-5xl font-semibold tracking-tight text-slate-900">{na(report.overall_score, dash)}</p>
                    <p className="mt-1 text-sm font-medium text-slate-500">{t('report.overall')}</p>
                </div>
                {report.company_scorecard_score !== null && report.company_scorecard_score !== undefined && (
                    <div>
                        <p className="text-5xl font-semibold tracking-tight text-slate-900">{report.company_scorecard_score}</p>
                        <p className="mt-1 text-sm font-medium text-slate-500">{t('report.companyScorecard')}</p>
                    </div>
                )}
            </div>

            {exec ? (
                <div className="mt-6 space-y-4">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.executive')}</h3>
                    <p className="text-base leading-7 text-slate-900">{exec.one_sentence}</p>
                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.outcome')}</dt>
                            <dd className="mt-1 text-slate-800">{t.enum('outcome', report.call_outcome)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.intent')}</dt>
                            <dd className="mt-1 text-slate-800">
                                {t.enum('intent', report.customer_intent)}
                                {report.customer_intent_confidence ? ` · ${t('common.confidence', { value: t.enum('confidence', report.customer_intent_confidence) })}` : ''}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.biggestStrength')}</dt>
                            <dd className="mt-1 text-slate-800">{exec.biggest_strength}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.biggestProblem')}</dt>
                            <dd className="mt-1 text-slate-800">{exec.biggest_problem}</dd>
                        </div>
                    </dl>
                    <p className="text-sm leading-6 text-slate-700">{exec.what_happened}</p>
                    <p className="text-sm leading-6 text-slate-700">{exec.why_it_ended_this_way}</p>
                    <p className="text-sm font-medium text-slate-900">{t('report.next', { action: exec.best_next_action })}</p>
                </div>
            ) : (
                <>
                    {report.summary && (
                        <div className="mt-6">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.summary')}</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-800">{report.summary}</p>
                        </div>
                    )}
                    <dl className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.callOutcome')}</dt>
                            <dd className="mt-1 text-slate-800">{t.enum('outcome', report.call_outcome)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.intent')}</dt>
                            <dd className="mt-1 text-slate-800">{t.enum('intent', report.customer_intent)}</dd>
                        </div>
                    </dl>
                </>
            )}

            {(metrics.seller_talk_percent !== null && metrics.seller_talk_percent !== undefined) && (
                <p className="mt-4 text-xs text-slate-500">
                    {t('report.talkBalance', { seller: metrics.seller_talk_percent, customer: metrics.customer_talk_percent })}
                    {metrics.speaker_switches !== null ? ` · ${t('report.speakerSwitches', { count: metrics.speaker_switches })}` : ''}
                </p>
            )}

            {report.timeline?.length > 0 && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.timeline')}</h3>
                    <div className="mt-4">
                        <CallTimeline items={report.timeline} />
                    </div>
                </div>
            )}

            {report.customer_signals && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.keySignals')}</h3>
                    <div className="mt-3">
                        <SignalGroups signals={report.customer_signals} />
                    </div>
                </div>
            )}

            {report.critical_mistakes?.length > 0 && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.criticalMistakes')}</h3>
                    <ul className="mt-3 space-y-3">
                        {report.critical_mistakes.map((item, index) => (
                            <li key={`${item.mistake}-${index}`} className="rounded-2xl border border-slate-200 p-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge tone="critical">{t('report.impact', { impact: t.enum('impact', item.impact) })}</Badge>
                                </div>
                                <p className="mt-2 text-sm font-medium text-slate-900">{item.mistake}</p>
                                {item.why && <p className="mt-1 text-sm leading-6 text-slate-700">{item.why}</p>}
                                {item.better_action && <p className="mt-2 text-sm text-slate-800">{t('report.instead', { action: item.better_action })}</p>}
                                {item.example_phrase && <p className="mt-1 text-sm text-slate-600">“{item.example_phrase}”</p>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {(report.what_to_repeat?.length > 0 || report.strengths?.length > 0) && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.whatWorked')}</h3>
                    {report.what_to_repeat?.length ? <PracticeList items={report.what_to_repeat} /> : <FindingList items={report.strengths} />}
                </div>
            )}

            <div className="mt-8 space-y-3">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.deepAnalysis')}</h3>
                {report.discovery_depth && (
                    <Collapsible title={t('report.discovery')}>
                        <ScoreBar score={report.discovery_depth.score} label={t('report.discoveryDepth')} />
                        <p className="mt-3 text-sm leading-6 text-slate-700">{report.discovery_depth.summary}</p>
                        <p className="mt-2 text-sm text-slate-700">{t('report.needsFound', { value: report.discovery_depth.needs_discovered?.join('; ') || t('common.none') })}</p>
                        <p className="mt-1 text-sm text-slate-700">{t('report.notExplored', { value: report.discovery_depth.needs_not_explored?.join('; ') || t('common.none') })}</p>
                    </Collapsible>
                )}
                {report.question_analysis && (
                    <Collapsible title={t('report.questions')}>
                        <p className="text-sm text-slate-700">{report.question_analysis.summary}</p>
                        <p className="mt-2 text-xs text-slate-500">{t('report.estimatedQuestions', { value: na(report.question_analysis.total_questions_estimate, dash) })}</p>
                    </Collapsible>
                )}
                {report.listening && (
                    <Collapsible title={t('report.listening')}>
                        <ScoreBar score={report.listening.score} label={t('report.listening')} />
                        <p className="mt-3 text-sm leading-6 text-slate-700">{report.listening.summary}</p>
                        <p className="mt-2 text-sm text-slate-700">{report.listening.paraphrasing_quality}</p>
                    </Collapsible>
                )}
                {report.value_communication && (
                    <Collapsible title={t('report.value')}>
                        <ScoreBar score={report.value_communication.score} label={t('report.valueCommunication')} />
                        <p className="mt-3 text-sm leading-6 text-slate-700">{report.value_communication.summary}</p>
                    </Collapsible>
                )}
                {(report.objection_map?.length > 0 || sections.objections) && (
                    <Collapsible title={t('report.objections')}>
                        {report.objection_map?.length ? (
                            <ul className="space-y-3">
                                {report.objection_map.map((item, index) => (
                                    <li key={`${item.objection}-${index}`} className="text-sm text-slate-800">
                                        <p className="font-medium">{item.objection} · {labelize(item.category)}</p>
                                        <p className="mt-1 text-slate-700">{item.better_response || item.what_was_missing}</p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <SectionCard section={sections.objections} />
                        )}
                    </Collapsible>
                )}
                {report.negotiation && (
                    <Collapsible title={t('report.negotiation')}>
                        {report.negotiation.applicable === false ? (
                            <p className="text-sm text-slate-500">{t('report.negotiationNa')}</p>
                        ) : (
                            <>
                                <ScoreBar score={report.negotiation.score} label={t('report.negotiation')} />
                                <p className="mt-3 text-sm leading-6 text-slate-700">{report.negotiation.summary}</p>
                            </>
                        )}
                    </Collapsible>
                )}
                {report.trust_rapport && (
                    <Collapsible title={t('report.rapport')}>
                        <ScoreBar score={report.trust_rapport.score} label={t('report.trustRapport')} />
                        <p className="mt-3 text-sm leading-6 text-slate-700">{report.trust_rapport.summary}</p>
                        <p className="mt-2 text-sm text-slate-600">{report.trust_rapport.tone_assessment}</p>
                    </Collapsible>
                )}
                {report.closing && (
                    <Collapsible title={t('report.closing')}>
                        <ScoreBar score={report.closing.score} label={t('report.closing')} />
                        <p className="mt-3 text-sm leading-6 text-slate-700">{report.closing.summary}</p>
                        <p className="mt-2 text-sm text-slate-800">{report.closing.better_closing}</p>
                    </Collapsible>
                )}
                <Collapsible title={t('report.sectionScores')}>
                    <div className="space-y-4">
                        <SectionCard section={sections.opening_rapport} />
                        <SectionCard section={sections.discovery_needs} />
                        <SectionCard section={sections.questions_listening} />
                        <SectionCard section={sections.presentation_value} />
                        <SectionCard section={sections.objections} />
                        <SectionCard section={sections.pricing_negotiation} />
                        <SectionCard section={sections.closing_next_step} />
                    </div>
                </Collapsible>
            </div>

            {(report.missed_signals?.length > 0 || report.missed_opportunities?.length > 0) && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.missed')}</h3>
                    {report.missed_signals?.length ? (
                        <ul className="mt-3 space-y-3">
                            {report.missed_signals.map((item, index) => (
                                <li key={`${item.signal}-${index}`} className="text-sm leading-6 text-slate-800">
                                    <p className="font-medium">{item.signal}</p>
                                    <p className="mt-1 text-slate-700">{item.recommended_action}</p>
                                    {item.better_response && <p className="mt-1 text-slate-600">“{item.better_response}”</p>}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <FindingList items={report.missed_opportunities} />
                    )}
                </div>
            )}

            {report.better_phrases?.length > 0 && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.betterPhrases')}</h3>
                    <ul className="mt-2 space-y-3">
                        {report.better_phrases.map((phrase, index) => (
                            <li key={`${phrase.better || phrase.suggested}-${index}`} className="text-sm leading-6 text-slate-800">
                                {phrase.original && <p className="text-slate-500">{t('report.insteadOf', { original: phrase.original })}</p>}
                                <p>{phrase.better || phrase.suggested}</p>
                                {(phrase.why_better || phrase.reason) && <p className="text-xs text-slate-500">{phrase.why_better || phrase.reason}</p>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {report.coaching_priorities?.length > 0 && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.coaching')}</h3>
                    <ol className="mt-3 space-y-3">
                        {report.coaching_priorities.map((item) => (
                            <li key={item.priority} className="rounded-2xl border border-slate-200 p-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.priority', { n: item.priority })}</p>
                                <p className="mt-1 text-sm font-semibold text-slate-900">{item.skill}</p>
                                <p className="mt-1 text-sm leading-6 text-slate-700">{item.why}</p>
                                <p className="mt-2 text-sm text-slate-800">{item.practice}</p>
                                {item.success_criteria && <p className="mt-1 text-xs text-slate-500">{t('report.doneWhen', { value: item.success_criteria })}</p>}
                            </li>
                        ))}
                    </ol>
                </div>
            )}

            {report.next_call_playbook && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.playbook')}</h3>
                    <div className="mt-3 grid gap-4 sm:grid-cols-2 text-sm">
                        {['before_call', 'during_call', 'closing', 'follow_up'].map((key) => (
                            <div key={key}>
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t.enum('playbook', key)}</p>
                                <ul className="mt-2 list-disc space-y-1 ps-4 text-slate-800">
                                    {(report.next_call_playbook[key] || []).map((line) => (
                                        <li key={line}>{line}</li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {report.alternative_path && (
                <div className="mt-8">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.alternative')}</h3>
                    <p className="mt-2 text-sm leading-6 text-slate-800">{report.alternative_path.summary}</p>
                    <ol className="mt-3 space-y-2">
                        {(report.alternative_path.steps || []).map((step, index) => (
                            <li key={`${step.stage}-${index}`} className="text-sm text-slate-800">
                                <span className="font-medium capitalize">{labelize(step.stage)}:</span> {step.what_to_do}
                                {step.example_phrase && <p className="mt-1 text-slate-600">“{step.example_phrase}”</p>}
                            </li>
                        ))}
                    </ol>
                </div>
            )}

            {(report.what_to_stop?.length > 0 || report.what_to_start?.length > 0) && (
                <div className="mt-8 grid gap-6 sm:grid-cols-2">
                    <div>
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.whatToStop')}</h3>
                        <PracticeList items={report.what_to_stop} />
                    </div>
                    <div>
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.whatToStart')}</h3>
                        <PracticeList items={report.what_to_start} />
                    </div>
                </div>
            )}

            {!isDeep && (
                <>
                    <div className="mt-8">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.strengths')}</h3>
                        <FindingList items={report.strengths} />
                    </div>
                    <div className="mt-6">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.weaknesses')}</h3>
                        <FindingList items={report.weaknesses} />
                    </div>
                    <div className="mt-6">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.recommendations')}</h3>
                        <FindingList items={report.recommendations} />
                    </div>
                    {report.next_step && (
                        <div className="mt-6">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.nextStep')}</h3>
                            <p className="mt-2 text-sm leading-6 text-slate-800">{report.next_step}</p>
                        </div>
                    )}
                </>
            )}

            {report.company_context_used && report.company_specific && (
                <CompanySpecific specific={report.company_specific} />
            )}
        </section>
    );
}

function CompanySpecific({ specific }) {
    const t = useT();
    const asked = specific.mandatory_questions?.asked || [];
    const missed = specific.mandatory_questions?.missed || [];
    const violations = specific.forbidden_claims?.violations || [];
    const matched = specific.objection_handling?.matched || [];
    const offeringIssues = specific.offering_accuracy?.issues || [];
    const criteria = specific.scorecard?.criteria || [];

    return (
        <div className="mt-8 space-y-6">
            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.companySpecific')}</h3>
            {specific.script_adherence && (
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.scriptAdherence')}</p>
                    <p className="mt-1 text-sm text-slate-800">
                        {specific.script_adherence.applicable === false
                            ? t('common.notApplicable')
                            : t('report.scoreNa', { score: specific.script_adherence.score ?? t('common.na') })}
                    </p>
                    {specific.script_adherence.summary && (
                        <p className="mt-1 text-sm leading-6 text-slate-700">{specific.script_adherence.summary}</p>
                    )}
                    <FindingList items={specific.script_adherence.issues} />
                </div>
            )}
            <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('analytics.mandatoryQuestions')}</p>
                <p className="mt-2 text-sm text-slate-800">{t('report.asked', { value: asked.length ? asked.join(', ') : t('report.noneNotedLower') })}</p>
                <p className="mt-1 text-sm text-slate-800">{t('report.missedList', { value: missed.length ? missed.join(', ') : t('report.noneNotedLower') })}</p>
            </div>
            <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.forbiddenClaims')}</p>
                <FindingList items={violations} />
            </div>
            <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.objectionHandling')}</p>
                {matched.length ? (
                    <ul className="mt-2 space-y-2 text-sm text-slate-800">
                        {matched.map((item, index) => (
                            <li key={`${item.objection}-${index}`}>
                                {item.objection}{item.handled === false ? t('report.notHandled') : ''}
                                {item.summary ? ` · ${item.summary}` : ''}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="mt-2 text-sm text-slate-500">{t('common.noneNoted')}</p>
                )}
            </div>
            <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.offeringAccuracy')}</p>
                <FindingList items={offeringIssues} />
            </div>
            {criteria.length > 0 && (
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.scorecardCriteria')}</p>
                    <ul className="mt-2 space-y-3">
                        {criteria.map((item) => (
                            <li key={item.key} className="text-sm text-slate-800">
                                <p className="font-medium">
                                    {item.applicable === false
                                        ? t('report.criterionNa', { key: item.key })
                                        : t('report.criterionScore', { key: item.key, score: item.score ?? t('common.na'), max: item.max_score ?? 100 })}
                                </p>
                                {item.summary && <p className="mt-1 text-slate-700">{item.summary}</p>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
