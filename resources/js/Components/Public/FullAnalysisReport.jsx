import {
    BetterPhraseCard,
    CoachingPriorityCard,
    CriticalMistakeCard,
    ExecutiveSummaryCard,
    ExpandCollapse,
    FindingList,
    InnerCard,
    MissedOpportunityCard,
    ModeBadge,
    PositiveCard,
    PracticeList,
    ReportGroup,
    ScoreBar,
    ScoreSummary,
    ScorecardTable,
    TimelineSection,
    hasItems,
    labelize,
    na,
} from '@/Components/Public/reportShared';
import { useT } from '@/i18n';

function SectionCard({ section }) {
    const t = useT();
    if (!section) {
        return null;
    }

    return (
        <InnerCard>
            <div className="flex items-start justify-between gap-3">
                <h3 className="text-sm font-semibold text-slate-900">{section.title}</h3>
                {section.applicable === false ? (
                    <span className="text-xs font-semibold text-slate-500">{t('common.notApplicable')}</span>
                ) : (
                    <span className="text-sm font-semibold text-slate-900">{section.score ?? t('common.na')}</span>
                )}
            </div>
            {section.summary && <p className="mt-2 text-sm leading-6 text-slate-700">{section.summary}</p>}
            {section.applicable !== false && (hasItems(section.strengths) || hasItems(section.issues)) && (
                <div className="mt-3 grid gap-4 sm:grid-cols-2">
                    {hasItems(section.strengths) ? (
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.strengths')}</p>
                            <FindingList items={section.strengths} />
                        </div>
                    ) : null}
                    {hasItems(section.issues) ? (
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('report.issues')}</p>
                            <FindingList items={section.issues} />
                        </div>
                    ) : null}
                </div>
            )}
        </InnerCard>
    );
}

function SignalGroups({ signals }) {
    const t = useT();
    if (!signals) {
        return null;
    }

    const groups = [
        ['positive_signals', t('report.signalsPositive')],
        ['buying_signals', t('report.signalsBuying')],
        ['trust_signals', t('report.signalsTrust')],
        ['hesitation_signals', t('report.signalsHesitation')],
        ['negative_signals', t('report.signalsNegative')],
        ['risk_signals', t('report.signalsRisk')],
    ].filter(([key]) => hasItems(signals[key]));

    if (!groups.length) {
        return null;
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {groups.map(([key, label]) => (
                <div key={key}>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
                    <FindingList items={signals[key]} />
                </div>
            ))}
        </div>
    );
}

function DeepSkill({ title, score, summary, extra, defaultOpen = false }) {
    if (!summary && (score === null || score === undefined) && !extra) {
        return null;
    }

    return (
        <ExpandCollapse
            title={title}
            meta={score !== null && score !== undefined ? (
                <span className="text-sm font-semibold text-slate-700">{score} / 100</span>
            ) : null}
            defaultOpen={defaultOpen}
        >
            {score !== null && score !== undefined ? <ScoreBar score={score} /> : null}
            {summary ? <p className="mt-3 text-sm leading-6 text-slate-700">{summary}</p> : null}
            {extra}
        </ExpandCollapse>
    );
}

