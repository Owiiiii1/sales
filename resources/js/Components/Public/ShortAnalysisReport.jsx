import {
    ExecutiveSummaryCard,
    ModeBadge,
    PositiveCard,
    ReportGroup,
    ScoreSummary,
    ScorecardSummary,
    hasItems,
} from '@/Components/Public/reportShared';
import { useT } from '@/i18n';

export default function ShortAnalysisReport({
    report,
    analysisMode,
    companyName,
    employeeName,
    fullReportUrl,
}) {
    const t = useT();

    if (!report) {
        return null;
    }

    const short = report.short || {};
    const strengths = short.strengths || [];
    const problems = short.problems || [];
    const actions = short.next_actions || [];

    return (
        <div className="space-y-6">
            <section className="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">{t('report.shortTitle')}</h2>
                <ModeBadge mode={analysisMode} companyName={companyName} employeeName={employeeName} />
                <div className="mt-6">
                    <ScoreSummary report={report} />
                </div>
                <div className="mt-6">
                    <ExecutiveSummaryCard report={report} />
                </div>
            </section>

            <div className="rounded-3xl border border-indigo-100 bg-indigo-50/70 p-6 shadow-sm">
                <p className="text-sm font-semibold text-slate-900">{t('report.shortInfoTitle')}</p>
                <p className="mt-2 text-sm leading-6 text-slate-700">{t('report.shortInfoBody')}</p>
                {fullReportUrl ? (
                    <a
                        href={fullReportUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-4 inline-flex rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        {t('report.openFull')}
                    </a>
                ) : null}
            </div>

            {hasItems(strengths) ? (
                <ReportGroup title={t('report.mainStrengths')}>
                    {strengths.map((item, index) => (
                        <PositiveCard key={`${item.text || item.mistake}-${index}`} item={item.text ? item : { text: item.mistake || item.text }} />
                    ))}
                </ReportGroup>
            ) : null}

            {hasItems(problems) ? (
                <ReportGroup title={t('report.mainProblems')}>
                    {problems.map((item, index) => (
                        <div key={`${item.mistake || item.text}-${index}`} className="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 shadow-sm">
                            <p className="text-sm font-medium text-slate-900">{item.mistake || item.text}</p>
                            {item.why ? <p className="mt-1 text-sm text-slate-700">{item.why}</p> : null}
                        </div>
                    ))}
                </ReportGroup>
            ) : null}

            {hasItems(actions) ? (
                <ReportGroup title={t('report.nextCallActions')}>
                    <ol className="space-y-2">
                        {actions.map((item, index) => (
                            <li key={`${item.text}-${index}`} className="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-800 shadow-sm">
                                {item.text}
                            </li>
                        ))}
                    </ol>
                </ReportGroup>
            ) : null}

            {short.scorecard ? (
                <ReportGroup title={t('report.companyScorecard')}>
                    <ScorecardSummary summary={short.scorecard} />
                </ReportGroup>
            ) : null}
        </div>
    );
}
