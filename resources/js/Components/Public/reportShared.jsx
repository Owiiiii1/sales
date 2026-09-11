import { useState } from 'react';
import { useT } from '@/i18n';

export function labelize(value) {
    if (!value) {
        return '—';
    }
    return String(value).replaceAll('_', ' ');
}

export function na(value, fallback = 'N/A') {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }
    return value;
}

export function bandFor(score) {
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

export function toneClass(tone) {
    if (tone === 'positive' || tone === 'good') {
        return 'bg-emerald-50 text-emerald-800';
    }
    if (tone === 'warning') {
        return 'bg-amber-50 text-amber-800';
    }
    if (tone === 'critical' || tone === 'poor') {
        return 'bg-rose-50 text-rose-800';
    }
    return 'bg-slate-50 text-slate-700';
}

export function timelineTone(type) {
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

export function criterionName(item) {
    return item?.name || labelize(item?.key);
}

export function hasItems(items) {
    return Array.isArray(items) && items.length > 0;
}

export function Badge({ children, tone = 'neutral' }) {
    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${toneClass(tone)}`}>
            {children}
        </span>
    );
}

export function Meta({ time, speaker, quote }) {
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

export function ScoreBar({ score, label, max = 100 }) {
    const t = useT();
    if (score === null || score === undefined) {
        return (
            <div>
                {label && <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>}
                <p className="mt-1 text-sm text-slate-500">{t('common.na')}</p>
            </div>
        );
    }

    const width = Math.max(0, Math.min(100, (Number(score) / Math.max(1, Number(max))) * 100));
    const band = bandFor((Number(score) / Math.max(1, Number(max))) * 100);

    return (
        <div>
            {label && (
                <div className="flex items-baseline justify-between gap-2">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
                    <p className="text-sm font-semibold text-slate-900">{score}{max && max !== 100 ? ` / ${max}` : ''}</p>
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

export function ModeBadge({ mode, companyName, employeeName }) {
    const t = useT();

    if (mode !== 'generic' && mode !== 'company') {
        return null;
    }

    return (
        <p className="mt-2 flex flex-wrap gap-2">
            <Badge tone={mode === 'company' ? 'good' : 'neutral'}>
                {mode === 'company'
                    ? t('report.companyBadge', { name: companyName || '' })
                    : t('report.genericBadge')}
            </Badge>
            {mode === 'company' && employeeName ? (
                <Badge tone="neutral">{t('report.employeeBadge', { name: employeeName })}</Badge>
            ) : null}
        </p>
    );
}

export function ScoreSummary({ report }) {
    const t = useT();
    const dash = t('common.na');

    return (
        <div>
            <div className="flex flex-wrap items-end gap-8">
                {report.primary_score_kind === 'company' ? (
                    <>
                        <div>
                            <p className="text-5xl font-semibold tracking-tight text-slate-900">{na(report.company_scorecard_score, dash)}</p>
                            <p className="mt-1 text-sm font-medium text-slate-500">{t('report.companyScore')}</p>
                            {report.company_score_band ? (
                                <p className="mt-1 text-xs text-slate-500">{report.company_score_band}</p>
                            ) : null}
                        </div>
                        <div>
                            <p className="text-3xl font-semibold tracking-tight text-slate-700">{na(report.overall_score, dash)}</p>
                            <p className="mt-1 text-sm font-medium text-slate-500">{t('report.generalSalesScore')}</p>
                        </div>
                    </>
                ) : (
                    <div>
                        <p className="text-5xl font-semibold tracking-tight text-slate-900">{na(report.overall_score, dash)}</p>
                        <p className="mt-1 text-sm font-medium text-slate-500">{t('report.overall')}</p>
                    </div>
                )}
            </div>
            {report.primary_score_kind === 'company' && (report.weighted_company_score !== null && report.weighted_company_score !== undefined) && (
                <div className="mt-3 space-y-1 text-sm text-slate-600">
                    <p>{t('report.weightedScore', { score: report.weighted_company_score })}</p>
                    <p>{t('report.finalScore', { score: report.company_scorecard_score })}</p>
                    {(report.triggered_caps || []).map((cap) => (
                        <p key={cap.id || cap.name}>{t('report.capApplied', { name: cap.name || cap.description || cap.trigger_type })}</p>
                    ))}
                </div>
            )}
        </div>
    );
}

export function ExecutiveSummaryCard({ report }) {
    const t = useT();
    const exec = report.executive_summary;

    if (!exec) {
        return report.summary ? (
            <div>
                <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.summary')}</h3>
                <p className="mt-2 text-sm leading-6 text-slate-800">{report.summary}</p>
            </div>
        ) : null;
    }

    return (
        <div className="space-y-4">
            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('report.executive')}</h3>
            <p className="text-base leading-7 text-slate-900">{exec.one_sentence}</p>
            <dl className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
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
                {exec.biggest_strength ? (
                    <div>
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.biggestStrength')}</dt>
                        <dd className="mt-1 text-slate-800">{exec.biggest_strength}</dd>
                    </div>
                ) : null}
                {exec.biggest_problem ? (
                    <div>
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.biggestProblem')}</dt>
                        <dd className="mt-1 text-slate-800">{exec.biggest_problem}</dd>
                    </div>
                ) : null}
            </dl>
            {exec.what_happened ? <p className="text-sm leading-6 text-slate-700">{exec.what_happened}</p> : null}
            {exec.why_it_ended_this_way ? <p className="text-sm leading-6 text-slate-700">{exec.why_it_ended_this_way}</p> : null}
            {exec.best_next_action ? (
                <p className="text-sm font-medium text-slate-900">{t('report.next', { action: exec.best_next_action })}</p>
            ) : null}
        </div>
    );
}

export function ReportGroup({ title, children }) {
    if (!children) {
        return null;
    }

    return (
        <section className="rounded-3xl border border-slate-200 bg-slate-50/70 p-6 shadow-sm">
            {title ? <h2 className="text-lg font-semibold tracking-tight text-slate-900">{title}</h2> : null}
            <div className={title ? 'mt-5 space-y-4' : 'space-y-4'}>{children}</div>
        </section>
    );
}

export function InnerCard({ children, tone = 'neutral' }) {
    const toneMap = {
        critical: 'border-rose-200 bg-rose-50/70',
        positive: 'border-emerald-200 bg-emerald-50/70',
        coaching: 'border-slate-200 bg-white',
        neutral: 'border-slate-200 bg-white',
    };

    return <div className={`rounded-2xl border p-4 shadow-sm ${toneMap[tone] || toneMap.neutral}`}>{children}</div>;
}

export function ExpandCollapse({ title, meta, subtitle, defaultOpen = false, children }) {
    const t = useT();
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <button
                type="button"
                className="flex w-full items-center gap-3 px-4 py-3 text-left"
                onClick={() => setOpen((current) => !current)}
            >
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-slate-900">{title}</p>
                    {subtitle ? <p className="mt-0.5 text-xs text-slate-500">{subtitle}</p> : null}
                </div>
                {meta}
                <span className="shrink-0 rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                    {open ? t('report.collapse') : t('report.expand')}
                </span>
            </button>
            {open ? <div className="border-t border-slate-100 px-4 py-4">{children}</div> : null}
        </div>
    );
}

export function FindingList({ items }) {
    if (!hasItems(items)) {
        return null;
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

export function PracticeList({ items }) {
    if (!hasItems(items)) {
        return null;
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

export function CriticalMistakeCard({ item }) {
    const t = useT();
    if (!item?.mistake) {
        return null;
    }

    return (
        <InnerCard tone="critical">
            <div className="flex flex-wrap items-center gap-2">
                <p className="text-sm font-semibold text-slate-900">{item.mistake}</p>
                {item.impact ? <Badge tone="critical">{t('report.impact', { impact: t.enum('impact', item.impact) })}</Badge> : null}
            </div>
            <Meta time={item.timestamp_label} quote={item.quote} />
            {item.why ? <p className="mt-2 text-sm leading-6 text-slate-700">{item.why}</p> : null}
            {item.better_action ? <p className="mt-2 text-sm text-slate-800">{t('report.instead', { action: item.better_action })}</p> : null}
            {item.example_phrase ? <p className="mt-1 text-sm text-slate-600">“{item.example_phrase}”</p> : null}
        </InnerCard>
    );
}

export function MissedOpportunityCard({ item }) {
    const t = useT();
    const signal = item.signal || item.text;
    if (!signal) {
        return null;
    }

    return (
        <InnerCard>
            <p className="text-sm font-semibold text-slate-900">{signal}</p>
            <Meta time={item.timestamp_label} speaker={item.speaker_label} quote={item.quote} />
            {item.seller_response_quality ? (
                <p className="mt-2 text-xs text-slate-500">{t('report.sellerResponse', { value: labelize(item.seller_response_quality) })}</p>
            ) : null}
            {item.impact ? <p className="mt-1 text-xs text-slate-500">{t('report.impact', { impact: t.enum('impact', item.impact) })}</p> : null}
            {item.recommended_action ? <p className="mt-2 text-sm text-slate-800">{item.recommended_action}</p> : null}
            {item.better_response ? <p className="mt-1 text-sm text-slate-600">“{item.better_response}”</p> : null}
        </InnerCard>
    );
}

export function BetterPhraseCard({ phrase }) {
    const t = useT();
    if (!phrase?.better && !phrase?.original) {
        return null;
    }

    return (
        <InnerCard>
            {phrase.original ? <p className="text-sm text-slate-500">{t('report.was', { original: phrase.original })}</p> : null}
            {phrase.problem ? <p className="mt-1 text-sm text-slate-700">{t('report.problem', { value: phrase.problem })}</p> : null}
            {phrase.better ? <p className="mt-2 text-sm font-medium text-slate-900">{t('report.sayInstead', { value: phrase.better })}</p> : null}
            {(phrase.why_better || phrase.reason) ? (
                <p className="mt-1 text-xs text-slate-500">{t('report.whyBetter', { value: phrase.why_better || phrase.reason })}</p>
            ) : null}
            <Meta time={phrase.timestamp_label} />
        </InnerCard>
    );
}

export function CoachingPriorityCard({ item }) {
    const t = useT();
    if (!item?.skill) {
        return null;
    }

    return (
        <InnerCard tone="coaching">
            <div className="flex items-start gap-3">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
                    {item.priority}
                </span>
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-slate-900">{item.skill}</p>
                    {item.why ? <p className="mt-1 text-sm leading-6 text-slate-700">{item.why}</p> : null}
                    {hasItems(item.evidence) ? (
                        <p className="mt-2 text-xs text-slate-500">{t('report.evidence', { value: item.evidence.join(' · ') })}</p>
                    ) : null}
                    {item.practice ? <p className="mt-2 text-sm text-slate-800">{item.practice}</p> : null}
                    {item.success_criteria ? <p className="mt-1 text-xs text-slate-500">{t('report.doneWhen', { value: item.success_criteria })}</p> : null}
                </div>
            </div>
        </InnerCard>
    );
}

export function PositiveCard({ item }) {
    if (!item?.text) {
        return null;
    }

    return (
        <InnerCard tone="positive">
            <p className="text-sm leading-6 text-slate-800">{item.text}</p>
            {item.why ? <p className="mt-1 text-xs text-slate-600">{item.why}</p> : null}
        </InnerCard>
    );
}

export function CallTimeline({ items }) {
    const t = useT();
    if (!hasItems(items)) {
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
                                ? 'bg-rose-600'
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

export function TimelineSection({ items }) {
    const t = useT();
    if (!hasItems(items)) {
        return null;
    }

    return (
        <ExpandCollapse
            title={t('report.timeline')}
            subtitle={t('report.timelineCount', { count: items.length })}
            defaultOpen={false}
        >
            <CallTimeline items={items} />
        </ExpandCollapse>
    );
}

export function ScorecardTable({ criteria }) {
    const t = useT();
    if (!hasItems(criteria)) {
        return null;
    }

    const applicable = criteria.filter((item) => item.applicable !== false);
    const notApplicable = criteria.filter((item) => item.applicable === false);

    return (
        <div>
            {applicable.length > 0 && (
                <ul className="space-y-3">
                    {applicable.map((item) => (
                        <li key={item.key}>
                            <ScoreBar
                                score={item.score}
                                max={item.max_score || 100}
                                label={`${criterionName(item)} — ${item.score ?? t('common.na')} / ${item.max_score ?? 100}`}
                            />
                            {item.summary ? <p className="mt-1 text-xs text-slate-600">{item.summary}</p> : null}
                        </li>
                    ))}
                </ul>
            )}
            {notApplicable.length > 0 && (
                <div className="mt-4 border-t border-slate-200 pt-3">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.notApplicableGroup')}</p>
                    <p className="mt-1 text-sm text-slate-600">{notApplicable.map(criterionName).join(', ')}</p>
                </div>
            )}
        </div>
    );
}

export function ScorecardSummary({ summary }) {
    const t = useT();
    if (!summary || (!hasItems(summary.weakest) && !hasItems(summary.strongest))) {
        return null;
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {hasItems(summary.weakest) ? (
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.weakestCriteria')}</p>
                    <ul className="mt-2 space-y-1 text-sm text-slate-800">
                        {summary.weakest.map((item) => (
                            <li key={item.key}>{criterionName(item)} — {item.score} / {item.max_score}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
            {hasItems(summary.strongest) ? (
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.strongestCriteria')}</p>
                    <ul className="mt-2 space-y-1 text-sm text-slate-800">
                        {summary.strongest.map((item) => (
                            <li key={item.key}>{criterionName(item)} — {item.score} / {item.max_score}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}