export default function FullAnalysisReport({
    report,
    analysisMode,
    companyName,
    employeeName,
    onOpenTranscript,
    transcriptAvailable = false,
}) {
    const t = useT();
    const dash = t('common.na');

    if (!report) {
        return null;
    }

    const sections = report.sections || {};
    const metrics = report.conversation_metrics || {};
    const missed = hasItems(report.missed_signals) ? report.missed_signals : (report.missed_opportunities || []);
    const coaching = (report.coaching_priorities || []).slice(0, 5);
    const specific = report.company_context_used ? report.company_specific : null;
    const criteria = specific?.scorecard?.criteria || [];
    const playbook = report.next_call_playbook;
    const playbookHasContent = playbook && ['before_call', 'during_call', 'closing', 'follow_up']
        .some((key) => hasItems(playbook[key]));
    const signalsHaveContent = report.customer_signals && [
        'positive_signals', 'buying_signals', 'trust_signals', 'hesitation_signals', 'negative_signals', 'risk_signals',
    ].some((key) => hasItems(report.customer_signals[key]));

    const happened = hasItems(report.timeline) || signalsHaveContent || hasItems(report.turning_points);
    const wentWell = hasItems(report.what_to_repeat) || hasItems(report.strengths);
    const wentBadly = hasItems(report.critical_mistakes) || hasItems(missed) || hasItems(report.what_to_stop);
    const improve = hasItems(report.better_phrases) || report.alternative_path || hasItems(report.what_to_start);
    const deepBlocks = [
        report.discovery_depth,
        report.question_analysis,
        report.listening,
        report.value_communication,
        hasItems(report.objection_map) || sections.objections,
        report.negotiation,
        report.trust_rapport,
        report.closing,
        sections.opening_rapport,
    ].some(Boolean);
    const coachingGroup = hasItems(coaching) || playbookHasContent;
    const companyGroup = Boolean(specific);

    return (
        <div className="space-y-8">
            <ReportGroup title={t('report.groupOutcome')}>
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <ModeBadge mode={analysisMode} companyName={companyName} employeeName={employeeName} />
                    <div className="mt-4">
                        <ScoreSummary report={report} />
                    </div>
                    <div className="mt-6">
                        <ExecutiveSummaryCard report={report} />
                    </div>
                    {(metrics.seller_talk_percent !== null && metrics.seller_talk_percent !== undefined) && (
                        <p className="mt-4 text-xs text-slate-500">
                            {t('report.talkBalance', { seller: metrics.seller_talk_percent, customer: metrics.customer_talk_percent })}
                            {metrics.speaker_switches !== null ? ` · ${t('report.speakerSwitches', { count: metrics.speaker_switches })}` : ''}
                        </p>
                    )}
                </div>
            </ReportGroup>

            {happened ? (
                <ReportGroup title={t('report.groupWhatHappened')}>
                    <TimelineSection items={report.timeline} />
                    {signalsHaveContent ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.keySignals')}</p>
                            <div className="mt-3">
                                <SignalGroups signals={report.customer_signals} />
                            </div>
                        </InnerCard>
                    ) : null}
                    {hasItems(report.turning_points) ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.turningPoints')}</p>
                            <ul className="mt-3 space-y-3">
                                {report.turning_points.map((item, index) => (
                                    <li key={`${item.what_changed}-${index}`} className="text-sm text-slate-800">
                                        <p className="font-medium">{item.what_changed}</p>
                                        {item.before ? <p className="mt-1 text-slate-600">{item.before}</p> : null}
                                        {item.after ? <p className="mt-1 text-slate-600">{item.after}</p> : null}
                                    </li>
                                ))}
                            </ul>
                        </InnerCard>
                    ) : null}
                </ReportGroup>
            ) : null}

            {wentWell ? (
                <ReportGroup title={t('report.groupWhatWentWell')}>
                    {hasItems(report.what_to_repeat)
                        ? report.what_to_repeat.map((item, index) => <PositiveCard key={`${item.text}-${index}`} item={item} />)
                        : report.strengths.map((item, index) => <PositiveCard key={`${item.text}-${index}`} item={item} />)}
                </ReportGroup>
            ) : null}

            {wentBadly ? (
                <ReportGroup title={t('report.groupWhatWentBadly')}>
                    {hasItems(report.critical_mistakes)
                        ? report.critical_mistakes.map((item, index) => <CriticalMistakeCard key={`${item.mistake}-${index}`} item={item} />)
                        : null}
                    {hasItems(missed)
                        ? missed.map((item, index) => <MissedOpportunityCard key={`${item.signal || item.text}-${index}`} item={item} />)
                        : null}
                    {hasItems(report.what_to_stop) ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.whatToStop')}</p>
                            <PracticeList items={report.what_to_stop} />
                        </InnerCard>
                    ) : null}
                </ReportGroup>
            ) : null}

            {improve ? (
                <ReportGroup title={t('report.groupHowToImprove')}>
                    {hasItems(report.better_phrases)
                        ? report.better_phrases.map((phrase, index) => <BetterPhraseCard key={`${phrase.better}-${index}`} phrase={phrase} />)
                        : null}
                    {report.alternative_path ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.alternative')}</p>
                            <p className="mt-2 text-sm leading-6 text-slate-800">{report.alternative_path.summary}</p>
                            {hasItems(report.alternative_path.steps) ? (
                                <ol className="mt-3 space-y-2">
                                    {report.alternative_path.steps.map((step, index) => (
                                        <li key={`${step.stage}-${index}`} className="text-sm text-slate-800">
                                            <span className="font-medium capitalize">{labelize(step.stage)}:</span> {step.what_to_do}
                                            {step.example_phrase && <p className="mt-1 text-slate-600">“{step.example_phrase}”</p>}
                                        </li>
                                    ))}
                                </ol>
                            ) : null}
                        </InnerCard>
                    ) : null}
                    {hasItems(report.what_to_start) ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.whatToStart')}</p>
                            <PracticeList items={report.what_to_start} />
                        </InnerCard>
                    ) : null}
                </ReportGroup>
            ) : null}

            {deepBlocks ? (
                <ReportGroup title={t('report.groupDeepSkills')}>
                    {report.discovery_depth ? (
                        <DeepSkill
                            title={t('report.discovery')}
                            score={report.discovery_depth.score}
                            summary={report.discovery_depth.summary}
                            extra={(
                                <>
                                    <p className="mt-2 text-sm text-slate-700">{t('report.needsFound', { value: report.discovery_depth.needs_discovered?.join('; ') || t('common.none') })}</p>
                                    <p className="mt-1 text-sm text-slate-700">{t('report.notExplored', { value: report.discovery_depth.needs_not_explored?.join('; ') || t('common.none') })}</p>
                                </>
                            )}
                        />
                    ) : null}
                    {report.question_analysis ? (
                        <DeepSkill
                            title={t('report.questions')}
                            summary={report.question_analysis.summary}
                            extra={<p className="mt-2 text-xs text-slate-500">{t('report.estimatedQuestions', { value: na(report.question_analysis.total_questions_estimate, dash) })}</p>}
                        />
                    ) : null}
                    {report.listening ? (
                        <DeepSkill title={t('report.listening')} score={report.listening.score} summary={report.listening.summary} extra={report.listening.paraphrasing_quality ? <p className="mt-2 text-sm text-slate-700">{report.listening.paraphrasing_quality}</p> : null} />
                    ) : null}
                    {report.value_communication ? (
                        <DeepSkill title={t('report.value')} score={report.value_communication.score} summary={report.value_communication.summary} />
                    ) : null}
                    {(hasItems(report.objection_map) || sections.objections) ? (
                        <ExpandCollapse title={t('report.objections')}>
                            {hasItems(report.objection_map) ? (
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
                        </ExpandCollapse>
                    ) : null}
                    {report.negotiation ? (
                        <ExpandCollapse title={t('report.negotiation')}>
                            {report.negotiation.applicable === false ? (
                                <p className="text-sm text-slate-500">{t('report.negotiationNa')}</p>
                            ) : (
                                <>
                                    <ScoreBar score={report.negotiation.score} label={t('report.negotiation')} />
                                    <p className="mt-3 text-sm leading-6 text-slate-700">{report.negotiation.summary}</p>
                                </>
                            )}
                        </ExpandCollapse>
                    ) : null}
                    {report.trust_rapport ? (
                        <DeepSkill title={t('report.rapport')} score={report.trust_rapport.score} summary={report.trust_rapport.summary} extra={report.trust_rapport.tone_assessment ? <p className="mt-2 text-sm text-slate-600">{report.trust_rapport.tone_assessment}</p> : null} />
                    ) : null}
                    {report.closing ? (
                        <DeepSkill title={t('report.closing')} score={report.closing.score} summary={report.closing.summary} extra={report.closing.better_closing ? <p className="mt-2 text-sm text-slate-800">{report.closing.better_closing}</p> : null} />
                    ) : null}
                </ReportGroup>
            ) : null}

            {coachingGroup ? (
                <ReportGroup title={t('report.coaching')}>
                    {hasItems(coaching)
                        ? coaching.map((item) => <CoachingPriorityCard key={item.priority} item={item} />)
                        : null}
                    {playbookHasContent ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.playbook')}</p>
                            <div className="mt-3 grid gap-4 text-sm sm:grid-cols-2">
                                {['before_call', 'during_call', 'closing', 'follow_up'].map((key) => (
                                    hasItems(playbook[key]) ? (
                                        <div key={key}>
                                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t.enum('playbook', key)}</p>
                                            <ul className="mt-2 list-disc space-y-1 ps-4 text-slate-800">
                                                {playbook[key].map((line) => (
                                                    <li key={line}>{line}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    ) : null
                                ))}
                            </div>
                        </InnerCard>
                    ) : null}
                </ReportGroup>
            ) : null}

            {companyGroup ? (
                <ReportGroup title={t('report.groupCompanyStandard')}>
                    {specific.script_adherence ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.scriptAdherence')}</p>
                            <p className="mt-1 text-sm text-slate-800">
                                {specific.script_adherence.applicable === false
                                    ? t('common.notApplicable')
                                    : t('report.scoreNa', { score: specific.script_adherence.score ?? t('common.na') })}
                            </p>
                            {specific.script_adherence.summary ? <p className="mt-1 text-sm leading-6 text-slate-700">{specific.script_adherence.summary}</p> : null}
                            <FindingList items={specific.script_adherence.issues} />
                        </InnerCard>
                    ) : null}
                    {(hasItems(specific.mandatory_questions?.asked) || hasItems(specific.mandatory_questions?.missed)) ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('analytics.mandatoryQuestions')}</p>
                            {hasItems(specific.mandatory_questions?.asked) ? <p className="mt-2 text-sm text-slate-800">{t('report.asked', { value: specific.mandatory_questions.asked.join(', ') })}</p> : null}
                            {hasItems(specific.mandatory_questions?.missed) ? <p className="mt-1 text-sm text-slate-800">{t('report.missedList', { value: specific.mandatory_questions.missed.join(', ') })}</p> : null}
                        </InnerCard>
                    ) : null}
                    {hasItems(specific.forbidden_claims?.violations) ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.forbiddenClaims')}</p>
                            <FindingList items={specific.forbidden_claims.violations} />
                        </InnerCard>
                    ) : null}
                    {hasItems(specific.offering_accuracy?.issues) ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.offeringAccuracy')}</p>
                            <FindingList items={specific.offering_accuracy.issues} />
                        </InnerCard>
                    ) : null}
                    {hasItems(criteria) ? (
                        <InnerCard>
                            <p className="text-sm font-semibold text-slate-900">{t('report.scorecardCriteria')}</p>
                            <div className="mt-3">
                                <ScorecardTable criteria={criteria} />
                            </div>
                        </InnerCard>
                    ) : null}
                </ReportGroup>
            ) : null}

            {transcriptAvailable && onOpenTranscript ? (
                <div className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <button
                        type="button"
                        onClick={onOpenTranscript}
                        className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                    >
                        {t('report.openTranscript')}
                    </button>
                </div>
            ) : null}
        </div>
    );
}
