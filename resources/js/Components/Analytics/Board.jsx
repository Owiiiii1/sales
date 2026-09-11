import { useT } from '@/i18n';

function formatDuration(seconds, t) {
    if (seconds === null || seconds === undefined) {
        return t('common.na');
    }
    const mins = Math.floor(Number(seconds) / 60);
    const secs = Number(seconds) % 60;
    return `${mins}:${String(secs).padStart(2, '0')}`;
}

function na(value, t, suffix = '') {
    if (value === null || value === undefined) {
        return t('common.na');
    }
    return `${value}${suffix}`;
}

function bandClass(band) {
    if (band === 'good') {
        return 'text-emerald-800';
    }
    if (band === 'warning') {
        return 'text-amber-800';
    }
    if (band === 'poor') {
        return 'text-slate-800';
    }
    return 'text-slate-900';
}

function trendLabel(trend, t) {
    if (!trend || trend.percent === null || trend.direction === null) {
        return t('common.na');
    }
    const sign = trend.percent > 0 ? '+' : '';
    return `${trend.direction === 'up' ? '↑' : trend.direction === 'down' ? '↓' : '→'} ${sign}${trend.percent}%`;
}

const PERIOD_KEYS = {
    last_7: 'analytics.last7',
    last_30: 'analytics.last30',
    last_90: 'analytics.last90',
    this_month: 'analytics.thisMonth',
    previous_month: 'analytics.previousMonth',
    all_time: 'analytics.allTime',
    custom: 'analytics.custom',
};

export function AnalyticsFilters({ filters, companies = [], employees = [], showCompany = false, showEmployee = false, extra = {}, action }) {
    const t = useT();

    const submit = (next) => {
        const params = { ...extra, period: next.period || filters.period };
        if ((next.period || filters.period) === 'custom') {
            params.from = next.from ?? filters.from_date ?? '';
            params.to = next.to ?? filters.to_date ?? '';
        }
        if (showCompany) {
            params.company_id = next.company_id ?? filters.company_id ?? '';
        }
        if (showEmployee) {
            params.employee_id = next.employee_id ?? filters.employee_id ?? '';
        }
        Object.keys(params).forEach((key) => {
            if (params[key] === '' || params[key] === null || params[key] === undefined) {
                delete params[key];
            }
        });
        action(params);
    };

    const employeesForCompany = showCompany && filters.company_id
        ? employees.filter((employee) => String(employee.company_id) === String(filters.company_id))
        : employees;

    return (
        <section className="app-widget p-4">
            <div className="flex flex-wrap gap-3">
                <Field label={t('common.period')}>
                    <select
                        value={filters.period}
                        onChange={(e) => submit({ period: e.target.value, company_id: filters.company_id, employee_id: filters.employee_id })}
                        className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                    >
                        {Object.entries(PERIOD_KEYS).map(([value, key]) => (
                            <option key={value} value={value}>{t(key)}</option>
                        ))}
                    </select>
                </Field>
                {filters.period === 'custom' && (
                    <>
                        <Field label={t('common.from')}>
                            <input
                                type="date"
                                value={filters.from_date || ''}
                                onChange={(e) => submit({ period: 'custom', from: e.target.value, to: filters.to_date, company_id: filters.company_id, employee_id: filters.employee_id })}
                                className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                            />
                        </Field>
                        <Field label={t('common.to')}>
                            <input
                                type="date"
                                value={filters.to_date || ''}
                                onChange={(e) => submit({ period: 'custom', from: filters.from_date, to: e.target.value, company_id: filters.company_id, employee_id: filters.employee_id })}
                                className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                            />
                        </Field>
                    </>
                )}
                {showCompany && (
                    <Field label={t('common.company')}>
                        <select
                            value={filters.company_id ?? ''}
                            onChange={(e) => submit({ period: filters.period, from: filters.from_date, to: filters.to_date, company_id: e.target.value, employee_id: '' })}
                            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                        >
                            <option value="">{t('companies.allCompanies')}</option>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>{company.name}</option>
                            ))}
                        </select>
                    </Field>
                )}
                {showEmployee && (
                    <Field label={t('common.employee')}>
                        <select
                            value={filters.employee_id ?? ''}
                            onChange={(e) => submit({ period: filters.period, from: filters.from_date, to: filters.to_date, company_id: filters.company_id, employee_id: e.target.value })}
                            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                        >
                            <option value="">{t('employees.allEmployees')}</option>
                            {employeesForCompany.map((employee) => (
                                <option key={employee.id} value={employee.id}>{employee.full_name}</option>
                            ))}
                        </select>
                    </Field>
                )}
            </div>
        </section>
    );
}

