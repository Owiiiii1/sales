function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) {
        return 'N/A';
    }
    const mins = Math.floor(Number(seconds) / 60);
    const secs = Number(seconds) % 60;
    return `${mins}:${String(secs).padStart(2, '0')}`;
}

function formatDate(value) {
    if (!value) {
        return 'N/A';
    }
    return new Date(value).toLocaleString();
}

function na(value, suffix = '') {
    if (value === null || value === undefined) {
        return 'N/A';
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

function trendLabel(trend) {
    if (!trend || trend.percent === null || trend.direction === null) {
        return 'N/A';
    }
    const sign = trend.percent > 0 ? '+' : '';
    return `${trend.direction === 'up' ? '↑' : trend.direction === 'down' ? '↓' : '→'} ${sign}${trend.percent}%`;
}

export function AnalyticsFilters({ filters, companies = [], employees = [], showCompany = false, showEmployee = false, extra = {}, action }) {
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

    const labels = {
        last_7: 'Last 7 days',
        last_30: 'Last 30 days',
        last_90: 'Last 90 days',
        this_month: 'This month',
        previous_month: 'Previous month',
        all_time: 'All time',
        custom: 'Custom',
    };

    const employeesForCompany = showCompany && filters.company_id
        ? employees.filter((employee) => String(employee.company_id) === String(filters.company_id))
        : employees;

    return (
        <section className="app-widget p-4">
            <div className="flex flex-wrap gap-3">
                <Field label="Period">
                    <select
                        value={filters.period}
                        onChange={(e) => submit({ period: e.target.value, company_id: filters.company_id, employee_id: filters.employee_id })}
                        className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                    >
                        {Object.entries(labels).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                </Field>
                {filters.period === 'custom' && (
                    <>
                        <Field label="From">
                            <input
                                type="date"
                                value={filters.from_date || ''}
                                onChange={(e) => submit({ period: 'custom', from: e.target.value, to: filters.to_date, company_id: filters.company_id, employee_id: filters.employee_id })}
                                className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                            />
                        </Field>
                        <Field label="To">
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
                    <Field label="Company">
                        <select
                            value={filters.company_id ?? ''}
                            onChange={(e) => submit({ period: filters.period, from: filters.from_date, to: filters.to_date, company_id: e.target.value, employee_id: '' })}
                            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                        >
                            <option value="">All companies</option>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>{company.name}</option>
                            ))}
                        </select>
                    </Field>
                )}
                {showEmployee && (
                    <Field label="Employee">
                        <select
                            value={filters.employee_id ?? ''}
                            onChange={(e) => submit({ period: filters.period, from: filters.from_date, to: filters.to_date, company_id: filters.company_id, employee_id: e.target.value })}
                            className="h-10 rounded-lg border border-slate-300 px-3 text-sm"
                        >
                            <option value="">All employees</option>
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
    const cards = [
        { label: 'Total Calls', value: kpis.total_calls },
        { label: 'Analyzed Calls', value: kpis.analyzed_calls },
        { label: 'Analysis Success Rate', value: na(kpis.analysis_success_rate, '%') },
        { label: 'Average Sales Score', value: na(kpis.average_sales_score), band: kpis.average_sales_score_band, trend: comparison?.average_sales_score },
        { label: 'Average Company Scorecard', value: na(kpis.average_company_score), band: kpis.average_company_score_band, trend: comparison?.average_company_score },
        { label: 'Active Employees', value: kpis.active_employees },
        { label: 'Calls This Period', value: kpis.calls_this_period ?? kpis.total_calls },
        { label: 'Failed Calls', value: kpis.failed_calls },
    ];

    if (kpis.employees_count !== undefined && kpis.employees_count !== null) {
        cards.push({ label: 'Employees', value: kpis.employees_count });
    }

    if (showPublic) {
        cards.push({ label: 'Public Analyses', value: kpis.public_analyses ?? 0 });
    }

    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((card) => (
                <div key={card.label} className="app-widget p-4">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{card.label}</p>
                    <p className={`mt-2 text-2xl font-semibold ${bandClass(card.band)}`}>{card.value}</p>
                    {card.trend && (
                        <p className="mt-1 text-xs text-slate-500">vs previous: {trendLabel(card.trend)}</p>
                    )}
                </div>
            ))}
        </div>
    );
}

export function RateNote({ kpis }) {
    return (
        <p className="text-sm text-slate-500">
            Completion {na(kpis.completion_rate, '%')} · Failed {na(kpis.failed_rate, '%')} · Pending {na(kpis.pending_rate, '%')}
            {kpis.average_duration_seconds !== null && kpis.average_duration_seconds !== undefined
                ? ` · Avg duration ${formatDuration(kpis.average_duration_seconds)}`
                : ' · Avg duration N/A'}
        </p>
    );
}

export function TrendCharts({ trends }) {
    return (
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <TrendChart title="Average Sales Score Over Time" points={trends?.sales || []} />
            <TrendChart title="Calls Over Time" points={trends?.calls || []} fillZeros />
            <TrendChart title="Company Scorecard Over Time" points={trends?.scorecard || []} />
        </div>
    );
}

function TrendChart({ title, points, fillZeros = false }) {
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
                <p className="mt-4 text-sm text-slate-500">No data for this period.</p>
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
    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">Section performance</h3>
            <div className="mt-3 space-y-3">
                {sections.map((section) => (
                    <div key={section.key}>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-slate-700">{section.title}</span>
                            <span className={bandClass(section.band)}>{na(section.average_score)}</span>
                        </div>
                        <div className="mt-1 h-2 rounded-full bg-slate-100">
                            <div
                                className="h-2 rounded-full bg-indigo-300"
                                style={{ width: `${section.average_score ?? 0}%` }}
                            />
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            Applicable {section.applicable_count} · Calls {section.call_count}
                        </p>
                    </div>
                ))}
                {sections.length === 0 && <p className="text-sm text-slate-500">No analyzed sections yet.</p>}
            </div>
        </section>
    );
}