function Field({ label, children }) {
    return (
        <div>
            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</label>
            {children}
        </div>
    );
}

export function KpiGrid({ kpis, comparison, showPublic = false }) {
    const t = useT();
    const cards = [
        { key: 'total_calls', label: t('analytics.totalCalls'), value: kpis.total_calls },
        { key: 'analyzed_calls', label: t('analytics.analyzedCalls'), value: kpis.analyzed_calls },
        { key: 'analysis_success', label: t('analytics.analysisSuccess'), value: na(kpis.analysis_success_rate, t, '%') },
        { key: 'avg_sales', label: t('analytics.avgSales'), value: na(kpis.average_sales_score, t), band: kpis.average_sales_score_band, trend: comparison?.average_sales_score },
        { key: 'avg_scorecard', label: t('analytics.avgScorecard'), value: na(kpis.average_company_score, t), band: kpis.average_company_score_band, trend: comparison?.average_company_score },
        { key: 'active_employees', label: t('analytics.activeEmployees'), value: kpis.active_employees },
        { key: 'calls_this_period', label: t('analytics.callsThisPeriod'), value: kpis.calls_this_period ?? kpis.total_calls },
        { key: 'failed_calls', label: t('analytics.failedCalls'), value: kpis.failed_calls },
    ];

    if (kpis.employees_count !== undefined && kpis.employees_count !== null) {
        cards.push({ key: 'employees_count', label: t('companies.employees'), value: kpis.employees_count });
    }

    if (showPublic) {
        cards.push({ key: 'public_analyses', label: t('analytics.publicAnalyses'), value: kpis.public_analyses ?? 0 });
    }

    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((card) => (
                <div key={card.key} className="app-widget p-4">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{card.label}</p>
                    <p className={`mt-2 text-2xl font-semibold ${bandClass(card.band)}`}>{card.value}</p>
                    {card.trend && (
                        <p className="mt-1 text-xs text-slate-500">{t('analytics.vsPrevious', { value: trendLabel(card.trend, t) })}</p>
                    )}
                </div>
            ))}
        </div>
    );
}

export function RateNote({ kpis }) {
    const t = useT();
    const duration = kpis.average_duration_seconds !== null && kpis.average_duration_seconds !== undefined
        ? t('analytics.avgDuration', { value: formatDuration(kpis.average_duration_seconds, t) })
        : t('analytics.avgDurationNa');

    return (
        <p className="text-sm text-slate-500">
            {t('analytics.completion', {
                completion: na(kpis.completion_rate, t, '%'),
                failed: na(kpis.failed_rate, t, '%'),
                pending: na(kpis.pending_rate, t, '%'),
            })}
            {' · '}
            {duration}
        </p>
    );
}

export function TrendCharts({ trends }) {
    const t = useT();

    return (
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <TrendChart title={t('analytics.salesOverTime')} points={trends?.sales || []} />
            <TrendChart title={t('analytics.callsOverTime')} points={trends?.calls || []} fillZeros />
            <TrendChart title={t('analytics.scorecardOverTime')} points={trends?.scorecard || []} />
        </div>
    );
}

function TrendChart({ title, points, fillZeros = false }) {
    const t = useT();
    const values = points.map((point) => (fillZeros ? point.value ?? 0 : point.value)).filter((value) => value !== null && value !== undefined);
    const max = Math.max(1, ...values, 0);
    const width = 320;
    const height = 120;
    const usable = points.filter((point) => fillZeros || (point.value !== null && point.value !== undefined));

    const polyline = usable.length > 1
        ? usable.map((point, index) => {
            const x = (index / (usable.length - 1)) * (width - 16) + 8;
            const y = height - 12 - ((Number(point.value) / max) * (height - 24));
            return `${x},${y}`;
        }).join(' ')
        : '';

    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            {points.length === 0 ? (
                <p className="mt-4 text-sm text-slate-500">{t('analytics.noData')}</p>
            ) : (
                <svg viewBox={`0 0 ${width} ${height}`} className="mt-3 h-32 w-full text-indigo-600" role="img">
                    {polyline && <polyline fill="none" stroke="currentColor" strokeWidth="2" points={polyline} />}
                    {usable.length === 1 && (
                        <circle cx={width / 2} cy={height / 2} r="3" fill="currentColor" />
                    )}
                </svg>
            )}
            <div className="mt-1 flex justify-between text-xs text-slate-500">
                <span>{points[0]?.label || ''}</span>
                <span>{points[points.length - 1]?.label || ''}</span>
            </div>
        </section>
    );
}