export function DistributionBars({ title, items }) {
    const max = Math.max(1, ...items.map((item) => item.count));
    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
            <div className="mt-3 space-y-2">
                {items.map((item) => (
                    <div key={item.key}>
                        <div className="flex justify-between text-sm text-slate-700">
                            <span className="capitalize">{item.key.replaceAll('_', ' ')}</span>
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
    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">Scorecard performance</h3>
            {rows.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">N/A — no scorecard results in this period.</p>
            ) : (
                <div className="mt-3 overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th className="py-2">Criterion</th>
                                <th>Avg</th>
                                <th>Weight</th>
                                <th>Applicable</th>
                                <th>Critical failures</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.map((row) => (
                                <tr key={row.key}>
                                    <td className="py-2 text-slate-800">{row.name}</td>
                                    <td className={bandClass(row.band)}>{row.average_score === null ? 'N/A' : `${row.average_score} / 100`}</td>
                                    <td>{na(row.weight)}</td>
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
    return (
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">Mandatory questions</h3>
                <p className="mt-2 text-sm text-slate-600">Asked {questions?.asked_count ?? 0} · Missed {questions?.missed_count ?? 0}</p>
                <ul className="mt-3 space-y-2 text-sm text-slate-800">
                    {(questions?.top_missed || []).map((item) => (
                        <li key={item.text}>{item.text} · {item.count}</li>
                    ))}
                    {(questions?.top_missed || []).length === 0 && <li className="text-slate-500">No missed questions in this period.</li>}
                </ul>
            </section>
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">Forbidden claims</h3>
                <p className="mt-2 text-sm text-slate-600">
                    Calls with violations {violations?.calls_with_violations ?? 0} · Violations {violations?.violation_count ?? 0}
                </p>
                <ul className="mt-3 space-y-2 text-sm text-slate-800">
                    {(violations?.latest || []).map((item) => (
                        <li key={item.call_id}>
                            <a href={item.show_url} className="text-indigo-700">Call #{item.call_id}</a>
                            {' '}{item.texts.join('; ')}
                        </li>
                    ))}
                    {(violations?.latest || []).length === 0 && <li className="text-slate-500">No violations in this period.</li>}
                </ul>
            </section>
        </div>
    );
}

export function Findings({ strengths, weaknesses, lowest }) {
    return (
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">Recent strengths</h3>
                <p className="mt-1 text-xs text-slate-500">Latest phrasing from analyses, not clustered statistics.</p>
                <List items={strengths} />
            </section>
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">Recent weaknesses</h3>
                <p className="mt-1 text-xs text-slate-500">Latest phrasing from analyses, not clustered statistics.</p>
                <List items={weaknesses} />
            </section>
            <section className="app-widget p-4">
                <h3 className="text-sm font-semibold text-slate-900">Lowest sections</h3>
                <ul className="mt-3 space-y-2 text-sm text-slate-800">
                    {(lowest || []).map((item) => (
                        <li key={item.key}>{item.title} · {na(item.average_score)}</li>
                    ))}
                    {(lowest || []).length === 0 && <li className="text-slate-500">N/A</li>}
                </ul>
            </section>
        </div>
    );
}

function List({ items }) {
    return (
        <ul className="mt-3 space-y-2 text-sm text-slate-800">
            {(items || []).map((item) => (
                <li key={item}>{item}</li>
            ))}
            {(items || []).length === 0 && <li className="text-slate-500">N/A</li>}
        </ul>
    );
}

export function EmployeeComparison({ rows }) {
    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">Employee comparison</h3>
            <div className="mt-3 overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th className="py-2">Employee</th>
                            <th>Calls</th>
                            <th>Analyzed</th>
                            <th>Avg Sales Score</th>
                            <th>Avg Company Score</th>
                            <th>Avg Duration</th>
                            <th>Failed</th>
                            <th>Trend</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td className="py-2"><a href={row.show_url} className="text-indigo-700">{row.full_name}</a></td>
                                <td>{row.calls}</td>
                                <td>{row.analyzed}</td>
                                <td>{na(row.average_sales_score)}</td>
                                <td>{na(row.average_company_score)}</td>
                                <td>{formatDuration(row.average_duration_seconds)}</td>
                                <td>{row.failed}</td>
                                <td>{trendLabel(row.trend)}</td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr><td className="py-4 text-slate-500" colSpan={8}>No employees yet.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export function RecentCallsTable({ calls }) {
    return (
        <section className="app-widget p-4">
            <h3 className="text-sm font-semibold text-slate-900">Recent calls</h3>
            <div className="mt-3 overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th className="py-2">Call</th>
                            <th>Company</th>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Sales score</th>
                            <th>Scorecard</th>
                            <th>Duration</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {calls.map((call) => (
                            <tr key={call.id}>
                                <td className="py-2"><a href={call.show_url} className="text-indigo-700">#{call.id}</a></td>
                                <td>{call.company_name || '—'}</td>
                                <td>{call.employee_name || '—'}</td>
                                <td>{call.status}</td>
                                <td>{na(call.overall_score)}</td>
                                <td>{na(call.company_scorecard_score)}</td>
                                <td>{formatDuration(call.duration_seconds)}</td>
                                <td>{formatDate(call.date)}</td>
                            </tr>
                        ))}
                        {calls.length === 0 && (
                            <tr><td className="py-4 text-slate-500" colSpan={8}>No calls in this period.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export function AnalyticsSections({ analytics, variant = 'dashboard' }) {
    const kpis = analytics?.kpis || {};
    return (
        <div className="space-y-6">
            <KpiGrid kpis={kpis} comparison={analytics?.comparison} showPublic={variant === 'dashboard' && !analytics?.hide_public} />
            <RateNote kpis={kpis} />
            <TrendCharts trends={analytics?.trends} />
            <SectionTable sections={analytics?.sections || []} />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <DistributionBars title="Call outcomes" items={analytics?.outcomes || []} />
                <DistributionBars title="Customer intent" items={analytics?.intents || []} />
            </div>
            <ScorecardTable rows={analytics?.scorecard || []} />
            <MandatoryAndClaims questions={analytics?.mandatory_questions} violations={analytics?.violations} />
            <Findings strengths={analytics?.recent_strengths} weaknesses={analytics?.recent_weaknesses} lowest={analytics?.lowest_sections} />
            {variant === 'company' && <EmployeeComparison rows={analytics?.employees || []} />}
            <RecentCallsTable calls={analytics?.recent_calls || []} />
        </div>
    );
}