export function SectionTable({ sections }) {
    const t = useT();

    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">{t('analytics.sectionPerformance')}</h3>
            <div className="mt-3 space-y-3">
                {sections.map((section) => (
                    <div key={section.key}>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-slate-700">{section.title}</span>
                            <span className={bandClass(section.band)}>{na(section.average_score, t)}</span>
                        </div>
                        <div className="mt-1 h-2 rounded-full bg-slate-100">
                            <div
                                className="h-2 rounded-full bg-indigo-300"
                                style={{ width: `${section.average_score ?? 0}%` }}
                            />
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            {t('analytics.applicableCalls', { applicable: section.applicable_count, calls: section.call_count })}
                        </p>
                    </div>
                ))}
                {sections.length === 0 && <p className="text-sm text-slate-500">{t('analytics.noSections')}</p>}
            </div>
        </section>
    );
}

export function DistributionBars({ title, items, enumGroup }) {
    const t = useT();
    const max = Math.max(1, ...items.map((item) => item.count));
    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            <div className="mt-3 space-y-2">
                {items.map((item) => (
                    <div key={item.key}>
                        <div className="flex justify-between text-sm text-slate-700">
                            <span className="capitalize">{enumGroup ? t.enum(enumGroup, item.key) : item.key.replaceAll('_', ' ')}</span>
                            <span>{item.count}</span>
                        </div>
                        <div className="mt-1 h-2 rounded-full bg-slate-100">
                            <div className="h-2 rounded-full bg-slate-400" style={{ width: `${(item.count / max) * 100}%` }} />
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

export function ScorecardTable({ rows }) {
    const t = useT();

    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">{t('analytics.scorecardPerformance')}</h3>
            {rows.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">{t('analytics.noScorecard')}</p>
            ) : (
                <div className="mt-3 overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th className="py-2">{t('analytics.criterion')}</th>
                                <th>{t('analytics.avg')}</th>
                                <th>{t('analytics.weight')}</th>
                                <th>{t('analytics.applicable')}</th>
                                <th>{t('analytics.criticalFailures')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((row) => (
                                <tr key={row.key}>
                                    <td className="py-2 text-slate-800">{row.name}</td>
                                    <td className={bandClass(row.band)}>{row.average_score === null ? t('common.na') : t('analytics.scoreOutOf', { score: row.average_score })}</td>
                                    <td>{na(row.weight, t)}</td>
                                    <td>{row.applicable_count}</td>
                                    <td>{row.critical_failure_count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

export function MandatoryAndClaims({ questions, violations }) {
    const t = useT();

    return (
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">{t('analytics.mandatoryQuestions')}</h3>
                <p className="mt-2 text-sm text-slate-600">{t('analytics.askedMissed', { asked: questions?.asked_count ?? 0, missed: questions?.missed_count ?? 0 })}</p>
                <ul className="mt-3 space-y-2 text-sm text-slate-800">
                    {(questions?.top_missed || []).map((item) => (
                        <li key={item.text}>{item.text} · {item.count}</li>
                    ))}
                    {(questions?.top_missed || []).length === 0 && <li className="text-slate-500">{t('analytics.noMissed')}</li>}
                </ul>
            </section>
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">{t('analytics.forbiddenClaims')}</h3>
                <p className="mt-2 text-sm text-slate-600">
                    {t('analytics.violations', { calls: violations?.calls_with_violations ?? 0, count: violations?.violation_count ?? 0 })}
                </p>
                <ul className="mt-3 space-y-2 text-sm text-slate-800">
                    {(violations?.latest || []).map((item) => (
                        <li key={item.call_id}>
                            <a href={item.show_url} className="text-indigo-700">{t('analytics.callNumber', { id: item.call_id })}</a>
                            {' '}{item.texts.join('; ')}
                        </li>
                    ))}
                    {(violations?.latest || []).length === 0 && <li className="text-slate-500">{t('analytics.noViolations')}</li>}
                </ul>
            </section>
        </div>
    );
}

export function Findings({ strengths, weaknesses, lowest }) {
    const t = useT();

    return (
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">{t('analytics.recentStrengths')}</h3>
                <p className="mt-1 text-xs text-slate-500">{t('analytics.findingsHint')}</p>
                <List items={strengths} />
            </section>
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">{t('analytics.recentWeaknesses')}</h3>
                <p className="mt-1 text-xs text-slate-500">{t('analytics.findingsHint')}</p>
                <List items={weaknesses} />
            </section>
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">{t('analytics.lowestSections')}</h3>
                <ul className="mt-3 space-y-2 text-sm text-slate-800">
                    {(lowest || []).map((item) => (
                        <li key={item.key}>{item.title} · {na(item.average_score, t)}</li>
                    ))}
                    {(lowest || []).length === 0 && <li className="text-slate-500">{t('common.na')}</li>}
                </ul>
            </section>
        </div>
    );
}

function List({ items }) {
    const t = useT();

    return (
        <ul className="mt-3 space-y-2 text-sm text-slate-800">
            {(items || []).map((item) => (
                <li key={item}>{item}</li>
            ))}
            {(items || []).length === 0 && <li className="text-slate-500">{t('common.na')}</li>}
        </ul>
    );
}

export function EmployeeComparison({ rows }) {
    const t = useT();

    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">{t('analytics.employeeComparison')}</h3>
            <div className="mt-3 overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th className="py-2">{t('common.employee')}</th>
                            <th>{t('nav.calls')}</th>
                            <th>{t('analytics.analyzed')}</th>
                            <th>{t('analytics.avgSalesScore')}</th>
                            <th>{t('analytics.avgCompanyScore')}</th>
                            <th>{t('analytics.avgDurationCol')}</th>
                            <th>{t('analytics.failed')}</th>
                            <th>{t('analytics.trend')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td className="py-2"><a href={row.show_url} className="text-indigo-700">{row.full_name}</a></td>
                                <td>{row.calls}</td>
                                <td>{row.analyzed}</td>
                                <td>{na(row.average_sales_score, t)}</td>
                                <td>{na(row.average_company_score, t)}</td>
                                <td>{formatDuration(row.average_duration_seconds, t)}</td>
                                <td>{row.failed}</td>
                                <td>{trendLabel(row.trend, t)}</td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr><td className="py-4 text-slate-500" colSpan={8}>{t('employees.empty')}</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export function RecentCallsTable({ calls }) {
    const t = useT();

    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">{t('analytics.recentCalls')}</h3>
            <div className="mt-3 overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th className="py-2">{t('companies.callFallback')}</th>
                            <th>{t('common.company')}</th>
                            <th>{t('common.employee')}</th>
                            <th>{t('common.status')}</th>
                            <th>{t('analytics.salesScore')}</th>
                            <th>{t('companies.scorecard')}</th>
                            <th>{t('common.duration')}</th>
                            <th>{t('common.date')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {calls.map((call) => (
                            <tr key={call.id}>
                                <td className="py-2"><a href={call.show_url} className="text-indigo-700">#{call.id}</a></td>
                                <td>{call.company_name || t('common.dash')}</td>
                                <td>{call.employee_name || t('common.dash')}</td>
                                <td>{t.status(call.status)}</td>
                                <td>{na(call.overall_score, t)}</td>
                                <td>{na(call.company_scorecard_score, t)}</td>
                                <td>{formatDuration(call.duration_seconds, t)}</td>
                                <td>{t.date(call.date)}</td>
                            </tr>
                        ))}
                        {calls.length === 0 && (
                            <tr><td className="py-4 text-slate-500" colSpan={8}>{t('analytics.noCallsPeriod')}</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export function AnalyticsSections({ analytics, variant = 'dashboard' }) {
    const t = useT();
    const kpis = analytics?.kpis || {};
    return (
        <div className="space-y-6">
            <KpiGrid kpis={kpis} comparison={analytics?.comparison} showPublic={variant === 'dashboard' && !analytics?.hide_public} />
            <RateNote kpis={kpis} />
            <TrendCharts trends={analytics?.trends} />
            <SectionTable sections={analytics?.sections || []} />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <DistributionBars title={t('analytics.outcomes')} items={analytics?.outcomes || []} enumGroup="outcome" />
                <DistributionBars title={t('analytics.intents')} items={analytics?.intents || []} enumGroup="intent" />
            </div>
            <ScorecardTable rows={analytics?.scorecard || []} />
            <MandatoryAndClaims questions={analytics?.mandatory_questions} violations={analytics?.violations} />
            <Findings strengths={analytics?.recent_strengths} weaknesses={analytics?.recent_weaknesses} lowest={analytics?.lowest_sections} />
            {variant === 'company' && <EmployeeComparison rows={analytics?.employees || []} />}
            <RecentCallsTable calls={analytics?.recent_calls || []} />
        </div>
    );
}
